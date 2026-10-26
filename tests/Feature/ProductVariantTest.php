<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    private function baseProduct(): Product
    {
        $category = Category::create(['name' => 'General']);
        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);

        return Product::create([
            'name' => 'T-Shirt',
            'sku' => 'TSHIRT',
            'category_id' => $category->id,
            'stock_unit_id' => $unit->id,
            'purchase_unit_conversion' => 1,
            'sale_unit_conversion' => 1,
            'estimated_cost' => 100,
            'selling_price' => 150,
            'reorder_level' => 0,
            'has_variants' => true,
        ]);
    }

    public function test_variant_label_joins_attribute_values(): void
    {
        $product = $this->baseProduct();
        $color = Attribute::create(['name' => 'Color']);
        $red = $color->values()->create(['value' => 'Red']);
        $size = Attribute::create(['name' => 'Size']);
        $medium = $size->values()->create(['value' => 'M']);

        $variant = $product->variants()->create([
            'sku' => 'TSHIRT-RED-M', 'selling_price' => 160, 'status' => true,
        ]);
        $variant->attributeValues()->attach($red->id, ['attribute_id' => $color->id]);
        $variant->attributeValues()->attach($medium->id, ['attribute_id' => $size->id]);

        $this->assertSame('Red / M', $variant->fresh()->label);
        $this->assertTrue($product->isVariable());
    }

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    private function variablePayload(array $variants): array
    {
        $category = Category::create(['name' => 'Apparel']);
        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);

        return [
            'name' => 'Polo',
            'type' => 'finished_good',
            'sku' => 'POLO',
            'category_id' => $category->id,
            'stock_unit_id' => $unit->id,
            'purchase_unit_conversion' => 1,
            'sale_unit_conversion' => 1,
            'estimated_cost' => 100,
            'selling_price' => 150,
            'reorder_level' => 0,
            'has_variants' => '1',
            'variants' => $variants,
        ];
    }

    public function test_creating_variable_product_stores_variants_and_values(): void
    {
        $color = Attribute::create(['name' => 'Color']);
        $red = $color->values()->create(['value' => 'Red']);
        $blue = $color->values()->create(['value' => 'Blue']);

        $this->actingAs($this->admin())
            ->post(route('products.store'), $this->variablePayload([
                ['sku' => 'POLO-RED', 'selling_price' => 160, 'status' => '1', 'values' => [$color->id => $red->id]],
                ['sku' => 'POLO-BLUE', 'selling_price' => 170, 'status' => '1', 'values' => [$color->id => $blue->id]],
            ]))
            ->assertRedirect(route('products.index'));

        $product = Product::firstWhere('sku', 'POLO');
        $this->assertCount(2, $product->variants);
        $this->assertEqualsCanonicalizing(['POLO-RED', 'POLO-BLUE'], $product->variants->pluck('sku')->all());
        $this->assertDatabaseHas('product_variant_values', [
            'attribute_id' => $color->id, 'attribute_value_id' => $red->id,
        ]);
    }

    public function test_updating_syncs_added_and_removed_variants(): void
    {
        $color = Attribute::create(['name' => 'Color']);
        $red = $color->values()->create(['value' => 'Red']);
        $blue = $color->values()->create(['value' => 'Blue']);

        $this->actingAs($this->admin())
            ->post(route('products.store'), $this->variablePayload([
                ['sku' => 'POLO-RED', 'selling_price' => 160, 'status' => '1', 'values' => [$color->id => $red->id]],
            ]));

        $product = Product::firstWhere('sku', 'POLO');
        $existingId = $product->variants->first()->id;

        $this->actingAs($this->admin())
            ->put(route('products.update', $product), array_merge(
                $this->variablePayload([
                    ['id' => $existingId, 'sku' => 'POLO-RED', 'selling_price' => 165, 'status' => '1', 'values' => [$color->id => $red->id]],
                    ['sku' => 'POLO-BLUE', 'selling_price' => 170, 'status' => '1', 'values' => [$color->id => $blue->id]],
                ]),
                ['sku' => 'POLO'] // keep same product sku
            ))
            ->assertRedirect(route('products.index'));

        $product->refresh()->load('variants');
        $this->assertCount(2, $product->variants);
        $this->assertSame('165.00', $product->variants->firstWhere('id', $existingId)->selling_price);
    }

    public function test_turning_off_variants_deletes_them(): void
    {
        $color = Attribute::create(['name' => 'Color']);
        $red = $color->values()->create(['value' => 'Red']);

        $this->actingAs($this->admin())
            ->post(route('products.store'), $this->variablePayload([
                ['sku' => 'POLO-RED', 'selling_price' => 160, 'status' => '1', 'values' => [$color->id => $red->id]],
            ]));
        $product = Product::firstWhere('sku', 'POLO');

        $this->actingAs($this->admin())
            ->put(route('products.update', $product), [
                'name' => 'Polo', 'type' => 'finished_good', 'sku' => 'POLO', 'category_id' => $product->category_id,
                'stock_unit_id' => $product->stock_unit_id, 'purchase_unit_conversion' => 1,
                'sale_unit_conversion' => 1, 'estimated_cost' => 100, 'selling_price' => 150,
                'reorder_level' => 0, 'has_variants' => '0',
            ])
            ->assertRedirect(route('products.index'));

        $this->assertCount(0, $product->fresh()->variants);
        $this->assertDatabaseCount('product_variant_values', 0);
    }

    public function test_variant_sku_must_be_unique(): void
    {
        $color = Attribute::create(['name' => 'Color']);
        $red = $color->values()->create(['value' => 'Red']);

        $this->actingAs($this->admin())
            ->post(route('products.store'), $this->variablePayload([
                ['sku' => 'DUP', 'selling_price' => 160, 'status' => '1', 'values' => [$color->id => $red->id]],
                ['sku' => 'DUP', 'selling_price' => 170, 'status' => '1', 'values' => [$color->id => $red->id]],
            ]))
            ->assertSessionHasErrors('variants.1.sku');
    }
}
