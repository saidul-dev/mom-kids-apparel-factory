<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductSkuAndPhotosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->actingAs($this->admin);

        $this->category = Category::create(['name' => 'Kids Wear']);
        $this->unit = Unit::create(['name' => 'Piece', 'short_name' => 'Pcs']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Girls Frock',
            'type' => 'finished_good',
            'category_id' => $this->category->id,
            'stock_unit_id' => $this->unit->id,
            'estimated_cost' => 0,
            'selling_price' => 650,
            'reorder_level' => 0,
        ], $overrides);
    }

    public function test_sku_is_generated_per_type_in_sequence(): void
    {
        $this->post(route('products.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('products.store'), $this->payload(['name' => 'Silk Fabric', 'type' => 'raw_material']))->assertSessionHasNoErrors();
        $this->post(route('products.store'), $this->payload(['name' => 'Baby Romper']))->assertSessionHasNoErrors();

        $this->assertSame(
            ['Girls Frock' => 'FG-00001', 'Silk Fabric' => 'RM-00001', 'Baby Romper' => 'FG-00002'],
            Product::orderBy('id')->pluck('sku', 'name')->all()
        );
    }

    public function test_editing_keeps_the_generated_sku(): void
    {
        $this->post(route('products.store'), $this->payload());
        $product = Product::firstOrFail();

        $this->put(route('products.update', $product), $this->payload(['name' => 'Girls Party Frock']))
            ->assertSessionHasNoErrors();

        $this->assertSame('FG-00001', $product->fresh()->sku);
    }

    public function test_size_skus_are_generated_from_the_product_sku(): void
    {
        $size = Attribute::create(['name' => 'Size']);
        $m = $size->values()->create(['value' => 'M']);
        $l = $size->values()->create(['value' => 'L']);

        $this->post(route('products.store'), $this->payload([
            'has_variants' => '1',
            'variants' => [
                ['sku' => '', 'selling_price' => 650, 'values' => [$size->id => $m->id]],
                ['sku' => '', 'selling_price' => 680, 'values' => [$size->id => $l->id]],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['FG-00001-M', 'FG-00001-L'], Product::firstOrFail()->variants()->orderBy('id')->pluck('sku')->all());
    }

    public function test_finished_goods_take_multiple_photos(): void
    {
        Storage::fake('public');

        $this->post(route('products.store'), $this->payload([
            'image' => UploadedFile::fake()->image('front.jpg'),
            'gallery_images' => [
                UploadedFile::fake()->image('back.jpg'),
                UploadedFile::fake()->image('detail.jpg'),
                UploadedFile::fake()->image('model.jpg'),
            ],
        ]))->assertSessionHasNoErrors();

        $product = Product::firstOrFail();
        $this->assertNotNull($product->image_path);
        $this->assertSame(3, $product->images()->count());
    }

    public function test_product_form_renders(): void
    {
        $this->get(route('products.create'))->assertOk()->assertSee('More Photos')->assertSee('FG-00001');
    }
}
