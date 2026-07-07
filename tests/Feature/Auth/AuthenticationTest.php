<?php

namespace Tests\Feature\Auth;

use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
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

    private function makeUser(string $slug, array $attrs = []): array
    {
        $org = Organisation::factory()->slug($slug)->create();
        $user = User::factory()->create(array_merge([
            'organisation_id' => $org->id,
            'email' => 'agent@cis.test',
            'password' => 'motdepasse1',
            'is_active' => true,
        ], $attrs));

        return [$org, $user];
    }

    public function test_user_can_authenticate_within_their_organisation(): void
    {
        [, $user] = $this->makeUser('caserne');

        $response = $this->post('http://caserne.localhost/login', [
            'email' => 'agent@cis.test',
            'password' => 'motdepasse1',
        ]);

        $response->assertStatus(302);
        $this->assertAuthenticatedAs($user);
    }

    public function test_authentication_fails_with_wrong_password(): void
    {
        $this->makeUser('caserne');

        $this->post('http://caserne.localhost/login', [
            'email' => 'agent@cis.test',
            'password' => 'mauvais',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_cannot_authenticate_on_another_organisation(): void
    {
        $this->makeUser('caserne'); // agent@cis.test dans "caserne"
        Organisation::factory()->slug('autre')->create();

        // Mêmes identifiants, mais sur le sous-domaine d'une autre organisation.
        $this->post('http://autre.localhost/login', [
            'email' => 'agent@cis.test',
            'password' => 'motdepasse1',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $this->makeUser('caserne', ['is_active' => false]);

        $this->post('http://caserne.localhost/login', [
            'email' => 'agent@cis.test',
            'password' => 'motdepasse1',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_is_locked_out_after_too_many_attempts(): void
    {
        $this->makeUser('caserne');

        for ($i = 0; $i < 5; $i++) {
            $this->post('http://caserne.localhost/login', [
                'email' => 'agent@cis.test',
                'password' => 'mauvais',
            ]);
        }

        // 6e tentative, mot de passe CORRECT : toujours bloqué.
        $response = $this->post('http://caserne.localhost/login', [
            'email' => 'agent@cis.test',
            'password' => 'motdepasse1',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString(
            'Trop de tentatives',
            session('errors')->first('email')
        );
    }

    public function test_user_can_logout(): void
    {
        [, $user] = $this->makeUser('caserne');

        $this->actingAs($user);

        $this->post('http://caserne.localhost/logout')->assertStatus(302);
        $this->assertGuest();
    }
}
