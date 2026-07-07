<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Location;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTest extends TestCase
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
    private function orgWithRole(string $role, string $slug = 'caserne'): array
    {
        $org = Organisation::factory()->slug($slug)->create();
        $user = $this->tenant()->runFor($org, function () use ($org, $role) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole($role);

            return $u;
        });

        return [$org, $user];
    }

    public function test_manager_can_list_vehicles(): void
    {
        [, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $this->actingAs($admin)->get('http://caserne.localhost/vehicles')->assertOk();
    }

    public function test_verifier_cannot_manage_vehicles(): void
    {
        [, $verifier] = $this->orgWithRole(Rbac::VERIFIER);
        $this->actingAs($verifier)->get('http://caserne.localhost/vehicles')->assertForbidden();
    }

    public function test_vehicle_detail_hub_renders_with_materials_by_location(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
        $location = Location::factory()->create(['organisation_id' => $org->id, 'vehicle_id' => $vehicle->id]);
        Material::factory()->create(['organisation_id' => $org->id, 'location_id' => $location->id]);

        $this->actingAs($admin)
            ->get("http://caserne.localhost/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Vehicles/Show', false)->has('locations', 1));
    }

    public function test_manager_can_create_a_vehicle(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);

        $this->actingAs($admin)->post('http://caserne.localhost/vehicles', [
            'name' => 'VSAV 01',
            'type' => 'VSAV',
            'status' => 'disponible',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vehicles', [
            'organisation_id' => $org->id,
            'name' => 'VSAV 01',
        ]);
    }

    public function test_manager_can_update_and_delete_a_vehicle(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->patch("http://caserne.localhost/vehicles/{$vehicle->id}", [
            'name' => 'VSAV renommé',
            'status' => 'maintenance',
        ])->assertSessionHasNoErrors();
        $this->assertSame('VSAV renommé', $vehicle->refresh()->name);

        $this->actingAs($admin)->delete("http://caserne.localhost/vehicles/{$vehicle->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }

    public function test_assignments_only_accept_users_of_the_organisation(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
        $member = User::factory()->create(['organisation_id' => $org->id]);

        $otherOrg = Organisation::factory()->slug('autre')->create();
        $foreign = User::factory()->create(['organisation_id' => $otherOrg->id]);

        $this->actingAs($admin)->put("http://caserne.localhost/vehicles/{$vehicle->id}/assignments", [
            'user_ids' => [$member->id, $foreign->id],
        ])->assertSessionHasNoErrors();

        // Seul l'utilisateur de l'organisation est affecté ; l'étranger est ignoré.
        $this->assertDatabaseHas('vehicle_user', ['vehicle_id' => $vehicle->id, 'user_id' => $member->id]);
        $this->assertDatabaseMissing('vehicle_user', ['vehicle_id' => $vehicle->id, 'user_id' => $foreign->id]);
    }

    public function test_cannot_update_a_vehicle_of_another_organisation(): void
    {
        [, $admin] = $this->orgWithRole(Rbac::ADMIN, 'caserne');
        $otherOrg = Organisation::factory()->slug('autre')->create();
        $foreignVehicle = Vehicle::factory()->create(['organisation_id' => $otherOrg->id]);

        $this->actingAs($admin)->patch("http://caserne.localhost/vehicles/{$foreignVehicle->id}", [
            'name' => 'Piraté',
            'status' => 'disponible',
        ])->assertNotFound();
    }
}
