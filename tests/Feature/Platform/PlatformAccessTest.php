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

    public function test_platform_routes_are_not_available_on_a_tenant_subdomain(): void
    {
        Organisation::factory()->slug('caserne')->create();

        $this->get('http://caserne.localhost/platform/login')->assertNotFound();
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
}
