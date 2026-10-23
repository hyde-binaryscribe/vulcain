<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\OxygenBottle;
use App\Models\OxygenBottleLog;
use App\Models\ServiceProtocol;
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

    public function test_dashboard_aggregates_fleet_oxygen(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);

            $a = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $b = Vehicle::factory()->create(['organisation_id' => $org->id]);
            OxygenBottle::create(['vehicle_id' => $a->id, 'label' => 'Pleine', 'pressure_min' => 0, 'pressure_max' => 200, 'current_pressure' => 200]);
            OxygenBottle::create(['vehicle_id' => $a->id, 'label' => 'Basse', 'pressure_min' => 0, 'pressure_max' => 200, 'current_pressure' => 40]);
            OxygenBottle::create(['vehicle_id' => $b->id, 'label' => 'HS', 'pressure_min' => 0, 'pressure_max' => 200, 'current_pressure' => 100, 'status' => OxygenBottle::STATUS_A_REMPLACER]);

            return $admin;
        });

        $this->actingAs($admin)->get('http://caserne.localhost/oxygene/tableau-de-bord')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Oxygen/Dashboard')
                ->where('kpis.vehicles_equipped', 2)
                ->where('kpis.bottles', 3)
                ->where('kpis.low', 1)          // 40/200 = 20 %
                ->where('kpis.to_replace', 1)
                ->where('kpis.avg_fill', 60)     // moyenne des en service : (100 + 20) / 2
                ->where('can_manage', true)
                ->has('attention', 2));          // la basse + la « à remplacer »
    }

    public function test_manager_can_act_on_a_bottle_without_an_open_session(): void
    {
        // Les actions du dashboard sont déclenchées par un gestionnaire, hors service.
        $org = Organisation::factory()->slug('caserne')->create();
        [$admin, $vehicle, $bottleId] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $bottle = OxygenBottle::create([
                'vehicle_id' => $vehicle->id, 'label' => 'Principale',
                'pressure_min' => 0, 'pressure_max' => 200, 'current_pressure' => 30,
            ]);

            return [$admin, $vehicle, $bottle->id];
        });

        $this->actingAs($admin)->post("http://caserne.localhost/vehicles/{$vehicle->id}/oxygen-bottles/{$bottleId}/releve", [
            'pressure' => 180,
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () use ($bottleId) {
            $this->assertSame(180, OxygenBottle::findOrFail($bottleId)->current_pressure);
        });
    }

    public function test_oxygen_protocol_field_prefills_and_updates_register_on_opening(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle, $bottleId, $fieldId] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $bottle = OxygenBottle::create([
                'vehicle_id' => $vehicle->id, 'label' => 'Principale', 'serial_number' => 'OLD-1',
                'pressure_min' => 0, 'pressure_max' => 200, 'current_pressure' => 120,
            ]);
            $protocol = ServiceProtocol::create(['vehicle_type' => null, 'phase' => 'ouverture', 'is_active' => true]);
            $field = $protocol->fields()->create(['label' => 'Contrôle oxygène', 'type' => 'oxygen', 'config' => [], 'alert' => ['enabled' => false], 'display_order' => 0]);

            return [$agent, $vehicle, $bottle->id, $field->id];
        });

        // Prise de service : nouveau n° de série + niveau 180 bar pour la bouteille.
        $this->actingAs($agent)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/prise-de-service", [
            'responses' => [(string) $fieldId => [(string) $bottleId => ['serial' => 'new-9', 'pressure' => 180]]],
        ])->assertRedirect("http://caserne.localhost/t/vehicules/{$vehicle->id}");

        $this->tenant()->runFor($org, function () use ($bottleId, $vehicle) {
            $bottle = OxygenBottle::findOrFail($bottleId);
            $this->assertSame(180, $bottle->current_pressure);  // niveau mis à jour
            $this->assertSame('NEW-9', $bottle->serial_number);  // n° changé (majuscules)
            $this->assertSame(90, $bottle->percent());

            // Changement de bouteille tracé (n° de série différent).
            $log = OxygenBottleLog::query()->where('oxygen_bottle_id', $bottleId)
                ->where('action', OxygenBottleLog::ACTION_REMPLACEMENT)->firstOrFail();
            $this->assertSame('OLD-1', $log->previous_serial);
            $this->assertSame('NEW-9', $log->serial_number);

            // La réponse figure dans la session de service.
            $session = VehicleSession::query()->where('vehicle_id', $vehicle->id)->firstOrFail();
            $row = collect($session->open_responses)->firstWhere('type', 'oxygen');
            $this->assertNotNull($row);
            $this->assertStringContainsString('Principale', $row['value']);
        });
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
