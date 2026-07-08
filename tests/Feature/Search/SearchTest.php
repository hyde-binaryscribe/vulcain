<?php

namespace Tests\Feature\Search;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SearchTest extends TestCase
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
    private function orgWithRole(string $role): array
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $user = $this->tenant()->runFor($org, function () use ($org, $role) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole($role);

            return $u;
        });

        return [$org, $user];
    }

    public function test_admin_finds_a_material(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $this->tenant()->runFor($org, fn () => Material::factory()->create(['organisation_id' => $org->id, 'name' => 'Collier cervical']));

        $this->actingAs($admin)->get('http://caserne.localhost/search?q=Collier')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Search/Index')
                ->where('q', 'Collier')
                ->where('groups.0.label', 'Matériel')
                ->where('groups.0.results.0.label', 'Collier cervical'));
    }

    public function test_suggest_returns_json_grouped_by_type(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $this->tenant()->runFor($org, fn () => Material::factory()->create(['organisation_id' => $org->id, 'name' => 'Collier cervical']));

        $this->actingAs($admin)->getJson('http://caserne.localhost/search/suggest?q=Collier')
            ->assertOk()
            ->assertJsonPath('groups.0.label', 'Matériel')
            ->assertJsonPath('groups.0.results.0.label', 'Collier cervical');
    }

    public function test_verifier_does_not_see_material_category(): void
    {
        [$org, $verifier] = $this->orgWithRole(Rbac::VERIFIER);
        $this->tenant()->runFor($org, fn () => Material::factory()->create(['organisation_id' => $org->id, 'name' => 'Collier cervical']));

        // Le vérificateur n'a pas catalog.manage : aucun résultat matériel.
        $this->actingAs($verifier)->get('http://caserne.localhost/search?q=Collier')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Search/Index')->where('groups', []));
    }
}
