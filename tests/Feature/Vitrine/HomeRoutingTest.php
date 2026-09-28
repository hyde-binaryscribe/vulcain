<?php

namespace Tests\Feature\Vitrine;

use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aiguillage de la racine « / » par hôte, indépendamment de la session « web »
 * (partagée sur *.vulkain.eu). L'app et le Desk doivent rester distincts.
 */
class HomeRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'tenancy.central_domains' => ['vulkain.eu', 'www.vulkain.eu', 'desk.vulkain.eu', 'app.vulkain.eu'],
            'tenancy.vitrine_domains' => ['vulkain.eu', 'www.vulkain.eu'],
            'tenancy.app_domains' => ['app.vulkain.eu'],
        ]);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->forget();
        parent::tearDown();
    }

    public function test_vitrine_host_shows_vitrine(): void
    {
        $this->get('http://vulkain.eu/')->assertOk();
    }

    public function test_app_host_redirects_guest_to_login(): void
    {
        $this->get('http://app.vulkain.eu/')->assertRedirect('/login');
    }

    public function test_desk_host_goes_to_desk_even_with_a_web_session(): void
    {
        // Session « web » coexistante (cookie partagé) : le Desk ne doit PAS
        // renvoyer vers le dashboard de l'application.
        $org = Organisation::factory()->slug('caserne')->create();
        $user = User::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($user, 'web')
            ->get('http://desk.vulkain.eu/')
            ->assertRedirect('/platform'); // Desk, et surtout PAS /dashboard
    }

    public function test_app_host_redirects_authenticated_user_to_dashboard(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $user = User::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($user, 'web')
            ->get('http://app.vulkain.eu/')
            ->assertRedirect('/dashboard');
    }
}
