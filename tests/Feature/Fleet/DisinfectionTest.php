<?php

namespace Tests\Feature\Fleet;

use App\Domain\Fleet\DisinfectionStatus;
use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Domain\Support\Severity;
use App\Models\DisinfectionProtocol;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DisinfectionTest extends TestCase
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

    /** @return array{Organisation, User} */
    private function orgWithAdmin(string $slug = 'caserne'): array
    {
        $org = Organisation::factory()->slug($slug)->create();
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::ADMIN);

            return $u;
        });

        return [$org, $admin];
    }

    public function test_status_computation(): void
    {
        // En retard (dernière il y a 10 j, périodicité 7 j) => critique.
        $overdue = DisinfectionStatus::compute(Carbon::now()->subDays(10), 7);
        $this->assertSame(Severity::CRITICAL, $overdue->severity);
        $this->assertSame('overdue', $overdue->state);

        // À prévoir (échéance dans 2 j) => important.
        $soon = DisinfectionStatus::compute(Carbon::now()->subDays(6), 7);
        $this->assertSame(Severity::WARNING, $soon->severity);

        // À jour => aucune alerte.
        $ok = DisinfectionStatus::compute(Carbon::now()->subDay(), 7);
        $this->assertNull($ok->severity);

        // Sans périodicité => aucune échéance.
        $none = DisinfectionStatus::compute(Carbon::now()->subDays(100), null);
        $this->assertSame('none', $none->state);
    }

    public function test_operator_can_record_a_disinfection(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->post("http://caserne.localhost/vehicles/{$vehicle->id}/disinfections", [
            'type' => 'desinfection',
            'performed_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'notes' => 'Surfaces + brancard',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('disinfection_records', [
            'organisation_id' => $org->id,
            'vehicle_id' => $vehicle->id,
            'user_id' => $admin->id,
            'type' => 'desinfection',
            'notes' => 'Surfaces + brancard',
        ]);
    }

    public function test_future_date_is_rejected(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->post("http://caserne.localhost/vehicles/{$vehicle->id}/disinfections", [
            'type' => 'desinfection',
            'performed_at' => now()->addDays(5)->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('performed_at');
    }

    public function test_recording_requires_permission(): void
    {
        [$org] = $this->orgWithAdmin();
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
        $stranger = $this->tenant()->runFor($org, fn () => User::factory()->create(['organisation_id' => $org->id]));

        $this->actingAs($stranger)->post("http://caserne.localhost/vehicles/{$vehicle->id}/disinfections", [
            'type' => 'desinfection',
            'performed_at' => now()->format('Y-m-d\TH:i'),
        ])->assertForbidden();
    }

    public function test_overdue_disinfection_surfaces_on_dashboard(): void
    {
        [$org, $admin] = $this->orgWithAdmin();

        // Périodicité portée par le protocole affecté au véhicule.
        $this->tenant()->runFor($org, function () use ($org) {
            $protocol = DisinfectionProtocol::create([
                'name' => 'Désinfection hebdomadaire',
                'type' => 'desinfection',
                'frequency_days' => 7,
            ]);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'type' => 'VSAV']);
            $vehicle->disinfectionProtocols()->attach($protocol->id, ['organisation_id' => $org->id]);
            $vehicle->disinfections()->create([
                'type' => 'desinfection',
                'disinfection_protocol_id' => $protocol->id,
                'performed_at' => now()->subDays(10), // > 7 j => en retard
            ]);
        });

        $this->actingAs($admin)->get('http://caserne.localhost/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('alerts.disinfection_overdue', 1));
    }

    public function test_recording_with_the_assigned_protocol_clears_the_never_state(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        [$vehicle, $protocol] = $this->tenant()->runFor($org, function () use ($org) {
            $v = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $p = DisinfectionProtocol::create(['name' => 'Hebdo', 'type' => 'desinfection', 'frequency_days' => 7]);
            $v->disinfectionProtocols()->attach($p->id, ['organisation_id' => $org->id]);

            return [$v, $p];
        });

        // Avant enregistrement : jamais fait pour ce protocole.
        $before = $this->tenant()->runFor($org, fn () => \App\Domain\Fleet\VehicleDisinfection::statusFor($vehicle));
        $this->assertSame('never', $before->state);

        // L'agent enregistre une désinfection EN POINTANT le protocole affecté.
        $this->actingAs($admin)->post("http://caserne.localhost/vehicles/{$vehicle->id}/disinfections", [
            'type' => 'desinfection',
            'disinfection_protocol_id' => $protocol->id,
            'performed_at' => now()->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        // Après : à jour (l'échéance du protocole est calée).
        $after = $this->tenant()->runFor($org, fn () => \App\Domain\Fleet\VehicleDisinfection::statusFor($vehicle->fresh()));
        $this->assertSame('ok', $after->state);
        $this->assertNull($after->severity);
    }

    public function test_admin_can_assign_protocols_and_status_uses_them(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        [$vehicle, $weekly, $monthly] = $this->tenant()->runFor($org, function () use ($org) {
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $weekly = DisinfectionProtocol::create(['name' => 'Hebdo', 'type' => 'desinfection', 'frequency_days' => 7]);
            $monthly = DisinfectionProtocol::create(['name' => 'Mensuel', 'type' => 'bio_nettoyage', 'frequency_days' => 30]);

            return [$vehicle, $weekly, $monthly];
        });

        // Affectation des deux protocoles au véhicule.
        $this->actingAs($admin)->put("http://caserne.localhost/vehicles/{$vehicle->id}/disinfection-protocols", [
            'protocol_ids' => [$weekly->id, $monthly->id],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('disinfection_protocol_vehicle', [
            'vehicle_id' => $vehicle->id,
            'disinfection_protocol_id' => $weekly->id,
            'organisation_id' => $org->id,
        ]);

        // Hebdo en retard (10 j), mensuel à jour (2 j) → statut agrégé = en retard.
        $this->tenant()->runFor($org, function () use ($vehicle, $weekly, $monthly) {
            $vehicle->disinfections()->create(['type' => 'desinfection', 'disinfection_protocol_id' => $weekly->id, 'performed_at' => now()->subDays(10)]);
            $vehicle->disinfections()->create(['type' => 'bio_nettoyage', 'disinfection_protocol_id' => $monthly->id, 'performed_at' => now()->subDays(2)]);
        });

        $status = $this->tenant()->runFor($org, fn () => \App\Domain\Fleet\VehicleDisinfection::statusFor($vehicle->fresh()));
        $this->assertSame('overdue', $status->state);
        $this->assertSame(Severity::CRITICAL, $status->severity);
    }
}
