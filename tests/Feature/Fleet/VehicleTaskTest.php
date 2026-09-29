<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleTask;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleTaskTest extends TestCase
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

    public function test_manager_adds_a_task_and_agent_completes_it(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$admin, $agent, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

            return [$admin, $agent, $vehicle];
        });

        // Le responsable ajoute une tâche.
        $this->actingAs($admin)->post("http://caserne.localhost/vehicles/{$vehicle->id}/tasks", [
            'title' => 'Rapporter la bouteille O2',
        ])->assertSessionHasNoErrors();

        $taskId = $this->tenant()->runFor($org, function () use ($vehicle) {
            $task = VehicleTask::query()->where('vehicle_id', $vehicle->id)->firstOrFail();
            $this->assertNull($task->done_at);

            return $task->id;
        });

        // L'agent la coche depuis le terrain.
        $this->actingAs($agent)->post("http://caserne.localhost/vehicles/{$vehicle->id}/tasks/{$taskId}/complete")
            ->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () use ($taskId, $agent) {
            $task = VehicleTask::query()->findOrFail($taskId);
            $this->assertNotNull($task->done_at);
            $this->assertSame($agent->id, $task->done_by);
        });
    }

    public function test_agent_cannot_create_a_task(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER); // pas de vehicles.manage
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

            return [$agent, $vehicle];
        });

        $this->actingAs($agent)->post("http://caserne.localhost/vehicles/{$vehicle->id}/tasks", [
            'title' => 'Test',
        ])->assertForbidden();
    }
}
