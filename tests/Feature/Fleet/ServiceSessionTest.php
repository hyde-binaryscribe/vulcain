<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceSessionTest extends TestCase
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

    /** @return array{Organisation, User, User, User, Vehicle} */
    private function crewFixture(): array
    {
        $org = Organisation::factory()->slug('caserne')->create();

        return $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $opener = User::factory()->create(['organisation_id' => $org->id]);
            $opener->assignRole(Rbac::ADMIN);
            $partner = User::factory()->create(['organisation_id' => $org->id]);
            $partner->assignRole(Rbac::VERIFIER);
            $stranger = User::factory()->create(['organisation_id' => $org->id]);
            $stranger->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

            return [$org, $opener, $partner, $stranger, $vehicle];
        });
    }

    public function test_partner_shares_the_session_and_can_close_it(): void
    {
        [$org, $opener, $partner, $stranger, $vehicle] = $this->crewFixture();

        // L'ouvreur prend le service avec un binôme.
        $this->actingAs($opener)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/prise-de-service", [
            'mileage' => 1000,
            'partner_user_id' => $partner->id,
        ])->assertRedirect("http://caserne.localhost/t/vehicules/{$vehicle->id}");

        $this->tenant()->runFor($org, function () use ($vehicle, $partner) {
            $s = VehicleSession::query()->open()->where('vehicle_id', $vehicle->id)->first();
            $this->assertSame($partner->id, $s->partner_user_id);
        });

        // Le binôme accède à la fiche (pas de redirection vers la prise de service).
        $this->actingAs($partner)->get("http://caserne.localhost/t/vehicules/{$vehicle->id}")->assertOk();

        // Un agent hors équipage est renvoyé vers la prise de service.
        $this->actingAs($stranger)->get("http://caserne.localhost/t/vehicules/{$vehicle->id}")
            ->assertRedirect("http://caserne.localhost/t/vehicules/{$vehicle->id}/prise-de-service");

        // Le binôme peut clôturer le service.
        $this->actingAs($partner)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/fin-de-service", [
            'mileage' => 1010,
        ])->assertRedirect('http://caserne.localhost/t');

        $this->tenant()->runFor($org, fn () => $this->assertNull(
            VehicleSession::query()->open()->where('vehicle_id', $vehicle->id)->first()
        ));
    }

    public function test_a_crew_member_can_change_the_partner_mid_service(): void
    {
        [$org, $opener, $partner, $newPartner, $vehicle] = $this->crewFixture();

        $this->actingAs($opener)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/prise-de-service", [
            'partner_user_id' => $partner->id,
        ]);

        // Changement de binôme en cours de journée (par l'ouvreur).
        $this->actingAs($opener)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/binome", [
            'partner_user_id' => $newPartner->id,
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, fn () => $this->assertSame(
            $newPartner->id,
            VehicleSession::query()->open()->where('vehicle_id', $vehicle->id)->value('partner_user_id')
        ));

        // Le nouveau binôme (désormais équipier) peut retirer le binôme.
        $this->actingAs($newPartner)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/binome", [
            'partner_user_id' => '',
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, fn () => $this->assertNull(
            VehicleSession::query()->open()->where('vehicle_id', $vehicle->id)->value('partner_user_id')
        ));
    }

    public function test_vehicle_section_opens_as_a_dedicated_page(): void
    {
        [, $opener, , , $vehicle] = $this->crewFixture();

        // Une section valide est transmise comme page dédiée.
        $this->actingAs($opener)->get("http://caserne.localhost/t/vehicules/{$vehicle->id}/s/disinfection")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Terrain/Vehicle')->where('section', 'disinfection'));

        // Une section inconnue retombe sur le menu (section nulle).
        $this->actingAs($opener)->get("http://caserne.localhost/t/vehicules/{$vehicle->id}/s/inconnu")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('section', null));
    }

    public function test_manager_sees_open_sessions_and_history(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $v1 = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $v2 = Vehicle::factory()->create(['organisation_id' => $org->id]);

            // Une session ouverte…
            VehicleSession::create(['vehicle_id' => $v1->id, 'user_id' => $agent->id, 'opened_at' => now()]);
            // …et une clôturée avec des réponses de vérification.
            VehicleSession::create([
                'vehicle_id' => $v2->id, 'user_id' => $agent->id, 'opened_at' => now()->subDay(),
                'closed_at' => now(), 'closed_by' => $agent->id, 'close_reason' => 'manual',
                'open_responses' => [['label' => 'DAE présent', 'type' => 'tristate', 'value' => 'nok', 'alert' => true]],
            ]);

            return $admin;
        });

        $this->actingAs($admin)->get('http://caserne.localhost/suivi-service')
            ->assertInertia(fn (Assert $p) => $p
                ->component('ServiceSessions/Index')
                ->has('open', 1)
                ->has('history', 1)
                ->where('history.0.open_responses.0.label', 'DAE présent')
                ->where('history.0.open_responses.0.display', 'NOK')
                ->where('history.0.open_responses.0.alert', true));
    }
}
