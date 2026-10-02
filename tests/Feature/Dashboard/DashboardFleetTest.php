<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Catalog\MaterialStatus;
use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\DisinfectionProtocol;
use App\Models\Location;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardFleetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.central_domains' => ['localhost']]);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->forget();
        parent::tearDown();
    }

    private function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }

    public function test_fleet_lists_vehicles_with_overdue_disinfection_first(): void
    {
        $org = Organisation::factory()->slug('cis')->create();

        [$admin, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);

            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'name' => 'A12']);
            $protocol = DisinfectionProtocol::create(['name' => 'Hebdo', 'type' => 'desinfection', 'frequency_days' => 7]);
            $vehicle->disinfectionProtocols()->attach($protocol->id, ['organisation_id' => $org->id]);
            $vehicle->disinfections()->create(['type' => 'desinfection', 'disinfection_protocol_id' => $protocol->id, 'performed_at' => now()->subDays(10)]);

            return [$admin, $vehicle];
        });

        $this->actingAs($admin)->get('http://cis.localhost/parc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Fleet/Dashboard')
                ->has('fleet', 1)
                ->where('fleet.0.id', $vehicle->id)
                ->where('fleet.0.disinfection', 'critical'));
    }

    public function test_fleet_flags_vehicles_short_of_consumables(): void
    {
        $org = Organisation::factory()->slug('cis')->create();

        [$admin, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);

            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'name' => 'A12']);
            $location = Location::create(['organisation_id' => $org->id, 'name' => 'Cellule', 'vehicle_id' => $vehicle->id, 'is_active' => true]);
            // Manque critique : 0 en stock pour un mini de 4 (théorique 4).
            Material::create([
                'organisation_id' => $org->id,
                'location_id' => $location->id,
                'name' => 'Gants nitrile M',
                'tracking_mode' => Material::MODE_QUANTITY,
                'theoretical_qty' => 4,
                'minimum_qty' => 4,
                'current_qty' => 0,
                'status' => MaterialStatus::CONFORME->value,
            ]);

            return [$admin, $vehicle];
        });

        $this->actingAs($admin)->get('http://cis.localhost/parc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Fleet/Dashboard')
                ->where('stats.vehicles', 1)
                ->where('stats.available', 1)
                ->where('fleet.0.id', $vehicle->id)
                ->where('fleet.0.consumables', 'critical')
                ->where('fleet.0.consumables_missing', 1));
    }
}
