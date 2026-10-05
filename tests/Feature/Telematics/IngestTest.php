<?php

namespace Tests\Feature\Telematics;

use App\Domain\Events\EventType;
use App\Models\Organisation;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.central_domains' => ['localhost']]);
        config(['security.telematics.token' => 'secret-token']);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->forget();
        parent::tearDown();
    }

    private function vehicleWithImei(string $imei): array
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $vehicle = app(TenantContext::class)->runFor($org, fn () => Vehicle::factory()->create([
            'organisation_id' => $org->id,
            'name' => 'A12',
            'telematics_imei' => $imei,
        ]));

        return [$org, $vehicle];
    }

    private function payload(string $imei, array $attributes = []): array
    {
        return [
            'device' => ['uniqueId' => $imei, 'name' => 'A12'],
            'position' => [
                'latitude' => 45.7578,
                'longitude' => 4.8320,
                'speed' => 10,            // nœuds
                'course' => 90,
                'deviceTime' => '2026-10-05T08:00:00Z',
                'attributes' => $attributes,
            ],
        ];
    }

    public function test_bad_token_is_not_found(): void
    {
        $this->vehicleWithImei('862272081619456');

        $this->postJson('http://caserne.localhost/ingest/traccar/wrong', $this->payload('862272081619456'))
            ->assertNotFound();
    }

    public function test_position_is_stored_for_a_known_device(): void
    {
        [$org, $vehicle] = $this->vehicleWithImei('862272081619456');

        $this->postJson('http://caserne.localhost/ingest/traccar/secret-token', $this->payload('862272081619456'))
            ->assertOk()->assertSee('stored');

        $this->assertDatabaseHas('vehicle_positions', [
            'organisation_id' => $org->id,
            'vehicle_id' => $vehicle->id,
            'speed' => 19, // 10 nœuds ≈ 19 km/h
        ]);
    }

    public function test_unknown_device_is_acknowledged_without_storing(): void
    {
        $this->vehicleWithImei('862272081619456');

        $this->postJson('http://caserne.localhost/ingest/traccar/secret-token', $this->payload('000000000000000'))
            ->assertOk()->assertSee('unknown-device');

        $this->assertDatabaseCount('vehicle_positions', 0);
    }

    public function test_fault_codes_create_an_event(): void
    {
        [$org, $vehicle] = $this->vehicleWithImei('862272081619456');

        $this->postJson('http://caserne.localhost/ingest/traccar/secret-token',
            $this->payload('862272081619456', ['faultCount' => 1, 'dtcs' => 'P0128']))
            ->assertOk();

        $this->assertDatabaseHas('events', [
            'organisation_id' => $org->id,
            'vehicle_id' => $vehicle->id,
            'type' => EventType::ANOMALIE->value,
            'source_key' => 'telematics-dtc:'.$vehicle->id,
        ]);
    }
}
