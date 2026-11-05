<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GarmentSizeSeeder extends Seeder
{
    /**
     * The factory's size run, smallest first. Every finished garment is
     * made in all of them.
     */
    public const SIZES = ['1-2', '3-4', '5-6', '14-16', '18-20', '22-24-26', '28-30-32', '34-36-38', '40-42', '44-46'];

    /**
     * Tables that keep a variant's history — a variant used in any of them
     * is switched off instead of deleted.
     */
    private const VARIANT_HISTORY = [
        'stock_movements', 'stock_transfer_items', 'purchase_items',
        'production_orders', 'production_materials', 'sales_order_items',
    ];

    /**
     * Brings the Size attribute and every finished garment's variants in
     * line with SIZES. Safe to re-run on an existing database
     * (`php artisan db:seed --class=GarmentSizeSeeder`): missing sizes are
     * added, and sizes no longer in the list are deleted when unused or
     * deactivated when they have stock/production/sales history.
     */
    public function run(): void
    {
        $size = Attribute::firstOrCreate(['name' => 'Size']);

        foreach (self::SIZES as $sortOrder => $value) {
            AttributeValue::updateOrCreate(
                ['attribute_id' => $size->id, 'value' => $value],
                ['sort_order' => $sortOrder]
            );
        }
        $valueIds = AttributeValue::where('attribute_id', $size->id)->pluck('id', 'value');

        $garments = Product::where('type', 'finished_good')->where('has_variants', true)->get();

        foreach ($garments as $product) {
            foreach (self::SIZES as $value) {
                $variant = ProductVariant::firstOrCreate(
                    ['sku' => "{$product->sku}-{$value}"],
                    ['product_id' => $product->id, 'selling_price' => $product->selling_price, 'estimated_cost' => 0, 'status' => true]
                );
                if (! $variant->barcode) {
                    $variant->update(['barcode' => Product::internalBarcode('21', $variant->id)]);
                }

                $variant->attributeValues()->syncWithoutDetaching([
                    $valueIds[$value] => ['attribute_id' => $size->id],
                ]);
            }
        }

        $this->retireOldSizes($size, $valueIds->only(self::SIZES)->values()->all());
    }

    private function retireOldSizes(Attribute $size, array $keepValueIds): void
    {
        $oldValueIds = AttributeValue::where('attribute_id', $size->id)
            ->whereNotIn('id', $keepValueIds)
            ->pluck('id');

        if ($oldValueIds->isEmpty()) {
            return;
        }

        $variantIds = DB::table('product_variant_values')
            ->whereIn('attribute_value_id', $oldValueIds)
            ->pluck('product_variant_id')
            ->unique();

        foreach (ProductVariant::whereIn('id', $variantIds)->get() as $variant) {
            $used = collect(self::VARIANT_HISTORY)
                ->contains(fn ($table) => DB::table($table)->where('product_variant_id', $variant->id)->exists());

            $used ? $variant->update(['status' => false]) : $variant->delete();
        }

        // Values a kept (deactivated) variant still points at stay.
        AttributeValue::whereIn('id', $oldValueIds)
            ->whereNotIn('id', DB::table('product_variant_values')->select('attribute_value_id'))
            ->delete();
    }
}
