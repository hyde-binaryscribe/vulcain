<?php

namespace Tests\Feature\Pharmacy;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PharmacyTest extends TestCase
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

    private function userWithRole(Organisation $org, string $role): User
    {
        return $this->tenant()->runFor($org, function () use ($org, $role) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole($role);

            return $u;
        });
    }

    public function test_pharmacist_can_declare_a_consumable(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $pharmacist = $this->userWithRole($org, Rbac::PHARMACY);

        $this->actingAs($pharmacist)->post('http://caserne.localhost/pharmacy/consumables', [
            'name' => 'Sérum physiologique 500 ml',
            'reference' => 'PHA-SERUM-500',
            'minimum_qty' => 5,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('materials', [
            'organisation_id' => $org->id,
            'name' => 'Sérum physiologique 500 ml',
            'tracking_mode' => Material::MODE_LOT,
        ]);
    }

    public function test_verifier_cannot_access_pharmacy(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $verifier = $this->userWithRole($org, Rbac::VERIFIER);

        $this->actingAs($verifier)->get('http://caserne.localhost/pharmacy')->assertForbidden();
    }
}
