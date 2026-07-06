<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\ResolveTenant;
use App\Models\Organisation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenancy.central_domains' => ['localhost']]);

        // Route sonde protégée par le seul middleware de résolution de tenant.
        Route::middleware(ResolveTenant::class)->get('/_tenant-probe', function () {
            return response()->json(['tenant' => app(TenantContext::class)->id()]);
        });
    }

    public function test_resolves_organisation_from_subdomain(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();

        $this->get('http://caserne.localhost/_tenant-probe')
            ->assertOk()
            ->assertExactJson(['tenant' => $org->id]);
    }

    public function test_central_domain_has_no_tenant(): void
    {
        $this->get('http://localhost/_tenant-probe')
            ->assertOk()
            ->assertExactJson(['tenant' => null]);
    }

    public function test_unknown_subdomain_returns_404(): void
    {
        $this->get('http://inconnu.localhost/_tenant-probe')
            ->assertNotFound();
    }

    public function test_suspended_organisation_returns_403(): void
    {
        Organisation::factory()->slug('suspendu')->suspended()->create();

        $this->get('http://suspendu.localhost/_tenant-probe')
            ->assertForbidden();
    }
}
