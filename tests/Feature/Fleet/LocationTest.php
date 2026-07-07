<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Location;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
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

    public function test_admin_can_create_a_location_on_a_vehicle(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->post('http://caserne.localhost/locations', [
            'name' => 'Coffre gauche',
            'vehicle_id' => $vehicle->id,
            'display_order' => 10,
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('locations', [
            'organisation_id' => $org->id,
            'name' => 'Coffre gauche',
            'vehicle_id' => $vehicle->id,
        ]);
    }

    public function test_cannot_attach_a_vehicle_from_another_organisation(): void
    {
        [, $admin] = $this->orgWithAdmin('caserne');
        $otherOrg = Organisation::factory()->slug('autre')->create();
        $foreignVehicle = Vehicle::factory()->create(['organisation_id' => $otherOrg->id]);

        $this->actingAs($admin)->post('http://caserne.localhost/locations', [
            'name' => 'Coffre',
            'vehicle_id' => $foreignVehicle->id,
        ])->assertSessionHasErrors('vehicle_id');
    }

    public function test_admin_can_toggle_and_delete_a_location(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $location = Location::factory()->create(['organisation_id' => $org->id, 'is_active' => true]);

        $this->actingAs($admin)->post("http://caserne.localhost/locations/{$location->id}/toggle")->assertSessionHasNoErrors();
        $this->assertFalse($location->refresh()->is_active);

        $this->actingAs($admin)->delete("http://caserne.localhost/locations/{$location->id}")->assertSessionHasNoErrors();
        $this->assertSoftDeleted('locations', ['id' => $location->id]);
    }

    public function test_location_cannot_be_its_own_parent(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $location = Location::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->patch("http://caserne.localhost/locations/{$location->id}", [
            'name' => $location->name,
            'parent_id' => $location->id,
        ])->assertSessionHasErrors('parent_id');
    }
}
