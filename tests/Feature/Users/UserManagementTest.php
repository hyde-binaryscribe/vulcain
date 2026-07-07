<?php

namespace Tests\Feature\Users;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\OrganisationInvitationNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
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
    private function orgWithAdmin(string $slug = 'caserne'): array
    {
        $org = Organisation::factory()->slug($slug)->create();

        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $user = User::factory()->create(['organisation_id' => $org->id]);
            $user->assignRole(Rbac::ADMIN);

            return $user;
        });

        return [$org, $admin];
    }

    public function test_admin_can_view_users_list(): void
    {
        [, $admin] = $this->orgWithAdmin();

        $this->actingAs($admin)->get('http://caserne.localhost/users')->assertOk();
    }

    public function test_non_admin_cannot_access_users(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $verifier = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $user = User::factory()->create(['organisation_id' => $org->id]);
            $user->assignRole(Rbac::VERIFIER);

            return $user;
        });

        $this->actingAs($verifier)->get('http://caserne.localhost/users')->assertForbidden();
    }

    public function test_admin_can_invite_a_user(): void
    {
        Notification::fake();
        [$org, $admin] = $this->orgWithAdmin();

        $this->actingAs($admin)
            ->post('http://caserne.localhost/users/invite', [
                'email' => 'nouveau@cis.test',
                'role' => Rbac::VERIFIER,
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invitations', [
            'organisation_id' => $org->id,
            'email' => 'nouveau@cis.test',
            'role' => Rbac::VERIFIER,
        ]);
        Notification::assertSentOnDemand(OrganisationInvitationNotification::class);
    }

    public function test_admin_can_update_a_user_role_and_status(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $member = User::factory()->create(['organisation_id' => $org->id]);
        $this->tenant()->runFor($org, fn () => $member->assignRole(Rbac::VERIFIER));

        $this->actingAs($admin)
            ->patch("http://caserne.localhost/users/{$member->id}", [
                'grade' => 'Sergent',
                'role' => Rbac::PHARMACY,
                'is_active' => false,
            ])->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertSame('Sergent', $member->grade);
        $this->assertFalse($member->is_active);
        $this->tenant()->runFor($org, fn () => $this->assertTrue($member->hasRole(Rbac::PHARMACY)));
    }

    public function test_admin_cannot_remove_their_own_admin_access(): void
    {
        [, $admin] = $this->orgWithAdmin();

        $this->actingAs($admin)
            ->patch("http://caserne.localhost/users/{$admin->id}", [
                'role' => Rbac::VERIFIER,
                'is_active' => true,
            ])->assertSessionHasErrors('role');
    }

    public function test_admin_cannot_update_a_user_of_another_organisation(): void
    {
        [, $admin] = $this->orgWithAdmin('caserne');
        $otherOrg = Organisation::factory()->slug('autre')->create();
        $foreign = User::factory()->create(['organisation_id' => $otherOrg->id]);

        // Le binding applique le scope tenant -> utilisateur invisible -> 404.
        $this->actingAs($admin)
            ->patch("http://caserne.localhost/users/{$foreign->id}", [
                'role' => Rbac::VERIFIER,
                'is_active' => true,
            ])->assertNotFound();
    }
}
