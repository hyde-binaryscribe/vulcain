<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Domain\Sectors\Sector;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class VehicleTypeTest extends TestCase
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
    private function admin(Sector $sector): array
    {
        $org = Organisation::factory()->slug('caserne')->sector($sector)->create();
        $user = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::ADMIN);

            return $u;
        });

        return [$org, $user];
    }

    public function test_catalog_is_seeded_from_sector_on_first_visit(): void
    {
        [$org, $admin] = $this->admin(Sector::AMBULANCE_PRIVEE);

        $this->actingAs($admin)->get('http://caserne.localhost/vehicle-types')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('VehicleTypes/Index')
                ->where('types.0.name', 'Ambulance type A'));

        $this->assertDatabaseHas('vehicle_types', ['organisation_id' => $org->id, 'name' => 'VSL']);
    }

    public function test_admin_can_create_and_names_are_unique_per_org(): void
    {
        [, $admin] = $this->admin(Sector::SDIS);

        $this->actingAs($admin)->post('http://caserne.localhost/vehicle-types', ['name' => 'VTU spécial'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vehicle_types', ['name' => 'VTU spécial']);

        // Doublon refusé.
        $this->actingAs($admin)->post('http://caserne.localhost/vehicle-types', ['name' => 'VTU spécial'])
            ->assertSessionHasErrors('name');
    }

    public function test_renaming_a_type_repoints_vehicles(): void
    {
        [$org, $admin] = $this->admin(Sector::SDIS);

        $type = $this->tenant()->runFor($org, function () use ($org) {
            $t = VehicleType::factory()->create(['organisation_id' => $org->id, 'name' => 'VSAV']);
            Vehicle::factory()->create(['organisation_id' => $org->id, 'name' => 'VSAV 1', 'type' => 'VSAV']);

            return $t;
        });

        $this->actingAs($admin)->patch("http://caserne.localhost/vehicle-types/{$type->id}", ['name' => 'VSAV (rénové)'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vehicles', ['name' => 'VSAV 1', 'type' => 'VSAV (rénové)']);
    }

    public function test_verifier_cannot_manage_vehicle_types(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $verifier = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::VERIFIER);

            return $u;
        });

        $this->actingAs($verifier)->get('http://caserne.localhost/vehicle-types')->assertForbidden();
    }
}
