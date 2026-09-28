<?php

namespace Tests\Feature\Fleet;

use App\Domain\Fleet\MaintenanceStatus;
use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Domain\Support\Severity;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MaintenanceTest extends TestCase
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

    public function test_status_combines_date_and_mileage(): void
    {
        $overdueDate = MaintenanceStatus::compute(Carbon::now()->subDay(), null, null);
        $this->assertSame(Severity::CRITICAL, $overdueDate->severity);

        $overdueKm = MaintenanceStatus::compute(null, 100000, 100000);
        $this->assertSame(Severity::CRITICAL, $overdueKm->severity);

        $soon = MaintenanceStatus::compute(Carbon::now()->addDays(5), null, null);
        $this->assertSame(Severity::WARNING, $soon->severity);

        $ok = MaintenanceStatus::compute(Carbon::now()->addMonths(6), null, null);
        $this->assertNull($ok->severity);
    }

    public function test_operator_can_record_maintenance_and_updates_mileage(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'mileage' => 50000]);

        $this->actingAs($admin)->post("http://caserne.localhost/vehicles/{$vehicle->id}/maintenances", [
            'type' => 'vidange',
            'performed_at' => now()->toDateString(),
            'mileage' => 62000,
            'cost' => 180.50,
            'provider' => 'Garage central',
            'next_due_mileage' => 72000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('maintenance_records', [
            'vehicle_id' => $vehicle->id,
            'type' => 'vidange',
            'next_due_mileage' => 72000,
        ]);
        // Le compteur du véhicule est mis à jour.
        $this->assertSame(62000, $vehicle->fresh()->mileage);
    }

    public function test_overdue_maintenance_surfaces_on_dashboard(): void
    {
        [$org, $admin] = $this->orgWithAdmin();

        $this->tenant()->runFor($org, function () use ($org) {
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'mileage' => 80000]);
            $vehicle->maintenances()->create([
                'type' => 'controle_technique',
                'performed_at' => now()->subYear(),
                'next_due_at' => now()->subWeek(), // dépassé
            ]);
        });

        $this->actingAs($admin)->get('http://caserne.localhost/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alerts.maintenance_overdue', 1));
    }

    public function test_mileage_due_surfaces_on_dashboard(): void
    {
        [$org, $admin] = $this->orgWithAdmin();

        $this->tenant()->runFor($org, function () use ($org) {
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'mileage' => 100000]);
            $vehicle->maintenances()->create([
                'type' => 'vidange',
                'performed_at' => now()->subMonths(6),
                'next_due_mileage' => 95000, // compteur (100000) déjà au-delà
            ]);
        });

        $this->actingAs($admin)->get('http://caserne.localhost/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alerts.maintenance_overdue', 1));
    }
}
