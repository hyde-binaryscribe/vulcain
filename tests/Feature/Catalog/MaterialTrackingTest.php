<?php

namespace Tests\Feature\Catalog;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Material;
use App\Models\MaterialItem;
use App\Models\Organisation;
use App\Models\StockLot;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialTrackingTest extends TestCase
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
    private function orgWithAdmin(string $slug = 'caserne'): array
    {
        $org = Organisation::factory()->slug($slug)->create();
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::ADMIN);

            return $u;
        });

        return [$org, $admin];
    }

    public function test_serial_material_stock_equals_unit_count(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $material = Material::factory()->create(['organisation_id' => $org->id, 'tracking_mode' => Material::MODE_SERIAL]);

        $this->actingAs($admin)->post("http://caserne.localhost/materials/{$material->id}/items", [
            'serial_number' => 'SN-1', 'status' => 'conforme',
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("http://caserne.localhost/materials/{$material->id}/items", [
            'serial_number' => 'SN-2', 'status' => 'conforme',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->tenant()->runFor($org, fn () => $material->fresh()->stockQuantity()));
    }

    public function test_lot_material_stock_equals_sum_of_quantities(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $material = Material::factory()->create(['organisation_id' => $org->id, 'tracking_mode' => Material::MODE_LOT, 'minimum_qty' => 10]);

        $this->actingAs($admin)->post("http://caserne.localhost/materials/{$material->id}/lots", [
            'lot_number' => 'L1', 'quantity' => 8, 'status' => 'conforme', 'expiry_date' => now()->addMonth()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("http://caserne.localhost/materials/{$material->id}/lots", [
            'lot_number' => 'L2', 'quantity' => 5, 'status' => 'conforme',
        ])->assertSessionHasNoErrors();

        $material = $this->tenant()->runFor($org, fn () => $material->fresh());
        $this->assertSame(13, $this->tenant()->runFor($org, fn () => $material->stockQuantity()));
    }

    public function test_quantity_material_stock_can_be_set(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $material = Material::factory()->create(['organisation_id' => $org->id, 'tracking_mode' => Material::MODE_QUANTITY]);

        $this->actingAs($admin)->patch("http://caserne.localhost/materials/{$material->id}/stock", [
            'current_qty' => 7,
        ])->assertSessionHasNoErrors();

        $this->assertSame(7, $material->fresh()->current_qty);
    }

    public function test_material_detail_page_renders(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $material = Material::factory()->create(['organisation_id' => $org->id, 'tracking_mode' => Material::MODE_SERIAL]);

        $this->actingAs($admin)->get("http://caserne.localhost/materials/{$material->id}")->assertOk();
    }

    public function test_cannot_modify_a_unit_of_another_organisation(): void
    {
        [, $admin] = $this->orgWithAdmin('caserne');
        $otherOrg = Organisation::factory()->slug('autre')->create();
        $foreignItem = MaterialItem::factory()->create([
            'organisation_id' => $otherOrg->id,
            'material_id' => Material::factory()->create(['organisation_id' => $otherOrg->id])->id,
        ]);

        $this->actingAs($admin)->delete("http://caserne.localhost/material-items/{$foreignItem->id}")->assertNotFound();
    }

    public function test_cannot_modify_a_lot_of_another_organisation(): void
    {
        [, $admin] = $this->orgWithAdmin('caserne');
        $otherOrg = Organisation::factory()->slug('autre')->create();
        $foreignLot = StockLot::factory()->create([
            'organisation_id' => $otherOrg->id,
            'material_id' => Material::factory()->create(['organisation_id' => $otherOrg->id])->id,
        ]);

        $this->actingAs($admin)->delete("http://caserne.localhost/stock-lots/{$foreignLot->id}")->assertNotFound();
    }
}
