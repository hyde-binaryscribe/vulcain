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

    public function test_admin_can_delete_an_agent_and_reinvite_the_email(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $agent = $this->tenant()->runFor($org, function () use ($org) {
            $u = User::factory()->create(['organisation_id' => $org->id, 'email' => 'inola@example.com']);
            $u->assignRole(Rbac::VERIFIER);

            return $u;
        });

        $this->actingAs($admin)->delete("http://caserne.localhost/users/{$agent->id}")
            ->assertSessionHasNoErrors();

        // Archivé (soft delete) et e-mail libéré.
        $this->assertSoftDeleted('users', ['id' => $agent->id]);
        $this->assertDatabaseMissing('users', ['email' => 'inola@example.com', 'deleted_at' => null]);

        // La même adresse peut être ré-invitée.
        Notification::fake();
        $this->actingAs($admin)->post('http://caserne.localhost/users/invite', [
            'email' => 'inola@example.com',
            'role' => Rbac::VERIFIER,
        ])->assertSessionHasNoErrors();
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        [, $admin] = $this->orgWithAdmin();

        $this->actingAs($admin)->delete("http://caserne.localhost/users/{$admin->id}")
            ->assertSessionHasErrors('user');
    }

    public function test_an_admin_can_delete_another_admin_when_others_remain(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $other = $this->tenant()->runFor($org, function () use ($org) {
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::ADMIN);

            return $u;
        });

        // $admin supprime le second admin ($other) : autorisé car $admin reste.
        $this->actingAs($admin)->delete("http://caserne.localhost/users/{$other->id}")
            ->assertSessionHasNoErrors();
        $this->assertSoftDeleted('users', ['id' => $other->id]);
    }

    public function test_admin_can_send_a_password_reset_link_to_an_active_user(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $member = $this->tenant()->runFor($org, function () use ($org) {
            $u = User::factory()->create(['organisation_id' => $org->id, 'email' => 'membre@cis.test']);
            $u->assignRole(Rbac::VERIFIER);

            return $u;
        });

        Notification::fake();

        $this->actingAs($admin)
            ->post("http://caserne.localhost/users/{$member->id}/reset-password")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('resetLink');

        $this->assertDatabaseHas('password_reset_tokens', [
            'organisation_id' => $org->id,
            'email' => 'membre@cis.test',
        ]);
        Notification::assertSentTo($member, \App\Notifications\ResetPasswordNotification::class);
    }

    public function test_admin_cannot_reset_password_of_an_inactive_user(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $member = $this->tenant()->runFor($org, function () use ($org) {
            $u = User::factory()->inactive()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::VERIFIER);

            return $u;
        });

        $this->actingAs($admin)
            ->post("http://caserne.localhost/users/{$member->id}/reset-password")
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('password_reset_tokens', ['organisation_id' => $org->id]);
    }

    public function test_admin_cannot_reset_password_of_a_user_in_another_organisation(): void
    {
        [, $admin] = $this->orgWithAdmin('caserne');
        $otherOrg = Organisation::factory()->slug('autre')->create();
        $foreign = User::factory()->create(['organisation_id' => $otherOrg->id]);

        $this->actingAs($admin)
            ->post("http://caserne.localhost/users/{$foreign->id}/reset-password")
            ->assertNotFound();
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
