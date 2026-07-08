<?php

namespace Tests\Feature\Export;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
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

    public function test_admin_can_export_materials_csv(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $admin = $this->userWithRole($org, Rbac::ADMIN);
        $this->tenant()->runFor($org, fn () => Material::factory()->create(['organisation_id' => $org->id, 'name' => 'Collier cervical']));

        $response = $this->actingAs($admin)->get('http://caserne.localhost/exports/materiel.csv');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('Collier cervical', $response->streamedContent());
    }

    public function test_verifier_cannot_export(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $verifier = $this->userWithRole($org, Rbac::VERIFIER);

        $this->actingAs($verifier)->get('http://caserne.localhost/exports/materiel.csv')->assertForbidden();
    }
}
