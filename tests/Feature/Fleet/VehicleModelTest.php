<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Location;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleModelTest extends TestCase
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

    public function test_creating_a_vehicle_on_a_model_generates_its_locations(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);

            // Gabarit : Cellule (mobile) > Sac PS (sac) > Pochette (mobile) ; Cabine (mobile).
            $model = VehicleModel::create(['name' => 'Master ASSU', 'display_order' => 0, 'is_active' => true]);
            $cellule = $model->templateLocations()->create(['name' => 'Cellule', 'kind' => 'mobile', 'display_order' => 0]);
            $sac = $model->templateLocations()->create(['name' => 'Sac PS', 'kind' => 'sac', 'display_order' => 1, 'parent_id' => $cellule->id]);
            $model->templateLocations()->create(['name' => 'Pochette', 'kind' => 'mobile', 'display_order' => 2, 'parent_id' => $sac->id]);
            $model->templateLocations()->create(['name' => 'Cabine', 'kind' => 'mobile', 'display_order' => 3]);

            return $admin;
        });

        $model = $this->tenant()->runFor($org, fn () => VehicleModel::query()->first());

        $this->actingAs($admin)->post('http://caserne.localhost/vehicles', [
            'name' => 'VL-1',
            'status' => 'disponible',
            'vehicle_model_id' => $model->id,
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () {
            $vehicle = Vehicle::query()->where('name', 'VL-1')->firstOrFail();
            $locations = Location::query()->where('vehicle_id', $vehicle->id)->get();

            // Les quatre emplacements du gabarit ont été générés.
            $this->assertCount(4, $locations);

            $cellule = $locations->firstWhere('name', 'Cellule');
            $sac = $locations->firstWhere('name', 'Sac PS');
            $pochette = $locations->firstWhere('name', 'Pochette');

            // La hiérarchie est préservée.
            $this->assertNull($cellule->parent_id);
            $this->assertSame($cellule->id, $sac->parent_id);
            $this->assertSame($sac->id, $pochette->parent_id);
            $this->assertSame('sac', $sac->kind->value);
        });
    }

    public function test_vehicle_without_model_gets_no_generated_locations(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);

            return $admin;
        });

        $this->actingAs($admin)->post('http://caserne.localhost/vehicles', [
            'name' => 'VL-2',
            'status' => 'disponible',
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () {
            $vehicle = Vehicle::query()->where('name', 'VL-2')->firstOrFail();
            $this->assertSame(0, Location::query()->where('vehicle_id', $vehicle->id)->count());
        });
    }

    public function test_admin_can_create_a_model_with_a_nested_template(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);

            return $admin;
        });

        $this->actingAs($admin)->post('http://caserne.localhost/vehicle-models', [
            'brand' => 'Renault',
            'model' => 'Trafic',
            'year' => 2023,
            'coachbuilder' => 'Gruau',
            'display_order' => 0,
            'template' => [
                ['name' => 'Coffre', 'kind' => 'mobile', 'children' => [
                    ['name' => 'Sac O2', 'kind' => 'sac', 'children' => []],
                ]],
                ['name' => '', 'kind' => 'mobile', 'children' => []], // ligne vide ignorée
            ],
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () {
            // Le libellé est composé à partir des champs structurés.
            $model = VehicleModel::query()->where('name', 'Renault Trafic 2023 · Gruau')->firstOrFail();
            $this->assertSame('Renault', $model->brand);
            $this->assertSame('Trafic', $model->model);
            $this->assertSame(2023, $model->year);
            $this->assertSame('Gruau', $model->coachbuilder);

            $locations = $model->templateLocations()->get();

            // La ligne vide est ignorée ; les deux emplacements réels sont créés.
            $this->assertCount(2, $locations);
            $coffre = $locations->firstWhere('name', 'Coffre');
            $sac = $locations->firstWhere('name', 'Sac O2');
            $this->assertSame($coffre->id, $sac->parent_id);
            $this->assertSame('sac', $sac->kind->value);
        });
    }
}
