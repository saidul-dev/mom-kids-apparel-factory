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

    private Product $silk;

    private Product $frock;

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
        $roll = Unit::create(['name' => 'Roll', 'short_name' => 'Roll']);
        $pcs = Unit::create(['name' => 'Piece', 'short_name' => 'Pcs']);

        // Fabric is stocked in its own unit — here Roll.
        $this->silk = Product::create([
            'name' => 'Silk Fabric', 'sku' => 'FAB-1', 'category_id' => $category->id,
            'stock_unit_id' => $roll->id, 'estimated_cost' => 0, 'selling_price' => 0, 'status' => true,
        ]);
        $this->frock = Product::create([
            'name' => 'Girls Frock', 'sku' => 'FRK-1', 'category_id' => $category->id,
            'stock_unit_id' => $pcs->id, 'estimated_cost' => 0, 'selling_price' => 650, 'status' => true,
        ]);

        // 20 rolls of silk in stock at 10,000 per roll.
        StockMovement::create([
            'product_id' => $this->silk->id, 'site_id' => $this->factory->id, 'type' => 'initial_stock',
            'quantity' => 20, 'unit_cost' => 10000, 'moved_at' => '2026-10-01', 'created_by' => $this->admin->id,
        ]);
    }

    /**
     * Creating a production = issuing the fabric and recording the cut.
     */
    private function createProduction(float $fabricUsed = 5, int $cut = 500)
    {
        return $this->post(route('production-orders.store'), [
            'site_id' => $this->factory->id,
            'item' => "product-{$this->frock->id}",
            'start_date' => '2026-10-02',
            'cut_quantity' => $cut,
            'fabrics' => [['item' => "product-{$this->silk->id}", 'quantity' => $fabricUsed]],
        ]);
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

    public function test_creating_a_production_issues_fabric_and_records_cutting(): void
    {
        $this->createProduction()->assertRedirect()->assertSessionHasNoErrors();

        $order = ProductionOrder::firstOrFail();
        $this->assertSame('PRD-000001', $order->production_no);
        $this->assertEquals(['cutting' => 500, 'sewing' => 0, 'finishing' => 0], $order->stageTotals());

        // 5 of the 20 rolls used; 15 left.
        $this->assertEquals(15, StockMovement::balanceFor($this->silk->id, null, $this->factory->id));
        $this->assertEquals(5, (float) $order->materials()->sum('quantity'));
        $this->assertEquals(50000, $order->wipValue());
    }

    public function test_a_production_needs_fabric(): void
    {
        $this->post(route('production-orders.store'), [
            'site_id' => $this->factory->id,
            'item' => "product-{$this->frock->id}",
            'start_date' => '2026-10-02',
            'cut_quantity' => 500,
        ])->assertSessionHasErrors('fabrics');

        $this->createProduction(21)->assertSessionHasErrors('fabrics.0.quantity');

        $this->assertSame(0, ProductionOrder::count());
        $this->assertEquals(20, StockMovement::balanceFor($this->silk->id, null, $this->factory->id));
    }

    public function test_pieces_move_stage_by_stage_into_finished_stock(): void
    {
        $this->createProduction();
        $order = ProductionOrder::firstOrFail();

        $this->stage($order, 'sewing', 300)->assertSessionHasNoErrors();
        $this->stage($order, 'finishing', 200)->assertSessionHasNoErrors();

        $totals = $order->stageTotals();
        $this->assertEquals(['cutting' => 500, 'sewing' => 300, 'finishing' => 200], $totals);
        $this->assertEquals(200, $order->availableFor('sewing', $totals));
        $this->assertEquals(100, $order->availableFor('finishing', $totals));

        // 50,000 of silk ÷ 500 pcs = 100 per piece.
        $this->assertEquals(200, StockMovement::balanceFor($this->frock->id, null, $this->factory->id));
        $this->assertEquals(100, (float) $this->frock->fresh()->estimated_cost);
        $this->assertEquals(30000, $this->balance('work_in_progress'));
        // Opening stock above was posted without a voucher, so Inventory
        // shows only this lot: −50,000 issued to WIP + 20,000 finished.
        $this->assertEquals(-30000, $this->balance('inventory'));
    }

    public function test_a_stage_cannot_take_more_than_the_previous_stage_handed_over(): void
    {
        $this->createProduction();
        $order = ProductionOrder::firstOrFail();
        $this->stage($order, 'sewing', 300);

        $this->stage($order, 'sewing', 201)->assertSessionHasErrors('quantity');
        $this->stage($order, 'finishing', 301)->assertSessionHasErrors('quantity');

        $this->assertEquals(['cutting' => 500, 'sewing' => 300, 'finishing' => 0], $order->stageTotals());
    }

    public function test_completing_a_production_writes_off_unfinished_wip(): void
    {
        $this->createProduction();
        $order = ProductionOrder::firstOrFail();
        $this->stage($order, 'sewing', 500);
        $this->stage($order, 'finishing', 490);

        $this->post(route('production-orders.complete', $order))->assertRedirect();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertEquals(1000, (float) $order->wastage_value);
        $this->assertEquals(0, $this->balance('work_in_progress'));
        $this->assertEquals(1000, $this->balance('production_wastage_expense'));

        $this->stage($order, 'sewing', 1)->assertSessionHas('error');
    }

    public function test_production_screens_render(): void
    {
        $this->createProduction();
        $order = ProductionOrder::firstOrFail();
        $this->stage($order, 'sewing', 300);

        $this->get(route('production-orders.index'))->assertOk()->assertSee($order->production_no);
        $this->get(route('production-orders.index', ['q' => 'PRD-000001']))->assertOk()->assertSee('Girls Frock');
        $this->get(route('production-orders.index', ['q' => 'PRD-999']))->assertOk()->assertDontSee('Girls Frock');
        $this->get(route('production-orders.create'))->assertOk();
        $this->get(route('production-orders.create', ['site_id' => $this->factory->id]))->assertOk()->assertSee('Silk Fabric');
        $this->get(route('production-orders.show', $order))->assertOk()
            ->assertSee('PRD-000001')
            ->assertSee('Fabric Used')
            ->assertSee('Waiting for sewing');
    }
}
