<?php

namespace Tests\Feature\Fleet;

use App\Domain\Fleet\ArmamentLevel;
use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\VehicleTypeEquipment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class VehicleTypeArmamentTest extends TestCase
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

    public function test_armament_page_offers_legal_templates(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$admin, $typeId] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);
            $type = VehicleType::create(['name' => 'Ambulance type A', 'display_order' => 0]);

            return [$admin, $type->id];
        });

        $this->actingAs($admin)->get("http://caserne.localhost/vehicle-types/{$typeId}/armement")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('VehicleTypes/Armament')
                ->where('type.name', 'Ambulance type A')
                ->has('templates.type_a.items')
                ->has('levels', 3));
    }

    public function test_admin_saves_armament_lines(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$admin, $typeId] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);
            $type = VehicleType::create(['name' => 'Ambulance type A', 'display_order' => 0]);

            return [$admin, $type->id];
        });

        $this->actingAs($admin)->post("http://caserne.localhost/vehicle-types/{$typeId}/armement", [
            'items' => [
                ['category' => 'Ventilation / Oxygène', 'designation' => 'Oxygène fixe', 'quantity' => 1, 'level' => 'obligatoire', 'note' => '2B5'],
                ['category' => 'DSA / DAE', 'designation' => 'Défibrillateur (DSA)', 'quantity' => 1, 'level' => 'conditionnel', 'note' => ''],
                ['category' => '', 'designation' => '', 'quantity' => 1, 'level' => 'obligatoire', 'note' => ''], // vide → ignoré
            ],
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () use ($typeId) {
            $items = VehicleTypeEquipment::query()->where('vehicle_type_id', $typeId)->orderBy('display_order')->get();
            $this->assertCount(2, $items); // la ligne vide est ignorée
            $this->assertSame('Oxygène fixe', $items[0]->designation);
            $this->assertSame(ArmamentLevel::OBLIGATOIRE, $items[0]->level);
            $this->assertSame('2B5', $items[0]->note);
            $this->assertSame(ArmamentLevel::CONDITIONNEL, $items[1]->level);
        });
    }
}
