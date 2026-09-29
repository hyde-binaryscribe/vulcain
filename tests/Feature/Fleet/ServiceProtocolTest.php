<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\ServiceProtocol;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceProtocolTest extends TestCase
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

    public function test_admin_can_configure_a_protocol_with_fields_and_alert(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::ADMIN);

            return $u;
        });

        $this->actingAs($admin)->post('http://caserne.localhost/protocoles-service', [
            'vehicle_type' => null,
            'phase' => 'ouverture',
            'fields' => [
                ['label' => 'Pression O₂', 'type' => 'gauge', 'required' => true, 'config' => ['min' => 0, 'max' => 200, 'unit' => 'bar'],
                    'alert' => ['enabled' => true, 'operator' => 'lt', 'threshold' => 50, 'severity' => 'warning', 'message' => 'O₂ bas']],
                ['label' => 'Propreté cellule', 'type' => 'tristate', 'required' => false, 'config' => [],
                    'alert' => ['enabled' => true, 'operator' => 'is_nok', 'severity' => 'critical', 'message' => '']],
                ['label' => '', 'type' => 'text', 'config' => [], 'alert' => ['enabled' => false]], // vide → ignoré
            ],
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () {
            $protocol = ServiceProtocol::query()->where('phase', 'ouverture')->whereNull('vehicle_type')->firstOrFail();
            $this->assertCount(2, $protocol->fields); // la ligne vide est ignorée
            $gauge = $protocol->fields->firstWhere('label', 'Pression O₂');
            $this->assertSame('gauge', $gauge->type->value);
            $this->assertTrue($gauge->alert['enabled']);
            $this->assertSame(50, $gauge->alert['threshold']);
        });
    }

    public function test_opening_with_a_nok_answer_fires_an_alert_event(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle, $fieldId] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

            // Protocole par défaut (tous types) avec un contrôle NOK → alerte critique.
            $protocol = ServiceProtocol::create(['vehicle_type' => null, 'phase' => 'ouverture', 'is_active' => true]);
            $field = $protocol->fields()->create([
                'label' => 'DAE présent', 'type' => 'tristate', 'required' => false, 'config' => [],
                'alert' => ['enabled' => true, 'operator' => 'is_nok', 'severity' => 'critical', 'message' => 'DAE manquant'],
                'display_order' => 0,
            ]);

            return [$agent, $vehicle, $field->id];
        });

        $this->actingAs($agent)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/prise-de-service", [
            'responses' => [(string) $fieldId => 'nok'],
        ])->assertRedirect("http://caserne.localhost/t/vehicules/{$vehicle->id}");

        $this->tenant()->runFor($org, function () use ($vehicle) {
            // L'anomalie a été créée avec la priorité haute (sévérité critique).
            $this->assertDatabaseHas('events', [
                'type' => 'anomalie',
                'vehicle_id' => $vehicle->id,
                'priority' => 'haute',
            ]);
            $event = Event::query()->where('vehicle_id', $vehicle->id)->firstOrFail();
            $this->assertStringContainsString('DAE présent', $event->title);

            // La réponse est bien stockée sur la session.
            $session = VehicleSession::query()->where('vehicle_id', $vehicle->id)->firstOrFail();
            $this->assertNotNull($session->open_responses);
        });
    }
}
