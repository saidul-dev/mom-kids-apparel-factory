<?php

namespace Tests\Feature;

use App\Models\Party;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyQuickAddTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $this->actingAs($admin);
    }

    public function test_a_supplier_or_customer_can_be_added_from_the_order_forms(): void
    {
        $this->postJson(route('parties.quick-store'), ['type' => 'supplier', 'name' => 'Rahim Fabrics', 'phone' => '01711000001'])
            ->assertCreated()
            ->assertJson(['name' => 'Rahim Fabrics']);
        $this->postJson(route('parties.quick-store'), ['type' => 'customer', 'name' => 'Karim Store', 'phone' => '01711000002', 'address' => 'Mirpur'])
            ->assertCreated();

        $supplier = Party::where('name', 'Rahim Fabrics')->firstOrFail();
        $this->assertTrue($supplier->is_supplier);
        $this->assertFalse($supplier->is_customer);
        $this->assertTrue($supplier->status);

        $customer = Party::where('name', 'Karim Store')->firstOrFail();
        $this->assertTrue($customer->is_customer);
        $this->assertSame('Mirpur', $customer->address);

        $this->postJson(route('parties.quick-store'), ['type' => 'customer', 'name' => 'Same Phone', 'phone' => '01711000001'])
            ->assertJsonValidationErrors('phone');
        $this->postJson(route('parties.quick-store'), ['type' => 'customer', 'phone' => '01711000003'])
            ->assertJsonValidationErrors('name');
    }

    public function test_the_forms_show_the_quick_add(): void
    {
        $site = Site::create(['name' => 'Factory', 'code' => 'FAC-01', 'type' => 'Factory', 'status' => true]);

        $this->get(route('purchases.create', ['site_id' => $site->id]))->assertOk()->assertSee('New Supplier');
        $this->get(route('sales-orders.create', ['site_id' => $site->id]))->assertOk()->assertSee('New Customer');
    }
}
