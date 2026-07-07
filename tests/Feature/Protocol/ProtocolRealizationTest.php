<?php

namespace Tests\Feature\Protocol;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Location;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\Protocol;
use App\Models\ProtocolTemplate;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtocolRealizationTest extends TestCase
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

    /**
     * Périmètre = véhicule ; un matériel rangé dans un emplacement du véhicule.
     *
     * @return array{Organisation, Vehicle, ProtocolTemplate, Material}
     */
    private function scenario(string $slug = 'caserne'): array
    {
        $org = Organisation::factory()->slug($slug)->create();

        return $this->tenant()->runFor($org, function () use ($org) {
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $location = Location::create([
                'organisation_id' => $org->id, 'vehicle_id' => $vehicle->id, 'kind' => 'mobile', 'name' => 'Cellule',
            ]);
            $material = Material::factory()->create([
                'organisation_id' => $org->id, 'location_id' => $location->id,
                'name' => 'Collier cervical', 'tracking_mode' => 'quantity', 'theoretical_qty' => 4,
            ]);
            $template = ProtocolTemplate::factory()->forVehicle($vehicle)->create(['organisation_id' => $org->id]);

            return [$org, $vehicle, $template, $material];
        });
    }

    public function test_starting_creates_an_immutable_snapshot(): void
    {
        [$org, , $template, $material] = $this->scenario();
        $admin = $this->userWithRole($org, Rbac::ADMIN);

        $this->actingAs($admin)->post('http://caserne.localhost/protocols', [
            'protocol_template_id' => $template->id,
        ])->assertRedirect();

        $protocol = $this->tenant()->runFor($org, fn () => Protocol::with('items')->first());
        $this->assertNotNull($protocol);
        $this->assertCount(1, $protocol->items);
        $this->assertSame('Collier cervical', $protocol->items->first()->material_name);

        // Modifier le catalogue ne change pas le protocole (immutabilité du snapshot).
        $this->tenant()->runFor($org, fn () => $material->update(['name' => 'Autre nom']));
        $this->assertSame('Collier cervical', $protocol->items->first()->fresh()->material_name);
    }

    public function test_verifier_can_only_start_on_authorised_vehicle(): void
    {
        [$org, $vehicle, $template] = $this->scenario();
        $verifier = $this->userWithRole($org, Rbac::VERIFIER);

        // Non affecté -> 403
        $this->actingAs($verifier)->post('http://caserne.localhost/protocols', [
            'protocol_template_id' => $template->id,
        ])->assertForbidden();

        // Affecté -> autorisé
        $this->tenant()->runFor($org, fn () => $vehicle->users()->attach($verifier->id));
        $this->actingAs($verifier)->post('http://caserne.localhost/protocols', [
            'protocol_template_id' => $template->id,
        ])->assertRedirect();
    }

    public function test_item_can_be_saved_during_realization(): void
    {
        [$org, , $template] = $this->scenario();
        $admin = $this->userWithRole($org, Rbac::ADMIN);

        $this->actingAs($admin)->post('http://caserne.localhost/protocols', ['protocol_template_id' => $template->id]);
        $protocol = $this->tenant()->runFor($org, fn () => Protocol::with('items')->first());
        $item = $protocol->items->first();

        $this->actingAs($admin)->patch("http://caserne.localhost/protocols/{$protocol->id}/items/{$item->id}", [
            'observed_qty' => 3,
            'state' => 'manquant',
            'observation' => 'Il en manque un',
        ])->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame(3, $item->observed_qty);
        $this->assertSame('manquant', $item->state->value);
        $this->assertTrue($item->checked);
    }

    public function test_validated_protocol_is_read_only(): void
    {
        [$org, $vehicle] = $this->scenario();
        $admin = $this->userWithRole($org, Rbac::ADMIN);
        $protocol = Protocol::factory()->validated()->create([
            'organisation_id' => $org->id, 'vehicle_id' => $vehicle->id, 'user_id' => $admin->id,
        ]);
        $item = $this->tenant()->runFor($org, fn () => $protocol->items()->create(['material_name' => 'X', 'expected_qty' => 1]));

        $this->actingAs($admin)->patch("http://caserne.localhost/protocols/{$protocol->id}/items/{$item->id}", [
            'observed_qty' => 2,
        ])->assertForbidden();
    }

    public function test_verifier_cannot_open_another_users_draft(): void
    {
        [$org, $vehicle] = $this->scenario();
        $owner = $this->userWithRole($org, Rbac::VERIFIER);
        $other = $this->userWithRole($org, Rbac::VERIFIER);
        $protocol = Protocol::factory()->create(['organisation_id' => $org->id, 'vehicle_id' => $vehicle->id, 'user_id' => $owner->id]);

        $this->actingAs($other)->get("http://caserne.localhost/protocols/{$protocol->id}")->assertForbidden();
    }
}
