<?php

namespace Tests\Feature\IntraChatel;

use App\Domain\Sectors\Sector;
use App\Models\ApiToken;
use App\Models\Organisation;
use App\Models\OxygenBottle;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API v1 : prises de poste et fins de service envoyées par intra-Chatel. */
class ServicesApiTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $org;

    private Vehicle $vehicle;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.central_domains' => ['localhost']]);

        $this->org = Organisation::factory()->slug('ambu')->sector(Sector::AMBULANCE_PRIVEE)->create();
        [$this->vehicle, $plain] = $this->tenant()->runFor($this->org, fn () => [
            Vehicle::factory()->create(['organisation_id' => $this->org->id, 'name' => 'Ambulance 9', 'callsign' => 'A9', 'mileage' => 118000]),
            ApiToken::issue('intra-Chatel', null)[1],
        ]);
        $this->headers = ['Authorization' => "Bearer {$plain}"];
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

    /** Prise de poste telle que remplie dans intra-Chatel. */
    private function openPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'external_id' => 'pdp-1001',
            'vehicle_callsign' => 'a9',
            'opened_by' => 'AJ',
            'partner' => 'MJM',
            'opened_at' => now()->subMinutes(5)->toIso8601String(),
            'mileage' => 118084,
            'fuel_percent' => 100,
            'oxygen' => [
                // Curseur en bar (plage 0–200 par défaut) ou en litres (ramenés en bar par la capacité en eau).
                ['position' => 1, 'serial_number' => 'f337f1l', 'pressure_bar' => 160, 'sealed' => true],
                ['position' => 2, 'serial_number' => 'F35YA5H', 'volume_l' => 200, 'capacity_l' => 5, 'sealed' => false],
            ],
            'fluids' => ['hot_engine' => false, 'coolant_percent' => 60, 'oil_percent' => 50, 'oil_added' => false, 'tyre_pressure_done' => true],
            'bag' => ['name' => 'Sac 2', 'seal_number' => 'c2164894'],
            'comments' => ['Rayure portière arrière', ' '],
        ], $overrides);
    }

    public function test_a_prise_de_poste_opens_a_service_with_its_checks(): void
    {
        $aj = $this->tenant()->runFor($this->org, fn () => User::factory()->create(['organisation_id' => $this->org->id, 'name' => 'Alice Jourdan', 'username' => 'aj']));

        $response = $this->postJson('/api/v1/services/open', $this->openPayload(), $this->headers)
            ->assertCreated()
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('handover', null)
            ->assertJsonPath('data.vehicle.uuid', $this->vehicle->uuid)
            ->assertJsonPath('data.source', 'intra_chatel')
            ->assertJsonPath('data.external_id', 'pdp-1001')
            ->assertJsonPath('data.open', true)
            // Initiales reliées au compte Vulkain dont l'identifiant est « aj » ; MJM n'a pas de compte.
            ->assertJsonPath('data.opened_by', 'Alice Jourdan')
            ->assertJsonPath('data.partner', 'MJM')
            ->assertJsonPath('data.open_mileage', 118084)
            ->assertJsonPath('data.open_notes', 'Rayure portière arrière');

        $checks = collect($response->json('data.open_checks'))->keyBy('ref');
        $this->assertSame(100, $checks['fuel_percent']['value']);
        $this->assertSame('F337F1L', $checks['oxygen.1.serial_number']['value']);
        $this->assertTrue($checks['oxygen.1.sealed']['value']);
        $this->assertSame([160, 'bar', 80], [$checks['oxygen.1.pressure_bar']['value'], $checks['oxygen.1.pressure_bar']['unit'], $checks['oxygen.1.pressure_bar']['percent']]);
        $this->assertNull($checks['oxygen.1.pressure_bar']['alert']);
        // 200 L dans une bouteille de 5 L = 40 bar = 20 % : alerte.
        $this->assertSame([200, 'L', 20], [$checks['oxygen.2.volume_l']['value'], $checks['oxygen.2.volume_l']['unit'], $checks['oxygen.2.volume_l']['percent']]);
        $this->assertSame('warning', $checks['oxygen.2.volume_l']['alert']['severity']);
        $this->assertArrayNotHasKey('oxygen.2.pressure_bar', $checks->all());
        $this->assertTrue($checks['fluids.tyre_pressure_done']['value']);
        $this->assertSame('C2164894', $checks['bag.seal_number']['value']);

        $session = $this->tenant()->runFor($this->org, fn () => VehicleSession::query()->sole());
        $this->assertSame($aj->id, $session->user_id);
        $this->assertNull($session->partner_user_id);
        $this->assertSame(118084, $this->vehicle->fresh()->mileage);
        $this->assertSame('prise_de_service', $this->vehicle->fresh()->mileage_source);
    }

    public function test_a_resent_form_does_not_create_a_second_service(): void
    {
        $this->postJson('/api/v1/services/open', $this->openPayload(), $this->headers)->assertCreated();
        $this->postJson('/api/v1/services/open', $this->openPayload(), $this->headers)
            ->assertOk()
            ->assertJsonPath('replayed', true)
            ->assertJsonPath('data.external_id', 'pdp-1001');

        $this->assertSame(1, $this->tenant()->runFor($this->org, fn () => VehicleSession::query()->count()));
    }

    public function test_opening_closes_the_service_left_open_handover(): void
    {
        $this->postJson('/api/v1/services/open', $this->openPayload(['opened_by' => 'HT', 'partner' => null]), $this->headers)->assertCreated();

        $this->postJson('/api/v1/services/open', $this->openPayload(['external_id' => 'pdp-1002', 'opened_at' => now()->toIso8601String()]), $this->headers)
            ->assertCreated()
            ->assertJsonPath('handover.opened_by', 'HT');

        $sessions = $this->tenant()->runFor($this->org, fn () => VehicleSession::query()->orderBy('id')->get());
        $this->assertSame(VehicleSession::REASON_HANDOVER, $sessions[0]->close_reason);
        $this->assertSame('AJ', $sessions[0]->closed_by_name);
        $this->assertNull($sessions[1]->closed_at);
    }

    public function test_a_fin_de_service_closes_the_open_service(): void
    {
        $this->postJson('/api/v1/services/close', [
            'external_id' => 'fds-1', 'vehicle_uuid' => $this->vehicle->uuid, 'closed_by' => 'AJ',
        ], $this->headers)->assertStatus(409)->assertJsonPath('code', 'no_open_service');

        $this->postJson('/api/v1/services/open', $this->openPayload(), $this->headers)->assertCreated();

        $close = [
            'external_id' => 'fds-1',
            'vehicle_uuid' => $this->vehicle->uuid,
            'closed_by' => 'AJ',
            'mileage' => 118190,
            'oxygen' => [
                ['serial_number' => 'F337F1L', 'pressure_bar' => 80, 'sealed' => false],
                ['serial_number' => 'F35YA5H', 'pressure_bar' => 20, 'sealed' => false],
            ],
            'comments' => ['RAS'],
        ];
        $response = $this->postJson('/api/v1/services/close', $close, $this->headers)
            ->assertOk()
            ->assertJsonPath('data.open', false)
            ->assertJsonPath('data.closed_by', 'AJ')
            ->assertJsonPath('data.close_reason', 'manual')
            ->assertJsonPath('data.close_mileage', 118190)
            ->assertJsonPath('data.close_notes', 'RAS')
            ->assertJsonPath('data.close_external_id', 'fds-1');
        $this->assertSame('oxygen.2.pressure_bar', $response->json('data.close_checks.4.ref'));
        $this->assertSame(10, $response->json('data.close_checks.4.percent'));
        $this->assertSame(118190, $this->vehicle->fresh()->mileage);

        // Renvoi de la même fin de service : rien ne change.
        $this->postJson('/api/v1/services/close', $close, $this->headers)->assertOk()->assertJsonPath('replayed', true);
    }

    public function test_current_service_gives_the_crew_mileage_and_last_bottles(): void
    {
        // Sans service : bouteilles de la dotation Vulkain.
        $this->tenant()->runFor($this->org, fn () => OxygenBottle::create([
            'vehicle_id' => $this->vehicle->id, 'label' => 'B15', 'serial_number' => 'DOT-1', 'is_active' => true,
        ]));
        $this->getJson('/api/v1/services/current?vehicle_callsign=A9', $this->headers)
            ->assertOk()
            ->assertJsonPath('data.open_service', null)
            ->assertJsonPath('data.vehicle.mileage', 118000)
            ->assertJsonPath('data.last_oxygen.0.serial_number', 'DOT-1')
            ->assertJsonPath('data.last_oxygen.0.source', 'dotation');

        $this->postJson('/api/v1/services/open', $this->openPayload(), $this->headers)->assertCreated();

        $this->getJson("/api/v1/services/current?vehicle_uuid={$this->vehicle->uuid}", $this->headers)
            ->assertOk()
            ->assertJsonPath('data.open_service.opened_by', 'AJ')
            ->assertJsonPath('data.open_service.partner', 'MJM')
            ->assertJsonPath('data.vehicle.mileage', 118084)
            ->assertJsonPath('data.last_oxygen.0.pressure_bar', 160)
            ->assertJsonPath('data.last_oxygen.1.serial_number', 'F35YA5H')
            ->assertJsonPath('data.last_oxygen.1.volume_l', 200)
            ->assertJsonPath('data.last_oxygen.1.percent', 20)
            ->assertJsonPath('data.last_oxygen.1.source', 'service');
    }

    public function test_services_since_a_date_include_terrain_sessions(): void
    {
        $this->tenant()->runFor($this->org, function () {
            $user = User::factory()->create(['organisation_id' => $this->org->id, 'name' => 'Julien D.']);
            VehicleSession::create(['vehicle_id' => $this->vehicle->id, 'user_id' => $user->id, 'opened_at' => now()]);
        });

        $this->getJson('/api/v1/services?since='.urlencode(now()->subHour()->toIso8601String()), $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.source', 'vulkain')
            ->assertJsonPath('data.0.opened_by', 'Julien D.');
    }

    public function test_the_itineris_checks_show_the_intra_chatel_service(): void
    {
        $this->postJson('/api/v1/services/open', $this->openPayload(), $this->headers)->assertCreated();

        $this->getJson('/api/v1/vehicles/checks', $this->headers)
            ->assertOk()
            ->assertJsonPath('data.0.service.opened_by', 'AJ')
            ->assertJsonPath('data.0.service.partner', 'MJM')
            ->assertJsonPath('data.0.o2.value', '160')
            ->assertJsonPath('data.0.o2.unit', 'bar')
            ->assertJsonPath('data.0.o2.percent', 80)
            ->assertJsonPath('data.0.o2.label', 'Bouteille O2 1 — pression');
    }

    public function test_bottle_level_uses_the_bottle_range_and_capacity(): void
    {
        // Bouteille Vulkain 0–300 bar ; capacité publiée par intra-Chatel (référence) : 15 L.
        $this->tenant()->runFor($this->org, fn () => OxygenBottle::create([
            'vehicle_id' => $this->vehicle->id, 'label' => 'B15', 'serial_number' => 'B15-01', 'capacity_l' => 5, 'pressure_max' => 300, 'is_active' => true,
        ]));
        $this->putJson('/api/v1/intra-chatel/oxygen-bottles', ['data' => [['id' => 7, 'serial_number' => 'b15-01', 'capacity_l' => 15]]], $this->headers)->assertOk();

        $response = $this->postJson('/api/v1/services/open', ['oxygen' => [
            ['position' => 1, 'serial_number' => 'B15-01', 'volume_l' => 2250],   // 150 bar sur 300 = 50 %
            ['position' => 2, 'serial_number' => 'INCONNUE', 'volume_l' => 400],  // capacité inconnue
            ['position' => 3, 'serial_number' => 'B15-01', 'pressure_bar' => 60], // 20 % de 300 bar
        ]] + $this->openPayload(), $this->headers)->assertCreated();

        $checks = collect($response->json('data.open_checks'))->keyBy('ref');
        $this->assertSame(50, $checks['oxygen.1.volume_l']['percent']);
        $this->assertNull($checks['oxygen.2.volume_l']['percent']);
        $this->assertNull($checks['oxygen.2.volume_l']['alert']);
        $this->assertSame(20, $checks['oxygen.3.pressure_bar']['percent']);
        $this->assertSame('warning', $checks['oxygen.3.pressure_bar']['alert']['severity']);
    }

    public function test_errors(): void
    {
        $this->postJson('/api/v1/services/open', $this->openPayload())->assertUnauthorized();

        $this->postJson('/api/v1/services/open', ['vehicle_callsign' => 'A9', 'fuel_percent' => 150], $this->headers)
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['external_id', 'opened_by', 'fuel_percent']]);

        $this->postJson('/api/v1/services/open', $this->openPayload(['vehicle_callsign' => 'Z99']), $this->headers)
            ->assertNotFound()->assertJsonPath('code', 'vehicle_not_found');

        $this->tenant()->runFor($this->org, fn () => Vehicle::factory()->create(['organisation_id' => $this->org->id, 'callsign' => 'A9']));
        $this->postJson('/api/v1/services/open', $this->openPayload(), $this->headers)
            ->assertStatus(422)->assertJsonPath('code', 'ambiguous_vehicle');

        // Un véhicule d'une autre organisation n'existe pas pour ce jeton.
        $other = Organisation::factory()->slug('autre')->sector(Sector::AMBULANCE_PRIVEE)->create();
        $foreign = $this->tenant()->runFor($other, fn () => Vehicle::factory()->create(['organisation_id' => $other->id]));
        $this->postJson('/api/v1/services/open', $this->openPayload(['vehicle_callsign' => null, 'vehicle_uuid' => $foreign->uuid]), $this->headers)
            ->assertNotFound();
    }
}
