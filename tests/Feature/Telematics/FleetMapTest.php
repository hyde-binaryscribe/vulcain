<?php

namespace Tests\Feature\Telematics;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePosition;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FleetMapTest extends TestCase
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

    public function test_fleet_map_lists_located_vehicles(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $admin = app(TenantContext::class)->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'name' => 'A12', 'telematics_imei' => '862272081619456']);
            VehiclePosition::create([
                'organisation_id' => $org->id,
                'vehicle_id' => $vehicle->id,
                'latitude' => 45.75, 'longitude' => 4.83, 'speed' => 50,
                'device_time' => now(), 'server_time' => now(),
            ]);

            return $admin;
        });

        $this->actingAs($admin)->get('http://caserne.localhost/suivi-flotte')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Fleet/Map')
                ->where('located', 1)
                ->has('vehicles', 1)
                ->where('vehicles.0.position.speed', 50));
    }
}
