<?php

namespace Tests\Feature\Platform;

use App\Domain\Identity\InvitationService;
use App\Domain\Identity\OrganisationProvisioner;
use App\Domain\Sectors\Sector;
use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Notifications\OrganisationInvitationNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProvisioningAndInvitationTest extends TestCase
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

    public function test_platform_admin_can_provision_an_organisation(): void
    {
        Notification::fake();
        $this->actingAs(PlatformAdmin::factory()->create(), 'platform');

        $this->post('http://localhost/platform/organisations', [
            'name' => 'CIS Test',
            'slug' => 'cis-test',
            'sector' => Sector::SDIS->value,
            'admin_email' => 'chef@cis.test',
        ])->assertStatus(302)->assertSessionHasNoErrors();

        $org = Organisation::where('slug', 'cis-test')->first();
        $this->assertNotNull($org);
        $this->assertSame(Sector::SDIS, $org->sector);

        // Rôles provisionnés pour l'organisation.
        $this->tenant()->runFor($org, function () {
            $this->assertTrue(Role::where('name', 'administrateur')->exists());
        });

        // Invitation créée + envoyée.
        $this->assertDatabaseHas('invitations', [
            'organisation_id' => $org->id,
            'email' => 'chef@cis.test',
            'role' => 'administrateur',
        ]);
        Notification::assertSentOnDemand(OrganisationInvitationNotification::class);
    }

    public function test_provisioning_rejects_duplicate_slug(): void
    {
        Organisation::factory()->slug('cis-test')->create();
        $this->actingAs(PlatformAdmin::factory()->create(), 'platform');

        $this->post('http://localhost/platform/organisations', [
            'name' => 'Autre',
            'slug' => 'cis-test',
            'sector' => Sector::SDIS->value,
            'admin_email' => 'x@cis.test',
        ])->assertSessionHasErrors('slug');
    }

    /** @return array{Organisation, string} */
    private function provision(string $slug = 'cis'): array
    {
        Notification::fake();
        $org = app(OrganisationProvisioner::class)->provision(
            ['name' => 'CIS', 'slug' => $slug, 'sector' => Sector::SDIS],
            'chef@cis.test',
        );

        $token = '';
        Notification::assertSentOnDemand(
            OrganisationInvitationNotification::class,
            function ($notification) use (&$token) {
                $token = basename(parse_url($notification->acceptUrl, PHP_URL_PATH));

                return true;
            }
        );

        return [$org, $token];
    }

    public function test_first_admin_accepts_invitation_and_becomes_administrator(): void
    {
        [$org, $token] = $this->provision();

        $this->post('http://cis.localhost/accept-invitation', [
            'token' => $token,
            'email' => 'chef@cis.test',
            'first_name' => 'Marie',
            'last_name' => 'Chef',
            'password' => 'motdepasse12',
            'password_confirmation' => 'motdepasse12',
        ])->assertRedirect('/dashboard');

        $this->tenant()->runFor($org, function () {
            $user = User::where('email', 'chef@cis.test')->first();
            $this->assertNotNull($user);
            $this->assertTrue($user->hasRole('administrateur'));
        });

        $this->assertNotNull(Invitation::where('email', 'chef@cis.test')->first()->accepted_at);
        $this->assertAuthenticated();
    }

    public function test_invitation_is_single_use(): void
    {
        [$org, $token] = $this->provision();
        $service = app(InvitationService::class);

        $data = ['first_name' => 'Marie', 'last_name' => 'Chef', 'password' => 'motdepasse12'];

        $first = $service->accept($org, 'chef@cis.test', $token, $data);
        $this->assertNotNull($first);

        // Deuxième utilisation du même jeton : refusée.
        $second = $service->accept($org, 'chef@cis.test', $token, $data);
        $this->assertNull($second);
    }

    public function test_expired_invitation_is_refused(): void
    {
        [$org, $token] = $this->provision();
        Invitation::where('organisation_id', $org->id)->update(['expires_at' => now()->subDay()]);

        $this->post('http://cis.localhost/accept-invitation', [
            'token' => $token,
            'email' => 'chef@cis.test',
            'first_name' => 'Marie',
            'last_name' => 'Chef',
            'password' => 'motdepasse12',
            'password_confirmation' => 'motdepasse12',
        ])->assertSessionHasErrors('email');
    }

    public function test_invitation_is_scoped_to_its_organisation(): void
    {
        [, $token] = $this->provision('cis');
        Organisation::factory()->slug('autre')->create();

        // Tenter d'accepter sur une autre organisation.
        $this->post('http://autre.localhost/accept-invitation', [
            'token' => $token,
            'email' => 'chef@cis.test',
            'first_name' => 'Marie',
            'last_name' => 'Chef',
            'password' => 'motdepasse12',
            'password_confirmation' => 'motdepasse12',
        ])->assertSessionHasErrors('email');
    }
}
