<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Site;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class GarmentsFactorySeeder extends Seeder
{
    /**
     * Demo setup for the garments flow (docs/garments-production.md): the
     * Factory and the wholesale Outlet as Sites, raw material to purchase
     * and issue to production, and finished kids-wear items to produce.
     */
    public function run(): void
    {
        $factory = Site::firstOrCreate(
            ['code' => 'FAC-01'],
            ['name' => 'Factory', 'type' => 'Factory', 'address' => 'Dhaka, Bangladesh', 'status' => true]
        );

        $outlet = Site::firstOrCreate(
            ['code' => 'OUT-01'],
            ['name' => 'Wholesale Outlet', 'type' => 'Outlet', 'address' => 'Dhaka, Bangladesh', 'status' => true]
        );

        // Fabric is stocked in whatever unit it's bought in — Roll or Gaj
        // (yard) — and production issues it in that same unit.
        $roll = Unit::firstOrCreate(['short_name' => 'Roll'], ['name' => 'Roll']);
        $gaj = Unit::firstOrCreate(['short_name' => 'Gaj'], ['name' => 'Gaj (Yard)']);
        $pcs = Unit::firstOrCreate(['short_name' => 'Pcs'], ['name' => 'Piece']);

        $rawMaterial = Category::firstOrCreate(['name' => 'Raw Material']);
        $kidsWear = Category::firstOrCreate(['name' => 'Kids Wear']);

        $products = [
            ['sku' => 'RM-FAB-001', 'name' => 'Silk Fabric', 'category' => $rawMaterial, 'unit' => $roll, 'price' => 0],
            ['sku' => 'RM-FAB-002', 'name' => 'Cotton Fabric', 'category' => $rawMaterial, 'unit' => $gaj, 'price' => 0],
            ['sku' => 'RM-THR-001', 'name' => 'Sewing Thread (Cone)', 'category' => $rawMaterial, 'unit' => $pcs, 'price' => 0],
            ['sku' => 'RM-POL-001', 'name' => 'Poly Bag', 'category' => $rawMaterial, 'unit' => $pcs, 'price' => 0],
            ['sku' => 'KW-TS-001', 'name' => 'Kids Round Neck T-Shirt', 'category' => $kidsWear, 'unit' => $pcs, 'price' => 250],
            ['sku' => 'KW-RMP-001', 'name' => 'Baby Romper', 'category' => $kidsWear, 'unit' => $pcs, 'price' => 380],
            ['sku' => 'KW-PJ-001', 'name' => 'Kids Pajama Set', 'category' => $kidsWear, 'unit' => $pcs, 'price' => 450],
        ];

        foreach ($products as $p) {
            Product::firstOrCreate(
                ['sku' => $p['sku']],
                [
                    'name' => $p['name'],
                    'category_id' => $p['category']->id,
                    'stock_unit_id' => $p['unit']->id,
                    'purchase_unit_id' => $p['unit']->id,
                    'purchase_unit_conversion' => 1,
                    'sale_unit_id' => $p['unit']->id,
                    'sale_unit_conversion' => 1,
                    'estimated_cost' => 0,
                    'selling_price' => $p['price'],
                    'reorder_level' => 0,
                    'status' => true,
                ]
            );
        }

        // Sized item: every size is produced under its own tracking no.
        $sizeAttributeId = Attribute::where('name', 'Size')->value('id');
        $sizeValueIds = AttributeValue::where('attribute_id', $sizeAttributeId)->pluck('id', 'value');

        if ($sizeAttributeId) {
            $frock = Product::firstOrCreate(
                ['sku' => 'KW-FRK-001'],
                [
                    'name' => 'Girls Silk Frock',
                    'category_id' => $kidsWear->id,
                    'stock_unit_id' => $pcs->id,
                    'purchase_unit_id' => $pcs->id,
                    'purchase_unit_conversion' => 1,
                    'sale_unit_id' => $pcs->id,
                    'sale_unit_conversion' => 1,
                    'estimated_cost' => 0,
                    'selling_price' => 650,
                    'reorder_level' => 0,
                    'status' => true,
                    'has_variants' => true,
                ]
            );

            foreach (['S', 'M', 'L', 'XL'] as $size) {
                if (! isset($sizeValueIds[$size])) {
                    continue;
                }

                $variant = ProductVariant::firstOrCreate(
                    ['sku' => "KW-FRK-001-{$size}"],
                    ['product_id' => $frock->id, 'selling_price' => 650, 'estimated_cost' => 0, 'status' => true]
                );

                $variant->attributeValues()->syncWithoutDetaching([
                    $sizeValueIds[$size] => ['attribute_id' => $sizeAttributeId],
                ]);
            }
        }

        $manager = User::where('email', 'manager@businesserp.test')->first();

        $manager?->sites()->syncWithoutDetaching([
            $factory->id => ['is_default' => false],
            $outlet->id => ['is_default' => false],
        ]);
    }
}
