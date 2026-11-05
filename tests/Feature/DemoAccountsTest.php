<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\Site;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DemoEmployeeSeeder;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAccountsTest extends TestCase
{
    use RefreshDatabase;

    private Site $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(LedgerAccountSeeder::class);

        $this->factory = Site::create(['name' => 'Factory', 'code' => 'FAC-01', 'type' => 'Factory', 'status' => true]);
    }

    public function test_the_login_page_lists_every_demo_role_only_when_turned_on(): void
    {
        config(['app.demo_logins' => true]);
        $this->get(route('login'))->assertOk()
            ->assertSee('supervisor@businesserp.test')
            ->assertSee('employee@businesserp.test')
            ->assertSee('Production Supervisor');

        config(['app.demo_logins' => false]);
        $this->get(route('login'))->assertOk()->assertDontSee('businesserp.test');
    }

    public function test_the_demo_employee_gets_a_login_and_reseeding_changes_nothing(): void
    {
        $this->seed(DemoEmployeeSeeder::class);
        $this->seed(DemoEmployeeSeeder::class);

        $employee = Employee::where('email', 'employee@businesserp.test')->sole();
        $this->assertSame('piece_rate', $employee->salaryStructure->mode);
        $this->assertTrue($employee->user->hasRole('Employee'));
        $this->assertSame(1, User::where('email', 'employee@businesserp.test')->count());

        $this->post(route('login'), ['email' => 'employee@businesserp.test', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_a_workers_dashboard_shows_their_production_work_and_the_floor_but_no_lists(): void
    {
        $this->seed(DemoEmployeeSeeder::class);
        $worker = Employee::where('email', 'employee@businesserp.test')->sole();

        $frock = Product::create([
            'name' => 'Girls Frock', 'sku' => 'FRK-1', 'category_id' => Category::create(['name' => 'Kids'])->id,
            'stock_unit_id' => Unit::create(['name' => 'Piece', 'short_name' => 'Pcs'])->id,
            'estimated_cost' => 0, 'selling_price' => 650, 'status' => true,
        ]);
        $order = ProductionOrder::create([
            'production_no' => 'PRD-000001', 'site_id' => $this->factory->id, 'product_id' => $frock->id,
            'start_date' => today(), 'status' => 'in_progress', 'created_by' => $worker->user_id,
        ]);
        $order->stageEntries()->create(['stage' => 'cutting', 'quantity' => 500, 'entry_date' => today(), 'created_by' => $worker->user_id]);
        $sewing = $order->stageEntries()->create(['stage' => 'sewing', 'quantity' => 120, 'entry_date' => today(), 'created_by' => $worker->user_id]);
        $sewing->pieceWorks()->create([
            'production_order_id' => $order->id, 'employee_id' => $worker->id, 'stage' => 'sewing',
            'quantity' => 120, 'rate' => 12, 'amount' => 1440, 'entry_date' => today(), 'created_by' => $worker->user_id,
        ]);

        $this->actingAs($worker->user);

        $this->get(route('dashboard'))->assertOk()
            ->assertSee('My Production Work')
            ->assertSee('1,440.00')
            ->assertSee('Production Floor')
            ->assertSee('At Sewing')
            ->assertDontSee('Low Stock Items')
            ->assertDontSee('Recent Collections')
            ->assertDontSee(route('departments.index'))
            ->assertDontSee(route('employees.index'));

        foreach (['employees.index', 'departments.index', 'designations.index', 'leave-types.index', 'attendance.summary'] as $page) {
            $this->get(route($page))->assertForbidden();
        }
        $this->get(route('leave-requests.index'))->assertOk();
    }

    public function test_an_employee_login_sees_only_their_own_pay(): void
    {
        $this->seed(DemoEmployeeSeeder::class);
        $worker = Employee::where('email', 'employee@businesserp.test')->sole();
        $colleague = Employee::create([
            'site_id' => $this->factory->id, 'name' => 'Colleague', 'phone' => '01700000098', 'email' => 'colleague@example.test',
            'joining_date' => '2026-01-01', 'employment_type' => 'permanent', 'employment_status' => 'active',
        ]);

        $run = PayrollRun::create(['site_id' => $this->factory->id, 'month' => 10, 'year' => 2026, 'status' => 'draft']);
        $line = fn (Employee $e) => $run->items()->create([
            'employee_id' => $e->id, 'mode' => 'flat', 'flat_amount' => 9000, 'gross_amount' => 9000, 'net_amount' => 9000,
        ]);
        $own = $line($worker);
        $theirs = $line($colleague);

        $this->actingAs($worker->user);

        $this->get(route('payroll-runs.items.payslip', $own))->assertOk();
        $this->get(route('payroll-runs.items.payslip', $theirs))->assertForbidden();
        $this->get(route('payroll-runs.index'))->assertForbidden();
        $this->get(route('payroll-runs.show', $run))->assertForbidden();
        $this->get(route('piece-works.index'))->assertForbidden();
        $this->get(route('piece-rates.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()->assertDontSee(route('payroll-runs.index'));

        // Their own record, read-only, with only their own payslips.
        $run->update(['status' => 'approved']);
        $this->get(route('my-profile'))->assertOk()
            ->assertSee('Salma Akter')
            ->assertSee('Piece rate (production)')
            ->assertSee(route('payroll-runs.items.payslip', $own))
            ->assertDontSee(route('payroll-runs.items.payslip', $theirs))
            ->assertDontSee('Colleague');
        $this->get(route('dashboard'))->assertSee(route('my-profile'));
        $this->get(route('employees.edit', $worker))->assertForbidden();

        // HR still sees everyone's.
        $hr = User::factory()->create();
        $hr->assignRole('HR');
        $this->actingAs($hr);
        $this->get(route('payroll-runs.index'))->assertOk();
        $this->get(route('payroll-runs.items.payslip', $theirs))->assertOk();
        $this->get(route('piece-works.index'))->assertOk();
        // A login with no employee record has no profile to show.
        $this->get(route('my-profile'))->assertForbidden();
    }
}
