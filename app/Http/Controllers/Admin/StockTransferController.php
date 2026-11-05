<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Site;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.view', only: ['index', 'show', 'print']),
            new Middleware('permission:inventory.create', only: ['create', 'store']),
            new Middleware('permission:inventory.approve', only: ['receive']),
            new Middleware('permission:inventory.edit', only: ['cancel']),
        ];
    }

    /**
     * Dispatch history — every transfer, filterable by Site (either leg)
     * and status, newest first.
     */
    public function index(Request $request)
    {
        $sites = Site::where('status', true)->orderBy('name')->get();
        $status = $request->get('status');
        $siteId = $request->filled('site_id') ? $request->integer('site_id') : null;

        $transfers = StockTransfer::with(['fromSite', 'toSite', 'dispatchedBy', 'receivedBy'])
            ->when($siteId, fn ($q) => $q->where(function ($q) use ($siteId) {
                $q->where('from_site_id', $siteId)->orWhere('to_site_id', $siteId);
            }))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('dispatched_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.stock.transfers.index', compact('transfers', 'sites', 'status', 'siteId'));
    }

    /**
     * Dispatch form: From Site + To Site, then a cart-style item picker
     * (search + add) sourced from whatever currently has balance > 0 at
     * From Site — nothing with zero stock is offered.
     */
    public function create(Request $request)
    {
        $sites = Site::where('status', true)->orderBy('name')->get();
        $fromSite = $request->filled('from_site_id') ? Site::findOrFail($request->integer('from_site_id')) : null;
        $toSite = $request->filled('to_site_id') ? Site::findOrFail($request->integer('to_site_id')) : null;

        $itemOptions = $fromSite ? $this->itemOptions($fromSite->id) : collect();

        return view('admin.stock.transfers.create', compact('sites', 'fromSite', 'toSite', 'itemOptions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_site_id' => ['required', 'integer', 'exists:sites,id'],
            'to_site_id' => ['required', 'integer', 'exists:sites,id', 'different:from_site_id'],
            'dispatched_at' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.batch_no' => ['nullable', 'string', 'max:100'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.serial_no' => ['nullable', 'string', 'max:100'],
        ]);

        $rows = [];
        foreach ($validated['items'] as $i => $row) {
            [$product, $variant] = $this->resolveItem($row['item']);

            if (! $product) {
                throw ValidationException::withMessages(["items.{$i}.item" => 'Pick a valid product or variant.']);
            }

            $balance = $this->currentBalance($validated['from_site_id'], $product->id, $variant?->id);

            if ((float) $row['quantity'] > $balance) {
                throw ValidationException::withMessages([
                    "items.{$i}.quantity" => "{$product->name}: cannot transfer more than the current stock ({$balance}).",
                ]);
            }

            $rows[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => $row['quantity'],
                'unit_cost' => (float) ($variant->estimated_cost ?? $product->estimated_cost),
                'batch_no' => $row['batch_no'] ?? null,
                'expiry_date' => $row['expiry_date'] ?? null,
                'serial_no' => $row['serial_no'] ?? null,
            ];
        }

        $transfer = DB::transaction(function () use ($validated, $rows) {
            $transfer = StockTransfer::create([
                'transfer_no' => 'PENDING',
                'from_site_id' => $validated['from_site_id'],
                'to_site_id' => $validated['to_site_id'],
                'status' => 'in_transit',
                'note' => $validated['note'] ?? null,
                'dispatched_at' => $validated['dispatched_at'],
                'dispatched_by' => Auth::id(),
            ]);
            $transfer->update(['transfer_no' => 'TRF-'.str_pad($transfer->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($rows as $row) {
                $transfer->items()->create([
                    'product_id' => $row['product']->id,
                    'product_variant_id' => $row['variant']?->id,
                    'quantity' => $row['quantity'],
                    'unit_cost' => $row['unit_cost'],
                    'batch_no' => $row['batch_no'],
                    'expiry_date' => $row['expiry_date'],
                    'serial_no' => $row['serial_no'],
                ]);

                StockMovement::create([
                    'product_id' => $row['product']->id,
                    'product_variant_id' => $row['variant']?->id,
                    'site_id' => $validated['from_site_id'],
                    'type' => 'transfer_out',
                    'quantity' => $row['quantity'],
                    'unit_cost' => $row['unit_cost'],
                    'batch_no' => $row['batch_no'],
                    'expiry_date' => $row['expiry_date'],
                    'serial_no' => $row['serial_no'],
                    'moved_at' => $validated['dispatched_at'],
                    'created_by' => Auth::id(),
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                ]);
            }

            return $transfer;
        });

        return redirect()->route('stock.transfers.show', $transfer)
            ->with('success', "Transfer {$transfer->transfer_no} dispatched — ".count($rows).' item(s).');
    }

    public function show(StockTransfer $transfer)
    {
        $transfer->load([
            'fromSite', 'toSite', 'dispatchedBy', 'receivedBy',
            'items.product.stockUnit', 'items.productVariant.attributeValues',
        ]);

        return view('admin.stock.transfers.show', compact('transfer'));
    }

    /**
     * Printable transfer bill (challan) that travels with the goods: every
     * line at the cost it carries to the receiving Site, with the total.
     */
    public function print(StockTransfer $transfer)
    {
        $transfer->load([
            'fromSite', 'toSite', 'dispatchedBy', 'receivedBy',
            'items.product.stockUnit', 'items.productVariant.attributeValues',
        ]);
        $company = CompanySetting::current();

        return view('admin.stock.transfers.print', compact('transfer', 'company'));
    }

    /**
     * Confirm arrival: posts one transfer_in per line item at to_site,
     * carrying over exactly what was dispatched (qty/cost/batch/expiry/
     * serial) — nothing is re-entered or editable here.
     */
    public function receive(StockTransfer $transfer)
    {
        if ($transfer->status !== 'in_transit') {
            return back()->with('error', 'This transfer is not in transit.');
        }

        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                StockMovement::create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'site_id' => $transfer->to_site_id,
                    'type' => 'transfer_in',
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'batch_no' => $item->batch_no,
                    'expiry_date' => $item->expiry_date,
                    'serial_no' => $item->serial_no,
                    'moved_at' => now()->toDateString(),
                    'created_by' => Auth::id(),
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                ]);
            }

            $transfer->update([
                'status' => 'received',
                'received_at' => now()->toDateString(),
                'received_by' => Auth::id(),
            ]);
        });

        return back()->with('success', "Transfer {$transfer->transfer_no} received.");
    }

    /**
     * Void an in-transit transfer: reverses the stock back at from_site.
     * The original transfer_out rows are never touched — an offsetting
     * transfer_in is posted instead, same as every other correction in
     * this ledger.
     */
    public function cancel(StockTransfer $transfer)
    {
        if ($transfer->status !== 'in_transit') {
            return back()->with('error', 'Only an in-transit transfer can be cancelled.');
        }

        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                StockMovement::create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'site_id' => $transfer->from_site_id,
                    'type' => 'transfer_in',
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'batch_no' => $item->batch_no,
                    'expiry_date' => $item->expiry_date,
                    'serial_no' => $item->serial_no,
                    'note' => "Cancelled: reversal of {$transfer->transfer_no}.",
                    'moved_at' => now()->toDateString(),
                    'created_by' => Auth::id(),
                    'reference_type' => StockTransfer::class,
                    'reference_id' => $transfer->id,
                ]);
            }

            $transfer->update(['status' => 'cancelled']);
        });

        return back()->with('success', "Transfer {$transfer->transfer_no} cancelled — stock reversed at {$transfer->fromSite->name}.");
    }

    /**
     * Every active simple product / variant that currently has balance > 0
     * at $siteId, as options for the cart-builder's item picker.
     */
    protected function itemOptions(int $siteId): Collection
    {
        $products = Product::with('stockUnit')
            ->where('status', true)->where('has_variants', false)->orderBy('name')->get();

        $variants = ProductVariant::with(['product.stockUnit', 'attributeValues'])
            ->where('status', true)
            ->whereHas('product', fn ($q) => $q->where('status', true)->where('has_variants', true))
            ->get();

        $simpleBalances = StockMovement::where('site_id', $siteId)
            ->whereIn('product_id', $products->pluck('id'))
            ->whereNull('product_variant_id')
            ->selectRaw("product_id, SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) as balance")
            ->groupBy('product_id')
            ->pluck('balance', 'product_id');

        $variantBalances = StockMovement::where('site_id', $siteId)
            ->whereIn('product_variant_id', $variants->pluck('id'))
            ->selectRaw("product_variant_id, SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) as balance")
            ->groupBy('product_variant_id')
            ->pluck('balance', 'product_variant_id');

        $options = collect();

        foreach ($products as $p) {
            $balance = (float) ($simpleBalances[$p->id] ?? 0);
            if ($balance <= 0) {
                continue;
            }
            $options->push([
                'id' => "product-{$p->id}",
                'name' => "{$p->name} ({$p->sku})",
                'unit' => $p->stockUnit?->short_name,
                'balance' => $balance,
                'trackBatch' => (bool) $p->track_batch,
                'trackExpiry' => (bool) $p->track_expiry,
                'trackSerial' => (bool) $p->track_serial,
            ]);
        }

        foreach ($variants as $v) {
            $balance = (float) ($variantBalances[$v->id] ?? 0);
            if ($balance <= 0) {
                continue;
            }
            $options->push([
                'id' => "variant-{$v->id}",
                'name' => "{$v->product->name} — {$v->label} ({$v->sku})",
                'unit' => $v->product->stockUnit?->short_name,
                'balance' => $balance,
                'trackBatch' => (bool) $v->product->track_batch,
                'trackExpiry' => (bool) $v->product->track_expiry,
                'trackSerial' => (bool) $v->product->track_serial,
            ]);
        }

        return $options->sortBy('name')->values();
    }

    /**
     * Split the "product-{id}"/"variant-{id}" picker value back into its
     * parent Product (and ProductVariant, when it's a variant row).
     */
    protected function resolveItem(?string $item): array
    {
        if (! $item || ! str_contains($item, '-')) {
            return [null, null];
        }

        [$kind, $id] = explode('-', $item, 2);

        if ($kind === 'product') {
            $product = Product::where('status', true)->where('has_variants', false)->find($id);

            return [$product, null];
        }

        if ($kind === 'variant') {
            $variant = ProductVariant::with('product')->where('status', true)->find($id);

            return [$variant?->product, $variant];
        }

        return [null, null];
    }

    protected function currentBalance(int $siteId, int $productId, ?int $variantId): float
    {
        $query = StockMovement::where('site_id', $siteId)->where('product_id', $productId);
        $variantId ? $query->where('product_variant_id', $variantId) : $query->whereNull('product_variant_id');

        return (float) $query->selectRaw("SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) as balance")
            ->value('balance') ?? 0;
    }
}
