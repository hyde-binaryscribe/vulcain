<?php

namespace Tests\Feature\Platform;

use App\Domain\Identity\OrganisationProvisioner;
use App\Domain\Sectors\Sector;
use App\Models\Organisation;
use App\Models\PlatformAdmin;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SubscriptionTest extends TestCase
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

    public function test_provisioning_an_organisation_creates_a_subscription(): void
    {
        $org = app(OrganisationProvisioner::class)->provision(
            ['name' => 'CIS Test', 'slug' => 'cis-test', 'sector' => Sector::SDIS],
            'admin@cis-test.fr',
        );

        $this->assertDatabaseHas('subscriptions', [
            'organisation_id' => $org->id,
            'plan' => 'decouverte',
            'status' => 'trial',
        ]);
    }

    public function test_platform_admin_can_view_a_subscription_page(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $org = Organisation::factory()->slug('caserne')->create();

        $this->actingAs($admin, 'platform')
            ->get("http://localhost/platform/organisations/{$org->id}/subscription")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Platform/Organisations/Subscription')
                ->where('organisation.id', $org->id)
                ->has('usage'));
    }

    public function test_platform_admin_can_update_a_subscription(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $org = Organisation::factory()->slug('caserne')->create();

        $this->actingAs($admin, 'platform')
            ->patch("http://localhost/platform/organisations/{$org->id}/subscription", [
                'plan' => 'pro',
                'status' => 'active',
                'current_period_end' => '2027-01-01',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subscriptions', [
            'organisation_id' => $org->id,
            'plan' => 'pro',
            'status' => 'active',
        ]);
    }

    public function test_subscription_management_requires_platform_auth(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();

        $this->get("http://localhost/platform/organisations/{$org->id}/subscription")
            ->assertRedirect('/platform/login');
    }
}
