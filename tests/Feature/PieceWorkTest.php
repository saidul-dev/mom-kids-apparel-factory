<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PieceRate;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionPieceWork;
use App\Models\Site;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PieceWorkTest extends TestCase
{
    use RefreshDatabase;

    private Site $factory;

    private Product $frock;

    private ProductionOrder $order;

    private Employee $rahima;

    private Employee $karim;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerAccountSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);

        $this->factory = Site::create(['name' => 'Factory', 'code' => 'FAC-01', 'type' => 'Factory', 'status' => true]);

        $category = Category::create(['name' => 'Garments']);
        $silk = Product::create([
            'name' => 'Silk Fabric', 'type' => 'raw_material', 'sku' => 'FAB-1', 'category_id' => $category->id,
            'stock_unit_id' => Unit::create(['name' => 'Roll', 'short_name' => 'Roll'])->id,
            'estimated_cost' => 0, 'selling_price' => 0, 'status' => true,
        ]);
        $this->frock = Product::create([
            'name' => 'Girls Frock', 'sku' => 'FRK-1', 'category_id' => $category->id,
            'stock_unit_id' => Unit::create(['name' => 'Piece', 'short_name' => 'Pcs'])->id,
            'estimated_cost' => 0, 'selling_price' => 650, 'status' => true,
        ]);
        StockMovement::create([
            'product_id' => $silk->id, 'site_id' => $this->factory->id, 'type' => 'initial_stock',
            'quantity' => 20, 'unit_cost' => 10000, 'moved_at' => '2026-10-01', 'created_by' => $admin->id,
        ]);

        $this->post(route('production-orders.store'), [
            'site_id' => $this->factory->id,
            'item' => "product-{$this->frock->id}",
            'start_date' => '2026-10-02',
            'cut_quantity' => 500,
            'fabrics' => [['item' => "product-{$silk->id}", 'quantity' => 5]],
        ]);
        $this->order = ProductionOrder::firstOrFail();

        $this->rahima = $this->employee('Rahima', 'piece_rate');
        $this->karim = $this->employee('Karim', 'piece_rate');
        $this->manager = $this->employee('Floor Manager', 'flat', 10000);
    }

    private function employee(string $name, string $mode, ?float $flat = null): Employee
    {
        static $n = 0;
        $n++;

        $employee = Employee::create([
            'site_id' => $this->factory->id, 'name' => $name, 'phone' => "0171000000{$n}", 'email' => "e{$n}@example.test",
            'joining_date' => '2026-01-01', 'employment_type' => 'permanent', 'employment_status' => 'active',
        ]);
        $employee->salaryStructure()->create(['mode' => $mode, 'flat_amount' => $flat, 'effective_from' => '2026-01-01']);

        return $employee;
    }

    private function setRates(array $rates): void
    {
        $this->put(route('piece-rates.update'), ['rates' => [$this->frock->id => $rates]])->assertSessionHasNoErrors();
    }

    private function stage(string $stage, array $workers, string $date = '2026-10-05')
    {
        return $this->post(route('production-orders.stages.store', $this->order), [
            'stage' => $stage,
            'entry_date' => $date,
            'workers' => $workers,
        ]);
    }

    public function test_the_rate_chart_saves_and_clears_rates(): void
    {
        $this->setRates(['sewing' => 12, 'finishing' => 2, 'packing' => '']);

        $this->assertEquals(['sewing' => 12, 'finishing' => 2], PieceRate::pluck('rate', 'stage')->map(fn ($r) => (float) $r)->all());

        $this->setRates(['sewing' => 12.5, 'finishing' => '', 'packing' => 1]);

        $this->assertEquals(['packing' => 1, 'sewing' => 12.5], PieceRate::orderBy('stage')->pluck('rate', 'stage')->map(fn ($r) => (float) $r)->all());
        $this->get(route('piece-rates.index'))->assertOk()->assertSee('Girls Frock');
    }

    public function test_a_stage_entry_split_between_workers_records_what_each_earned(): void
    {
        $this->setRates(['sewing' => 12]);

        $this->stage('sewing', [
            ['employee_id' => $this->rahima->id, 'quantity' => 120],
            ['employee_id' => $this->karim->id, 'quantity' => 180],
        ])->assertSessionHasNoErrors();

        // The entry is the workers' total.
        $this->assertEquals(300, $this->order->stageTotals()['sewing']);
        $this->assertEquals(
            [$this->rahima->id => 1440, $this->karim->id => 2160],
            ProductionPieceWork::pluck('amount', 'employee_id')->map(fn ($a) => (float) $a)->all()
        );

        $this->get(route('production-orders.show', $this->order))->assertOk()->assertSee('Rahima: 120 pcs');
        $this->get(route('piece-works.index', ['from' => '2026-10-05']))->assertOk()
            ->assertSee('Karim')
            ->assertSee('3,600.00');
    }

    public function test_piece_work_needs_a_rate_a_piece_rate_worker_and_a_paid_stage(): void
    {
        $this->stage('sewing', [['employee_id' => $this->rahima->id, 'quantity' => 10]])->assertSessionHasErrors('workers');

        $this->setRates(['sewing' => 12]);
        $this->stage('sewing', [['employee_id' => $this->manager->id, 'quantity' => 10]])->assertSessionHasErrors('workers');
        $this->stage('cutting', [['employee_id' => $this->rahima->id, 'quantity' => 10]])->assertSessionHasErrors('workers');
        // Still can't record more than the previous stage handed over.
        $this->stage('sewing', [['employee_id' => $this->rahima->id, 'quantity' => 501]])->assertSessionHasErrors('quantity');

        $this->assertSame(0, ProductionPieceWork::count());
        $this->assertEquals(0, $this->order->stageTotals()['sewing']);
    }

    public function test_payroll_pays_piece_rate_workers_for_the_months_pieces(): void
    {
        $this->setRates(['sewing' => 12, 'finishing' => 2]);
        $this->stage('sewing', [['employee_id' => $this->rahima->id, 'quantity' => 300]]);
        $this->stage('finishing', [['employee_id' => $this->rahima->id, 'quantity' => 100]]);
        // Next month's work belongs to next month's run.
        $this->stage('finishing', [['employee_id' => $this->rahima->id, 'quantity' => 50]], '2026-11-02');

        $this->post(route('payroll-runs.store'), ['site_id' => $this->factory->id, 'month' => 10, 'year' => 2026])
            ->assertSessionHasNoErrors();

        $run = PayrollRun::firstOrFail();
        $items = $run->items()->get()->keyBy('employee_id');

        // 300 × 12 + 100 × 2. Karim did no piece work, so gets no line.
        $this->assertEquals(3800, (float) $items[$this->rahima->id]->gross_amount);
        $this->assertEquals(400, (float) $items[$this->rahima->id]->piece_quantity);
        $this->assertEquals(3800, (float) $items[$this->rahima->id]->net_amount);
        $this->assertArrayNotHasKey($this->karim->id, $items->all());
        $this->assertEquals(10000, (float) $items[$this->manager->id]->gross_amount);

        $this->get(route('payroll-runs.show', $run))->assertOk()->assertSee('400 pcs');
        $this->get(route('payroll-runs.items.payslip', $items[$this->rahima->id]))->assertOk()
            ->assertSee('Girls Frock — Sewing')
            ->assertSee('3,600.00')
            ->assertSee('3,800.00');
    }
}
