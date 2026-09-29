<?php

namespace Tests\Feature\Terrain;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TerrainTest extends TestCase
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

    /** @return array{0:User,1:Vehicle} */
    private function bootstrap(Organisation $org): array
    {
        return $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

            return [$admin, $vehicle];
        });
    }

    public function test_terrain_home_and_vehicle_screens_load(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$admin, $vehicle] = $this->bootstrap($org);

        $this->actingAs($admin)->get('http://caserne.localhost/t')->assertOk();
        $this->actingAs($admin)->get("http://caserne.localhost/t/vehicules/{$vehicle->id}")->assertOk();
        $this->actingAs($admin)->get('http://caserne.localhost/t/scanner')->assertOk();
        $this->actingAs($admin)->get('http://caserne.localhost/t/anomalie')->assertOk();
        // Congés natifs terrain (pas de redirection vers l'app complète).
        $this->actingAs($admin)->get('http://caserne.localhost/t/conges')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Terrain/Leave'));
    }

    public function test_home_focuses_on_active_session_and_hides_list(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            \App\Models\VehicleSession::create([
                'vehicle_id' => $vehicle->id, 'user_id' => $agent->id, 'opened_at' => now(),
            ]);

            return [$agent, $vehicle];
        });

        $this->actingAs($agent)->get('http://caserne.localhost/t')
            ->assertInertia(fn (Assert $p) => $p
                ->component('Terrain/Home')
                ->where('active_session.vehicle_id', $vehicle->id)
                ->where('vehicles', [])); // liste masquée pendant le service
    }

    public function test_reporting_an_anomaly_creates_an_event(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$admin, $vehicle] = $this->bootstrap($org);

        $this->actingAs($admin)->post('http://caserne.localhost/t/anomalie', [
            'title' => 'Bouteille O2 vide',
            'description' => 'À remplacer',
            'priority' => 'haute',
            'vehicle_id' => $vehicle->id,
        ])->assertRedirect('http://caserne.localhost/t');

        $this->tenant()->runFor($org, function () use ($vehicle, $admin) {
            $this->assertDatabaseHas('events', [
                'type' => 'anomalie',
                'title' => 'Bouteille O2 vide',
                'priority' => 'haute',
                'vehicle_id' => $vehicle->id,
                'created_by' => $admin->id,
            ]);
        });
    }

    public function test_anomaly_with_photo_is_stored_and_served(): void
    {
        Storage::fake('local');
        $org = Organisation::factory()->slug('caserne')->create();
        [$admin, $vehicle] = $this->bootstrap($org);

        $this->actingAs($admin)->post('http://caserne.localhost/t/anomalie', [
            'title' => 'Vitre cassée',
            'priority' => 'normale',
            'vehicle_id' => $vehicle->id,
            'photo' => UploadedFile::fake()->image('anomalie.jpg', 400, 300),
        ])->assertRedirect('http://caserne.localhost/t');

        $eventId = $this->tenant()->runFor($org, function () {
            $event = Event::query()->where('title', 'Vitre cassée')->firstOrFail();
            $this->assertNotNull($event->photo_path);
            Storage::disk('local')->assertExists($event->photo_path);

            return $event->id;
        });

        // La photo est servie via la route authentifiée.
        $this->actingAs($admin)->get("http://caserne.localhost/t/anomalie/{$eventId}/photo")->assertOk();
    }

    public function test_opening_a_session_grants_access_and_updates_mileage(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER); // pas de droit vehicles.manage
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'mileage' => 100]);

            return [$agent, $vehicle];
        });

        // Sans session, l'accès à la fiche redirige vers la prise de service.
        $this->actingAs($agent)->get("http://caserne.localhost/t/vehicules/{$vehicle->id}")
            ->assertRedirect("http://caserne.localhost/t/vehicules/{$vehicle->id}/prise-de-service");

        // Ouverture de session (vérification) → accès accordé + km mis à jour.
        $this->actingAs($agent)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/prise-de-service", [
            'mileage' => 12345,
            'steps' => [['label' => 'Niveaux', 'done' => true], ['label' => 'Pneus', 'done' => false]],
            'notes' => 'RAS',
        ])->assertRedirect("http://caserne.localhost/t/vehicules/{$vehicle->id}");

        $this->actingAs($agent)->get("http://caserne.localhost/t/vehicules/{$vehicle->id}")->assertOk();

        $this->tenant()->runFor($org, function () use ($vehicle, $agent) {
            $this->assertDatabaseHas('vehicle_sessions', [
                'vehicle_id' => $vehicle->id,
                'user_id' => $agent->id,
                'open_mileage' => 12345,
                'closed_at' => null,
            ]);
            $this->assertSame(12345, (int) $vehicle->fresh()->mileage);
        });
    }

    public function test_body_inspection_is_mandatory_when_enabled(): void
    {
        $org = Organisation::factory()->slug('caserne')->create(['settings' => ['body_inspection_enabled' => true]]);
        [$agent, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

            return [$agent, $vehicle];
        });

        // Sans le contrôle carrosserie coché → refus, aucune session ouverte.
        $this->actingAs($agent)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/prise-de-service", [
            'mileage' => 500,
        ])->assertSessionHasErrors('body_ack');
        $this->tenant()->runFor($org, fn () => $this->assertDatabaseMissing('vehicle_sessions', ['vehicle_id' => $vehicle->id]));

        // Avec le contrôle coché → session ouverte.
        $this->actingAs($agent)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/prise-de-service", [
            'mileage' => 500,
            'body_ack' => true,
        ])->assertSessionHasNoErrors();
        $this->tenant()->runFor($org, fn () => $this->assertDatabaseHas('vehicle_sessions', [
            'vehicle_id' => $vehicle->id, 'user_id' => $agent->id, 'closed_at' => null,
        ]));
    }

    public function test_opening_by_another_agent_hands_over_the_session(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$a, $b, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $a = User::factory()->create(['organisation_id' => $org->id]);
            $b = User::factory()->create(['organisation_id' => $org->id]);
            $a->assignRole(Rbac::VERIFIER);
            $b->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

            return [$a, $b, $vehicle];
        });

        $this->actingAs($a)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/prise-de-service", [])->assertRedirect();
        // B prend le véhicule : la session de A est clôturée par passation.
        $this->actingAs($b)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/prise-de-service", [])->assertRedirect();

        $this->tenant()->runFor($org, function () use ($vehicle, $a, $b) {
            $sessions = \App\Models\VehicleSession::query()->where('vehicle_id', $vehicle->id)->get();
            // Une seule session ouverte, celle de B.
            $open = $sessions->whereNull('closed_at');
            $this->assertCount(1, $open);
            $this->assertSame($b->id, $open->first()->user_id);
            // Celle de A est clôturée par passation, par B.
            $closed = $sessions->firstWhere('user_id', $a->id);
            $this->assertNotNull($closed->closed_at);
            $this->assertSame('handover', $closed->close_reason);
            $this->assertSame($b->id, $closed->closed_by);
        });
    }

    public function test_agent_can_close_their_session(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

            return [$agent, $vehicle];
        });

        $this->actingAs($agent)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/prise-de-service", [])->assertRedirect();
        $this->actingAs($agent)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/fin-de-service", ['notes' => 'Fin'])
            ->assertRedirect('http://caserne.localhost/t');

        $this->tenant()->runFor($org, function () use ($vehicle, $agent) {
            $session = \App\Models\VehicleSession::query()->where('vehicle_id', $vehicle->id)->firstOrFail();
            $this->assertNotNull($session->closed_at);
            $this->assertSame('manual', $session->close_reason);
            $this->assertSame($agent->id, $session->closed_by);
        });
    }

    public function test_qr_only_hides_vehicle_list_for_non_managers(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $viewer = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $org->settings = ['vehicle_access_qr_only' => true];
            $org->save();
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::VERIFIER); // pas de droit vehicles.manage
            Vehicle::factory()->create(['organisation_id' => $org->id]);

            return $u;
        });

        $this->actingAs($viewer)->get('http://caserne.localhost/t')
            ->assertInertia(fn (Assert $p) => $p
                ->component('Terrain/Home')
                ->where('qr_only', true)
                ->where('vehicles', []));
    }

    public function test_anomaly_requires_permission(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $viewer = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            // Utilisateur sans rôle : aucune permission anomalies.manage.
            return User::factory()->create(['organisation_id' => $org->id]);
        });

        $this->actingAs($viewer)->post('http://caserne.localhost/t/anomalie', [
            'title' => 'Test',
            'priority' => 'normale',
        ])->assertForbidden();
    }
}
