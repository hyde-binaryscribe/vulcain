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

    public function test_operator_can_regenerate_the_admin_password(): void
    {
        [$org] = $this->orgWithAdmin();
        $operator = PlatformAdmin::factory()->create();

        $this->actingAs($operator, 'platform')
            ->post("http://localhost/platform/organisations/{$org->id}/credentials")
            ->assertSessionHas('credentials');
    }

    public function test_operator_can_generate_credentials_from_a_pending_invitation(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        // Organisation provisionnée : invitation d'admin en attente, aucun compte.
        $org = app(\App\Domain\Identity\OrganisationProvisioner::class)->provision(
            ['name' => 'CIS', 'slug' => 'cis', 'sector' => \App\Domain\Sectors\Sector::SDIS],
            'chef@cis.test',
        );
        $operator = PlatformAdmin::factory()->create();

        $this->actingAs($operator, 'platform')
            ->post("http://localhost/platform/organisations/{$org->id}/credentials")
            ->assertSessionHas('credentials', fn ($c) => $c['email'] === 'chef@cis.test' && ! empty($c['password']));

        // Le compte administrateur a bien été créé et l'invitation consommée.
        $this->tenant()->runFor($org, function () {
            $user = User::where('email', 'chef@cis.test')->first();
            $this->assertNotNull($user);
            $this->assertTrue($user->hasRole(Rbac::ADMIN));
            $this->assertNotNull(\App\Models\Invitation::where('email', 'chef@cis.test')->first()->accepted_at);
        });
    }

    public function test_generating_credentials_without_admin_or_invitation_fails(): void
    {
        $org = Organisation::factory()->slug('vide')->create();
        $this->tenant()->runFor($org, fn () => app(RoleProvisioner::class)->provision($org));
        $operator = PlatformAdmin::factory()->create();

        $this->actingAs($operator, 'platform')
            ->post("http://localhost/platform/organisations/{$org->id}/credentials")
            ->assertSessionHas('error');
    }

    public function test_operator_sees_users_across_organisations(): void
    {
        [, $userA] = $this->orgWithAdmin('a');
        [, $userB] = $this->orgWithAdmin('b');
        $operator = PlatformAdmin::factory()->create();

        $this->actingAs($operator, 'platform')->get('http://localhost/platform/users')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Platform/Users')
                ->has('users', 2));
    }

    public function test_group_manager_only_sees_users_of_their_group(): void
    {
        $group = Group::create(['name' => 'Groupe A']);
        $orgIn = Organisation::factory()->slug('in')->create(['group_id' => $group->id]);
        $this->tenant()->runFor($orgIn, fn () => User::factory()->create(['organisation_id' => $orgIn->id]));
        $this->orgWithAdmin('out'); // hors groupe
        $manager = PlatformAdmin::factory()->create(['group_id' => $group->id]);

        $this->actingAs($manager, 'platform')->get('http://localhost/platform/users')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Platform/Users')
                ->has('users', 1));
    }

    public function test_activity_page_renders(): void
    {
        $operator = PlatformAdmin::factory()->create();
        $this->actingAs($operator, 'platform')->get('http://localhost/platform/activity')
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Platform/Activity'));
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
