<?php

namespace Tests\Feature\Tenancy;

use App\Models\PlatformAdmin;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Séparation stricte par hôte :
 * - le client n'accède qu'à l'application (app.vulkain.eu) ;
 * - le Desk (management) n'est servi que sur desk.vulkain.eu.
 * Tout accès croisé renvoie 404.
 */
class HostSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'tenancy.central_domains' => ['vulkain.eu', 'www.vulkain.eu', 'desk.vulkain.eu', 'app.vulkain.eu'],
            'tenancy.vitrine_domains' => ['vulkain.eu', 'www.vulkain.eu'],
            'tenancy.app_domains' => ['app.vulkain.eu'],
            'tenancy.desk_domains' => ['desk.vulkain.eu'],
        ]);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->forget();
        parent::tearDown();
    }

    public function test_client_login_is_served_on_app_host(): void
    {
        $this->get('http://app.vulkain.eu/login')->assertOk();
    }

    public function test_client_login_is_absent_from_desk_host(): void
    {
        $this->get('http://desk.vulkain.eu/login')->assertNotFound();
    }

    public function test_desk_is_served_on_desk_host(): void
    {
        // Guest plateforme : la page de connexion Desk est servie sur desk.
        $this->get('http://desk.vulkain.eu/platform/login')->assertOk();
    }

    public function test_desk_is_absent_from_app_host(): void
    {
        // Un client sur l'hôte app ne peut pas atteindre le Desk.
        $this->get('http://app.vulkain.eu/platform/login')->assertNotFound();
        $this->get('http://app.vulkain.eu/platform')->assertNotFound();
    }

    public function test_platform_admin_cannot_reach_desk_from_app_host(): void
    {
        $admin = PlatformAdmin::factory()->create();

        $this->actingAs($admin, 'platform')
            ->get('http://app.vulkain.eu/platform')
            ->assertNotFound();
    }

    public function test_client_dashboard_is_absent_from_desk_host(): void
    {
        $this->get('http://desk.vulkain.eu/dashboard')->assertNotFound();
    }
}
