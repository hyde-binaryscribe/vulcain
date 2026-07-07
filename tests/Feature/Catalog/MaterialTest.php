<?php

namespace Tests\Feature\Catalog;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialTest extends TestCase
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

    public function test_manager_can_list_materials(): void
    {
        [, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $this->actingAs($admin)->get('http://caserne.localhost/materials')->assertOk();
    }

    public function test_verifier_cannot_manage_catalog(): void
    {
        [, $verifier] = $this->orgWithRole(Rbac::VERIFIER);
        $this->actingAs($verifier)->get('http://caserne.localhost/materials')->assertForbidden();
    }

    public function test_manager_can_create_a_material(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $category = MaterialCategory::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->post('http://caserne.localhost/materials', [
            'name' => 'Collier cervical adulte',
            'reference' => 'IMM-COL-AD',
            'category_id' => $category->id,
            'tracking_mode' => 'quantity',
            'theoretical_qty' => 4,
            'status' => 'conforme',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('materials', [
            'organisation_id' => $org->id,
            'reference' => 'IMM-COL-AD',
            'category_id' => $category->id,
        ]);
    }

    public function test_category_name_is_unique_per_organisation(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        MaterialCategory::factory()->create(['organisation_id' => $org->id, 'name' => 'Immobilisation']);

        $this->actingAs($admin)->post('http://caserne.localhost/material-categories', [
            'name' => 'Immobilisation',
        ])->assertSessionHasErrors('name');
    }

    public function test_quick_status_update(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $material = Material::factory()->create(['organisation_id' => $org->id, 'status' => 'conforme']);

        $this->actingAs($admin)->patch("http://caserne.localhost/materials/{$material->id}/status", [
            'status' => 'hs',
            'note' => 'Sangle cassée',
        ])->assertSessionHasNoErrors();

        $material->refresh();
        $this->assertSame('hs', $material->status->value);
        $this->assertSame('Sangle cassée', $material->observations);
    }

    public function test_cannot_use_a_category_from_another_organisation(): void
    {
        [, $admin] = $this->orgWithRole(Rbac::ADMIN, 'caserne');
        $otherOrg = Organisation::factory()->slug('autre')->create();
        $foreignCategory = MaterialCategory::factory()->create(['organisation_id' => $otherOrg->id]);

        $this->actingAs($admin)->post('http://caserne.localhost/materials', [
            'name' => 'Test',
            'tracking_mode' => 'quantity',
            'status' => 'conforme',
            'category_id' => $foreignCategory->id,
        ])->assertSessionHasErrors('category_id');
    }

    public function test_cannot_update_a_material_of_another_organisation(): void
    {
        [, $admin] = $this->orgWithRole(Rbac::ADMIN, 'caserne');
        $otherOrg = Organisation::factory()->slug('autre')->create();
        $foreign = Material::factory()->create(['organisation_id' => $otherOrg->id]);

        $this->actingAs($admin)->patch("http://caserne.localhost/materials/{$foreign->id}", [
            'name' => 'Piraté',
            'tracking_mode' => 'quantity',
            'status' => 'conforme',
        ])->assertNotFound();
    }
}
