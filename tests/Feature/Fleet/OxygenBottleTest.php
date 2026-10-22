<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\OxygenBottle;
use App\Models\OxygenBottleLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OxygenBottleTest extends TestCase
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

    public function test_manager_declares_a_bottle_full_by_default(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$admin, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);

            return [$admin, Vehicle::factory()->create(['organisation_id' => $org->id])];
        });

        $this->actingAs($admin)->post("http://caserne.localhost/vehicles/{$vehicle->id}/oxygen-bottles", [
            'label' => 'Principale', 'serial_number' => 'ab-12', 'capacity_l' => 5,
            'pressure_min' => 0, 'pressure_max' => 200,
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () use ($vehicle) {
            $bottle = OxygenBottle::query()->where('vehicle_id', $vehicle->id)->firstOrFail();
            $this->assertSame('Principale', $bottle->label);
            $this->assertSame('AB-12', $bottle->serial_number); // majuscules
            $this->assertSame(200, $bottle->current_pressure);   // plein par défaut
            $this->assertSame(100, $bottle->percent());
        });
    }

    public function test_crew_in_service_adjusts_level_and_is_logged(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle, $bottleId] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $bottle = OxygenBottle::create([
                'vehicle_id' => $vehicle->id, 'label' => 'Principale',
                'pressure_min' => 0, 'pressure_max' => 200, 'current_pressure' => 200,
            ]);
            VehicleSession::create(['vehicle_id' => $vehicle->id, 'user_id' => $agent->id, 'opened_at' => now()]);

            return [$agent, $vehicle, $bottle->id];
        });

        $this->actingAs($agent)->post("http://caserne.localhost/vehicles/{$vehicle->id}/oxygen-bottles/{$bottleId}/releve", [
            'pressure' => 150,
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () use ($bottleId) {
            $bottle = OxygenBottle::findOrFail($bottleId);
            $this->assertSame(150, $bottle->current_pressure);
            $this->assertSame(75, $bottle->percent());

            $log = OxygenBottleLog::query()->where('oxygen_bottle_id', $bottleId)->firstOrFail();
            $this->assertSame(OxygenBottleLog::ACTION_RELEVE, $log->action);
            $this->assertSame(75, $log->percent);
        });
    }

    public function test_crew_replaces_bottle_resets_to_full_and_tracks_serials(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle, $bottleId] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $bottle = OxygenBottle::create([
                'vehicle_id' => $vehicle->id, 'label' => 'Principale', 'serial_number' => 'OLD-1',
                'pressure_min' => 0, 'pressure_max' => 200, 'current_pressure' => 20,
                'status' => OxygenBottle::STATUS_A_REMPLACER,
            ]);
            VehicleSession::create(['vehicle_id' => $vehicle->id, 'user_id' => $agent->id, 'opened_at' => now()]);

            return [$agent, $vehicle, $bottle->id];
        });

        $this->actingAs($agent)->post("http://caserne.localhost/vehicles/{$vehicle->id}/oxygen-bottles/{$bottleId}/remplacer", [
            'serial_number' => 'new-9',
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () use ($bottleId) {
            $bottle = OxygenBottle::findOrFail($bottleId);
            $this->assertSame('NEW-9', $bottle->serial_number);
            $this->assertSame(200, $bottle->current_pressure); // remise à plein
            $this->assertSame(OxygenBottle::STATUS_EN_SERVICE, $bottle->status);

            $log = OxygenBottleLog::query()->where('action', OxygenBottleLog::ACTION_REMPLACEMENT)->firstOrFail();
            $this->assertSame('OLD-1', $log->previous_serial);
            $this->assertSame('NEW-9', $log->serial_number);
        });
    }

    public function test_agent_without_open_session_cannot_adjust(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle, $bottleId] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $bottle = OxygenBottle::create([
                'vehicle_id' => $vehicle->id, 'label' => 'Principale',
                'pressure_min' => 0, 'pressure_max' => 200, 'current_pressure' => 200,
            ]);

            return [$agent, $vehicle, $bottle->id];
        });

        // Aucune session ouverte pour cet agent → interdit.
        $this->actingAs($agent)->post("http://caserne.localhost/vehicles/{$vehicle->id}/oxygen-bottles/{$bottleId}/releve", [
            'pressure' => 100,
        ])->assertForbidden();
    }

    public function test_terrain_vehicle_page_exposes_oxygen_bottles(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            OxygenBottle::create([
                'vehicle_id' => $vehicle->id, 'label' => 'Principale',
                'pressure_min' => 0, 'pressure_max' => 200, 'current_pressure' => 100,
            ]);
            VehicleSession::create(['vehicle_id' => $vehicle->id, 'user_id' => $agent->id, 'opened_at' => now()]);

            return [$agent, $vehicle];
        });

        $this->actingAs($agent)->get("http://caserne.localhost/t/vehicules/{$vehicle->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Terrain/Vehicle')
                ->has('oxygen.bottles', 1)
                ->where('oxygen.bottles.0.percent', 50));
    }
}
