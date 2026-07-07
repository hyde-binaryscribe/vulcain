<?php

namespace Tests\Feature\Auth;

use App\Models\Organisation;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
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

    private function seedOrgUser(string $slug = 'caserne'): array
    {
        $org = Organisation::factory()->slug($slug)->create();
        $user = User::factory()->create([
            'organisation_id' => $org->id,
            'email' => 'agent@cis.test',
            'password' => 'ancienpass1',
        ]);

        return [$org, $user];
    }

    private function requestLinkAndCaptureToken(string $slug, string $email): string
    {
        Notification::fake();

        $this->post("http://{$slug}.localhost/forgot-password", ['email' => $email])
            ->assertSessionHas('status');

        $token = '';
        Notification::assertSentTo(
            User::withoutOrganisationScope()->where('email', $email)->first(),
            ResetPasswordNotification::class,
            function ($notification) use (&$token) {
                // /reset-password/{token}?email=...
                $path = parse_url($notification->resetUrl, PHP_URL_PATH);
                $token = basename($path);

                return true;
            }
        );

        return $token;
    }

    public function test_reset_token_is_stored_hashed(): void
    {
        [$org] = $this->seedOrgUser();
        $token = $this->requestLinkAndCaptureToken('caserne', 'agent@cis.test');

        $row = DB::table('password_reset_tokens')
            ->where('organisation_id', $org->id)
            ->where('email', 'agent@cis.test')
            ->first();

        $this->assertNotNull($row);
        $this->assertNotEquals($token, $row->token); // stocké haché
        $this->assertSame(hash('sha256', $token), $row->token);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $this->seedOrgUser();
        $token = $this->requestLinkAndCaptureToken('caserne', 'agent@cis.test');

        $this->post('http://caserne.localhost/reset-password', [
            'email' => 'agent@cis.test',
            'token' => $token,
            'password' => 'nouveaupass1',
            'password_confirmation' => 'nouveaupass1',
        ])->assertRedirect('/login');

        // Jeton consommé (usage unique).
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'agent@cis.test']);

        // Connexion avec le nouveau mot de passe.
        $this->post('http://caserne.localhost/login', [
            'email' => 'agent@cis.test',
            'password' => 'nouveaupass1',
        ])->assertStatus(302);
        $this->assertAuthenticated();
    }

    public function test_reset_fails_with_wrong_token(): void
    {
        $this->seedOrgUser();
        $this->requestLinkAndCaptureToken('caserne', 'agent@cis.test');

        $this->post('http://caserne.localhost/reset-password', [
            'email' => 'agent@cis.test',
            'token' => 'jeton-invalide',
            'password' => 'nouveaupass1',
            'password_confirmation' => 'nouveaupass1',
        ])->assertSessionHasErrors('email');
    }

    public function test_reset_fails_with_expired_token(): void
    {
        [$org] = $this->seedOrgUser();
        $token = $this->requestLinkAndCaptureToken('caserne', 'agent@cis.test');

        // Vieillir le jeton au-delà de l'expiration.
        DB::table('password_reset_tokens')
            ->where('organisation_id', $org->id)
            ->where('email', 'agent@cis.test')
            ->update(['created_at' => now()->subMinutes(config('security.password_reset.expires_minutes') + 5)]);

        $this->post('http://caserne.localhost/reset-password', [
            'email' => 'agent@cis.test',
            'token' => $token,
            'password' => 'nouveaupass1',
            'password_confirmation' => 'nouveaupass1',
        ])->assertSessionHasErrors('email');
    }

    public function test_reset_token_is_scoped_to_its_organisation(): void
    {
        $this->seedOrgUser('caserne');
        Organisation::factory()->slug('autre')->create();
        $token = $this->requestLinkAndCaptureToken('caserne', 'agent@cis.test');

        // Tenter la réinitialisation sur une autre organisation.
        $this->post('http://autre.localhost/reset-password', [
            'email' => 'agent@cis.test',
            'token' => $token,
            'password' => 'nouveaupass1',
            'password_confirmation' => 'nouveaupass1',
        ])->assertSessionHasErrors('email');
    }
}
