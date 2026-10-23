<?php

namespace Tests\Feature\Catalog;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Location;
use App\Models\Material;
use App\Models\MaterialType;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialIdentifierTest extends TestCase
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
    private function orgWithRole(string $role): array
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $user = $this->tenant()->runFor($org, function () use ($org, $role) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole($role);

            return $u;
        });

        return [$org, $user];
    }

    public function test_code_is_derived_from_consonants(): void
    {
        $this->assertSame('BRN', MaterialType::generateCode('Brancard'));
        $this->assertSame('MAT', MaterialType::generateCode('???'));
    }

    public function test_a_new_type_receives_an_auto_code(): void
    {
        [$org] = $this->orgWithRole(Rbac::ADMIN);

        $type = $this->tenant()->runFor($org, fn () => MaterialType::factory()->create([
            'organisation_id' => $org->id, 'name' => 'Brancard', 'code' => null,
        ]));

        $this->assertSame('BRN', $type->code);
    }

    public function test_generated_codes_are_unique_per_organisation(): void
    {
        [$org] = $this->orgWithRole(Rbac::ADMIN);

        [$first, $second] = $this->tenant()->runFor($org, fn () => [
            MaterialType::factory()->create(['organisation_id' => $org->id, 'name' => 'Brancard', 'code' => null]),
            MaterialType::factory()->create(['organisation_id' => $org->id, 'name' => 'Brancart', 'code' => null]),
        ]);

        $this->assertSame('BRN', $first->code);
        $this->assertNotSame($first->code, $second->code);
    }

    public function test_material_in_a_vehicle_gets_an_identifier(): void
    {
        [$org] = $this->orgWithRole(Rbac::ADMIN);

        $identifier = $this->tenant()->runFor($org, function () use ($org) {
            $type = MaterialType::factory()->create([
                'organisation_id' => $org->id, 'name' => 'Brancard', 'code' => 'BRCD',
            ]);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'callsign' => 'A09']);
            $location = Location::factory()->create(['organisation_id' => $org->id, 'vehicle_id' => $vehicle->id]);

            return Material::factory()->create([
                'organisation_id' => $org->id,
                'material_type_id' => $type->id,
                'location_id' => $location->id,
                'name' => 'Brancard principal',
            ])->identifier;
        });

        $this->assertSame('BRCD_A09_01', $identifier);
    }

    public function test_identifier_sequence_increments_per_type_and_vehicle(): void
    {
        [$org] = $this->orgWithRole(Rbac::ADMIN);

        $identifiers = $this->tenant()->runFor($org, function () use ($org) {
            $type = MaterialType::factory()->create([
                'organisation_id' => $org->id, 'name' => 'Brancard', 'code' => 'BRCD',
            ]);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'callsign' => 'A09']);
            $location = Location::factory()->create(['organisation_id' => $org->id, 'vehicle_id' => $vehicle->id]);

            return collect(range(1, 2))->map(fn () => Material::factory()->create([
                'organisation_id' => $org->id,
                'material_type_id' => $type->id,
                'location_id' => $location->id,
            ])->identifier)->all();
        });

        $this->assertSame(['BRCD_A09_01', 'BRCD_A09_02'], $identifiers);
    }
}
