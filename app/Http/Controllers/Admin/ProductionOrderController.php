<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesStockItems;
use App\Http\Controllers\Controller;
use App\Models\ProductionOrder;
use App\Models\ProductionStageEntry;
use App\Models\Site;
use App\Models\StockMovement;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Garments production, stage by stage — see docs/garments-production.md.
 *
 * A production (tracking no. PRD-…) is one size of one item. Creating it
 * IS the cutting step: the fabric used and the pieces it gave are
 * recorded together. Sewing and Finishing are then entered on the
 * production's page as the pieces move forward. Fabric is entered in its
 * own stock unit (e.g. 5 Roll or 500 Gaj), like any other inventory item.
 *
 * Stock/ledger side-effects:
 * - Material issued with any stage entry → `production_consumption` out
 *   at its average cost; Debit Work in Progress / Credit Inventory.
 * - Finishing → `production` in of the finished item, costed at the lot's
 *   WIP value ÷ pieces still in WIP; Debit Inventory / Credit WIP.
 * - Complete → any WIP left (pieces cut but never finished) is written
 *   off; Debit Production Wastage / Credit WIP.
 */
class ProductionOrderController extends Controller implements HasMiddleware
{
    use ResolvesStockItems;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.view', only: ['index', 'show']),
            new Middleware('permission:inventory.create', only: ['create', 'store', 'storeStage']),
            new Middleware('permission:inventory.approve', only: ['complete']),
        ];
    }

    public function index(Request $request)
    {
        $sites = Site::where('status', true)->orderBy('name')->get();
        $siteId = $request->filled('site_id') ? $request->integer('site_id') : null;
        $status = $request->get('status');
        $search = trim((string) $request->get('q'));

        $orders = ProductionOrder::with(['site', 'product.stockUnit', 'productVariant.attributeValues'])
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('production_no', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            }))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // One grouped query for the whole page instead of stageTotals() per row.
        $totals = DB::table('production_stage_entries')
            ->whereIn('production_order_id', $orders->pluck('id'))
            ->selectRaw('production_order_id, stage, SUM(quantity) as total')
            ->groupBy('production_order_id', 'stage')
            ->get()
            ->groupBy('production_order_id')
            ->map(fn ($rows) => $rows->pluck('total', 'stage')->map(fn ($v) => (float) $v));

        $summary = [
            'running' => ProductionOrder::where('status', 'in_progress')->count(),
            'waitingSewing' => 0,
            'waitingFinishing' => 0,
        ];
        $open = DB::table('production_stage_entries')
            ->join('production_orders', 'production_orders.id', '=', 'production_stage_entries.production_order_id')
            ->where('production_orders.status', 'in_progress')
            ->selectRaw('stage, SUM(quantity) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');
        $summary['waitingSewing'] = max((float) ($open['cutting'] ?? 0) - (float) ($open['sewing'] ?? 0), 0);
        $summary['waitingFinishing'] = max((float) ($open['sewing'] ?? 0) - (float) ($open['finishing'] ?? 0), 0);

        return view('admin.production.index', compact('orders', 'sites', 'siteId', 'status', 'search', 'totals', 'summary'));
    }

    /**
     * Pick the Site first (like Purchase/Transfer) so the fabric picker
     * only offers what is actually in stock there.
     */
    public function create(Request $request)
    {
        $sites = Site::where('status', true)->orderBy('name')->get();
        $site = $request->filled('site_id') ? Site::findOrFail($request->integer('site_id')) : null;

        // searchable-select reads ->id / ->name, so hand it objects.
        $items = $this->stockItemOptions(null, false, 'finished_good')->map(fn ($o) => (object) $o);
        $fabricOptions = $site ? $this->stockItemOptions($site->id, true, 'raw_material') : collect();

        return view('admin.production.create', compact('sites', 'site', 'items', 'fabricOptions'));
    }

    /**
     * Opens the production and records its cutting in one go: the fabric
     * issued and how many pieces of this size it produced.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'item' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'cut_quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
            'fabrics' => ['required', 'array', 'min:1'],
            'fabrics.*.item' => ['required', 'string', 'distinct'],
            'fabrics.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ], [
            'fabrics.required' => 'Add the fabric used for this cutting.',
        ]);

        [$product, $variant] = $this->resolveItem($validated['item']);

        if (! $product || $product->isRawMaterial()) {
            throw ValidationException::withMessages(['item' => 'Pick the finished item and size being produced.']);
        }

        $site = Site::findOrFail($validated['site_id']);
        $fabrics = $this->resolveMaterials($validated['fabrics'], $site, 'fabrics');

        $order = DB::transaction(function () use ($validated, $product, $variant, $fabrics) {
            $order = ProductionOrder::create([
                'production_no' => 'PENDING',
                'site_id' => $validated['site_id'],
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'start_date' => $validated['start_date'],
                'status' => 'in_progress',
                'note' => $validated['note'] ?? null,
                'created_by' => Auth::id(),
            ]);
            $order->update(['production_no' => 'PRD-'.str_pad($order->id, 6, '0', STR_PAD_LEFT)]);

            $this->recordStage($order, 'cutting', (float) $validated['cut_quantity'], $validated['start_date'], null, $fabrics);

            return $order;
        });

        return redirect()->route('production-orders.show', $order)
            ->with('success', "Production {$order->production_no} created — {$validated['cut_quantity']} pcs cut.");
    }

    public function show(ProductionOrder $productionOrder)
    {
        $productionOrder->load([
            'site', 'product.stockUnit', 'productVariant.attributeValues', 'createdBy', 'completedBy',
            'stageEntries' => fn ($q) => $q->orderByDesc('entry_date')->orderByDesc('id'),
            'stageEntries.createdBy',
            'stageEntries.materials.product.stockUnit',
            'stageEntries.materials.productVariant.attributeValues',
        ]);

        $totals = $productionOrder->stageTotals();
        $available = collect(ProductionOrder::STAGES)
            ->map(fn ($label, $stage) => $productionOrder->availableFor($stage, $totals))
            ->all();

        // Fabric = whatever was issued at cutting; anything issued later
        // (thread, poly bags...) is listed separately as accessories.
        $materials = $productionOrder->stageEntries->flatMap(fn ($e) => $e->materials->each(fn ($m) => $m->setAttribute('stage', $e->stage)));
        $fabrics = $materials->where('stage', 'cutting')->values();
        $accessories = $materials->where('stage', '!=', 'cutting')->values();
        $materialCost = round($materials->sum(fn ($m) => (float) $m->quantity * (float) $m->unit_cost), 2);

        // Next stage with pieces waiting is what the entry form opens on.
        $nextStage = collect(['sewing', 'finishing'])->first(fn ($s) => ($available[$s] ?? 0) > 0) ?? 'sewing';

        $materialOptions = $productionOrder->isOpen()
            ? $this->stockItemOptions($productionOrder->site_id, true, 'raw_material')
            : collect();

        return view('admin.production.show', [
            'order' => $productionOrder,
            'totals' => $totals,
            'available' => $available,
            'inProcess' => $productionOrder->piecesInProcess($totals),
            'wipValue' => $productionOrder->wipValue(),
            'fabrics' => $fabrics,
            'accessories' => $accessories,
            'materialCost' => $materialCost,
            'nextStage' => $nextStage,
            'materialOptions' => $materialOptions,
        ]);
    }

    /**
     * Record pieces moving through a stage — usually Sewing or Finishing
     * (Cutting is recorded when the production is created, but more can
     * be cut later if extra fabric is issued). Material such as thread or
     * poly bags can be issued at the same time.
     */
    public function storeStage(Request $request, ProductionOrder $productionOrder)
    {
        if (! $productionOrder->isOpen()) {
            return back()->with('error', 'This production is already completed.');
        }

        $validated = $request->validate([
            'stage' => ['required', Rule::in(array_keys(ProductionOrder::STAGES))],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'entry_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'materials' => ['nullable', 'array'],
            'materials.*.item' => ['required', 'string', 'distinct'],
            'materials.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $stage = $validated['stage'];
        $quantity = (float) $validated['quantity'];
        $available = $productionOrder->availableFor($stage);

        if ($available !== null && $quantity > $available) {
            $stages = array_keys(ProductionOrder::STAGES);
            $previous = $stages[array_search($stage, $stages, true) - 1];

            throw ValidationException::withMessages([
                'quantity' => "Only {$available} pcs are waiting for ".ProductionOrder::STAGES[$stage]
                    .' (from '.ProductionOrder::STAGES[$previous].').',
            ]);
        }

        $materials = $this->resolveMaterials($validated['materials'] ?? [], $productionOrder->site, 'materials');

        DB::transaction(function () use ($productionOrder, $validated, $stage, $quantity, $materials) {
            $this->recordStage($productionOrder, $stage, $quantity, $validated['entry_date'], $validated['note'] ?? null, $materials);
        });

        return back()->with('success', rtrim(rtrim(number_format($quantity, 4), '0'), '.')
            .' pcs recorded at '.ProductionOrder::STAGES[$stage].'.');
    }

    /**
     * Close the production. Pieces still on the floor (cut but not
     * finished) are treated as wastage and their share of WIP is written
     * off, so the WIP account never carries cost nobody is working on.
     */
    public function complete(ProductionOrder $productionOrder)
    {
        if (! $productionOrder->isOpen()) {
            return back()->with('error', 'This production is already completed.');
        }

        DB::transaction(function () use ($productionOrder) {
            $wip = $productionOrder->wipValue();

            if ($wip > 0) {
                LedgerService::post([
                    'type' => 'production',
                    'date' => now()->toDateString(),
                    'site_id' => $productionOrder->site_id,
                    'narration' => "Wastage written off on closing {$productionOrder->production_no}",
                    'reference' => $productionOrder,
                    'created_by' => Auth::id(),
                    'lines' => [
                        ['account' => 'production_wastage_expense', 'debit' => $wip, 'credit' => 0],
                        ['account' => 'work_in_progress', 'debit' => 0, 'credit' => $wip],
                    ],
                ]);
            }

            $productionOrder->update([
                'status' => 'completed',
                'completed_at' => now()->toDateString(),
                'completed_by' => Auth::id(),
                'wastage_value' => $wip,
            ]);
        });

        return back()->with('success', "Production {$productionOrder->production_no} completed.");
    }

    /**
     * Validate picker rows against stock at $site and capture each one's
     * current average cost. $field is the request key, for error messages.
     */
    protected function resolveMaterials(array $rows, Site $site, string $field): array
    {
        $materials = [];

        foreach ($rows as $i => $row) {
            [$product, $variant] = $this->resolveItem($row['item']);

            if (! $product || ! $product->isRawMaterial()) {
                throw ValidationException::withMessages(["{$field}.{$i}.item" => 'Pick a raw material.']);
            }

            $balance = StockMovement::balanceFor($product->id, $variant?->id, $site->id);

            if ((float) $row['quantity'] > $balance) {
                throw ValidationException::withMessages([
                    "{$field}.{$i}.quantity" => "{$this->itemLabel($product, $variant)}: only {$balance} in stock at {$site->name}.",
                ]);
            }

            $materials[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => (float) $row['quantity'],
                'unit_cost' => $this->averageCost($product, $variant),
            ];
        }

        return $materials;
    }

    /**
     * One stage entry plus its material issue, and finished goods when the
     * stage is Finishing. Callers wrap this in a transaction.
     */
    protected function recordStage(ProductionOrder $order, string $stage, float $quantity, string $date, ?string $note, array $materials): ProductionStageEntry
    {
        $entry = $order->stageEntries()->create([
            'stage' => $stage,
            'quantity' => $quantity,
            'entry_date' => $date,
            'note' => $note,
            'created_by' => Auth::id(),
        ]);

        $issuedValue = 0;
        foreach ($materials as $m) {
            $entry->materials()->create([
                'production_order_id' => $order->id,
                'product_id' => $m['product']->id,
                'product_variant_id' => $m['variant']?->id,
                'quantity' => $m['quantity'],
                'unit_cost' => $m['unit_cost'],
            ]);

            StockMovement::create([
                'product_id' => $m['product']->id,
                'product_variant_id' => $m['variant']?->id,
                'site_id' => $order->site_id,
                'type' => 'production_consumption',
                'quantity' => $m['quantity'],
                'unit_cost' => $m['unit_cost'],
                'note' => "{$order->production_no} — {$entry->stage_label}",
                'moved_at' => $date,
                'created_by' => Auth::id(),
                'reference_type' => ProductionOrder::class,
                'reference_id' => $order->id,
            ]);

            $issuedValue += $m['quantity'] * $m['unit_cost'];
        }

        $issuedValue = round($issuedValue, 2);
        if ($issuedValue > 0) {
            LedgerService::post([
                'type' => 'production',
                'date' => $date,
                'site_id' => $order->site_id,
                'narration' => "Material issued to {$order->production_no} ({$entry->stage_label})",
                'reference' => $order,
                'created_by' => Auth::id(),
                'lines' => [
                    ['account' => 'work_in_progress', 'debit' => $issuedValue, 'credit' => 0],
                    ['account' => 'inventory', 'debit' => 0, 'credit' => $issuedValue],
                ],
            ]);
        }

        if ($stage === 'finishing') {
            $this->postFinishedGoods($order, $entry, $quantity, $date);
        }

        return $entry;
    }

    /**
     * Finishing hands pieces to finished-goods stock at the lot's Site.
     * Each finished piece carries an equal share of the WIP still on the
     * lot (WIP value ÷ pieces cut but not yet finished), so the lot's
     * total cost ends up spread over everything it actually produced.
     */
    protected function postFinishedGoods(ProductionOrder $order, ProductionStageEntry $entry, float $quantity, string $date): void
    {
        // Totals already include this entry, so add it back to get the
        // pieces in WIP before it.
        $inProcess = $order->piecesInProcess() + $quantity;
        $wip = $order->wipValue();

        $unitCost = $inProcess > 0 ? round($wip / $inProcess, 4) : 0;
        // Last pieces out take whatever is left, so rounding never strands
        // a few paisa in WIP.
        $value = $quantity >= $inProcess ? $wip : round($quantity * $unitCost, 2);

        $entry->update(['unit_cost' => $unitCost]);

        StockMovement::create([
            'product_id' => $order->product_id,
            'product_variant_id' => $order->product_variant_id,
            'site_id' => $order->site_id,
            'type' => 'production',
            'quantity' => $quantity,
            // No cost yet (no material issued) → leave it null so it doesn't
            // drag the item's average cost toward zero.
            'unit_cost' => $unitCost > 0 ? $unitCost : null,
            'note' => "{$order->production_no} — finished goods",
            'moved_at' => $date,
            'created_by' => Auth::id(),
            'reference_type' => ProductionOrder::class,
            'reference_id' => $order->id,
        ]);

        if ($value > 0) {
            LedgerService::post([
                'type' => 'production',
                'date' => $date,
                'site_id' => $order->site_id,
                'narration' => "Finished goods from {$order->production_no}",
                'reference' => $order,
                'created_by' => Auth::id(),
                'lines' => [
                    ['account' => 'inventory', 'debit' => $value, 'credit' => 0],
                    ['account' => 'work_in_progress', 'debit' => 0, 'credit' => $value],
                ],
            ]);
        }
    }
}
