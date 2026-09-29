<?php

namespace Tests\Feature\Auth;

use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\CrossTenantUserProvider;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La résolution des comptes d'authentification (rechargement depuis la session)
 * doit s'exécuter en inter-tenant : elle ne peut pas dépendre d'un tenant déjà
 * résolu, puisque c'est précisément elle qui sert à le déterminer. Une session
 * obsolète pointant vers un compte supprimé ou une page invité ferait sinon
 * lever OrganisationScope (HTTP 500 en production).
 */
class CrossTenantAuthResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(TenantContext::class)->forget();
        parent::tearDown();
    }

    private function userProvider(): UserProvider
    {
        return app('auth')->createUserProvider('users');
    }

    public function test_the_web_user_provider_resolves_across_tenants(): void
    {
        $provider = $this->userProvider();
        $this->assertInstanceOf(CrossTenantUserProvider::class, $provider);
    }

    public function test_retrieve_by_id_ignores_the_current_organisation_scope(): void
    {
        $orgA = Organisation::factory()->slug('a')->create();
        $orgB = Organisation::factory()->slug('b')->create();

        $tenant = app(TenantContext::class);
        $userB = $tenant->runFor($orgB, fn () => User::factory()->create(['organisation_id' => $orgB->id]));

        // Tenant courant = organisation A ; on recharge un compte de l'organisation
        // B (autre tenant), comme le ferait une session obsolète.
        $tenant->set($orgA);

        $resolved = $this->userProvider()->retrieveById($userB->id);

        $this->assertNotNull($resolved, 'Le compte doit être résolu malgré un tenant courant différent.');
        $this->assertTrue($userB->is($resolved));
    }

    public function test_retrieve_by_id_of_a_soft_deleted_account_returns_null_without_error(): void
    {
        $org = Organisation::factory()->slug('cis')->create();
        $tenant = app(TenantContext::class);

        $deleted = $tenant->runFor($org, function () use ($org) {
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->delete();

            return $u;
        });

        // Aucun tenant courant (page invité) : la résolution ne doit pas échouer
        // et le compte supprimé reste introuvable (soft delete respecté).
        $tenant->forget();

        $this->assertNull($this->userProvider()->retrieveById($deleted->id));
    }
}
