<?php

namespace Tests\Feature\Site;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SiteTest extends TestCase
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
    private function orgWithRole(string $role): array
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $user = $this->tenant()->runFor($org, function () use ($org, $role) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole($role);

            return $u;
        });

        return [$org, $user];
    }

    public function test_admin_can_create_a_site(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);

        $this->actingAs($admin)->post('http://caserne.localhost/sites', [
            'name' => 'CIS Nord',
            'kind' => 'centre',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sites', ['organisation_id' => $org->id, 'name' => 'CIS Nord', 'kind' => 'centre']);
    }

    public function test_verifier_cannot_manage_sites(): void
    {
        [, $verifier] = $this->orgWithRole(Rbac::VERIFIER);
        $this->actingAs($verifier)->get('http://caserne.localhost/sites')->assertForbidden();
    }

    public function test_vehicle_can_be_attached_to_a_site(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $site = Site::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->post('http://caserne.localhost/vehicles', [
            'name' => 'VSAV 02',
            'site_id' => $site->id,
            'status' => 'disponible',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('vehicles', ['organisation_id' => $org->id, 'name' => 'VSAV 02', 'site_id' => $site->id]);
    }

    public function test_user_restricted_to_a_site_only_sees_its_vehicles(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);

        [$siteA, $vehicleA] = $this->tenant()->runFor($org, function () use ($org, $admin) {
            $siteA = Site::factory()->create(['organisation_id' => $org->id]);
            $siteB = Site::factory()->create(['organisation_id' => $org->id]);
            $vehicleA = Vehicle::factory()->create(['organisation_id' => $org->id, 'name' => 'VSAV A', 'site_id' => $siteA->id]);
            Vehicle::factory()->create(['organisation_id' => $org->id, 'name' => 'VSAV B', 'site_id' => $siteB->id]);
            $admin->sites()->attach($siteA->id); // admin restreint au site A

            return [$siteA, $vehicleA];
        });

        $this->actingAs($admin)->get('http://caserne.localhost/vehicles')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Vehicles/Index')
                ->has('vehicles', 1)
                ->where('vehicles.0.name', 'VSAV A'));
    }

    public function test_plan_limit_blocks_creating_a_site_over_quota(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        // Plan Découverte : 1 site maximum.
        $org->subscription()->create(['plan' => 'decouverte', 'status' => 'trial']);
        Site::factory()->create(['organisation_id' => $org->id]); // quota atteint

        $this->actingAs($admin)->post('http://caserne.localhost/sites', [
            'name' => 'Site en trop',
            'kind' => 'centre',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('sites', ['name' => 'Site en trop']);
    }

    public function test_cannot_attach_a_site_from_another_organisation(): void
    {
        [, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $otherOrg = Organisation::factory()->slug('autre')->create();
        $foreignSite = Site::factory()->create(['organisation_id' => $otherOrg->id]);

        $this->actingAs($admin)->post('http://caserne.localhost/vehicles', [
            'name' => 'VSAV 03',
            'site_id' => $foreignSite->id,
            'status' => 'disponible',
        ])->assertSessionHasErrors('site_id');
    }
}
