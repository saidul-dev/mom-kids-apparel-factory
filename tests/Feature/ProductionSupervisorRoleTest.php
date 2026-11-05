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

    public function test_the_supervisor_sees_only_their_own_profile_not_the_employee_list(): void
    {
        $colleague = Employee::create([
            'site_id' => $this->factory->id, 'name' => 'Rahima Begum', 'phone' => '01711111111', 'email' => 'rahima@example.test',
            'joining_date' => '2026-11-01', 'employment_type' => 'permanent', 'employment_status' => 'active',
        ]);
        $me = Employee::create([
            'site_id' => $this->factory->id, 'name' => 'Jamal Uddin', 'phone' => '01700000097', 'email' => 'jamal@example.test',
            'joining_date' => '2026-01-01', 'employment_type' => 'permanent', 'employment_status' => 'active',
        ]);
        $me->user_id = $this->supervisor->id;
        $me->save();

        $this->get(route('employees.index'))->assertForbidden();
        $this->get(route('employees.create'))->assertForbidden();
        $this->get(route('employees.edit', $colleague))->assertForbidden();
        $this->get(route('employees.edit', $me))->assertForbidden();

        $this->get(route('my-profile'))->assertOk()
            ->assertSee('Jamal Uddin')
            ->assertDontSee('Rahima Begum');
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

    public function test_the_menu_shows_only_production(): void
    {
        $this->get(route('production-orders.index'))->assertOk()
            ->assertDontSee(route('employees.index'))
            ->assertSee(route('production-orders.index'))
            ->assertDontSee(route('payroll-runs.index'))
            ->assertDontSee(route('purchases.index'));

        // No collection / expense / due figures on their dashboard.
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('New Production')
            ->assertSee('Production Floor')
            ->assertSee('Low Stock Items')
            ->assertDontSee('Recent Collections')
            ->assertDontSee("Today's Collection", false)
            ->assertDontSee('collectionChart');

        // Phone app shell: home header, bottom tabs, app bar on inner pages.
        $this->get(route('dashboard'))
            ->assertSee('Finished today')
            ->assertSee('aria-label="Main"', false);
        $this->get(route('production-orders.index'))
            ->assertSee('aria-label="Back"', false)
            ->assertSee('aria-label="Main"', false);

        $owner = User::factory()->create();
        $owner->assignRole('Admin');
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()
            ->assertSee('Recent Collections')
            ->assertDontSee('aria-label="Main"', false);
    }
}
