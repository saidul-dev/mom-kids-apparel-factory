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

    public function test_sku_is_built_from_the_name(): void
    {
        $this->post(route('products.store'), $this->payload(['name' => 'Girls Silk Frock']))->assertSessionHasNoErrors();
        $this->post(route('products.store'), $this->payload(['name' => 'Silk Fabric', 'type' => 'raw_material']))->assertSessionHasNoErrors();
        $this->post(route('products.store'), $this->payload(['name' => 'Soft Fleece']))->assertSessionHasNoErrors();
        $this->post(route('products.store'), $this->payload(['name' => 'Kids Pajama Set Winter']))->assertSessionHasNoErrors();

        $this->assertSame(
            ['GSF-001', 'SF-001', 'SF-002', 'KPS-001'],
            Product::orderBy('id')->pluck('sku')->all()
        );
    }

    public function test_sku_preview_matches_what_save_will_use(): void
    {
        $this->post(route('products.store'), $this->payload(['name' => 'Silk Fabric', 'type' => 'raw_material']));

        $this->getJson(route('products.next-sku', ['name' => 'Soft Fleece']))->assertOk()->assertJson(['sku' => 'SF-002']);
        $this->getJson(route('products.next-sku', ['name' => 'Baby Romper']))->assertOk()->assertJson(['sku' => 'BR-001']);
    }

    public function test_barcode_is_generated_unless_one_is_given(): void
    {
        $this->post(route('products.store'), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('products.store'), $this->payload(['name' => 'Baby Romper', 'barcode' => '8901234567890']))->assertSessionHasNoErrors();

        [$auto, $given] = Product::orderBy('id')->get()->all();
        $this->assertSame(Product::internalBarcode('20', $auto->id), $auto->barcode);
        $this->assertMatchesRegularExpression('/^20\d{11}$/', $auto->barcode);
        $this->assertSame('8901234567890', $given->barcode);
    }

    public function test_editing_keeps_the_generated_sku(): void
    {
        $this->post(route('products.store'), $this->payload());
        $product = Product::firstOrFail();

        $this->put(route('products.update', $product), $this->payload(['name' => 'Girls Party Frock']))
            ->assertSessionHasNoErrors();

        $this->assertSame('GF-001', $product->fresh()->sku);
        $this->assertNotNull($product->fresh()->barcode);
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

        $variants = Product::firstOrFail()->variants()->orderBy('id')->get();
        $this->assertSame(['GF-001-M', 'GF-001-L'], $variants->pluck('sku')->all());
        $this->assertSame(Product::internalBarcode('21', $variants[0]->id), $variants[0]->barcode);
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
        $this->get(route('products.create'))->assertOk()->assertSee('More Photos')->assertSee('Generated on save');
    }
}
