<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attributes = [
            'Color' => ['White', 'Pink', 'Sky Blue', 'Yellow', 'Peach', 'Navy', 'Black', 'Red'],
            'Size' => GarmentSizeSeeder::SIZES,
        ];

        foreach ($attributes as $name => $values) {
            $attribute = Attribute::firstOrCreate(['name' => $name]);

            foreach ($values as $sortOrder => $value) {
                AttributeValue::firstOrCreate(
                    ['attribute_id' => $attribute->id, 'value' => $value],
                    ['sort_order' => $sortOrder]
                );
            }
        }
    }
}
