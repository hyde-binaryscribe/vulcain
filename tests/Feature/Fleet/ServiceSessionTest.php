<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceSessionTest extends TestCase
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

    public function test_manager_sees_open_sessions_and_history(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $v1 = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $v2 = Vehicle::factory()->create(['organisation_id' => $org->id]);

            // Une session ouverte…
            VehicleSession::create(['vehicle_id' => $v1->id, 'user_id' => $agent->id, 'opened_at' => now()]);
            // …et une clôturée avec des réponses de vérification.
            VehicleSession::create([
                'vehicle_id' => $v2->id, 'user_id' => $agent->id, 'opened_at' => now()->subDay(),
                'closed_at' => now(), 'closed_by' => $agent->id, 'close_reason' => 'manual',
                'open_responses' => [['label' => 'DAE présent', 'type' => 'tristate', 'value' => 'nok', 'alert' => true]],
            ]);

            return $admin;
        });

        $this->actingAs($admin)->get('http://caserne.localhost/suivi-service')
            ->assertInertia(fn (Assert $p) => $p
                ->component('ServiceSessions/Index')
                ->has('open', 1)
                ->has('history', 1)
                ->where('history.0.open_responses.0.label', 'DAE présent')
                ->where('history.0.open_responses.0.display', 'NOK')
                ->where('history.0.open_responses.0.alert', true));
    }
}
