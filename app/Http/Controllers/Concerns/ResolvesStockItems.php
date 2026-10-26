<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

/**
 * Shared "product-{id}" / "variant-{id}" item picker used by the
 * Purchase, Production and Sales Order screens — same option shape and
 * encoding as the Stock Transfer cart, so the existing Alpine carts
 * (purchaseCart/saleCart/transferCart) work unchanged.
 */
trait ResolvesStockItems
{
    /**
     * Every active simple product / variant as a picker option. With a
     * $siteId each option carries its live balance there; $inStockOnly
     * additionally drops anything with nothing on hand.
     */
    protected function stockItemOptions(?int $siteId = null, bool $inStockOnly = false): Collection
    {
        $products = Product::with('stockUnit')
            ->where('status', true)->where('has_variants', false)->orderBy('name')->get();

        $variants = ProductVariant::with(['product.stockUnit', 'attributeValues'])
            ->where('status', true)
            ->whereHas('product', fn ($q) => $q->where('status', true)->where('has_variants', true))
            ->get();

        $simpleBalances = collect();
        $variantBalances = collect();

        if ($siteId) {
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
        }

        $options = collect();

        foreach ($products as $p) {
            $options->push([
                'id' => "product-{$p->id}",
                'name' => "{$p->name} ({$p->sku})",
                'unit' => $p->stockUnit?->short_name,
                'balance' => round((float) ($simpleBalances[$p->id] ?? 0), 4),
                'cost' => round((float) $p->estimated_cost, 2),
                'price' => round((float) $p->selling_price, 2),
            ]);
        }

        foreach ($variants as $v) {
            $options->push([
                'id' => "variant-{$v->id}",
                'name' => "{$v->product->name} — {$v->label} ({$v->sku})",
                'unit' => $v->product->stockUnit?->short_name,
                'balance' => round((float) ($variantBalances[$v->id] ?? 0), 4),
                'cost' => round((float) ($v->estimated_cost ?? $v->product->estimated_cost), 2),
                'price' => round((float) ($v->selling_price ?? $v->product->selling_price), 2),
            ]);
        }

        if ($inStockOnly) {
            $options = $options->filter(fn ($o) => $o['balance'] > 0);
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
            return [Product::where('status', true)->where('has_variants', false)->find($id), null];
        }

        if ($kind === 'variant') {
            $variant = ProductVariant::with('product')->where('status', true)->find($id);

            return [$variant?->product, $variant];
        }

        return [null, null];
    }

    protected function itemLabel(Product $product, ?ProductVariant $variant): string
    {
        return $variant ? "{$product->name} — {$variant->label}" : $product->name;
    }

    /**
     * An item's current weighted-average cost — what every "out" movement
     * is valued at. Never taken from the client.
     */
    protected function averageCost(Product $product, ?ProductVariant $variant): float
    {
        return (float) ($variant->estimated_cost ?? $product->estimated_cost);
    }
}
