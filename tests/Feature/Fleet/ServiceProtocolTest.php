<?php

namespace Tests\Feature\Fleet;

use App\Domain\Fleet\ProtocolPhase;
use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\ServiceProtocol;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
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

    public function test_resolution_follows_vehicle_model_type_default_cascade(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $this->tenant()->runFor($org, function () use ($org) {
            $model = VehicleModel::create(['name' => 'Master L2H2', 'is_active' => true]);
            $vehicle = Vehicle::factory()->create([
                'organisation_id' => $org->id, 'type' => 'VSL', 'vehicle_model_id' => $model->id,
            ]);

            $make = function (array $scope, string $label) {
                $p = ServiceProtocol::create($scope + ['phase' => 'ouverture', 'is_active' => true]);
                $p->fields()->create(['label' => $label, 'type' => 'tristate', 'config' => [], 'alert' => ['enabled' => false], 'display_order' => 0]);

                return $p;
            };

            // Défaut uniquement.
            $make(['vehicle_type' => null, 'vehicle_model_id' => null, 'vehicle_id' => null], 'DEFAUT');
            $this->assertSame('DEFAUT', ServiceProtocol::resolveFor($vehicle, ProtocolPhase::OUVERTURE)->fields->first()->label);

            // Le type l'emporte sur le défaut.
            $make(['vehicle_type' => 'VSL', 'vehicle_model_id' => null, 'vehicle_id' => null], 'TYPE');
            $this->assertSame('TYPE', ServiceProtocol::resolveFor($vehicle, ProtocolPhase::OUVERTURE)->fields->first()->label);

            // Le modèle l'emporte sur le type.
            $make(['vehicle_type' => null, 'vehicle_model_id' => $model->id, 'vehicle_id' => null], 'MODELE');
            $this->assertSame('MODELE', ServiceProtocol::resolveFor($vehicle, ProtocolPhase::OUVERTURE)->fields->first()->label);

            // Le véhicule précis l'emporte sur tout.
            $make(['vehicle_type' => null, 'vehicle_model_id' => null, 'vehicle_id' => $vehicle->id], 'VEHICULE');
            $this->assertSame('VEHICULE', ServiceProtocol::resolveFor($vehicle, ProtocolPhase::OUVERTURE)->fields->first()->label);
        });
    }

    public function test_opening_stores_gauge_percent_uppercases_text_and_skips_section(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

            $protocol = ServiceProtocol::create(['vehicle_type' => null, 'phase' => 'ouverture', 'is_active' => true]);
            $protocol->fields()->create(['label' => 'Oxygène', 'type' => 'section', 'config' => [], 'alert' => ['enabled' => false], 'display_order' => 0]);
            $protocol->fields()->create(['label' => 'Niveau O₂', 'type' => 'gauge', 'config' => ['min' => 0, 'max' => 200, 'unit' => 'bar'], 'alert' => ['enabled' => false], 'display_order' => 1]);
            $protocol->fields()->create(['label' => 'N° série', 'type' => 'text', 'config' => ['uppercase' => true], 'alert' => ['enabled' => false], 'display_order' => 2]);

            return [$agent, $vehicle];
        });

        $fields = $this->tenant()->runFor($org, fn () => ServiceProtocol::query()->where('phase', 'ouverture')->with('fields')->firstOrFail()->fields);
        $gauge = $fields->firstWhere('label', 'Niveau O₂');
        $serial = $fields->firstWhere('label', 'N° série');

        $this->actingAs($agent)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/prise-de-service", [
            'responses' => [(string) $gauge->id => 150, (string) $serial->id => 'ab12cd'],
        ])->assertRedirect("http://caserne.localhost/t/vehicules/{$vehicle->id}");

        $this->tenant()->runFor($org, function () use ($vehicle) {
            $session = VehicleSession::query()->where('vehicle_id', $vehicle->id)->firstOrFail();
            $responses = collect($session->open_responses);

            // La section n'apparaît pas dans les réponses.
            $this->assertNull($responses->firstWhere('type', 'section'));

            // La jauge stocke le pourcentage (150 sur 0–200 = 75 %).
            $gaugeRow = $responses->firstWhere('label', 'Niveau O₂');
            $this->assertSame(150, $gaugeRow['value']);
            $this->assertSame(75, $gaugeRow['percent']);

            // Le numéro de série est stocké en majuscules.
            $serialRow = $responses->firstWhere('label', 'N° série');
            $this->assertSame('AB12CD', $serialRow['value']);
        });
    }

    public function test_gauge_alert_can_trigger_on_percentage(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle, $gaugeId] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

            $protocol = ServiceProtocol::create(['vehicle_type' => null, 'phase' => 'ouverture', 'is_active' => true]);
            $gauge = $protocol->fields()->create([
                'label' => 'Niveau O₂', 'type' => 'gauge', 'config' => ['min' => 0, 'max' => 200, 'unit' => 'bar'],
                'alert' => ['enabled' => true, 'operator' => 'lt', 'threshold' => 25, 'basis' => 'percent', 'severity' => 'warning', 'message' => 'O₂ sous 25 %'],
                'display_order' => 0,
            ]);

            return [$agent, $vehicle, $gauge->id];
        });

        // 40 sur 0–200 = 20 % < 25 % → alerte déclenchée.
        $this->actingAs($agent)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/prise-de-service", [
            'responses' => [(string) $gaugeId => 40],
        ])->assertRedirect("http://caserne.localhost/t/vehicules/{$vehicle->id}");

        $this->tenant()->runFor($org, function () use ($vehicle) {
            $this->assertDatabaseHas('events', ['type' => 'anomalie', 'vehicle_id' => $vehicle->id]);
        });
    }
}
