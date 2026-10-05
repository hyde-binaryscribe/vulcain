<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminPanelTest extends TestCase
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

    /** @return array{Organisation, User, User} */
    private function orgWithUsers(): array
    {
        $org = Organisation::factory()->slug('caserne')->create();

        return $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);
            $agent = User::factory()->create(['organisation_id' => $org->id]);

            return [$org, $admin, $agent];
        });
    }

    public function test_admin_sees_the_panel_with_counts(): void
    {
        [, $admin] = $this->orgWithUsers();

        $this->actingAs($admin)->get('http://caserne.localhost/administration')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Index')
                ->has('counts.vehicle_types')
                ->has('counts.users'));
    }

    public function test_agent_without_admin_permission_is_forbidden(): void
    {
        [, , $agent] = $this->orgWithUsers();

        $this->actingAs($agent)->get('http://caserne.localhost/administration')->assertForbidden();
    }
}
