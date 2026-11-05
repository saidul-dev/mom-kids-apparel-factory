<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LedgerAccount;
use App\Models\Party;
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
            'name' => 'Silk Fabric', 'type' => 'raw_material', 'sku' => 'FAB-1', 'category_id' => $category->id,
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
        $this->assertEquals(['cutting' => 500, 'sewing' => 0, 'finishing' => 0, 'packing' => 0], $order->stageTotals());

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

        // Ironed but not yet blistered — nothing in finished stock yet.
        $this->assertEquals(0, StockMovement::balanceFor($this->frock->id, null, $this->factory->id));

        $this->stage($order, 'packing', 150)->assertSessionHasNoErrors();

        $totals = $order->stageTotals();
        $this->assertEquals(['cutting' => 500, 'sewing' => 300, 'finishing' => 200, 'packing' => 150], $totals);
        $this->assertEquals(
            ['cutting' => 200, 'sewing' => 100, 'finishing' => 50, 'stock' => 150],
            $order->currentlyAt($totals)
        );

        // 50,000 of silk ÷ 500 pcs = 100 per piece.
        $this->assertEquals(150, StockMovement::balanceFor($this->frock->id, null, $this->factory->id));
        $this->assertEquals(100, (float) $this->frock->fresh()->estimated_cost);
        $this->assertEquals(35000, $this->balance('work_in_progress'));
        // Opening stock above was posted without a voucher, so Inventory
        // shows only this lot: −50,000 issued to WIP + 15,000 finished.
        $this->assertEquals(-35000, $this->balance('inventory'));
    }

    public function test_a_stage_cannot_take_more_than_the_previous_stage_handed_over(): void
    {
        $this->createProduction();
        $order = ProductionOrder::firstOrFail();
        $this->stage($order, 'sewing', 300);
        $this->stage($order, 'finishing', 100);

        $this->stage($order, 'sewing', 201)->assertSessionHasErrors('quantity');
        $this->stage($order, 'finishing', 201)->assertSessionHasErrors('quantity');
        $this->stage($order, 'packing', 101)->assertSessionHasErrors('quantity');

        $this->assertEquals(['cutting' => 500, 'sewing' => 300, 'finishing' => 100, 'packing' => 0], $order->stageTotals());
    }

    public function test_completing_a_production_writes_off_unfinished_wip(): void
    {
        $this->createProduction();
        $order = ProductionOrder::firstOrFail();
        $this->stage($order, 'sewing', 500);
        $this->stage($order, 'finishing', 500);
        $this->stage($order, 'packing', 490);

        $this->post(route('production-orders.complete', $order))->assertRedirect();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertEquals(1000, (float) $order->wastage_value);
        $this->assertEquals(0, $this->balance('work_in_progress'));
        $this->assertEquals(1000, $this->balance('production_wastage_expense'));

        $this->stage($order, 'sewing', 1)->assertSessionHas('error');
    }

    private function outsideWork(ProductionOrder $order, array $data)
    {
        return $this->post(route('production-orders.outside-work.store', $order), $data + [
            'work_type' => 'Embroidery',
            'entry_date' => '2026-10-03',
        ]);
    }

    public function test_outside_work_adds_to_the_finished_piece_cost_and_is_owed_to_the_vendor(): void
    {
        $vendor = Party::create(['name' => 'Rahim Embroidery', 'phone' => '01700000001', 'is_supplier' => true, 'status' => true]);
        $this->createProduction();
        $order = ProductionOrder::firstOrFail();

        // 500 pcs × 20 per piece = 10,000 — the total is worked out, even
        // if a different amount is posted alongside the price.
        $this->outsideWork($order, ['party_id' => $vendor->id, 'quantity' => 500, 'unit_price' => 20, 'amount' => 1])
            ->assertSessionHasNoErrors();
        // A flat total, no price per piece.
        $this->outsideWork($order, ['party_id' => $vendor->id, 'work_type' => 'Print', 'amount' => 5000])
            ->assertSessionHasNoErrors();

        $this->assertEquals([10000, 5000], $order->outsideWorks()->orderBy('id')->pluck('amount')->map(fn ($a) => (float) $a)->all());
        $this->assertEquals(65000, $order->wipValue());
        $this->assertEquals(-15000, $this->balance('accounts_payable'));
        $this->assertEquals(
            -15000,
            (float) LedgerAccount::where('code', 'accounts_payable')->firstOrFail()->lines()
                ->where('party_id', $vendor->id)->selectRaw('SUM(debit) - SUM(credit) as bal')->value('bal')
        );

        // (50,000 silk + 15,000 outside work) ÷ 500 pcs = 130 per piece.
        $this->stage($order, 'sewing', 500);
        $this->stage($order, 'finishing', 500);
        $this->stage($order, 'packing', 500);

        $this->assertEquals(130, (float) $this->frock->fresh()->estimated_cost);
        $this->assertEquals(0, $this->balance('work_in_progress'));

        $this->get(route('production-orders.show', $order))->assertOk()
            ->assertSee('Rahim Embroidery')
            ->assertSee('15,000.00');
    }

    public function test_outside_work_needs_a_supplier_and_a_price_or_total(): void
    {
        $customer = Party::create(['name' => 'A Customer', 'phone' => '01700000002', 'is_customer' => true, 'status' => true]);
        $vendor = Party::create(['name' => 'Print House', 'phone' => '01700000003', 'is_supplier' => true, 'status' => true]);
        $this->createProduction();
        $order = ProductionOrder::firstOrFail();

        $this->outsideWork($order, ['party_id' => $vendor->id])->assertSessionHasErrorsIn('outsideWork', 'amount');
        $this->outsideWork($order, ['party_id' => $vendor->id, 'unit_price' => 20])->assertSessionHasErrorsIn('outsideWork', 'quantity');
        $this->outsideWork($order, ['party_id' => $customer->id, 'amount' => 100])->assertSessionHasErrorsIn('outsideWork', 'party_id');
        $this->assertSame(0, $order->outsideWorks()->count());

        $this->post(route('production-orders.complete', $order));
        $this->outsideWork($order, ['party_id' => $vendor->id, 'amount' => 100])->assertSessionHas('error');
        $this->assertSame(0, $order->outsideWorks()->count());
    }

    public function test_production_screens_render(): void
    {
        $this->createProduction();
        $order = ProductionOrder::firstOrFail();
        $this->stage($order, 'sewing', 300);

        $this->get(route('production-orders.index'))->assertOk()
            ->assertSee('Production Dashboard')
            ->assertSee($order->production_no);
        $this->get(route('production-orders.index', ['q' => 'PRD-999']))->assertOk()->assertSee('No production matches');
        $this->get(route('production-orders.index', ['status' => 'completed']))->assertOk();
        $this->get(route('production-orders.create'))->assertOk();
        $this->get(route('production-orders.create', ['site_id' => $this->factory->id]))->assertOk()->assertSee('Silk Fabric');
        $this->get(route('production-orders.show', $order))->assertOk()
            ->assertSee('PRD-000001')
            ->assertSee('Material Used')
            ->assertSee('waiting for sewing')
            ->assertSee('Blister / Poly');
    }
}
