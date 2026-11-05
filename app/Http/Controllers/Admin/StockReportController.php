<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Site;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StockReportController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventory.view'),
        ];
    }

    /**
     * Current stock per SKU at a Site — SUM(in) - SUM(out) from the
     * stock_movements ledger, never a stored counter.
     *
     * `view=detail` (default): variable products contribute one row per
     * variant (their own SKU/stock).
     * `view=summary`: variable products contribute a single row for the
     * parent product, with stock summed across all its variants.
     */
    public function index(Request $request)
    {
        $sites = Site::where('status', true)->orderBy('name')->get();
        $categories = Category::orderBy('name')->get();
        $site = Site::find($this->dutySiteId($request));
        $availability = $request->get('availability') === 'out' ? 'out' : 'in';

        $products = null;

        if ($site) {
            $q = $request->q;
            $categoryId = $request->filled('category_id') ? $request->integer('category_id') : null;
            $isSummary = $request->get('view') === 'summary';

            $simpleRows = Product::with(['stockUnit', 'category'])
                ->where('status', true)
                ->where('has_variants', false)
                ->when($q, fn ($query) => $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%");
                }))
                ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
                ->get()
                ->map(fn (Product $product) => (object) [
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name,
                    'unit' => $product->stockUnit?->short_name,
                    'reorder_level' => $product->reorder_level,
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'is_group' => false,
                    'cost_price' => (float) $product->estimated_cost,
                    'sale_price' => (float) $product->selling_price,
                ]);

            $variableProducts = Product::with(['stockUnit', 'category', 'variants.attributeValues'])
                ->where('status', true)
                ->where('has_variants', true)
                ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
                ->get()
                ->map(function (Product $product) {
                    $product->setRelation('variants', $product->variants->where('status', true)->values());

                    return $product;
                })
                ->filter(fn (Product $product) => $product->variants->isNotEmpty());

            if ($isSummary) {
                $variantRows = $variableProducts
                    ->when($q, fn ($products) => $products->filter(
                        fn (Product $p) => str_contains(strtolower($p->name), strtolower($q))
                            || $p->variants->contains(fn (ProductVariant $v) => str_contains(strtolower($v->sku), strtolower($q)))
                    ))
                    ->map(fn (Product $product) => (object) [
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'category' => $product->category?->name,
                        'unit' => $product->stockUnit?->short_name,
                        'reorder_level' => $product->reorder_level,
                        'product_id' => $product->id,
                        'variant_id' => null,
                        'is_group' => true,
                        'variant_count' => $product->variants->count(),
                    ])
                    ->values();
            } else {
                $variantRows = $variableProducts
                    ->flatMap(function (Product $product) use ($q) {
                        return $product->variants
                            ->when($q, fn ($variants) => $variants->filter(
                                fn (ProductVariant $v) => str_contains(strtolower($product->name), strtolower($q))
                                    || str_contains(strtolower($v->sku), strtolower($q))
                            ))
                            ->map(fn (ProductVariant $variant) => (object) [
                                'name' => $product->name.' — '.$variant->label,
                                'sku' => $variant->sku,
                                'category' => $product->category?->name,
                                'unit' => $product->stockUnit?->short_name,
                                'reorder_level' => $product->reorder_level,
                                'product_id' => $product->id,
                                'variant_id' => $variant->id,
                                'is_group' => false,
                                // A variant without its own estimated cost inherits the parent product's.
                                'cost_price' => (float) ($variant->estimated_cost ?? $product->estimated_cost),
                                'sale_price' => (float) $variant->selling_price,
                            ]);
                    });
            }

            $all = $simpleRows->concat($variantRows)->sortBy('name')->values();

            // Balances/valuations must be known for every matching row (not
            // just one page) before the Availability filter can decide who
            // even makes it into the paginated set.
            $simpleProductIds = $all->where('is_group', false)->whereNull('variant_id')->pluck('product_id');
            $variantIds = $all->whereNotNull('variant_id')->pluck('variant_id');
            $groupProductIds = $all->where('is_group', true)->pluck('product_id');

            $simpleBalances = StockMovement::where('site_id', $site->id)
                ->whereIn('product_id', $simpleProductIds)
                ->whereNull('product_variant_id')
                ->selectRaw("product_id, SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) as balance")
                ->groupBy('product_id')
                ->pluck('balance', 'product_id');

            $variantBalances = StockMovement::where('site_id', $site->id)
                ->whereIn('product_variant_id', $variantIds)
                ->selectRaw("product_variant_id, SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) as balance")
                ->groupBy('product_variant_id')
                ->pluck('balance', 'product_variant_id');

            // Variant movements always carry the parent product_id too, so
            // the group total is just "every variant movement for this product".
            $groupBalances = StockMovement::where('site_id', $site->id)
                ->whereIn('product_id', $groupProductIds)
                ->whereNotNull('product_variant_id')
                ->selectRaw("product_id, SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) as balance")
                ->groupBy('product_id')
                ->pluck('balance', 'product_id');

            // A group row has no single unit price (its variants can each
            // have their own), so its valuation is the sum of every
            // variant's own balance × price rather than qty × one price.
            $groupCostValuation = collect();
            $groupSaleValuation = collect();
            $groupProducts = $variableProducts->whereIn('id', $groupProductIds)->keyBy('id');

            if ($groupProducts->isNotEmpty()) {
                $groupVariantIds = $groupProducts->flatMap(fn (Product $p) => $p->variants->pluck('id'));

                $groupVariantBalances = StockMovement::where('site_id', $site->id)
                    ->whereIn('product_variant_id', $groupVariantIds)
                    ->selectRaw("product_variant_id, SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) as balance")
                    ->groupBy('product_variant_id')
                    ->pluck('balance', 'product_variant_id');

                foreach ($groupProducts as $product) {
                    $cost = 0.0;
                    $sale = 0.0;

                    foreach ($product->variants as $variant) {
                        $bal = (float) ($groupVariantBalances[$variant->id] ?? 0);
                        $cost += $bal * (float) ($variant->estimated_cost ?? $product->estimated_cost);
                        $sale += $bal * (float) $variant->selling_price;
                    }

                    $groupCostValuation[$product->id] = $cost;
                    $groupSaleValuation[$product->id] = $sale;
                }
            }

            $all = $all->map(function ($row) use ($simpleBalances, $variantBalances, $groupBalances, $groupCostValuation, $groupSaleValuation) {
                $row->balance = (float) match (true) {
                    $row->variant_id !== null => $variantBalances[$row->variant_id] ?? 0,
                    $row->is_group => $groupBalances[$row->product_id] ?? 0,
                    default => $simpleBalances[$row->product_id] ?? 0,
                };
                $row->cost_valuation = $row->is_group
                    ? (float) ($groupCostValuation[$row->product_id] ?? 0)
                    : $row->balance * $row->cost_price;
                $row->sale_valuation = $row->is_group
                    ? (float) ($groupSaleValuation[$row->product_id] ?? 0)
                    : $row->balance * $row->sale_price;

                return $row;
            });

            // Default "In Stock" (balance >= 1) — "Out of Stock" is the
            // complement, so a fractional leftover (e.g. 0.5) counts as out.
            $all = $all->filter(fn ($row) => $availability === 'out' ? $row->balance < 1 : $row->balance >= 1)->values();

            $perPage = 20;
            $page = max(1, $request->integer('page', 1));
            $products = new LengthAwarePaginator(
                $all->forPage($page, $perPage)->values(),
                $all->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        return view('admin.stock.report', compact('sites', 'categories', 'site', 'products', 'availability'));
    }
}
