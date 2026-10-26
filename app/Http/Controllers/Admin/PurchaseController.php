<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesStockItems;
use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Purchase;
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
 * Purchase from a Supplier — fabric rolls, thread, poly bags, etc. land
 * in stock at the receiving Site (usually the Factory) so Production can
 * consume them. See docs/garments-production.md.
 */
class PurchaseController extends Controller implements HasMiddleware
{
    use ResolvesStockItems;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.view', only: ['index', 'show']),
            new Middleware('permission:inventory.create', only: ['create', 'store']),
        ];
    }

    public function index(Request $request)
    {
        $sites = Site::where('status', true)->orderBy('name')->get();
        $suppliers = Party::where('is_supplier', true)->orderBy('name')->get(['id', 'name']);
        $siteId = $request->filled('site_id') ? $request->integer('site_id') : null;
        $partyId = $request->filled('party_id') ? $request->integer('party_id') : null;

        $purchases = Purchase::with(['site', 'party', 'createdBy'])
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($partyId, fn ($q) => $q->where('party_id', $partyId))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.purchases.index', compact('purchases', 'sites', 'suppliers', 'siteId', 'partyId'));
    }

    public function create(Request $request)
    {
        $sites = Site::where('status', true)->orderBy('name')->get();
        $suppliers = Party::where('is_supplier', true)->where('status', true)->orderBy('name')->get(['id', 'name']);
        $site = $request->filled('site_id') ? Site::findOrFail($request->integer('site_id')) : null;

        $itemOptions = $site ? $this->stockItemOptions($site->id) : collect();

        return view('admin.purchases.create', compact('sites', 'suppliers', 'site', 'itemOptions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'party_id' => ['required', 'integer', 'exists:parties,id'],
            'purchase_date' => ['required', 'date'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item' => ['required', 'string', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $party = Party::findOrFail($validated['party_id']);
        if (! $party->is_supplier) {
            throw ValidationException::withMessages(['party_id' => 'Pick a party marked as a Supplier.']);
        }

        $rows = [];
        foreach ($validated['items'] as $i => $row) {
            [$product, $variant] = $this->resolveItem($row['item']);

            if (! $product) {
                throw ValidationException::withMessages(["items.{$i}.item" => 'Pick a valid product or variant.']);
            }

            $rows[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => (float) $row['quantity'],
                'unit_cost' => (float) $row['unit_cost'],
                'line_total' => round((float) $row['quantity'] * (float) $row['unit_cost'], 2),
            ];
        }

        $subtotal = round(array_sum(array_column($rows, 'line_total')), 2);
        $discount = round((float) ($validated['discount'] ?? 0), 2);

        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount' => 'Discount cannot exceed the subtotal.']);
        }

        $total = round($subtotal - $discount, 2);
        // Spread the header discount across lines pro-rata so each item's
        // stocked cost reflects what was actually paid for it.
        $costFactor = $subtotal > 0 ? $total / $subtotal : 0;

        $purchase = DB::transaction(function () use ($validated, $rows, $subtotal, $discount, $total, $costFactor, $party) {
            $purchase = Purchase::create([
                'purchase_no' => 'PENDING',
                'site_id' => $validated['site_id'],
                'party_id' => $party->id,
                'purchase_date' => $validated['purchase_date'],
                'supplier_invoice_no' => $validated['supplier_invoice_no'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'note' => $validated['note'] ?? null,
                'created_by' => Auth::id(),
            ]);
            $purchase->update(['purchase_no' => 'PUR-'.str_pad($purchase->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($rows as $row) {
                $purchase->items()->create([
                    'product_id' => $row['product']->id,
                    'product_variant_id' => $row['variant']?->id,
                    'quantity' => $row['quantity'],
                    'unit_cost' => $row['unit_cost'],
                    'line_total' => $row['line_total'],
                ]);

                StockMovement::create([
                    'product_id' => $row['product']->id,
                    'product_variant_id' => $row['variant']?->id,
                    'site_id' => $validated['site_id'],
                    'type' => 'purchase',
                    'quantity' => $row['quantity'],
                    'unit_cost' => round($row['unit_cost'] * $costFactor, 4),
                    'moved_at' => $validated['purchase_date'],
                    'created_by' => Auth::id(),
                    'reference_type' => Purchase::class,
                    'reference_id' => $purchase->id,
                ]);
            }

            if ($total > 0) {
                LedgerService::post([
                    'type' => 'purchase',
                    'date' => $validated['purchase_date'],
                    'site_id' => $validated['site_id'],
                    'narration' => "Purchase {$purchase->purchase_no} from {$party->name}",
                    'reference' => $purchase,
                    'created_by' => Auth::id(),
                    'lines' => [
                        ['account' => 'inventory', 'debit' => $total, 'credit' => 0],
                        ['account' => 'accounts_payable', 'party_id' => $party->id, 'debit' => 0, 'credit' => $total],
                    ],
                ]);
            }

            return $purchase;
        });

        return redirect()->route('purchases.show', $purchase)
            ->with('success', "Purchase {$purchase->purchase_no} recorded — ".count($rows).' item(s) received.');
    }

    public function show(Purchase $purchase)
    {
        $purchase->load([
            'site', 'party', 'createdBy', 'ledgerTransaction',
            'items.product.stockUnit', 'items.productVariant.attributeValues',
        ]);

        return view('admin.purchases.show', compact('purchase'));
    }
}
