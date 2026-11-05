<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class GarmentsFactorySeeder extends Seeder
{
    /**
     * Demo catalogue for Mom & Kids Apparel (docs/garments-production.md):
     * raw material to purchase and issue to production, and finished
     * baby/kids/mom wear in sizes to produce and sell. SKU and barcode
     * follow the same auto rules as the product form. The two Sites come
     * from SiteSeeder.
     */
    public function run(): void
    {
        // Fabric is stocked in whatever unit it's bought in — Roll or Gaj
        // (yard) — and production issues it in that same unit.
        $units = [
            'Roll' => Unit::firstOrCreate(['short_name' => 'Roll'], ['name' => 'Roll']),
            'Gaj' => Unit::firstOrCreate(['short_name' => 'Gaj'], ['name' => 'Gaj (Yard)']),
            'Pcs' => Unit::firstOrCreate(['short_name' => 'Pcs'], ['name' => 'Piece']),
        ];

        $categories = collect(['Raw Material', 'Baby Wear', 'Kids Wear', 'Mom Wear'])
            ->mapWithKeys(fn ($name) => [$name => Category::firstOrCreate(['name' => $name])]);

        $rawMaterials = [
            ['name' => 'Silk Fabric', 'unit' => 'Roll', 'reorder' => 5],
            ['name' => 'Cotton Fabric', 'unit' => 'Gaj', 'reorder' => 200],
            ['name' => 'Rib Fabric', 'unit' => 'Gaj', 'reorder' => 100],
            ['name' => 'Sewing Thread', 'unit' => 'Pcs', 'reorder' => 50],
            ['name' => 'Button', 'unit' => 'Pcs', 'reorder' => 500],
            ['name' => 'Elastic', 'unit' => 'Gaj', 'reorder' => 100],
            ['name' => 'Poly Bag', 'unit' => 'Pcs', 'reorder' => 500],
        ];

        foreach ($rawMaterials as $item) {
            $this->product($item['name'], 'raw_material', $categories['Raw Material'], $units[$item['unit']], 0, $item['reorder']);
        }

        $finishedGoods = [
            ['name' => 'Baby Romper', 'category' => 'Baby Wear', 'price' => 380],
            ['name' => 'Baby Jhabla Set', 'category' => 'Baby Wear', 'price' => 320],
            ['name' => 'Girls Silk Frock', 'category' => 'Kids Wear', 'price' => 650],
            ['name' => 'Kids Round Neck T-Shirt', 'category' => 'Kids Wear', 'price' => 250],
            ['name' => 'Kids Pajama Set', 'category' => 'Kids Wear', 'price' => 450],
            ['name' => 'Maternity Gown', 'category' => 'Mom Wear', 'price' => 950],
            ['name' => 'Nursing Top', 'category' => 'Mom Wear', 'price' => 650],
        ];

        foreach ($finishedGoods as $item) {
            $this->product($item['name'], 'finished_good', $categories[$item['category']], $units['Pcs'], $item['price'], 0, true);
        }

        // Every garment in every size of the factory's size run.
        $this->call(GarmentSizeSeeder::class);
    }

    /**
     * Idempotent by name, so re-running the seeder never duplicates.
     */
    protected function product(string $name, string $type, Category $category, Unit $unit, float $price, int $reorder, bool $hasVariants = false): Product
    {
        $product = Product::firstOrCreate(
            ['name' => $name],
            [
                'type' => $type,
                'sku' => Product::nextSku($name),
                'category_id' => $category->id,
                'stock_unit_id' => $unit->id,
                'purchase_unit_id' => $unit->id,
                'purchase_unit_conversion' => 1,
                'sale_unit_id' => $unit->id,
                'sale_unit_conversion' => 1,
                'estimated_cost' => 0,
                'selling_price' => $price,
                'reorder_level' => $reorder,
                'status' => true,
                'has_variants' => $hasVariants,
            ]
        );

        if (! $product->barcode) {
            $product->update(['barcode' => Product::internalBarcode('20', $product->id)]);
        }

        return $product;
    }
}
