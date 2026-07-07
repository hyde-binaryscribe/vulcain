<?php

namespace Tests\Feature\Inventory;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Inventory;
use App\Models\InventoryTemplate;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryRealizationTest extends TestCase
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

    private function userWithRole(Organisation $org, string $role): User
    {
        return $this->tenant()->runFor($org, function () use ($org, $role) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole($role);

            return $u;
        });
    }

    /** @return array{Organisation, Vehicle, InventoryTemplate, Material} */
    private function scenario(string $slug = 'caserne'): array
    {
        $org = Organisation::factory()->slug($slug)->create();
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
        $material = Material::factory()->create(['organisation_id' => $org->id, 'name' => 'Collier cervical', 'theoretical_qty' => 4]);
        $template = InventoryTemplate::factory()->create(['organisation_id' => $org->id, 'vehicle_id' => $vehicle->id]);
        $this->tenant()->runFor($org, fn () => $template->items()->create([
            'material_id' => $material->id,
            'expected_qty' => 4,
            'display_order' => 10,
        ]));

        return [$org, $vehicle, $template, $material];
    }

    public function test_starting_creates_an_immutable_snapshot(): void
    {
        [$org, , $template, $material] = $this->scenario();
        $admin = $this->userWithRole($org, Rbac::ADMIN);

        $this->actingAs($admin)->post('http://caserne.localhost/inventories', [
            'inventory_template_id' => $template->id,
        ])->assertRedirect();

        $inventory = $this->tenant()->runFor($org, fn () => Inventory::with('items')->first());
        $this->assertNotNull($inventory);
        $this->assertCount(1, $inventory->items);
        $this->assertSame('Collier cervical', $inventory->items->first()->material_name);

        // Modifier le catalogue ne change pas l'inventaire (immutabilité du snapshot).
        $this->tenant()->runFor($org, fn () => $material->update(['name' => 'Autre nom']));
        $this->assertSame('Collier cervical', $inventory->items->first()->fresh()->material_name);
    }

    public function test_verifier_can_only_start_on_authorised_vehicle(): void
    {
        [$org, $vehicle, $template] = $this->scenario();
        $verifier = $this->userWithRole($org, Rbac::VERIFIER);

        // Non affecté -> 403
        $this->actingAs($verifier)->post('http://caserne.localhost/inventories', [
            'inventory_template_id' => $template->id,
        ])->assertForbidden();

        // Affecté -> autorisé
        $this->tenant()->runFor($org, fn () => $vehicle->users()->attach($verifier->id));
        $this->actingAs($verifier)->post('http://caserne.localhost/inventories', [
            'inventory_template_id' => $template->id,
        ])->assertRedirect();
    }

    public function test_item_can_be_saved_during_realization(): void
    {
        [$org, , $template] = $this->scenario();
        $admin = $this->userWithRole($org, Rbac::ADMIN);

        $this->actingAs($admin)->post('http://caserne.localhost/inventories', ['inventory_template_id' => $template->id]);
        $inventory = $this->tenant()->runFor($org, fn () => Inventory::with('items')->first());
        $item = $inventory->items->first();

        $this->actingAs($admin)->patch("http://caserne.localhost/inventories/{$inventory->id}/items/{$item->id}", [
            'observed_qty' => 3,
            'state' => 'manquant',
            'observation' => 'Il en manque un',
        ])->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame(3, $item->observed_qty);
        $this->assertSame('manquant', $item->state->value);
        $this->assertTrue($item->checked);
    }

    public function test_validated_inventory_is_read_only(): void
    {
        [$org, $vehicle] = $this->scenario();
        $admin = $this->userWithRole($org, Rbac::ADMIN);
        $inventory = Inventory::factory()->validated()->create([
            'organisation_id' => $org->id, 'vehicle_id' => $vehicle->id, 'user_id' => $admin->id,
        ]);
        $item = $this->tenant()->runFor($org, fn () => $inventory->items()->create(['material_name' => 'X', 'expected_qty' => 1]));

        $this->actingAs($admin)->patch("http://caserne.localhost/inventories/{$inventory->id}/items/{$item->id}", [
            'observed_qty' => 2,
        ])->assertForbidden();
    }

    public function test_verifier_cannot_open_another_users_draft(): void
    {
        [$org, $vehicle] = $this->scenario();
        $owner = $this->userWithRole($org, Rbac::VERIFIER);
        $other = $this->userWithRole($org, Rbac::VERIFIER);
        $inventory = Inventory::factory()->create(['organisation_id' => $org->id, 'vehicle_id' => $vehicle->id, 'user_id' => $owner->id]);

        $this->actingAs($other)->get("http://caserne.localhost/inventories/{$inventory->id}")->assertForbidden();
    }
}
