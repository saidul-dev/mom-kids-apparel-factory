<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DutySiteTest extends TestCase
{
    use RefreshDatabase;

    private Site $factory;

    private Site $outlet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);

        $this->factory = Site::create(['name' => 'Factory', 'code' => 'FAC-01', 'type' => 'Factory', 'status' => true]);
        $this->outlet = Site::create(['name' => 'Wholesale Outlet', 'code' => 'OUT-01', 'type' => 'Outlet', 'status' => true]);
    }

    private function manager(array $sites): User
    {
        $user = User::factory()->create();
        $user->assignRole('Manager');
        $user->sites()->attach(collect($sites)->pluck('id'));

        return $user;
    }

    public function test_a_multi_site_user_picks_a_duty_site_after_every_login_and_pickers_start_on_it(): void
    {
        // Worked at the Outlet last time — that alone doesn't skip the picker.
        $user = $this->manager([$this->factory, $this->outlet]);
        $user->update(['current_site_id' => $this->outlet->id]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->get(route('purchases.create'))->assertRedirect(route('sites.select'));
        $this->get(route('sites.select'))->assertOk()->assertSee('Select your Duty Site');

        $this->post(route('sites.switch'), ['site_id' => $this->factory->id])->assertRedirect();

        $this->get(route('purchases.create'))->assertOk()->assertViewHas('site', fn ($site) => $site?->is($this->factory));
        $this->get(route('sales-orders.create'))->assertOk()->assertViewHas('site', fn ($site) => $site?->is($this->factory));
        $this->get(route('production-orders.create'))->assertOk()->assertViewHas('site', fn ($site) => $site?->is($this->factory));
        $this->get(route('stock.report'))->assertOk()->assertViewHas('site', fn ($site) => $site?->is($this->factory));
        $this->get(route('stock.transfers.create'))->assertOk()->assertViewHas('fromSite', fn ($site) => $site?->is($this->factory));

        // List filters start on the duty site, but "all sites" can still be picked.
        $this->get(route('purchases.index'))->assertViewHas('siteId', $this->factory->id);
        $this->get(route('purchases.index', ['site_id' => '']))->assertViewHas('siteId', null);
        // And any other site can be picked on the page itself.
        $this->get(route('purchases.create', ['site_id' => $this->outlet->id]))->assertViewHas('site', fn ($site) => $site?->is($this->outlet));

        // Topbar switch changes the duty site for the rest of the session.
        $this->post(route('sites.switch'), ['site_id' => $this->outlet->id]);
        $this->get(route('purchases.create'))->assertViewHas('site', fn ($site) => $site?->is($this->outlet));

        // Next login asks again.
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
        $this->get(route('purchases.create'))->assertRedirect(route('sites.select'));
    }

    public function test_a_single_site_user_goes_straight_in_on_that_site(): void
    {
        $user = $this->manager([$this->factory]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);

        $this->get(route('purchases.create'))->assertOk()->assertViewHas('site', fn ($site) => $site?->is($this->factory));
    }

    public function test_a_user_cannot_pick_a_site_they_are_not_assigned_to(): void
    {
        $other = Site::create(['name' => 'Other', 'code' => 'OTH-01', 'type' => 'Outlet', 'status' => true]);
        $this->actingAs($this->manager([$this->factory, $this->outlet]));

        $this->post(route('sites.switch'), ['site_id' => $other->id])->assertForbidden();
    }
}
