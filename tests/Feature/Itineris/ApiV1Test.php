<?php

namespace Tests\Feature\Itineris;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Domain\Sectors\Sector;
use App\Models\ApiToken;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lien Itineris : jetons d'organisation et API v1 des véhicules. */
class ApiV1Test extends TestCase
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

    private function ambulanceOrg(string $slug = 'ambu'): Organisation
    {
        return Organisation::factory()->slug($slug)->sector(Sector::AMBULANCE_PRIVEE)->create();
    }

    private function userWithRole(Organisation $org, string $role): User
    {
        return $this->tenant()->runFor($org, function () use ($org, $role) {
            app(RoleProvisioner::class)->provision($org);
            $user = User::factory()->create(['organisation_id' => $org->id]);
            $user->assignRole($role);

            return $user;
        });
    }

    /** @return array{0: ApiToken, 1: string} */
    private function issue(Organisation $org): array
    {
        return $this->tenant()->runFor($org, fn () => ApiToken::issue('Itineris', null));
    }

    private function vehicle(Organisation $org, array $attributes = []): Vehicle
    {
        return $this->tenant()->runFor($org, fn () => Vehicle::factory()->create(array_merge(
            ['organisation_id' => $org->id, 'name' => 'AMB 1', 'registration' => 'GH-214-KL'],
            $attributes,
        )));
    }

    public function test_vehicles_get_a_public_uuid_and_start_at_version_one(): void
    {
        $vehicle = $this->vehicle($this->ambulanceOrg());

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $vehicle->uuid);
        $this->assertSame(1, $vehicle->fresh()->version);
    }

    public function test_version_moves_on_shared_fields_only(): void
    {
        $org = $this->ambulanceOrg();
        $vehicle = $this->vehicle($org);

        $this->tenant()->runFor($org, fn () => $vehicle->update(['mileage' => 187400]));
        $this->assertSame(1, $vehicle->fresh()->version);

        $this->tenant()->runFor($org, fn () => $vehicle->update(['registration' => 'GH-215-KL']));
        $this->assertSame(2, $vehicle->fresh()->version, 'base');
        $this->assertSame(2, $vehicle->version, 'memoire');
    }

    public function test_api_requires_a_valid_token(): void
    {
        $this->getJson('/api/v1/vehicles')->assertUnauthorized();
        $this->getJson('/api/v1/vehicles', ['Authorization' => 'Bearer vk_inconnu'])->assertUnauthorized();
    }

    public function test_api_lists_only_the_vehicles_of_the_token_organisation(): void
    {
        $org = $this->ambulanceOrg();
        $other = $this->ambulanceOrg('autre');
        $mine = $this->vehicle($org, ['vin' => 'VF1MA000000000001', 'telematics_imei' => '862272081619456']);
        $this->vehicle($other, ['name' => 'AUTRE 1']);
        [, $plain] = $this->issue($org);

        $response = $this->getJson('/api/v1/vehicles', ['Authorization' => "Bearer {$plain}"])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $mine->uuid)
            ->assertJsonPath('data.0.vin', 'VF1MA000000000001')
            ->assertJsonPath('data.0.registration', 'GH-214-KL')
            ->assertJsonPath('data.0.telematics_imei', '862272081619456')
            ->assertJsonPath('data.0.version', 1);

        // L'identifiant interne ne sort jamais.
        $this->assertArrayNotHasKey('id', $response->json('data.0'));
    }

    public function test_api_shows_one_vehicle_by_uuid_including_deleted_ones(): void
    {
        $org = $this->ambulanceOrg();
        $vehicle = $this->vehicle($org);
        $foreign = $this->vehicle($this->ambulanceOrg('autre'));
        [, $plain] = $this->issue($org);
        $headers = ['Authorization' => "Bearer {$plain}"];

        $this->getJson("/api/v1/vehicles/{$vehicle->uuid}", $headers)
            ->assertOk()->assertJsonPath('data.deleted', false);
        $this->getJson("/api/v1/vehicles/{$foreign->uuid}", $headers)->assertNotFound();

        $this->tenant()->runFor($org, fn () => $vehicle->delete());
        $this->getJson("/api/v1/vehicles/{$vehicle->uuid}", $headers)
            ->assertOk()->assertJsonPath('data.deleted', true);
    }

    public function test_revoked_token_is_refused(): void
    {
        $org = $this->ambulanceOrg();
        [$token, $plain] = $this->issue($org);
        $this->tenant()->runFor($org, fn () => $token->update(['revoked_at' => now()]));

        $this->getJson('/api/v1/vehicles', ['Authorization' => "Bearer {$plain}"])->assertUnauthorized();
    }

    public function test_admin_creates_and_revokes_a_token_from_settings(): void
    {
        $org = $this->ambulanceOrg();
        $admin = $this->userWithRole($org, Rbac::ADMIN);

        $this->actingAs($admin)
            ->post('http://ambu.localhost/settings/api-tokens', ['name' => 'Itineris'])
            ->assertSessionHas('api_token_plain');

        $token = $this->tenant()->runFor($org, fn () => ApiToken::query()->sole());
        $this->assertNull($token->revoked_at);

        $this->actingAs($admin)
            ->delete("http://ambu.localhost/settings/api-tokens/{$token->id}")
            ->assertRedirect();
        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_tokens_are_not_available_outside_transport_sanitaire(): void
    {
        $org = Organisation::factory()->slug('caserne')->create(); // SDIS
        $admin = $this->userWithRole($org, Rbac::ADMIN);

        $this->actingAs($admin)
            ->post('http://caserne.localhost/settings/api-tokens', ['name' => 'Itineris'])
            ->assertNotFound();
    }

    public function test_verifier_cannot_create_tokens(): void
    {
        $org = $this->ambulanceOrg();
        $verifier = $this->userWithRole($org, Rbac::VERIFIER);

        $this->actingAs($verifier)
            ->post('http://ambu.localhost/settings/api-tokens', ['name' => 'Itineris'])
            ->assertForbidden();
    }

    public function test_vin_is_validated_and_uppercased(): void
    {
        $org = $this->ambulanceOrg();
        $admin = $this->userWithRole($org, Rbac::ADMIN);
        $vehicle = $this->vehicle($org);
        $payload = ['name' => 'AMB 1', 'status' => 'disponible'];

        $this->actingAs($admin)
            ->patch("http://ambu.localhost/vehicles/{$vehicle->id}", $payload + ['vin' => 'TROPCOURT'])
            ->assertSessionHasErrors('vin');

        $this->actingAs($admin)
            ->patch("http://ambu.localhost/vehicles/{$vehicle->id}", $payload + ['vin' => 'vf1ma000000000001'])
            ->assertSessionHasNoErrors();
        $this->assertSame('VF1MA000000000001', $vehicle->fresh()->vin);
    }
}
