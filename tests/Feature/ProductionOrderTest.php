<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Site;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Site $factory;

    private Product $fabric;

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

        $this->factory = Site::create(['name' => 'Factory', 'code' => 'FAC-01', 'type' => 'Factory', 'status' => true]);

        $category = Category::create(['name' => 'Garments']);
        $kg = Unit::create(['name' => 'Kilogram', 'short_name' => 'Kg']);
        $pcs = Unit::create(['name' => 'Piece', 'short_name' => 'Pcs']);

        $this->fabric = Product::create([
            'name' => 'Cotton Jersey', 'sku' => 'FAB-1', 'category_id' => $category->id,
            'stock_unit_id' => $kg->id, 'estimated_cost' => 0, 'selling_price' => 0, 'status' => true,
        ]);
        $this->tshirt = Product::create([
            'name' => 'Kids T-Shirt', 'sku' => 'KTS-1', 'category_id' => $category->id,
            'stock_unit_id' => $pcs->id, 'estimated_cost' => 0, 'selling_price' => 250, 'status' => true,
        ]);

        // 50 Kg of fabric in stock at 400/Kg = 20,000.
        StockMovement::create([
            'product_id' => $this->fabric->id, 'site_id' => $this->factory->id, 'type' => 'initial_stock',
            'quantity' => 50, 'unit_cost' => 400, 'moved_at' => '2026-10-01', 'created_by' => $this->admin->id,
        ]);
    }

    private function startLot(): ProductionOrder
    {
        $this->post(route('production-orders.store'), [
            'site_id' => $this->factory->id,
            'item' => "product-{$this->tshirt->id}",
            'planned_quantity' => 200,
            'start_date' => '2026-10-02',
        ])->assertRedirect();

        return ProductionOrder::firstOrFail();
    }

    private function stage(ProductionOrder $order, string $stage, float $qty, array $materials = [])
    {
        return $this->post(route('production-orders.stages.store', $order), [
            'stage' => $stage,
            'quantity' => $qty,
            'entry_date' => '2026-10-03',
            'materials' => $materials,
        ]);
    }

    private function balance(string $code): float
    {
        return LedgerAccount::where('code', $code)->firstOrFail()->lines()
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as bal')->value('bal');
    }

    public function test_pieces_flow_stage_by_stage_into_finished_stock(): void
    {
        $order = $this->startLot();
        $this->assertSame('PRD-000001', $order->production_no);

        // Roll cut into 200 pcs, using all 50 Kg of fabric.
        $this->stage($order, 'cutting', 200, [['item' => "product-{$this->fabric->id}", 'quantity' => 50]])
            ->assertSessionHasNoErrors();
        $this->stage($order, 'sewing', 100)->assertSessionHasNoErrors();
        $this->stage($order, 'finishing', 50)->assertSessionHasNoErrors();

        $totals = $order->stageTotals();
        $this->assertEquals(['cutting' => 200, 'sewing' => 100, 'finishing' => 50], $totals);
        $this->assertEquals(100, $order->availableFor('sewing', $totals));
        $this->assertEquals(50, $order->availableFor('finishing', $totals));

        // Fabric used up; only finished pieces are in stock.
        $this->assertEquals(0, StockMovement::balanceFor($this->fabric->id, null, $this->factory->id));
        $this->assertEquals(50, StockMovement::balanceFor($this->tshirt->id, null, $this->factory->id));

        // 20,000 ÷ 200 pcs = 100 per piece.
        $this->assertEquals(100, (float) $this->tshirt->fresh()->estimated_cost);
        // Opening stock above was posted without a voucher, so Inventory
        // shows only this lot: −20,000 issued to WIP + 5,000 finished.
        $this->assertEquals(-15000, $this->balance('inventory'));
        $this->assertEquals(15000, $this->balance('work_in_progress'));
        $this->assertEquals(15000, $order->wipValue());
    }

    public function test_a_stage_cannot_take_more_than_the_previous_stage_handed_over(): void
    {
        $order = $this->startLot();
        $this->stage($order, 'cutting', 200);
        $this->stage($order, 'sewing', 100);

        $this->stage($order, 'sewing', 101)->assertSessionHasErrors('quantity');
        $this->stage($order, 'finishing', 101)->assertSessionHasErrors('quantity');

        $this->assertEquals(['cutting' => 200, 'sewing' => 100, 'finishing' => 0], $order->stageTotals());
    }

    public function test_material_issue_cannot_exceed_stock(): void
    {
        $order = $this->startLot();

        $this->stage($order, 'cutting', 200, [['item' => "product-{$this->fabric->id}", 'quantity' => 51]])
            ->assertSessionHasErrors('materials.0.quantity');

        $this->assertSame(0, $order->stageEntries()->count());
        $this->assertEquals(50, StockMovement::balanceFor($this->fabric->id, null, $this->factory->id));
    }

    public function test_completing_a_lot_writes_off_unfinished_wip(): void
    {
        $order = $this->startLot();
        $this->stage($order, 'cutting', 200, [['item' => "product-{$this->fabric->id}", 'quantity' => 50]]);
        $this->stage($order, 'sewing', 200);
        $this->stage($order, 'finishing', 190);

        $this->post(route('production-orders.complete', $order))->assertRedirect();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertEquals(1000, (float) $order->wastage_value);
        $this->assertEquals(0, $this->balance('work_in_progress'));
        $this->assertEquals(1000, $this->balance('production_wastage_expense'));

        $this->stage($order, 'cutting', 10)->assertSessionHas('error');
    }

    public function test_production_screens_render(): void
    {
        $order = $this->startLot();
        $this->stage($order, 'cutting', 200, [['item' => "product-{$this->fabric->id}", 'quantity' => 50]]);

        $this->get(route('production-orders.index'))->assertOk()->assertSee($order->production_no);
        $this->get(route('production-orders.create'))->assertOk();
        $this->get(route('production-orders.show', $order))->assertOk()->assertSee('waiting for sewing');
    }
}
