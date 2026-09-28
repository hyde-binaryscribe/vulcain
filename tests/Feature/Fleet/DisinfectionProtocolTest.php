<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Domain\Sectors\Sector;
use App\Models\DisinfectionProtocol;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DisinfectionProtocolTest extends TestCase
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
    private function orgWithAdmin(Sector $sector, string $slug = 'ambu'): array
    {
        $org = Organisation::factory()->slug($slug)->create(['sector' => $sector]);
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::ADMIN);

            return $u;
        });

        return [$org, $admin];
    }

    public function test_ambulance_prive_seeds_ars_protocols_on_first_visit(): void
    {
        [, $admin] = $this->orgWithAdmin(Sector::AMBULANCE_PRIVEE);

        $this->actingAs($admin)->get('http://ambu.localhost/disinfection-protocols')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('DisinfectionProtocols/Index')
                ->has('protocols', 3));
    }

    public function test_feature_is_hidden_for_other_sectors(): void
    {
        [, $admin] = $this->orgWithAdmin(Sector::SDIS, 'cis');

        $this->actingAs($admin)->get('http://cis.localhost/disinfection-protocols')->assertNotFound();
    }

    public function test_a_disinfection_can_reference_a_protocol(): void
    {
        [$org, $admin] = $this->orgWithAdmin(Sector::AMBULANCE_PRIVEE);

        [$vehicle, $protocol] = $this->tenant()->runFor($org, function () use ($org) {
            $v = Vehicle::factory()->create(['organisation_id' => $org->id, 'type' => 'Ambulance type A']);
            $p = DisinfectionProtocol::create(['name' => 'Renforcée', 'type' => 'bio_nettoyage']);

            return [$v, $p];
        });

        $this->actingAs($admin)->post("http://ambu.localhost/vehicles/{$vehicle->id}/disinfections", [
            'type' => 'bio_nettoyage',
            'disinfection_protocol_id' => $protocol->id,
            'performed_at' => now()->format('Y-m-d\TH:i'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('disinfection_records', [
            'vehicle_id' => $vehicle->id,
            'disinfection_protocol_id' => $protocol->id,
        ]);
    }
}
