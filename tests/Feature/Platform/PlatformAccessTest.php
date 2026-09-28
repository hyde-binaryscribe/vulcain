<?php

namespace Tests\Feature\Platform;

use App\Models\Organisation;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAccessTest extends TestCase
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

    public function test_platform_admin_can_login_on_central_domain(): void
    {
        $admin = PlatformAdmin::factory()->create([
            'email' => 'op@vulcain.test',
            'password' => 'MotDePasse12A',
        ]);

        $this->post('http://localhost/platform/login', [
            'email' => 'op@vulcain.test',
            'password' => 'MotDePasse12A',
        ])->assertStatus(302);

        $this->assertAuthenticatedAs($admin, 'platform');
    }

    public function test_platform_dashboard_requires_platform_authentication(): void
    {
        $this->get('http://localhost/platform')->assertRedirect('/platform/login');
    }

    public function test_tenant_user_cannot_access_platform(): void
    {
        // Un utilisateur métier (guard web) n'est pas authentifié sur le guard plateforme.
        $org = Organisation::factory()->slug('caserne')->create();
        $user = User::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($user) // guard web
            ->get('http://localhost/platform')
            ->assertRedirect('/platform/login');
    }

    public function test_desk_reste_accessible_avec_une_session_web_coexistante(): void
    {
        // Reproduit le cas hôte unique : l'opérateur est connecté au Desk (guard
        // platform) ET une session métier (guard web) coexiste dans le navigateur
        // — typiquement après « Se connecter en tant qu'admin » (cookie partagé
        // sur .vulkain.eu). Le Desk ne doit pas tomber en 404.
        $org = Organisation::factory()->slug('caserne')->create();
        $webUser = app(TenantContext::class)->runFor($org, function () use ($org) {
            app(\App\Domain\Identity\RoleProvisioner::class)->provision($org);

            return User::factory()->create(['organisation_id' => $org->id]);
        });
        $operator = PlatformAdmin::factory()->create();

        $this->actingAs($operator, 'platform');
        $this->actingAs($webUser, 'web'); // session web qui « fuite » sur le Desk

        $this->get("http://localhost/platform/organisations/{$org->id}")->assertOk();
        $this->get("http://localhost/platform/organisations/{$org->id}/subscription")->assertOk();
    }
}
