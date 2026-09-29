<?php

namespace Tests\Feature\Events;

use App\Domain\Events\EcheanceEvents;
use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\DisinfectionProtocol;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcheanceEventsTest extends TestCase
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

    public function test_overdue_disinfection_generates_and_closes_an_echeance_event(): void
    {
        $org = Organisation::factory()->slug('cis')->create();

        $vehicle = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $protocol = DisinfectionProtocol::create(['name' => 'Hebdo', 'type' => 'desinfection', 'frequency_days' => 7]);
            $vehicle->disinfectionProtocols()->attach($protocol->id, ['organisation_id' => $org->id]);
            $vehicle->disinfections()->create(['type' => 'desinfection', 'disinfection_protocol_id' => $protocol->id, 'performed_at' => now()->subDays(10)]);

            return $vehicle;
        });

        // Première génération : un événement d'échéance est créé.
        $r1 = $this->tenant()->runFor($org, fn () => EcheanceEvents::generate($org));
        $this->assertSame(1, $r1['created']);
        $this->assertDatabaseHas('events', [
            'organisation_id' => $org->id,
            'source_key' => 'ech:disinfection:'.$vehicle->id,
            'status' => 'a_traiter',
        ]);

        // Deuxième passage : pas de doublon.
        $r2 = $this->tenant()->runFor($org, fn () => EcheanceEvents::generate($org));
        $this->assertSame(0, $r2['created']);
        $this->assertSame(1, Event::query()->where('organisation_id', $org->id)->where('source_key', 'ech:disinfection:'.$vehicle->id)->count());

        // Désinfection refaite : l'échéance est levée -> événement clôturé.
        $this->tenant()->runFor($org, function () use ($vehicle) {
            $protocolId = $vehicle->disinfectionProtocols()->value('disinfection_protocols.id');
            $vehicle->disinfections()->create(['type' => 'desinfection', 'disinfection_protocol_id' => $protocolId, 'performed_at' => now()]);
        });
        $r3 = $this->tenant()->runFor($org, fn () => EcheanceEvents::generate($org));
        $this->assertSame(1, $r3['closed']);
        $this->assertDatabaseHas('events', [
            'source_key' => 'ech:disinfection:'.$vehicle->id,
            'status' => 'resolu',
        ]);
    }

    public function test_body_damage_report_creates_an_event(): void
    {
        $org = Organisation::factory()->slug('cis')->create();
        $org->settings = array_merge($org->settings ?? [], ['body_inspection_enabled' => true]);
        $org->save();

        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::ADMIN);

            return $u;
        });
        $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->post("http://cis.localhost/vehicles/{$vehicle->id}/body-damages", [
            'view' => 'avant',
            'pos_x' => 40,
            'pos_y' => 55,
            'description' => 'Rayure pare-chocs',
        ])->assertSessionHasNoErrors();

        $damageId = \App\Models\BodyDamage::query()->where('vehicle_id', $vehicle->id)->value('id');
        $this->assertDatabaseHas('events', [
            'organisation_id' => $org->id,
            'source_key' => 'body:'.$damageId,
            'type' => 'anomalie',
            'vehicle_id' => $vehicle->id,
            'created_by' => $admin->id,
        ]);
    }
}
