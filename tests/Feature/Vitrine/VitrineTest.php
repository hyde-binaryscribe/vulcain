<?php

namespace Tests\Feature\Vitrine;

use App\Models\Organisation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class VitrineTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(TenantContext::class)->forget();
        parent::tearDown();
    }

    public function test_vitrine_renders_on_a_vitrine_host_with_plans(): void
    {
        config([
            'tenancy.central_domains' => ['vulkain.eu', 'app.vulkain.eu', 'desk.vulkain.eu'],
            'tenancy.vitrine_domains' => ['vulkain.eu'],
        ]);

        $this->get('http://vulkain.eu/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Vitrine/Home')
                ->has('plans'));
    }

    public function test_vitrine_is_previewable_at_accueil(): void
    {
        $this->get('http://localhost/accueil')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Vitrine/Home'));
    }

    public function test_tenant_subdomain_resolves_under_app_host(): void
    {
        config(['tenancy.central_domains' => ['vulkain.eu', 'app.vulkain.eu', 'desk.vulkain.eu']]);

        $org = Organisation::factory()->slug('caserne')->create();

        // caserne.app.vulkain.eu doit résoudre l'org « caserne » (et non « caserne.app »).
        $this->get('http://caserne.app.vulkain.eu/')
            ->assertRedirect(); // redirige vers le login tenant

        $this->assertSame('caserne', $org->slug);
    }
}
