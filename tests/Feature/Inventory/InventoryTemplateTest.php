<?php

namespace Tests\Feature\Inventory;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\InventoryTemplate;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTemplateTest extends TestCase
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

    public function test_manager_can_create_a_template(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->post('http://caserne.localhost/templates', [
            'vehicle_id' => $vehicle->id,
            'name' => 'Inventaire hebdo',
            'frequency' => 'weekly',
        ])->assertRedirect();

        $this->assertDatabaseHas('inventory_templates', [
            'organisation_id' => $org->id,
            'vehicle_id' => $vehicle->id,
            'name' => 'Inventaire hebdo',
        ]);
    }

    public function test_verifier_cannot_manage_templates(): void
    {
        [, $verifier] = $this->orgWithRole(Rbac::VERIFIER);
        $this->actingAs($verifier)->get('http://caserne.localhost/templates')->assertForbidden();
    }

    public function test_manager_can_add_an_item_and_version_increments(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
        $template = InventoryTemplate::factory()->create(['organisation_id' => $org->id, 'vehicle_id' => $vehicle->id, 'version' => 1]);
        $material = Material::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->post("http://caserne.localhost/templates/{$template->id}/items", [
            'material_id' => $material->id,
            'expected_qty' => 4,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('inventory_template_items', [
            'inventory_template_id' => $template->id,
            'material_id' => $material->id,
            'expected_qty' => 4,
        ]);
        $this->assertSame(2, $template->fresh()->version);
    }

    public function test_cannot_add_a_material_from_another_organisation(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN, 'caserne');
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
        $template = InventoryTemplate::factory()->create(['organisation_id' => $org->id, 'vehicle_id' => $vehicle->id]);

        $otherOrg = Organisation::factory()->slug('autre')->create();
        $foreignMaterial = Material::factory()->create(['organisation_id' => $otherOrg->id]);

        $this->actingAs($admin)->post("http://caserne.localhost/templates/{$template->id}/items", [
            'material_id' => $foreignMaterial->id,
            'expected_qty' => 1,
        ])->assertSessionHasErrors('material_id');
    }

    public function test_cannot_edit_a_template_of_another_organisation(): void
    {
        [, $admin] = $this->orgWithRole(Rbac::ADMIN, 'caserne');
        $otherOrg = Organisation::factory()->slug('autre')->create();
        $foreignVehicle = Vehicle::factory()->create(['organisation_id' => $otherOrg->id]);
        $foreignTemplate = InventoryTemplate::factory()->create(['organisation_id' => $otherOrg->id, 'vehicle_id' => $foreignVehicle->id]);

        $this->actingAs($admin)->get("http://caserne.localhost/templates/{$foreignTemplate->id}/edit")->assertNotFound();
    }
}
