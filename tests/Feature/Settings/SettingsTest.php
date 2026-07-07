<?php

namespace Tests\Feature\Settings;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
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

    private function userWithRole(Organisation $org, string $role): User
    {
        return $this->tenant()->runFor($org, function () use ($org, $role) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole($role);

            return $u;
        });
    }

    public function test_expiry_tracking_in_mobile_defaults_to_true(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $this->assertTrue($org->tracksExpiryInMobile());
    }

    public function test_admin_can_disable_expiry_tracking_in_mobile(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $admin = $this->userWithRole($org, Rbac::ADMIN);

        $this->actingAs($admin)->patch('http://caserne.localhost/settings', [
            'track_expiry_in_mobile' => false,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($org->refresh()->tracksExpiryInMobile());
    }

    public function test_verifier_cannot_manage_settings(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $verifier = $this->userWithRole($org, Rbac::VERIFIER);

        $this->actingAs($verifier)->get('http://caserne.localhost/settings')->assertForbidden();
    }
}
