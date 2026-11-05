<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Site;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransferBillTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_transfer_prints_a_bill_with_each_line_at_cost_and_the_total(): void
    {
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create(['name' => 'Store Keeper']);
        $admin->assignRole('Admin');
        $this->actingAs($admin);

        $factory = Site::create(['name' => 'Factory', 'code' => 'FAC-01', 'type' => 'Factory', 'address' => 'Gazipur', 'status' => true]);
        $outlet = Site::create(['name' => 'Wholesale Outlet', 'code' => 'OUT-01', 'type' => 'Outlet', 'status' => true]);

        $frock = Product::create([
            'name' => 'Girls Frock', 'sku' => 'FRK-1', 'category_id' => Category::create(['name' => 'Kids'])->id,
            'stock_unit_id' => Unit::create(['name' => 'Piece', 'short_name' => 'Pcs'])->id,
            'estimated_cost' => 130, 'selling_price' => 650, 'status' => true,
        ]);
        StockMovement::create([
            'product_id' => $frock->id, 'site_id' => $factory->id, 'type' => 'initial_stock',
            'quantity' => 500, 'unit_cost' => 130, 'moved_at' => '2026-11-01', 'created_by' => $admin->id,
        ]);

        $this->post(route('stock.transfers.store'), [
            'from_site_id' => $factory->id,
            'to_site_id' => $outlet->id,
            'dispatched_at' => '2026-11-06',
            'items' => [['item' => "product-{$frock->id}", 'quantity' => 200]],
        ])->assertSessionHasNoErrors();

        $transfer = StockTransfer::firstOrFail();

        $this->get(route('stock.transfers.show', $transfer))->assertOk()
            ->assertSee(route('stock.transfers.print', $transfer));

        // 200 pcs × 130 = 26,000.
        $this->get(route('stock.transfers.print', $transfer))->assertOk()
            ->assertSee('Transfer Bill / Challan')
            ->assertSee($transfer->transfer_no)
            ->assertSee('Gazipur')
            ->assertSee('Wholesale Outlet')
            ->assertSee('Girls Frock')
            ->assertSee('130.00')
            ->assertSee('26,000.00')
            ->assertSee('Store Keeper');
    }
}
