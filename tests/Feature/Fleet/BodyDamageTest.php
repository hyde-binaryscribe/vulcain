<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\BodyDamage;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BodyDamageTest extends TestCase
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

    public function test_agent_in_service_reports_a_body_damage_with_photo(): void
    {
        Storage::fake('local');
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            VehicleSession::create(['vehicle_id' => $vehicle->id, 'user_id' => $agent->id, 'opened_at' => now()]);

            return [$agent, $vehicle];
        });

        $this->actingAs($agent)->post("http://caserne.localhost/vehicles/{$vehicle->id}/body-damages", [
            'view' => 'gauche',
            'pos_x' => 42.5,
            'pos_y' => 60,
            'description' => 'Rayure profonde porte latérale',
            'photo' => UploadedFile::fake()->image('choc.jpg'),
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () use ($vehicle) {
            $damage = BodyDamage::query()->where('vehicle_id', $vehicle->id)->firstOrFail();
            $this->assertSame('gauche', $damage->view);
            $this->assertSame('ouverte', $damage->status);
            $this->assertNotNull($damage->photo_path);
            Storage::disk('local')->assertExists($damage->photo_path);
        });
    }

    public function test_agent_without_session_cannot_report(): void
    {
        Storage::fake('local');
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

            return [$agent, $vehicle];
        });

        $this->actingAs($agent)->post("http://caserne.localhost/vehicles/{$vehicle->id}/body-damages", [
            'view' => 'avant',
            'pos_x' => 10,
            'pos_y' => 10,
            'description' => 'Choc pare-chocs',
        ])->assertForbidden();

        $this->assertSame(0, BodyDamage::withoutGlobalScopes()->count());
    }

    public function test_manager_resolves_and_deletes_a_body_damage(): void
    {
        Storage::fake('local');
        $org = Organisation::factory()->slug('caserne')->create();
        [$manager, $vehicle, $damage] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $manager = User::factory()->create(['organisation_id' => $org->id]);
            $manager->assignRole(Rbac::ADMIN);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $damage = $vehicle->bodyDamages()->create([
                'view' => 'droite', 'pos_x' => 20, 'pos_y' => 30,
                'description' => 'Enfoncement aile', 'status' => 'ouverte',
            ]);

            return [$manager, $vehicle, $damage];
        });

        $this->actingAs($manager)->post("http://caserne.localhost/vehicles/{$vehicle->id}/body-damages/{$damage->id}/resolve")
            ->assertSessionHasNoErrors();
        $this->assertSame('resolue', $damage->fresh()->status);
        $this->assertNotNull($damage->fresh()->resolved_at);

        $this->actingAs($manager)->delete("http://caserne.localhost/vehicles/{$vehicle->id}/body-damages/{$damage->id}")
            ->assertSessionHasNoErrors();
        $this->assertSame(0, BodyDamage::withoutGlobalScopes()->count());
    }

    public function test_agent_cannot_delete_body_damage(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle, $damage] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            VehicleSession::create(['vehicle_id' => $vehicle->id, 'user_id' => $agent->id, 'opened_at' => now()]);
            $damage = $vehicle->bodyDamages()->create([
                'view' => 'dessus', 'pos_x' => 50, 'pos_y' => 50,
                'description' => 'Impact toit', 'status' => 'ouverte',
            ]);

            return [$agent, $vehicle, $damage];
        });

        $this->actingAs($agent)->delete("http://caserne.localhost/vehicles/{$vehicle->id}/body-damages/{$damage->id}")
            ->assertForbidden();

        $this->assertSame(1, BodyDamage::withoutGlobalScopes()->count());
    }
}
