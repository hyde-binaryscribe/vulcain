<?php

namespace Tests\Feature\Platform;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Group;
use App\Models\Organisation;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DeskToolsTest extends TestCase
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
    private function orgWithAdmin(string $slug = 'caserne'): array
    {
        $org = Organisation::factory()->slug($slug)->create();
        $user = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::ADMIN);

            return $u;
        });

        return [$org, $user];
    }

    public function test_operator_can_view_organisation_detail(): void
    {
        [$org, $user] = $this->orgWithAdmin();
        $operator = PlatformAdmin::factory()->create();

        $this->actingAs($operator, 'platform')->get("http://localhost/platform/organisations/{$org->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Platform/Organisations/Show')
                ->where('org.slug', 'caserne')
                ->where('admin.email', $user->email)
                ->has('counts'));
    }

    public function test_operator_can_impersonate_the_org_admin(): void
    {
        [$org, $user] = $this->orgWithAdmin();
        $operator = PlatformAdmin::factory()->create();

        $this->actingAs($operator, 'platform')
            ->post("http://localhost/platform/organisations/{$org->id}/impersonate")
            ->assertRedirect();

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_operator_can_reset_the_admin_password(): void
    {
        [$org] = $this->orgWithAdmin();
        $operator = PlatformAdmin::factory()->create();

        $this->actingAs($operator, 'platform')
            ->post("http://localhost/platform/organisations/{$org->id}/reset-admin")
            ->assertSessionHas('status');
    }

    public function test_group_manager_cannot_view_an_org_outside_its_group(): void
    {
        [$org] = $this->orgWithAdmin('autre');
        $group = Group::create(['name' => 'Groupe A']);
        $manager = PlatformAdmin::factory()->create(['group_id' => $group->id]);

        $this->actingAs($manager, 'platform')
            ->get("http://localhost/platform/organisations/{$org->id}")
            ->assertForbidden();
    }
}
