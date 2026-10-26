<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LedgerTransactionLine;
use App\Models\Party;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Site;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Site $factory;

    private Party $supplier;

    private Product $fabric;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerAccountSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->factory = Site::create(['name' => 'Factory', 'code' => 'FAC-01', 'type' => 'Factory', 'status' => true]);
        $this->supplier = Party::create(['name' => 'Fabric House', 'phone' => '01700000001', 'is_supplier' => true, 'is_customer' => false, 'status' => true]);

        $category = Category::create(['name' => 'Fabric']);
        $unit = Unit::create(['name' => 'Kilogram', 'short_name' => 'Kg']);
        $this->fabric = Product::create([
            'name' => 'Cotton Jersey', 'sku' => 'FAB-1', 'category_id' => $category->id,
            'stock_unit_id' => $unit->id, 'estimated_cost' => 0, 'selling_price' => 0, 'status' => true,
        ]);
    }

    public function test_purchase_adds_stock_and_posts_supplier_payable(): void
    {
        $this->actingAs($this->admin)
            ->post(route('purchases.store'), [
                'site_id' => $this->factory->id,
                'party_id' => $this->supplier->id,
                'purchase_date' => '2026-10-01',
                'discount' => 100,
                'items' => [
                    ['item' => "product-{$this->fabric->id}", 'quantity' => 50, 'unit_cost' => 400],
                ],
            ])
            ->assertRedirect();

        $purchase = Purchase::firstOrFail();
        $this->assertSame('PUR-000001', $purchase->purchase_no);
        $this->assertEquals(19900, (float) $purchase->total);

        $this->assertEquals(50, StockMovement::balanceFor($this->fabric->id, null, $this->factory->id));

        // Discount is spread into the stocked cost: 19,900 / 50 = 398.
        $this->assertEquals(398, (float) $this->fabric->fresh()->estimated_cost);

        $this->assertEquals(19900, $this->supplier->payableBalance());
        $this->assertEquals(19900, (float) LedgerTransactionLine::whereHas('account', fn ($q) => $q->where('code', 'inventory'))->sum('debit'));
    }

    public function test_purchase_rejects_a_non_supplier_party(): void
    {
        $customer = Party::create(['name' => 'Walk-in', 'phone' => '01700000002', 'is_customer' => true, 'is_supplier' => false, 'status' => true]);

        $this->actingAs($this->admin)
            ->post(route('purchases.store'), [
                'site_id' => $this->factory->id,
                'party_id' => $customer->id,
                'purchase_date' => '2026-10-01',
                'items' => [
                    ['item' => "product-{$this->fabric->id}", 'quantity' => 10, 'unit_cost' => 400],
                ],
            ])
            ->assertSessionHasErrors('party_id');

        $this->assertSame(0, Purchase::count());
    }

    public function test_purchase_screens_render(): void
    {
        $this->actingAs($this->admin)->get(route('purchases.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('purchases.create', ['site_id' => $this->factory->id]))->assertOk();
    }
}
