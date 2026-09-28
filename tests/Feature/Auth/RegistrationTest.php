<?php

namespace Tests\Feature\Auth;

use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
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

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Ambulances du Val',
            'slug' => 'val',
            'sector' => 'ambulance_privee',
            'admin_name' => 'Alex Jouanneau',
            'admin_email' => 'alex@example.com',
            'password' => 'motdepasse12',
            'password_confirmation' => 'motdepasse12',
        ], $override);
    }

    public function test_visitor_can_self_register_an_organisation_with_a_trial(): void
    {
        $this->post('http://localhost/inscription', $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('organisations', ['slug' => 'val', 'name' => 'Ambulances du Val', 'sector' => 'ambulance_privee']);
        $org = Organisation::where('slug', 'val')->first();
        $this->assertDatabaseHas('subscriptions', ['organisation_id' => $org->id, 'plan' => 'decouverte', 'status' => 'trial']);
        $this->assertDatabaseHas('users', ['organisation_id' => $org->id, 'email' => 'alex@example.com']);
        $this->assertAuthenticated();
    }

    public function test_reserved_slug_is_rejected(): void
    {
        $this->post('http://localhost/inscription', $this->payload(['slug' => 'app']))
            ->assertSessionHasErrors('slug');
        $this->assertDatabaseMissing('organisations', ['slug' => 'app']);
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        Organisation::factory()->slug('val')->create();

        $this->post('http://localhost/inscription', $this->payload())
            ->assertSessionHasErrors('slug');
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->post('http://localhost/inscription', $this->payload(['password' => 'court', 'password_confirmation' => 'court']))
            ->assertSessionHasErrors('password');
    }

    public function test_registration_is_not_available_to_an_authenticated_user(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $user = User::factory()->create(['organisation_id' => $org->id]);

        // Un utilisateur connecté a déjà une organisation : l'inscription (réservée
        // aux hôtes sans tenant) n'est pas accessible.
        $this->actingAs($user)->get('http://app.localhost/inscription')->assertNotFound();
    }
}
