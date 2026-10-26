<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        $category = Category::create(['name' => 'General']);
        $unit = Unit::create(['name' => 'Piece', 'short_name' => 'pc']);

        return array_merge([
            'name' => 'Basic Product',
            'type' => 'finished_good',
            'sku' => 'BASIC-1',
            'category_id' => $category->id,
            'stock_unit_id' => $unit->id,
            'purchase_unit_conversion' => 1,
            'sale_unit_conversion' => 1,
            'estimated_cost' => 10,
            'selling_price' => 20,
            'reorder_level' => 0,
        ], $overrides);
    }

    public function test_expiry_forces_batch_tracking_on(): void
    {
        $this->actingAs($this->admin())
            ->post(route('products.store'), $this->payload([
                'track_expiry' => '1',
                'track_batch' => '0',
            ]))
            ->assertRedirect(route('products.index'));

        $product = Product::firstWhere('sku', 'BASIC-1');
        $this->assertTrue($product->track_expiry);
        $this->assertTrue($product->track_batch); // forced on by expiry
    }

    public function test_warranty_duration_persists_and_unit_nulls_when_disabled(): void
    {
        $this->actingAs($this->admin())
            ->post(route('products.store'), $this->payload([
                'warranty_period' => 12,
                'warranty_unit' => 'months',
                'guarantee_period' => null,
                'guarantee_unit' => 'years', // should be nulled since period is null
            ]))
            ->assertRedirect(route('products.index'));

        $product = Product::firstWhere('sku', 'BASIC-1');
        $this->assertSame(12, $product->warranty_period);
        $this->assertSame('months', $product->warranty_unit);
        $this->assertNull($product->guarantee_period);
        $this->assertNull($product->guarantee_unit);
    }

    public function test_invalid_warranty_unit_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post(route('products.store'), $this->payload([
                'warranty_period' => 12,
                'warranty_unit' => 'decades',
            ]))
            ->assertSessionHasErrors('warranty_unit');
    }
}
