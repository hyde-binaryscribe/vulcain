<?php

namespace Tests\Feature\Catalog;

use App\Domain\Catalog\VehicleInventory;
use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Location;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleInventoryTest extends TestCase
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

    /** @return array{Organisation, Vehicle, Location} */
    private function org(): array
    {
        $org = Organisation::factory()->slug('cis')->create();
        [$vehicle, $location] = $this->tenant()->runFor($org, function () use ($org) {
            $v = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $l = Location::create(['vehicle_id' => $v->id, 'name' => 'Cellule', 'kind' => 'mobile']);

            return [$v, $l];
        });

        return [$org, $vehicle, $location];
    }

    public function test_consume_quantity_decrements_and_restock_resets(): void
    {
        [$org, $vehicle, $location] = $this->org();

        $this->tenant()->runFor($org, function () use ($vehicle, $location) {
            $m = Material::create([
                'name' => 'Compresses', 'location_id' => $location->id,
                'tracking_mode' => Material::MODE_QUANTITY,
                'theoretical_qty' => 10, 'current_qty' => 10,
            ]);

            VehicleInventory::consume($vehicle, $m, 3, null, null, null);
            $this->assertSame(7, $m->fresh()->stockQuantity());
            $this->assertSame(3, VehicleInventory::missing($m->fresh()));
            $this->assertDatabaseHas('material_consumptions', ['material_id' => $m->id, 'quantity' => 3]);

            VehicleInventory::restock($m->fresh());
            $this->assertSame(10, $m->fresh()->stockQuantity());
        });
    }

    public function test_consume_lot_uses_fefo(): void
    {
        [$org, $vehicle, $location] = $this->org();

        $this->tenant()->runFor($org, function () use ($vehicle, $location) {
            $m = Material::create([
                'name' => 'Sérum', 'location_id' => $location->id,
                'tracking_mode' => Material::MODE_LOT, 'theoretical_qty' => 10,
            ]);
            $m->lots()->create(['lot_number' => 'A', 'quantity' => 4, 'expiry_date' => now()->addDays(5)]);
            $m->lots()->create(['lot_number' => 'B', 'quantity' => 6, 'expiry_date' => now()->addDays(30)]);

            // Sort 5 : vide le lot A (péremption la plus proche) puis 1 de B.
            VehicleInventory::consume($vehicle, $m, 5, null, null, null);
            $this->assertSame(5, $m->fresh()->stockQuantity());
            $this->assertDatabaseMissing('stock_lots', ['lot_number' => 'A', 'deleted_at' => null]);
            $this->assertSame(5, $m->fresh()->lots()->where('lot_number', 'B')->value('quantity'));

            // Réarmement : un lot de réarmement ramène au théorique.
            VehicleInventory::restock($m->fresh());
            $this->assertSame(10, $m->fresh()->stockQuantity());
        });
    }

    public function test_consume_serial_removes_the_unit(): void
    {
        [$org, $vehicle, $location] = $this->org();

        $this->tenant()->runFor($org, function () use ($vehicle, $location) {
            $m = Material::create([
                'name' => 'Défibrillateur', 'location_id' => $location->id,
                'tracking_mode' => Material::MODE_SERIAL, 'theoretical_qty' => 2,
            ]);
            $m->items()->create(['serial_number' => 'SN-1', 'status' => 'conforme', 'location_id' => $location->id]);
            $m->items()->create(['serial_number' => 'SN-2', 'status' => 'conforme', 'location_id' => $location->id]);

            VehicleInventory::consume($vehicle, $m, 1, null, null, null);
            $this->assertSame(1, $m->fresh()->stockQuantity());
            $this->assertDatabaseHas('material_consumptions', ['material_id' => $m->id, 'serial_number' => 'SN-1']);
        });
    }

    public function test_manager_can_record_a_consumption_via_terrain(): void
    {
        [$org, $vehicle, $location] = $this->org();
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::ADMIN);

            return $u;
        });
        $material = $this->tenant()->runFor($org, fn () => Material::create([
            'name' => 'Masques O2', 'location_id' => $location->id,
            'tracking_mode' => Material::MODE_QUANTITY, 'theoretical_qty' => 5, 'current_qty' => 5,
        ]));

        $this->actingAs($admin)->post("http://cis.localhost/t/vehicules/{$vehicle->id}/consommation", [
            'material_id' => $material->id,
            'quantity' => 2,
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, fn () => $this->assertSame(3, $material->fresh()->stockQuantity()));
    }

    public function test_closing_without_restock_creates_a_shortage_event(): void
    {
        [$org, $vehicle, $location] = $this->org();
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::ADMIN);

            return $u;
        });

        $this->tenant()->runFor($org, function () use ($vehicle, $admin, $location) {
            Material::create([
                'name' => 'Garrots', 'location_id' => $location->id,
                'tracking_mode' => Material::MODE_QUANTITY, 'theoretical_qty' => 4, 'current_qty' => 1, // déjà en manque
            ]);
            VehicleSession::create(['vehicle_id' => $vehicle->id, 'user_id' => $admin->id, 'opened_at' => now()]);
        });

        // Clôture sans rien réarmer -> alerte de manquement.
        $this->actingAs($admin)->post("http://cis.localhost/t/vehicules/{$vehicle->id}/fin-de-service", [])
            ->assertRedirect('http://cis.localhost/t');

        $this->assertDatabaseHas('events', [
            'organisation_id' => $org->id,
            'source_key' => 'rearm:'.$vehicle->id,
            'status' => 'a_traiter',
        ]);
    }
}
