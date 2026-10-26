<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesStockItems;
use App\Http\Controllers\Controller;
use App\Models\ProductionOrder;
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

        $orders = ProductionOrder::with(['site', 'product.stockUnit', 'productVariant.attributeValues'])
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($status, fn ($q) => $q->where('status', $status))
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

        return view('admin.production.index', compact('orders', 'sites', 'siteId', 'status', 'totals'));
    }

    public function create()
    {
        $sites = Site::where('status', true)->orderBy('name')->get();
        // searchable-select reads ->id / ->name, so hand it objects.
        $items = $this->stockItemOptions()->map(fn ($o) => (object) $o);

        return view('admin.production.create', compact('sites', 'items'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'item' => ['required', 'string'],
            'planned_quantity' => ['nullable', 'numeric', 'min:1'],
            'start_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        [$product, $variant] = $this->resolveItem($validated['item']);

        if (! $product) {
            throw ValidationException::withMessages(['item' => 'Pick a valid product or variant to produce.']);
        }

        $order = DB::transaction(function () use ($validated, $product, $variant) {
            $order = ProductionOrder::create([
                'production_no' => 'PENDING',
                'site_id' => $validated['site_id'],
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'planned_quantity' => $validated['planned_quantity'] ?? null,
                'start_date' => $validated['start_date'],
                'status' => 'in_progress',
                'note' => $validated['note'] ?? null,
                'created_by' => Auth::id(),
            ]);
            $order->update(['production_no' => 'PRD-'.str_pad($order->id, 6, '0', STR_PAD_LEFT)]);

            return $order;
        });

        return redirect()->route('production-orders.show', $order)
            ->with('success', "Production lot {$order->production_no} started — record cutting to begin.");
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

        $materialOptions = $productionOrder->isOpen()
            ? $this->stockItemOptions($productionOrder->site_id, true)
            : collect();

        return view('admin.production.show', [
            'order' => $productionOrder,
            'totals' => $totals,
            'available' => $available,
            'inProcess' => $productionOrder->piecesInProcess($totals),
            'wipValue' => $productionOrder->wipValue(),
            'materialOptions' => $materialOptions,
        ]);
    }

    /**
     * Record pieces passing through one stage, optionally issuing raw
     * material from the lot's Site at the same time (fabric at cutting,
     * thread at sewing, poly bags at finishing...).
     */
    public function storeStage(Request $request, ProductionOrder $productionOrder)
    {
        if (! $productionOrder->isOpen()) {
            return back()->with('error', 'This production lot is already completed.');
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
            $previous = array_keys(ProductionOrder::STAGES)[array_search($stage, array_keys(ProductionOrder::STAGES), true) - 1];

            throw ValidationException::withMessages([
                'quantity' => "Only {$available} pcs are waiting for ".ProductionOrder::STAGES[$stage]
                    .' (from '.ProductionOrder::STAGES[$previous].').',
            ]);
        }

        $materials = [];
        foreach ($validated['materials'] ?? [] as $i => $row) {
            [$product, $variant] = $this->resolveItem($row['item']);

            if (! $product) {
                throw ValidationException::withMessages(["materials.{$i}.item" => 'Pick a valid material.']);
            }

            $balance = StockMovement::balanceFor($product->id, $variant?->id, $productionOrder->site_id);

            if ((float) $row['quantity'] > $balance) {
                throw ValidationException::withMessages([
                    "materials.{$i}.quantity" => "{$this->itemLabel($product, $variant)}: only {$balance} in stock at {$productionOrder->site->name}.",
                ]);
            }

            $materials[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => (float) $row['quantity'],
                'unit_cost' => $this->averageCost($product, $variant),
            ];
        }

        DB::transaction(function () use ($productionOrder, $validated, $stage, $quantity, $materials) {
            $entry = $productionOrder->stageEntries()->create([
                'stage' => $stage,
                'quantity' => $quantity,
                'entry_date' => $validated['entry_date'],
                'note' => $validated['note'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $issuedValue = 0;
            foreach ($materials as $m) {
                $entry->materials()->create([
                    'production_order_id' => $productionOrder->id,
                    'product_id' => $m['product']->id,
                    'product_variant_id' => $m['variant']?->id,
                    'quantity' => $m['quantity'],
                    'unit_cost' => $m['unit_cost'],
                ]);

                StockMovement::create([
                    'product_id' => $m['product']->id,
                    'product_variant_id' => $m['variant']?->id,
                    'site_id' => $productionOrder->site_id,
                    'type' => 'production_consumption',
                    'quantity' => $m['quantity'],
                    'unit_cost' => $m['unit_cost'],
                    'note' => "{$productionOrder->production_no} — {$entry->stage_label}",
                    'moved_at' => $validated['entry_date'],
                    'created_by' => Auth::id(),
                    'reference_type' => ProductionOrder::class,
                    'reference_id' => $productionOrder->id,
                ]);

                $issuedValue += $m['quantity'] * $m['unit_cost'];
            }

            $issuedValue = round($issuedValue, 2);
            if ($issuedValue > 0) {
                LedgerService::post([
                    'type' => 'production',
                    'date' => $validated['entry_date'],
                    'site_id' => $productionOrder->site_id,
                    'narration' => "Material issued to {$productionOrder->production_no} ({$entry->stage_label})",
                    'reference' => $productionOrder,
                    'created_by' => Auth::id(),
                    'lines' => [
                        ['account' => 'work_in_progress', 'debit' => $issuedValue, 'credit' => 0],
                        ['account' => 'inventory', 'debit' => 0, 'credit' => $issuedValue],
                    ],
                ]);
            }

            if ($stage === 'finishing') {
                $this->postFinishedGoods($productionOrder, $entry, $quantity, $validated['entry_date']);
            }
        });

        return back()->with('success', rtrim(rtrim(number_format($quantity, 4), '0'), '.')
            .' pcs recorded at '.ProductionOrder::STAGES[$stage].'.');
    }

    /**
     * Close the lot. Pieces still on the floor (cut but not finished) are
     * treated as wastage and their share of WIP is written off, so the
     * WIP account never carries cost for a lot nobody is working on.
     */
    public function complete(ProductionOrder $productionOrder)
    {
        if (! $productionOrder->isOpen()) {
            return back()->with('error', 'This production lot is already completed.');
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

        return back()->with('success', "Production lot {$productionOrder->production_no} completed.");
    }

    /**
     * Finishing hands pieces to finished-goods stock at the lot's Site.
     * Each finished piece carries an equal share of the WIP still on the
     * lot (WIP value ÷ pieces cut but not yet finished), so the lot's
     * total cost ends up spread over everything it actually produced.
     */
    protected function postFinishedGoods(ProductionOrder $order, $entry, float $quantity, string $date): void
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
