<?php

namespace Tests\Feature\Pharmacy;

use App\Domain\Catalog\MaterialStatus;
use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PharmacyTest extends TestCase
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

    public function test_pharmacist_can_declare_a_consumable(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $pharmacist = $this->userWithRole($org, Rbac::PHARMACY);

        $this->actingAs($pharmacist)->post('http://caserne.localhost/pharmacy/consumables', [
            'name' => 'Sérum physiologique 500 ml',
            'reference' => 'PHA-SERUM-500',
            'minimum_qty' => 5,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('materials', [
            'organisation_id' => $org->id,
            'name' => 'Sérum physiologique 500 ml',
            'tracking_mode' => Material::MODE_LOT,
        ]);
    }

    public function test_verifier_cannot_access_pharmacy(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $verifier = $this->userWithRole($org, Rbac::VERIFIER);

        $this->actingAs($verifier)->get('http://caserne.localhost/pharmacy')->assertForbidden();
    }

    public function test_pharmacy_dashboard_reports_expiry_and_restock(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $pharmacist = $this->userWithRole($org, Rbac::PHARMACY);

        $this->tenant()->runFor($org, function () use ($org) {
            // Consommable sous le seuil (stock 2 / mini 20) et péremption proche.
            $material = Material::create([
                'organisation_id' => $org->id,
                'name' => 'Compresses stériles',
                'tracking_mode' => Material::MODE_LOT,
                'minimum_qty' => 20,
                'status' => MaterialStatus::CONFORME->value,
            ]);
            $material->lots()->create([
                'organisation_id' => $org->id,
                'lot_number' => 'A-1',
                'quantity' => 2,
                'expiry_date' => now()->addDays(10),
                'status' => MaterialStatus::CONFORME->value,
            ]);
        });

        $this->actingAs($pharmacist)->get('http://caserne.localhost/pharmacy/tableau-de-bord')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Pharmacy/Dashboard')
                ->where('kpis.references', 1)
                ->where('kpis.low_stock', 1)
                ->where('kpis.expiring_soon', 1)
                ->has('expiryChart')
                ->has('restock', 1)
                ->where('restock.0.name', 'Compresses stériles')
                ->where('restock.0.tone', 'critical'));
    }
}
