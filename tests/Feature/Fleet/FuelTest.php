<?php

namespace Tests\Feature\Fleet;

use App\Domain\Fleet\FuelConsumption;
use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\FuelRecord;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelTest extends TestCase
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

    public function test_recording_a_fill_stores_it_and_updates_mileage(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER); // agent de terrain, sans vehicles.manage
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'mileage' => 900]);

            return [$agent, $vehicle];
        });

        $this->actingAs($agent)->post("http://caserne.localhost/vehicles/{$vehicle->id}/fuel", [
            'mileage' => 1000,
            'liters' => 45.5,
            'cost' => 78.90,
            'full_tank' => true,
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () use ($vehicle, $agent) {
            $this->assertDatabaseHas('fuel_records', [
                'vehicle_id' => $vehicle->id,
                'user_id' => $agent->id,
                'mileage' => 1000,
            ]);
            // Le relevé met à jour le compteur.
            $this->assertSame(1000, (int) $vehicle->fresh()->mileage);
        });
    }

    public function test_consumption_is_computed_between_full_fills(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $summary = $this->tenant()->runFor($org, function () use ($org) {
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            // Plein de référence à 1000 km, puis 40 L à 1500 km → 40/500*100 = 8 L/100.
            FuelRecord::create(['vehicle_id' => $vehicle->id, 'filled_at' => now()->subDays(10), 'mileage' => 1000, 'liters' => 50, 'full_tank' => true]);
            FuelRecord::create(['vehicle_id' => $vehicle->id, 'filled_at' => now(), 'mileage' => 1500, 'liters' => 40, 'full_tank' => true]);

            return FuelConsumption::summary($vehicle->fuelRecords()->get());
        });

        $this->assertSame(8.0, $summary['average']);
        $this->assertSame(8.0, $summary['last']);
        $this->assertSame(2, $summary['count']);
        $this->assertSame(90.0, $summary['total_liters']);
    }
}
