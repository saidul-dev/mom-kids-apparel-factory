<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Employee;
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

class ProductionSupervisorRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private Site $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerAccountSeeder::class);

        $this->factory = Site::create(['name' => 'Factory', 'code' => 'FAC-01', 'type' => 'Factory', 'status' => true]);

        $this->supervisor = User::factory()->create();
        $this->supervisor->assignRole('Production Supervisor');
        $this->supervisor->sites()->attach($this->factory->id);
        $this->actingAs($this->supervisor);
    }

    public function test_the_supervisor_adds_and_updates_employees_but_not_their_pay_or_login(): void
    {
        $this->get(route('employees.index'))->assertOk()->assertSee('Add');
        $this->get(route('employees.create'))->assertOk();

        $this->post(route('employees.store'), [
            'site_id' => $this->factory->id, 'name' => 'Rahima Begum', 'phone' => '01711111111', 'email' => 'rahima@example.test',
            'joining_date' => '2026-11-01', 'employment_type' => 'permanent', 'employment_status' => 'active',
        ])->assertSessionHasNoErrors();
        $employee = Employee::where('name', 'Rahima Begum')->firstOrFail();

        $this->get(route('employees.edit', $employee))->assertOk()
            ->assertDontSee('Salary Structure')
            ->assertDontSee('Login Access');

        $this->put(route('employees.salary.update', $employee), ['mode' => 'piece_rate', 'effective_from' => '2026-11-01'])->assertForbidden();
        $this->patch(route('employees.toggle-login', $employee))->assertForbidden();
        $this->delete(route('employees.destroy', $employee))->assertForbidden();
    }

    public function test_the_supervisor_records_production_but_cannot_complete_it_or_touch_purchases_and_payroll(): void
    {
        $category = Category::create(['name' => 'Garments']);
        $silk = Product::create([
            'name' => 'Silk Fabric', 'type' => 'raw_material', 'sku' => 'FAB-1', 'category_id' => $category->id,
            'stock_unit_id' => Unit::create(['name' => 'Roll', 'short_name' => 'Roll'])->id,
            'estimated_cost' => 0, 'selling_price' => 0, 'status' => true,
        ]);
        $frock = Product::create([
            'name' => 'Girls Frock', 'sku' => 'FRK-1', 'category_id' => $category->id,
            'stock_unit_id' => Unit::create(['name' => 'Piece', 'short_name' => 'Pcs'])->id,
            'estimated_cost' => 0, 'selling_price' => 650, 'status' => true,
        ]);
        StockMovement::create([
            'product_id' => $silk->id, 'site_id' => $this->factory->id, 'type' => 'initial_stock',
            'quantity' => 20, 'unit_cost' => 10000, 'moved_at' => '2026-10-01', 'created_by' => $this->supervisor->id,
        ]);

        $this->get(route('production-orders.index'))->assertOk();
        $this->post(route('production-orders.store'), [
            'site_id' => $this->factory->id,
            'item' => "product-{$frock->id}",
            'start_date' => '2026-11-02',
            'cut_quantity' => 100,
            'fabrics' => [['item' => "product-{$silk->id}", 'quantity' => 1]],
        ])->assertSessionHasNoErrors();
        $order = ProductionOrder::firstOrFail();

        $this->post(route('production-orders.stages.store', $order), ['stage' => 'sewing', 'quantity' => 60, 'entry_date' => '2026-11-03'])
            ->assertSessionHasNoErrors();
        $this->assertEquals(60, $order->stageTotals()['sewing']);

        $this->get(route('production-orders.show', $order))->assertOk()->assertDontSee('Complete Production');
        $this->post(route('production-orders.complete', $order))->assertForbidden();

        $this->get(route('purchases.create'))->assertForbidden();
        $this->get(route('stock.transfers.create'))->assertForbidden();
        $this->get(route('payroll-runs.index'))->assertForbidden();
        $this->get(route('piece-rates.index'))->assertForbidden();
    }

    public function test_the_menu_shows_only_employees_and_production(): void
    {
        $this->get(route('production-orders.index'))->assertOk()
            ->assertSee(route('employees.index'))
            ->assertSee(route('production-orders.index'))
            ->assertDontSee(route('payroll-runs.index'))
            ->assertDontSee(route('purchases.index'));

        // No collection / expense / due figures on their dashboard.
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('New Production')
            ->assertSee('Low Stock Items')
            ->assertDontSee('Recent Collections')
            ->assertDontSee("Today's Collection", false)
            ->assertDontSee('collectionChart');

        $owner = User::factory()->create();
        $owner->assignRole('Admin');
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('Recent Collections');
    }
}
