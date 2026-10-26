<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
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

        $babySizes = ['0-3M', '3-6M', '6-12M'];
        $kidsSizes = ['1-2Y', '2-3Y', '3-4Y', '4-5Y', '5-6Y', '7-8Y'];
        $momSizes = ['S', 'M', 'L', 'XL', 'XXL'];

        $finishedGoods = [
            ['name' => 'Baby Romper', 'category' => 'Baby Wear', 'price' => 380, 'sizes' => $babySizes],
            ['name' => 'Baby Jhabla Set', 'category' => 'Baby Wear', 'price' => 320, 'sizes' => $babySizes],
            ['name' => 'Girls Silk Frock', 'category' => 'Kids Wear', 'price' => 650, 'sizes' => $kidsSizes],
            ['name' => 'Kids Round Neck T-Shirt', 'category' => 'Kids Wear', 'price' => 250, 'sizes' => $kidsSizes],
            ['name' => 'Kids Pajama Set', 'category' => 'Kids Wear', 'price' => 450, 'sizes' => $kidsSizes],
            ['name' => 'Maternity Gown', 'category' => 'Mom Wear', 'price' => 950, 'sizes' => $momSizes],
            ['name' => 'Nursing Top', 'category' => 'Mom Wear', 'price' => 650, 'sizes' => $momSizes],
        ];

        $sizeAttributeId = Attribute::where('name', 'Size')->value('id');
        $sizeValueIds = AttributeValue::where('attribute_id', $sizeAttributeId)->pluck('id', 'value');

        foreach ($finishedGoods as $item) {
            $product = $this->product($item['name'], 'finished_good', $categories[$item['category']], $units['Pcs'], $item['price'], 0, (bool) $sizeAttributeId);

            if (! $sizeAttributeId) {
                continue;
            }

            foreach ($item['sizes'] as $size) {
                if (! isset($sizeValueIds[$size])) {
                    continue;
                }

                $variant = ProductVariant::firstOrCreate(
                    ['sku' => "{$product->sku}-".strtoupper($size)],
                    ['product_id' => $product->id, 'selling_price' => $item['price'], 'estimated_cost' => 0, 'status' => true]
                );
                if (! $variant->barcode) {
                    $variant->update(['barcode' => Product::internalBarcode('21', $variant->id)]);
                }

                $variant->attributeValues()->syncWithoutDetaching([
                    $sizeValueIds[$size] => ['attribute_id' => $sizeAttributeId],
                ]);
            }
        }
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
