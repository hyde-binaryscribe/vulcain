<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\ResolveTenant;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Accès par compte : l'organisation courante est déduite de l'utilisateur
 * connecté (plus de résolution par sous-domaine).
 */
class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Route sonde protégée par le seul middleware de résolution de tenant.
        Route::middleware(ResolveTenant::class)->get('/_tenant-probe', function () {
            return response()->json(['tenant' => app(TenantContext::class)->id()]);
        });
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->forget();
        parent::tearDown();
    }

    public function test_resolves_organisation_from_authenticated_account(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $user = User::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($user)->get('/_tenant-probe')
            ->assertOk()
            ->assertExactJson(['tenant' => $org->id]);
    }

    public function test_guest_has_no_tenant(): void
    {
        $this->get('/_tenant-probe')
            ->assertOk()
            ->assertExactJson(['tenant' => null]);
    }
}
