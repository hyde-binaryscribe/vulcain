<?php

namespace Tests\Feature\Terrain;

use App\Domain\Events\EventType;
use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Event;
use App\Models\KanbanBoard;
use App\Models\Location;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SealTest extends TestCase
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

    /** @return array{Organisation, User, Vehicle, Location} */
    private function scenario(): array
    {
        $org = Organisation::factory()->slug('caserne')->create();

        return $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            KanbanBoard::ensureSeeded($org);
            $manager = User::factory()->create(['organisation_id' => $org->id]);
            $manager->assignRole(Rbac::ADMIN);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'name' => 'A12']);
            $location = Location::create([
                'organisation_id' => $org->id,
                'vehicle_id' => $vehicle->id,
                'name' => 'Sac rouge',
                'kind' => 'sac',
                'is_sealable' => true,
                'is_active' => true,
            ]);

            return [$org, $manager, $vehicle, $location];
        });
    }

    public function test_crew_can_seal_a_sealable_location(): void
    {
        [$org, $manager, $vehicle, $location] = $this->scenario();

        $this->actingAs($manager)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/emplacements/{$location->id}/sceller", [
            'seal_number' => 'PLB-00123',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'seal_number' => 'PLB-00123',
            'sealed_by' => $manager->id,
        ]);
    }

    public function test_breaking_a_seal_clears_it_and_creates_an_event(): void
    {
        [$org, $manager, $vehicle, $location] = $this->scenario();
        $this->tenant()->runFor($org, fn () => $location->update(['seal_number' => 'PLB-00123', 'sealed_at' => now(), 'sealed_by' => $manager->id]));

        $this->actingAs($manager)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/emplacements/{$location->id}/rompre-scelle")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('locations', ['id' => $location->id, 'seal_number' => null]);
        $this->assertDatabaseHas('events', [
            'organisation_id' => $org->id,
            'vehicle_id' => $vehicle->id,
            'type' => EventType::ANOMALIE->value,
        ]);
    }

    public function test_agent_without_session_cannot_seal(): void
    {
        [$org, , $vehicle, $location] = $this->scenario();
        $agent = $this->tenant()->runFor($org, fn () => User::factory()->create(['organisation_id' => $org->id]));

        $this->actingAs($agent)->post("http://caserne.localhost/t/vehicules/{$vehicle->id}/emplacements/{$location->id}/sceller", [
            'seal_number' => 'X',
        ])->assertForbidden();
    }
}
