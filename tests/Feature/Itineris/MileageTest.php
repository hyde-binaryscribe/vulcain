<?php

namespace Tests\Feature\Itineris;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Domain\Sectors\Sector;
use App\Models\ApiToken;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Relevés kilométriques : le compteur ne recule jamais, un saut de plus de 2 000 km est à vérifier. */
class MileageTest extends TestCase
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

    /** @return array{0: Organisation, 1: User, 2: Vehicle} */
    private function setUpVehicle(int $mileage = 187000): array
    {
        $org = Organisation::factory()->slug('ambu')->sector(Sector::AMBULANCE_PRIVEE)->create();
        [$user, $vehicle] = $this->tenant()->runFor($org, function () use ($org, $mileage) {
            app(RoleProvisioner::class)->provision($org);
            $user = User::factory()->create(['organisation_id' => $org->id, 'name' => 'Julien D.']);
            $user->assignRole(Rbac::ADMIN);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'name' => 'AMB 1', 'mileage' => $mileage]);

            return [$user, $vehicle];
        });

        return [$org, $user, $vehicle];
    }

    private function fuel(User $user, Vehicle $vehicle, int $km): void
    {
        $this->actingAs($user)->post("http://ambu.localhost/vehicles/{$vehicle->id}/fuel", [
            'mileage' => $km, 'liters' => 50, 'full_tank' => true,
        ])->assertSessionHasNoErrors();
    }

    public function test_a_reading_updates_the_counter_with_its_date_and_source(): void
    {
        [, $user, $vehicle] = $this->setUpVehicle();

        $this->fuel($user, $vehicle, 187412);

        $vehicle->refresh();
        $this->assertSame(187412, (int) $vehicle->mileage);
        $this->assertSame('plein', $vehicle->mileage_source);
        $this->assertSame('Julien D.', $vehicle->mileage_by);
        $this->assertNotNull($vehicle->mileage_at);
        // Le kilométrage ne change pas la version (il n'est pas un champ partagé).
        $this->assertSame(1, $vehicle->version);
    }

    public function test_the_counter_never_goes_back(): void
    {
        [, $user, $vehicle] = $this->setUpVehicle();

        $this->fuel($user, $vehicle, 186000);

        $this->assertSame(187000, (int) $vehicle->fresh()->mileage);
    }

    public function test_a_jump_over_2000_km_is_flagged_not_applied(): void
    {
        [$org, $user, $vehicle] = $this->setUpVehicle();

        $this->fuel($user, $vehicle, 1874120);
        $this->fuel($user, $vehicle, 1874200);

        $this->assertSame(187000, (int) $vehicle->fresh()->mileage);
        $events = $this->tenant()->runFor($org, fn () => Event::query()->where('source_key', "mileage-jump:{$vehicle->id}")->get());
        $this->assertCount(1, $events);
        $this->assertStringContainsString('1 874 200 km', $events->first()->description);
    }

    public function test_a_manual_correction_applies_as_is(): void
    {
        [$org, , $vehicle] = $this->setUpVehicle();

        $this->tenant()->runFor($org, fn () => $vehicle->update(['mileage' => 150000]));

        $vehicle->refresh();
        $this->assertSame(150000, (int) $vehicle->mileage);
        $this->assertSame('fiche', $vehicle->mileage_source);
    }

    public function test_the_api_gives_the_reading_date_and_source(): void
    {
        [$org, $user, $vehicle] = $this->setUpVehicle();
        $this->fuel($user, $vehicle, 187412);
        [, $plain] = $this->tenant()->runFor($org, fn () => ApiToken::issue('Itineris', null));

        $this->getJson("/api/v1/vehicles/{$vehicle->uuid}", ['Authorization' => "Bearer {$plain}"])
            ->assertOk()
            ->assertJsonPath('data.mileage', 187412)
            ->assertJsonPath('data.mileage_source', 'plein');
    }
}
