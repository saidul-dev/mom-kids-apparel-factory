<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
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

        $kg = Unit::firstOrCreate(['short_name' => 'Kg'], ['name' => 'Kilogram']);
        $pcs = Unit::firstOrCreate(['short_name' => 'Pcs'], ['name' => 'Piece']);

        $rawMaterial = Category::firstOrCreate(['name' => 'Raw Material']);
        $kidsWear = Category::firstOrCreate(['name' => 'Kids Wear']);

        $products = [
            ['sku' => 'RM-FAB-001', 'name' => 'Cotton Single Jersey Fabric', 'category' => $rawMaterial, 'unit' => $kg, 'price' => 0],
            ['sku' => 'RM-FAB-002', 'name' => 'Rib Fabric', 'category' => $rawMaterial, 'unit' => $kg, 'price' => 0],
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

        $manager = User::where('email', 'manager@businesserp.test')->first();

        $manager?->sites()->syncWithoutDetaching([
            $factory->id => ['is_default' => false],
            $outlet->id => ['is_default' => false],
        ]);
    }
}
