<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Party;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Site;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Site $outlet;

    private Party $customer;

    private Product $tshirt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerAccountSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->actingAs($this->admin);

        $this->outlet = Site::create(['name' => 'Wholesale Outlet', 'code' => 'WO-01', 'type' => 'Outlet', 'status' => true]);
        $this->customer = Party::create(['name' => 'Rahim Traders', 'phone' => '01700000003', 'is_customer' => true, 'status' => true]);

        $category = Category::create(['name' => 'Garments']);
        $pcs = Unit::create(['name' => 'Piece', 'short_name' => 'Pcs']);
        $this->tshirt = Product::create([
            'name' => 'Kids T-Shirt', 'sku' => 'KTS-1', 'category_id' => $category->id,
            'stock_unit_id' => $pcs->id, 'estimated_cost' => 0, 'selling_price' => 250, 'status' => true,
        ]);

        // 30 pcs at the outlet, costed at 100 each.
        StockMovement::create([
            'product_id' => $this->tshirt->id, 'site_id' => $this->outlet->id, 'type' => 'transfer_in',
            'quantity' => 30, 'unit_cost' => 100, 'moved_at' => '2026-10-01', 'created_by' => $this->admin->id,
        ]);
    }

    private function order(float $qty): SalesOrder
    {
        $this->post(route('sales-orders.store'), [
            'site_id' => $this->outlet->id,
            'party_id' => $this->customer->id,
            'order_date' => '2026-10-05',
            'discount' => 500,
            'items' => [
                ['item' => "product-{$this->tshirt->id}", 'quantity' => $qty, 'unit_price' => 250],
            ],
        ])->assertRedirect();

        return SalesOrder::latest('id')->firstOrFail();
    }

    public function test_delivering_an_order_deducts_stock_and_bills_the_customer(): void
    {
        $order = $this->order(20);

        $this->assertSame('pending', $order->status);
        $this->assertEquals(4500, (float) $order->total);
        // Taking an order doesn't touch stock.
        $this->assertEquals(30, StockMovement::balanceFor($this->tshirt->id, null, $this->outlet->id));

        $this->post(route('sales-orders.deliver', $order))->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertEquals(10, StockMovement::balanceFor($this->tshirt->id, null, $this->outlet->id));
        $this->assertEquals(4500, $this->customer->receivableBalance());
        $this->assertEquals(100, (float) $order->items->first()->unit_cost);
    }

    public function test_delivery_is_blocked_when_stock_is_short(): void
    {
        $order = $this->order(40);

        $this->post(route('sales-orders.deliver', $order))->assertSessionHas('error');

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertEquals(30, StockMovement::balanceFor($this->tshirt->id, null, $this->outlet->id));
        $this->assertEquals(0, $this->customer->receivableBalance());
    }

    public function test_a_cancelled_order_cannot_be_delivered(): void
    {
        $order = $this->order(5);

        $this->post(route('sales-orders.cancel', $order))->assertSessionHas('success');
        $this->post(route('sales-orders.deliver', $order))->assertSessionHas('error');

        $this->assertEquals(30, StockMovement::balanceFor($this->tshirt->id, null, $this->outlet->id));
    }

    public function test_sales_order_screens_render(): void
    {
        $order = $this->order(5);

        $this->get(route('sales-orders.index'))->assertOk()->assertSee($order->order_no);
        $this->get(route('sales-orders.create', ['site_id' => $this->outlet->id]))->assertOk();
        $this->get(route('sales-orders.show', $order))->assertOk();
    }
}
