<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Location;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BagTransferTest extends TestCase
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

    public function test_admin_can_transfer_a_bag_with_its_content(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$admin, $vehA, $vehB, $bag, $child] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);
            $a = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $b = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $bag = Location::create(['name' => 'Sac PS', 'kind' => 'sac', 'vehicle_id' => $a->id]);
            $child = Location::create(['name' => 'Pochette', 'kind' => 'mobile', 'vehicle_id' => $a->id, 'parent_id' => $bag->id]);

            return [$admin, $a, $b, $bag, $child];
        });

        $this->actingAs($admin)->post("http://caserne.localhost/sacs/{$bag->id}/transfer", [
            'to_vehicle_id' => $vehB->id,
        ])->assertSessionHasNoErrors();

        // Le sac et son emplacement enfant ont suivi.
        $this->assertSame($vehB->id, $bag->fresh()->vehicle_id);
        $this->assertSame($vehB->id, $child->fresh()->vehicle_id);
        $this->assertDatabaseHas('bag_movements', [
            'location_id' => $bag->id,
            'from_vehicle_id' => $vehA->id,
            'to_vehicle_id' => $vehB->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_send_a_bag_to_the_depot(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$admin, $veh, $bag] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);
            $v = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $bag = Location::create(['name' => 'Sac', 'kind' => 'sac', 'vehicle_id' => $v->id]);

            return [$admin, $v, $bag];
        });

        // Cible vide (dépôt) : le sac sort de tout véhicule.
        $this->actingAs($admin)->post("http://caserne.localhost/sacs/{$bag->id}/transfer", [
            'to_vehicle_id' => null,
        ])->assertSessionHasNoErrors();

        $this->assertNull($bag->fresh()->vehicle_id);
        $this->assertDatabaseHas('bag_movements', [
            'location_id' => $bag->id,
            'from_vehicle_id' => $veh->id,
            'to_vehicle_id' => null,
            'user_id' => $admin->id,
        ]);
    }

    public function test_bag_already_in_depot_cannot_be_sent_to_depot(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$admin, $bag] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);
            $bag = Location::create(['name' => 'Sac', 'kind' => 'sac', 'vehicle_id' => null]);

            return [$admin, $bag];
        });

        $this->actingAs($admin)->post("http://caserne.localhost/sacs/{$bag->id}/transfer", [
            'to_vehicle_id' => null,
        ])->assertSessionHasErrors('to_vehicle_id');
    }

    public function test_transfer_to_same_vehicle_is_rejected(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$admin, $veh, $bag] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);
            $v = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $bag = Location::create(['name' => 'Sac', 'kind' => 'sac', 'vehicle_id' => $v->id]);

            return [$admin, $v, $bag];
        });

        $this->actingAs($admin)->post("http://caserne.localhost/sacs/{$bag->id}/transfer", [
            'to_vehicle_id' => $veh->id,
        ])->assertSessionHasErrors('to_vehicle_id');
    }
}
