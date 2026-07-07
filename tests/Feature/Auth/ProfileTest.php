<?php

namespace Tests\Feature\Auth;

use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
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

    private function makeUser(array $attrs = []): array
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $user = User::factory()->create(array_merge([
            'organisation_id' => $org->id,
            'email' => 'agent@cis.test',
            'password' => 'ancienpass1',
        ], $attrs));

        return [$org, $user];
    }

    public function test_user_can_update_profile(): void
    {
        [, $user] = $this->makeUser();

        $this->actingAs($user)
            ->patch('http://caserne.localhost/profile', [
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'grade' => 'Sergent',
                'email' => 'jean.dupont@cis.test',
            ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Jean', $user->first_name);
        $this->assertSame('Jean Dupont', $user->name);
        $this->assertSame('jean.dupont@cis.test', $user->email);
    }

    public function test_email_must_be_unique_within_organisation(): void
    {
        [$org, $user] = $this->makeUser();
        User::factory()->create(['organisation_id' => $org->id, 'email' => 'occupe@cis.test']);

        $this->actingAs($user)
            ->patch('http://caserne.localhost/profile', [
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'email' => 'occupe@cis.test',
            ])->assertSessionHasErrors('email');
    }

    public function test_user_can_change_password(): void
    {
        [, $user] = $this->makeUser();

        $this->actingAs($user)
            ->put('http://caserne.localhost/password', [
                'current_password' => 'ancienpass1',
                'password' => 'nouveaupass1',
                'password_confirmation' => 'nouveaupass1',
            ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('nouveaupass1', $user->refresh()->password));
    }

    public function test_change_password_requires_correct_current_password(): void
    {
        [, $user] = $this->makeUser();

        $this->actingAs($user)
            ->put('http://caserne.localhost/password', [
                'current_password' => 'faux',
                'password' => 'nouveaupass1',
                'password_confirmation' => 'nouveaupass1',
            ])->assertSessionHasErrors('current_password');
    }

    public function test_user_can_revoke_a_session_but_not_of_another_user(): void
    {
        config(['session.driver' => 'database']);
        [$org, $user] = $this->makeUser();
        $other = User::factory()->create(['organisation_id' => $org->id]);

        DB::table('sessions')->insert([
            ['id' => 'sess-user', 'user_id' => $user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => now()->timestamp],
            ['id' => 'sess-other', 'user_id' => $other->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'x', 'payload' => '', 'last_activity' => now()->timestamp],
        ]);

        $this->actingAs($user)->delete('http://caserne.localhost/sessions/sess-user')->assertStatus(302);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-user']);

        // Ne peut pas révoquer la session d'un autre utilisateur.
        $this->actingAs($user)->delete('http://caserne.localhost/sessions/sess-other');
        $this->assertDatabaseHas('sessions', ['id' => 'sess-other']);
    }
}
