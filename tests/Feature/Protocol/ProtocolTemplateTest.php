<?php

namespace Tests\Feature\Protocol;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\ProtocolTemplate;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtocolTemplateTest extends TestCase
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

    public function test_manager_can_create_a_vehicle_scoped_template(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->post('http://caserne.localhost/templates', [
            'name' => 'Protocole hebdo',
            'types' => ['inventaire'],
            'scope_type' => 'vehicle',
            'scope_id' => $vehicle->id,
            'include_children' => true,
            'frequency' => 'weekly',
        ])->assertRedirect();

        $this->assertDatabaseHas('protocol_templates', [
            'organisation_id' => $org->id,
            'vehicle_id' => $vehicle->id,
            'scope_type' => 'vehicle',
            'scope_id' => $vehicle->id,
            'name' => 'Protocole hebdo',
        ]);
    }

    public function test_verifier_cannot_manage_templates(): void
    {
        [, $verifier] = $this->orgWithRole(Rbac::VERIFIER);
        $this->actingAs($verifier)->get('http://caserne.localhost/templates')->assertForbidden();
    }

    public function test_manager_can_exclude_a_material_from_the_scope(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
        $material = Material::factory()->create(['organisation_id' => $org->id]);
        $template = ProtocolTemplate::factory()->forVehicle($vehicle)->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->patch("http://caserne.localhost/templates/{$template->id}/exclusions", [
            'excluded_material_ids' => [$material->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$material->id], $template->fresh()->excluded_material_ids);
    }

    public function test_cannot_edit_a_template_of_another_organisation(): void
    {
        [, $admin] = $this->orgWithRole(Rbac::ADMIN, 'caserne');
        $otherOrg = Organisation::factory()->slug('autre')->create();
        $foreignVehicle = Vehicle::factory()->create(['organisation_id' => $otherOrg->id]);
        $foreignTemplate = ProtocolTemplate::factory()->forVehicle($foreignVehicle)->create(['organisation_id' => $otherOrg->id]);

        $this->actingAs($admin)->get("http://caserne.localhost/templates/{$foreignTemplate->id}/edit")->assertNotFound();
    }
}
