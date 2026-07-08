<?php

namespace Tests\Feature\Platform;

use App\Models\Group;
use App\Models\Organisation;
use App\Models\PlatformAdmin;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GroupTest extends TestCase
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

    public function test_global_operator_can_create_a_group_and_attach_an_organisation(): void
    {
        $admin = PlatformAdmin::factory()->create(); // group_id null = exploitant global
        $org = Organisation::factory()->slug('caserne')->create();

        $this->actingAs($admin, 'platform')->post('http://localhost/platform/groups', ['name' => 'Groupe Alpha'])
            ->assertSessionHasNoErrors();
        $group = Group::query()->firstOrFail();

        $this->actingAs($admin, 'platform')->patch("http://localhost/platform/organisations/{$org->id}/group", [
            'group_id' => $group->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('organisations', ['id' => $org->id, 'group_id' => $group->id]);
    }

    public function test_group_manager_only_sees_their_group_organisations(): void
    {
        $group = Group::create(['name' => 'Groupe A']);
        Organisation::factory()->slug('a')->create(['group_id' => $group->id]);
        Organisation::factory()->slug('b')->create(); // hors groupe
        $manager = PlatformAdmin::factory()->create(['group_id' => $group->id]);

        $this->actingAs($manager, 'platform')->get('http://localhost/platform')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Platform/Dashboard')
                ->has('organisations', 1)
                ->where('organisations.0.slug', 'a'));
    }

    public function test_group_manager_cannot_manage_groups(): void
    {
        $group = Group::create(['name' => 'Groupe A']);
        $manager = PlatformAdmin::factory()->create(['group_id' => $group->id]);

        $this->actingAs($manager, 'platform')->get('http://localhost/platform/groups')->assertForbidden();
    }

    public function test_group_manager_cannot_touch_an_organisation_outside_their_group(): void
    {
        $group = Group::create(['name' => 'Groupe A']);
        $manager = PlatformAdmin::factory()->create(['group_id' => $group->id]);
        $foreign = Organisation::factory()->slug('foreign')->create(); // hors groupe

        // Abonnement d'une organisation hors périmètre : interdit.
        $this->actingAs($manager, 'platform')
            ->get("http://localhost/platform/organisations/{$foreign->id}/subscription")
            ->assertForbidden();

        // Suspension d'une organisation hors périmètre : interdit.
        $this->actingAs($manager, 'platform')
            ->post("http://localhost/platform/organisations/{$foreign->id}/toggle")
            ->assertForbidden();

        // Création d'organisation : réservé à l'exploitant global.
        $this->actingAs($manager, 'platform')
            ->get('http://localhost/platform/organisations/create')
            ->assertForbidden();
    }

    public function test_group_manager_can_manage_an_organisation_in_their_group(): void
    {
        $group = Group::create(['name' => 'Groupe A']);
        $manager = PlatformAdmin::factory()->create(['group_id' => $group->id]);
        $own = Organisation::factory()->slug('own')->create(['group_id' => $group->id]);

        $this->actingAs($manager, 'platform')
            ->get("http://localhost/platform/organisations/{$own->id}/subscription")
            ->assertOk();
    }

    public function test_global_operator_can_create_a_group_manager(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $group = Group::create(['name' => 'Groupe A']);

        $this->actingAs($admin, 'platform')->post("http://localhost/platform/groups/{$group->id}/managers", [
            'name' => 'Gestionnaire',
            'email' => 'manager@groupe.fr',
            'password' => 'MotDePasse12',
            'password_confirmation' => 'MotDePasse12',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('platform_admins', ['email' => 'manager@groupe.fr', 'group_id' => $group->id]);
    }
}
