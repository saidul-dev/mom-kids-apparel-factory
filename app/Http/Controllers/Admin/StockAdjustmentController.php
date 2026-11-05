<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Site;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockAdjustmentController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.edit'),
        ];
    }

    /**
     * Single-item correction against the stock_movements ledger: pick a
     * Site, then a product or variant, see its current balance, and post
     * one adjustment_in/adjustment_out movement for the difference.
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
                // Global, all-time weighted-average cost — kept in sync by
                // StockMovement::recalculateAverageCost() on every costed
                // "in" movement, so this is always the current figure.
                $avgCost = (float) ($variant->estimated_cost ?? $product->estimated_cost);
            }
        }

        return view('admin.stock.adjustment', [
            'sites' => $sites,
            'site' => $site,
            'items' => $items,
            'selectedItem' => $request->string('item')->toString(),
            'product' => $product,
            'variant' => $variant,
            'balance' => $balance,
            'avgCost' => $avgCost,
            'reasons' => StockMovement::REASONS,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'item' => ['required', 'string'],
            'direction' => ['required', 'in:in,out'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            // Required for Additions — every unit added needs a known cost
            // to keep the item's average cost meaningful. Deductions don't
            // carry a cost (they're valued at whatever the average already is).
            'unit_cost' => ['required_if:direction,in', 'nullable', 'numeric', 'min:0'],
            'reason' => ['required', Rule::in(array_keys(StockMovement::REASONS))],
            'note' => ['nullable', 'string', 'max:1000'],
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

        if ($validated['direction'] === 'out' && $validated['quantity'] > $balance) {
            throw ValidationException::withMessages([
                'quantity' => "Cannot deduct more than the current stock ({$balance}).",
            ]);
        }

        StockMovement::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'site_id' => $validated['site_id'],
            'type' => $validated['direction'] === 'in' ? 'adjustment_in' : 'adjustment_out',
            'reason' => $validated['reason'],
            'quantity' => $validated['quantity'],
            'unit_cost' => $validated['unit_cost'] ?? null,
            'batch_no' => $validated['batch_no'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'serial_no' => $validated['serial_no'] ?? null,
            'note' => $validated['note'] ?? null,
            'moved_at' => $validated['moved_at'],
            'created_by' => Auth::id(),
        ]);

        $label = $variant ? "{$product->name} — {$variant->label}" : $product->name;

        return redirect()->route('stock.adjustment.index', ['site_id' => $validated['site_id']])
            ->with('success', "Stock adjustment saved for {$label}.");
    }

    /**
     * Every active simple product and active variant at a site, as
     * {value, name} options for the item picker — value is "product-{id}"
     * or "variant-{id}" so a single select can carry either kind.
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
