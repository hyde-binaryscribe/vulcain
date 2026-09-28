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
