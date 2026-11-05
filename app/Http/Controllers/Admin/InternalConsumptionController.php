<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Site;
use App\Models\StockMovement;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class InternalConsumptionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.edit'),
        ];
    }

    /**
     * Single-item deduction against the stock_movements ledger for goods
     * used internally (office/self-use), not sold — see
     * docs/inventory-movement-types.md. Unlike Stock Adjustment this also
     * posts a Debit internal_consumption_expense / Credit inventory voucher
     * (see store()), since a deliberate consumption is a real cost, not a
     * count correction.
     */
    public function index(Request $request)
    {
        $sites = Site::where('status', true)->orderBy('name')->get();
        $site = Site::find($this->dutySiteId($request));

        $items = collect();
        $product = null;
        $variant = null;
        $balance = 0;
        $avgCost = 0;

        if ($site) {
            $items = $this->itemOptions();

            [$product, $variant] = $this->resolveItem($request->string('item'));

            if ($product) {
                $balance = $this->currentBalance($site->id, $product->id, $variant?->id);
                $avgCost = (float) ($variant->estimated_cost ?? $product->estimated_cost);
            }
        }

        return view('admin.stock.internal-consumption', [
            'sites' => $sites,
            'site' => $site,
            'items' => $items,
            'selectedItem' => $request->string('item')->toString(),
            'product' => $product,
            'variant' => $variant,
            'balance' => $balance,
            'avgCost' => $avgCost,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'item' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'note' => ['required', 'string', 'max:1000'],
            'batch_no' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'serial_no' => ['nullable', 'string', 'max:100'],
            'moved_at' => ['required', 'date'],
        ]);

        [$product, $variant] = $this->resolveItem($validated['item']);

        if (! $product) {
            throw ValidationException::withMessages(['item' => 'Pick a valid product or variant.']);
        }

        $balance = $this->currentBalance($validated['site_id'], $product->id, $variant?->id);

        if ($validated['quantity'] > $balance) {
            throw ValidationException::withMessages([
                'quantity' => "Cannot consume more than the current stock ({$balance}).",
            ]);
        }

        // Never trust a client-supplied cost — an out movement is always
        // valued at whatever the item's current weighted-average cost is.
        $unitCost = (float) ($variant->estimated_cost ?? $product->estimated_cost);

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'site_id' => $validated['site_id'],
            'type' => 'internal_consumption',
            'quantity' => $validated['quantity'],
            'unit_cost' => $unitCost,
            'batch_no' => $validated['batch_no'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'serial_no' => $validated['serial_no'] ?? null,
            'note' => $validated['note'],
            'moved_at' => $validated['moved_at'],
            'created_by' => Auth::id(),
        ]);

        $label = $variant ? "{$product->name} — {$variant->label}" : $product->name;
        $costValue = round($validated['quantity'] * $unitCost, 2);

        // Nothing costed yet (fresh item, never had a costed "in" movement)
        // — the stock still drops, there's just no financial entry to post.
        if ($costValue > 0) {
            LedgerService::post([
                'type' => 'internal_consumption',
                'date' => $validated['moved_at'],
                'site_id' => $validated['site_id'],
                'narration' => "Internal consumption — {$label}",
                'reference' => $movement,
                'created_by' => Auth::id(),
                'lines' => [
                    ['account' => 'internal_consumption_expense', 'debit' => $costValue, 'credit' => 0],
                    ['account' => 'inventory', 'debit' => 0, 'credit' => $costValue],
                ],
            ]);
        }

        return redirect()->route('stock.internal-consumption.index', ['site_id' => $validated['site_id']])
            ->with('success', "Internal consumption recorded for {$label}.");
    }

    /**
     * Every active simple product and active variant, as {value, name}
     * options for the item picker — value is "product-{id}" or
     * "variant-{id}" so a single select can carry either kind.
     */
    protected function itemOptions()
    {
        $products = Product::where('status', true)
            ->where('has_variants', false)
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => (object) [
                'id' => "product-{$p->id}",
                'name' => "{$p->name} ({$p->sku})",
            ]);

        $variants = ProductVariant::with(['product', 'attributeValues'])
            ->where('status', true)
            ->whereHas('product', fn ($q) => $q->where('status', true)->where('has_variants', true))
            ->get()
            ->map(fn (ProductVariant $v) => (object) [
                'id' => "variant-{$v->id}",
                'name' => "{$v->product->name} — {$v->label} ({$v->sku})",
            ]);

        return $products->concat($variants)->sortBy('name')->values();
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
            $product = Product::with('stockUnit')->where('status', true)->where('has_variants', false)->find($id);

            return [$product, null];
        }

        if ($kind === 'variant') {
            $variant = ProductVariant::with('product.stockUnit')->where('status', true)->find($id);

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
