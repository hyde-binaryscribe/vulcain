<?php

namespace Tests\Feature\Catalog;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\MaterialType;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialTypeTest extends TestCase
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

    public function test_admin_can_create_a_material_type(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);

        $this->actingAs($admin)->post('http://caserne.localhost/material-types', [
            'name' => 'Thermomètre',
            'tracking_mode' => 'serial',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('material_types', [
            'organisation_id' => $org->id, 'name' => 'Thermomètre', 'tracking_mode' => 'serial',
        ]);
    }

    public function test_type_imposes_its_tracking_mode_on_a_model(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $type = $this->tenant()->runFor($org, fn () => MaterialType::factory()->create([
            'organisation_id' => $org->id, 'name' => 'Compresse 5×5', 'tracking_mode' => 'lot',
        ]));

        // On envoie volontairement un mode « serial » : le type doit primer.
        $this->actingAs($admin)->post('http://caserne.localhost/materials', [
            'name' => 'Compresse stérile',
            'brand' => 'Hartmann',
            'material_type_id' => $type->id,
            'tracking_mode' => 'serial',
            'status' => 'conforme',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('materials', [
            'name' => 'Compresse stérile', 'brand' => 'Hartmann',
            'material_type_id' => $type->id, 'tracking_mode' => 'lot',
        ]);
    }

    public function test_verifier_cannot_manage_material_types(): void
    {
        [, $verifier] = $this->orgWithRole(Rbac::VERIFIER);
        $this->actingAs($verifier)->get('http://caserne.localhost/material-types')->assertForbidden();
    }

    public function test_type_name_is_unique_per_org(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $this->tenant()->runFor($org, fn () => MaterialType::factory()->create([
            'organisation_id' => $org->id, 'name' => 'Scope',
        ]));

        $this->actingAs($admin)->post('http://caserne.localhost/material-types', [
            'name' => 'Scope',
            'tracking_mode' => 'serial',
        ])->assertSessionHasErrors('name');
    }
}
