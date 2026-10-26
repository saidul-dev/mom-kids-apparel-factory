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
            // Baby (months) and kids (years) sizes first, then mom wear.
            'Size' => ['0-3M', '3-6M', '6-12M', '1-2Y', '2-3Y', '3-4Y', '4-5Y', '5-6Y', '7-8Y', 'S', 'M', 'L', 'XL', 'XXL'],
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
