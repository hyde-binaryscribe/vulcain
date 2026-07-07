<?php

namespace Tests\Feature\Rbac;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.central_domains' => ['localhost']]);

        Route::middleware(['web', 'tenant', 'auth', 'permission:users.manage'])
            ->get('/_perm-probe', fn () => response('ok'));
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
            $user = User::factory()->create(['organisation_id' => $org->id]);
            $user->assignRole($role);

            return $user;
        });
    }

    public function test_verifier_has_only_their_permissions(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $user = $this->userWithRole($org, Rbac::VERIFIER);

        $this->tenant()->runFor($org, function () use ($user) {
            $this->assertTrue($user->can('inventories.perform'));
            $this->assertTrue($user->can('anomalies.manage'));
            $this->assertFalse($user->can('users.manage'));
            $this->assertFalse($user->can('settings.manage'));
        });
    }

    public function test_admin_has_all_permissions(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $user = $this->userWithRole($org, Rbac::ADMIN);

        $this->tenant()->runFor($org, function () use ($user) {
            foreach (Rbac::PERMISSIONS as $permission) {
                $this->assertTrue($user->can($permission), "admin devrait avoir {$permission}");
            }
        });
    }

    public function test_roles_are_scoped_per_organisation(): void
    {
        $orgA = Organisation::factory()->slug('a')->create();
        $orgB = Organisation::factory()->slug('b')->create();
        $user = $this->userWithRole($orgA, Rbac::ADMIN);

        // Dans le contexte de B, le rôle attribué dans A ne s'applique pas.
        $this->tenant()->runFor($orgB, function () use ($user) {
            app(RoleProvisioner::class)->provision(app(TenantContext::class)->organisation());
            $this->assertFalse($user->hasRole(Rbac::ADMIN));
            $this->assertFalse($user->can('users.manage'));
        });
    }

    public function test_permission_middleware_allows_authorized_user(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $user = $this->userWithRole($org, Rbac::ADMIN);

        $this->actingAs($user)
            ->get('http://caserne.localhost/_perm-probe')
            ->assertOk();
    }

    public function test_permission_middleware_blocks_unauthorized_user(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $user = $this->userWithRole($org, Rbac::VERIFIER);

        $this->actingAs($user)
            ->get('http://caserne.localhost/_perm-probe')
            ->assertForbidden();
    }
}
