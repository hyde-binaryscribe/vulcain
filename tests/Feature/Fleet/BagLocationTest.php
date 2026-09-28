<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BagLocationTest extends TestCase
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
    private function orgWithAdmin(): array
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::ADMIN);

            return $u;
        });

        return [$org, $admin];
    }

    public function test_bag_kind_is_hidden_until_enabled(): void
    {
        [$org, $admin] = $this->orgWithAdmin();

        // Désactivé par défaut : la nature « Sac » n'est pas proposée.
        $this->actingAs($admin)->get('http://caserne.localhost/locations')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('kinds', fn ($kinds) => collect($kinds)->pluck('value')->doesntContain('sac')));

        // Activation.
        $this->actingAs($admin)->patch('http://caserne.localhost/settings', ['bags_enabled' => true]);

        $this->actingAs($admin)->get('http://caserne.localhost/locations')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('kinds', fn ($kinds) => collect($kinds)->pluck('value')->contains('sac')));
    }

    public function test_can_create_a_bag_attached_to_a_vehicle(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->post('http://caserne.localhost/locations', [
            'name' => 'Sac de prompt secours',
            'kind' => 'sac',
            'vehicle_id' => $vehicle->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('locations', [
            'organisation_id' => $org->id,
            'name' => 'Sac de prompt secours',
            'kind' => 'sac',
            'vehicle_id' => $vehicle->id,
        ]);
    }

    public function test_a_bag_requires_a_vehicle(): void
    {
        [, $admin] = $this->orgWithAdmin();

        $this->actingAs($admin)->post('http://caserne.localhost/locations', [
            'name' => 'Sac sans véhicule',
            'kind' => 'sac',
        ])->assertSessionHasErrors('vehicle_id');
    }
}
