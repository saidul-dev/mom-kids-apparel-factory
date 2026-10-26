<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesStockItems;
use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\SalesOrder;
use App\Models\Site;
use App\Models\StockMovement;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer orders out of the Factory or the wholesale Outlet — see
 * docs/garments-production.md. Order → Deliver (stock out + bill the
 * customer), with Cancel as the only other exit from pending.
 */
class SalesOrderController extends Controller implements HasMiddleware
{
    use ResolvesStockItems;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:sales.view', only: ['index', 'show']),
            new Middleware('permission:sales.create', only: ['create', 'store']),
            new Middleware('permission:sales.approve', only: ['deliver']),
            new Middleware('permission:sales.edit', only: ['cancel']),
        ];
    }

    public function index(Request $request)
    {
        $sites = Site::where('status', true)->orderBy('name')->get();
        $customers = Party::where('is_customer', true)->orderBy('name')->get(['id', 'name']);
        $siteId = $request->filled('site_id') ? $request->integer('site_id') : null;
        $partyId = $request->filled('party_id') ? $request->integer('party_id') : null;
        $status = $request->get('status');

        $orders = SalesOrder::with(['site', 'party'])
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($partyId, fn ($q) => $q->where('party_id', $partyId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.sales-orders.index', compact('orders', 'sites', 'customers', 'siteId', 'partyId', 'status'));
    }

    public function create(Request $request)
    {
        $sites = Site::where('status', true)->orderBy('name')->get();
        $customers = Party::where('is_customer', true)->where('status', true)->orderBy('name')->get(['id', 'name']);
        $site = $request->filled('site_id') ? Site::findOrFail($request->integer('site_id')) : null;

        // Every item is orderable (an order can be taken before production
        // finishes) — stock is only enforced on delivery.
        $itemOptions = $site ? $this->stockItemOptions($site->id, false, 'finished_good') : collect();

        return view('admin.sales-orders.create', compact('sites', 'customers', 'site', 'itemOptions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'party_id' => ['required', 'integer', 'exists:parties,id'],
            'order_date' => ['required', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item' => ['required', 'string', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $party = Party::findOrFail($validated['party_id']);
        if (! $party->is_customer) {
            throw ValidationException::withMessages(['party_id' => 'Pick a party marked as a Customer.']);
        }

        $rows = [];
        foreach ($validated['items'] as $i => $row) {
            [$product, $variant] = $this->resolveItem($row['item']);

            if (! $product || $product->isRawMaterial()) {
                throw ValidationException::withMessages(["items.{$i}.item" => 'Pick a finished goods item.']);
            }

            $rows[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => (float) $row['quantity'],
                'unit_price' => round((float) $row['unit_price'], 2),
                'line_total' => round((float) $row['quantity'] * (float) $row['unit_price'], 2),
            ];
        }

        $subtotal = round(array_sum(array_column($rows, 'line_total')), 2);
        $discount = round((float) ($validated['discount'] ?? 0), 2);

        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount' => 'Discount cannot exceed the subtotal.']);
        }

        $order = DB::transaction(function () use ($validated, $rows, $subtotal, $discount, $party) {
            $order = SalesOrder::create([
                'order_no' => 'PENDING',
                'site_id' => $validated['site_id'],
                'party_id' => $party->id,
                'order_date' => $validated['order_date'],
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => round($subtotal - $discount, 2),
                'note' => $validated['note'] ?? null,
                'created_by' => Auth::id(),
            ]);
            $order->update(['order_no' => 'SO-'.str_pad($order->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($rows as $row) {
                $order->items()->create([
                    'product_id' => $row['product']->id,
                    'product_variant_id' => $row['variant']?->id,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'line_total' => $row['line_total'],
                ]);
            }

            return $order;
        });

        return redirect()->route('sales-orders.show', $order)
            ->with('success', "Order {$order->order_no} created — deliver it once the goods are ready.");
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load([
            'site', 'party', 'createdBy', 'deliveredBy', 'ledgerTransactions',
            'items.product.stockUnit', 'items.productVariant.attributeValues',
        ]);

        // Live stock per line, so the screen shows what's short before
        // anyone tries to deliver.
        $balances = $salesOrder->items->mapWithKeys(fn ($item) => [
            $item->id => StockMovement::balanceFor($item->product_id, $item->product_variant_id, $salesOrder->site_id),
        ]);

        return view('admin.sales-orders.show', ['order' => $salesOrder, 'balances' => $balances]);
    }

    /**
     * Ship the order: one `sale` out per line at the order's Site, valued
     * at the current average cost, then bill the customer —
     * Debit Accounts Receivable (+ Discount Allowed) / Credit Sales Revenue,
     * and Debit COGS / Credit Inventory.
     */
    public function deliver(SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== 'pending') {
            return back()->with('error', 'Only a pending order can be delivered.');
        }

        $salesOrder->load(['items.product', 'items.productVariant', 'party', 'site']);

        foreach ($salesOrder->items as $item) {
            $balance = StockMovement::balanceFor($item->product_id, $item->product_variant_id, $salesOrder->site_id);

            if ((float) $item->quantity > $balance) {
                $label = $this->itemLabel($item->product, $item->productVariant);

                return back()->with('error', "{$label}: only {$balance} in stock at {$salesOrder->site->name}.");
            }
        }

        DB::transaction(function () use ($salesOrder) {
            $date = now()->toDateString();
            $costTotal = 0;

            foreach ($salesOrder->items as $item) {
                $unitCost = $this->averageCost($item->product, $item->productVariant);
                $item->update(['unit_cost' => $unitCost]);

                StockMovement::create([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'site_id' => $salesOrder->site_id,
                    'type' => 'sale',
                    'quantity' => $item->quantity,
                    'unit_cost' => $unitCost,
                    'moved_at' => $date,
                    'created_by' => Auth::id(),
                    'reference_type' => SalesOrder::class,
                    'reference_id' => $salesOrder->id,
                ]);

                $costTotal += (float) $item->quantity * $unitCost;
            }

            $total = (float) $salesOrder->total;
            $discount = (float) $salesOrder->discount;

            if ((float) $salesOrder->subtotal > 0) {
                $lines = [
                    ['account' => 'accounts_receivable', 'party_id' => $salesOrder->party_id, 'debit' => $total, 'credit' => 0],
                    ['account' => 'sales_revenue', 'debit' => 0, 'credit' => (float) $salesOrder->subtotal],
                ];
                if ($discount > 0) {
                    $lines[] = ['account' => 'discount_allowed', 'debit' => $discount, 'credit' => 0];
                }

                LedgerService::post([
                    'type' => 'sale',
                    'date' => $date,
                    'site_id' => $salesOrder->site_id,
                    'narration' => "Sale {$salesOrder->order_no} to {$salesOrder->party->name}",
                    'reference' => $salesOrder,
                    'created_by' => Auth::id(),
                    'lines' => $lines,
                ]);
            }

            $costTotal = round($costTotal, 2);
            if ($costTotal > 0) {
                LedgerService::post([
                    'type' => 'sale',
                    'date' => $date,
                    'site_id' => $salesOrder->site_id,
                    'narration' => "Cost of goods for {$salesOrder->order_no}",
                    'reference' => $salesOrder,
                    'created_by' => Auth::id(),
                    'lines' => [
                        ['account' => 'cost_of_goods_sold', 'debit' => $costTotal, 'credit' => 0],
                        ['account' => 'inventory', 'debit' => 0, 'credit' => $costTotal],
                    ],
                ]);
            }

            $salesOrder->update([
                'status' => 'delivered',
                'delivered_at' => $date,
                'delivered_by' => Auth::id(),
            ]);
        });

        return back()->with('success', "Order {$salesOrder->order_no} delivered — {$salesOrder->party->name} billed ".number_format($salesOrder->total, 2).'.');
    }

    public function cancel(SalesOrder $salesOrder)
    {
        if ($salesOrder->status !== 'pending') {
            return back()->with('error', 'Only a pending order can be cancelled.');
        }

        $salesOrder->update(['status' => 'cancelled']);

        return back()->with('success', "Order {$salesOrder->order_no} cancelled.");
    }
}
