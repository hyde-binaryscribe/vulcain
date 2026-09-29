<?php

namespace Tests\Feature\Fleet;

use App\Domain\Fleet\DisinfectionStatus;
use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Domain\Support\Severity;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DisinfectionTest extends TestCase
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

    public function test_status_computation(): void
    {
        // En retard (dernière il y a 10 j, périodicité 7 j) => critique.
        $overdue = DisinfectionStatus::compute(Carbon::now()->subDays(10), 7);
        $this->assertSame(Severity::CRITICAL, $overdue->severity);
        $this->assertSame('overdue', $overdue->state);

        // À prévoir (échéance dans 2 j) => important.
        $soon = DisinfectionStatus::compute(Carbon::now()->subDays(6), 7);
        $this->assertSame(Severity::WARNING, $soon->severity);

        // À jour => aucune alerte.
        $ok = DisinfectionStatus::compute(Carbon::now()->subDay(), 7);
        $this->assertNull($ok->severity);

        // Sans périodicité => aucune échéance.
        $none = DisinfectionStatus::compute(Carbon::now()->subDays(100), null);
        $this->assertSame('none', $none->state);
    }

    public function test_operator_can_record_a_disinfection(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->post("http://caserne.localhost/vehicles/{$vehicle->id}/disinfections", [
            'type' => 'desinfection',
            'performed_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'notes' => 'Surfaces + brancard',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('disinfection_records', [
            'organisation_id' => $org->id,
            'vehicle_id' => $vehicle->id,
            'user_id' => $admin->id,
            'type' => 'desinfection',
            'notes' => 'Surfaces + brancard',
        ]);
    }

    public function test_future_date_is_rejected(): void
    {
        [$org, $admin] = $this->orgWithAdmin();
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->post("http://caserne.localhost/vehicles/{$vehicle->id}/disinfections", [
            'type' => 'desinfection',
            'performed_at' => now()->addDays(5)->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('performed_at');
    }

    public function test_recording_requires_permission(): void
    {
        [$org] = $this->orgWithAdmin();
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
        $stranger = $this->tenant()->runFor($org, fn () => User::factory()->create(['organisation_id' => $org->id]));

        $this->actingAs($stranger)->post("http://caserne.localhost/vehicles/{$vehicle->id}/disinfections", [
            'type' => 'desinfection',
            'performed_at' => now()->format('Y-m-d\TH:i'),
        ])->assertForbidden();
    }

    public function test_overdue_disinfection_surfaces_on_dashboard(): void
    {
        [$org, $admin] = $this->orgWithAdmin();

        $this->tenant()->runFor($org, function () use ($org) {
            VehicleType::create(['name' => 'VSAV', 'disinfection_interval_days' => 7]);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'type' => 'VSAV']);
            $vehicle->disinfections()->create([
                'type' => 'desinfection',
                'performed_at' => now()->subDays(10), // > 7 j => en retard
            ]);
        });

        $this->actingAs($admin)->get('http://caserne.localhost/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('alerts.disinfection_overdue', 1));
    }
}
