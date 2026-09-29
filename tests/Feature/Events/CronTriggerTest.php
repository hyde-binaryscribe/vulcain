<?php

namespace Tests\Feature\Events;

use App\Models\DisinfectionProtocol;
use App\Models\Organisation;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronTriggerTest extends TestCase
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

    public function test_cron_endpoint_requires_a_valid_token(): void
    {
        config(['security.cron.token' => 'secret-token']);

        $this->get('http://localhost/cron/echeances')->assertNotFound();
        $this->get('http://localhost/cron/echeances?token=wrong')->assertNotFound();
    }

    public function test_cron_endpoint_is_disabled_without_configured_token(): void
    {
        config(['security.cron.token' => null]);

        $this->get('http://localhost/cron/echeances?token=anything')->assertNotFound();
    }

    public function test_cron_endpoint_generates_echeance_events(): void
    {
        config(['security.cron.token' => 'secret-token']);

        $org = Organisation::factory()->slug('cis')->create();
        $vehicle = $this->tenant()->runFor($org, function () use ($org) {
            $v = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $p = DisinfectionProtocol::create(['name' => 'Hebdo', 'type' => 'desinfection', 'frequency_days' => 7]);
            $v->disinfectionProtocols()->attach($p->id, ['organisation_id' => $org->id]);
            $v->disinfections()->create(['type' => 'desinfection', 'disinfection_protocol_id' => $p->id, 'performed_at' => now()->subDays(10)]);

            return $v;
        });

        $this->get('http://localhost/cron/echeances?token=secret-token')
            ->assertOk()
            ->assertSee('créé');

        $this->assertDatabaseHas('events', [
            'organisation_id' => $org->id,
            'source_key' => 'ech:disinfection:'.$vehicle->id,
            'status' => 'a_traiter',
        ]);
    }
}
