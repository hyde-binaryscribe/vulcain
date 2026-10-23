<?php

namespace Tests\Feature\IntraChatel;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Domain\Sectors\Sector;
use App\Models\ApiToken;
use App\Models\IntraChatelRecord;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Référentiels publiés par intra-Chatel : copie en lecture seule, associée aux fiches Vulkain. */
class ReferentialsTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $org;

    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.central_domains' => ['localhost']]);
        $this->org = Organisation::factory()->slug('ambu')->sector(Sector::AMBULANCE_PRIVEE)->create();
        $plain = $this->tenant()->runFor($this->org, fn () => ApiToken::issue('intra-Chatel', null)[1]);
        $this->headers = ['Authorization' => "Bearer {$plain}"];
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

    private function inOrg(callable $callback): mixed
    {
        return $this->tenant()->runFor($this->org, $callback);
    }

    private function record(string $kind, string $id): IntraChatelRecord
    {
        return $this->inOrg(fn () => IntraChatelRecord::query()->where('kind', $kind)->where('external_id', $id)->sole());
    }

    public function test_agents_are_mirrored_auto_linked_and_marked_missing(): void
    {
        $alice = $this->inOrg(fn () => User::factory()->create(['organisation_id' => $this->org->id, 'name' => 'Alice Jourdan', 'username' => 'AJ']));

        $this->putJson('/api/v1/intra-chatel/agents', ['data' => [
            ['id' => 12, 'initials' => 'aj', 'first_name' => 'Alice', 'last_name' => 'Jourdan'],
            ['id' => 13, 'initials' => 'MJM'],
        ]], $this->headers)
            ->assertOk()
            ->assertJsonPath('data', ['received' => 2, 'created' => 2, 'updated' => 0, 'missing' => 0, 'linked' => 1]);

        $this->assertSame($alice->id, $this->record('agent', '12')->linked_id);
        $this->assertSame('auto', $this->record('agent', '12')->link_mode);
        $this->assertSame('AJ', $alice->fresh()->username); // jamais modifiée

        // Nouvelle publication sans MJM : marqué absent, pas supprimé.
        $this->putJson('/api/v1/intra-chatel/agents', ['data' => [['id' => 12, 'initials' => 'AJ']]], $this->headers)
            ->assertOk()->assertJsonPath('data.missing', 1);
        $this->assertNotNull($this->record('agent', '13')->missing_since);

        $this->getJson('/api/v1/intra-chatel/agents', $this->headers)
            ->assertOk()
            ->assertJsonPath('data.0.id', '12')
            ->assertJsonPath('data.0.vulkain.name', 'Alice Jourdan')
            ->assertJsonPath('data.1.present', false);
    }

    public function test_vehicles_link_by_callsign_or_registration_and_are_never_modified(): void
    {
        [$a9, $a10] = $this->inOrg(fn () => [
            Vehicle::factory()->create(['organisation_id' => $this->org->id, 'name' => 'Ambulance 9', 'callsign' => 'A9', 'registration' => 'AA-111-AA']),
            Vehicle::factory()->create(['organisation_id' => $this->org->id, 'name' => 'Ambulance 10', 'callsign' => 'AMB10', 'registration' => 'BB-222-BB']),
        ]);

        $this->putJson('/api/v1/intra-chatel/vehicles', ['data' => [
            ['id' => 'v9', 'callsign' => 'a9', 'registration' => 'ZZ-999-ZZ', 'name' => 'Autre nom'],
            ['id' => 'v10', 'callsign' => 'A10', 'registration' => 'bb 222 bb'],
            ['id' => 'v11', 'callsign' => 'A11'],
        ]], $this->headers)->assertOk()->assertJsonPath('data.linked', 2);

        $this->assertSame($a9->id, $this->record('vehicle', 'v9')->linked_id);
        $this->assertSame($a10->id, $this->record('vehicle', 'v10')->linked_id);
        $this->assertNull($this->record('vehicle', 'v11')->linked_id);
        $this->assertSame(['Ambulance 9', 'A9', 'AA-111-AA'], [$a9->fresh()->name, $a9->fresh()->callsign, $a9->fresh()->registration]);

        $this->getJson('/api/v1/intra-chatel/vehicles', $this->headers)
            ->assertOk()
            ->assertJsonFragment(['id' => 'v10', 'vulkain' => ['uuid' => $a10->uuid, 'callsign' => 'AMB10', 'name' => 'Ambulance 10'], 'link_mode' => 'auto']);
    }

    public function test_the_service_api_uses_the_links(): void
    {
        [$vehicle, $user] = $this->inOrg(fn () => [
            Vehicle::factory()->create(['organisation_id' => $this->org->id, 'callsign' => 'AMB10', 'registration' => 'BB-222-BB']),
            User::factory()->create(['organisation_id' => $this->org->id, 'name' => 'Marie-Jeanne Martin', 'username' => null]),
        ]);
        $this->putJson('/api/v1/intra-chatel/vehicles', ['data' => [
            ['id' => 'v10', 'callsign' => 'A10', 'registration' => 'BB222BB'],
            ['id' => 'v12', 'callsign' => 'A12'],
        ]], $this->headers)->assertOk();
        $this->putJson('/api/v1/intra-chatel/agents', ['data' => [['id' => 13, 'initials' => 'MJM']]], $this->headers)->assertOk();
        $this->inOrg(fn () => $this->record('agent', '13')->update(['linked_id' => $user->id, 'link_mode' => 'manual']));

        // Indicatif intra-Chatel « A10 » → véhicule Vulkain AMB10 ; initiales MJM → agent associé.
        $this->postJson('/api/v1/services/open', ['external_id' => 'p1', 'vehicle_callsign' => 'A10', 'opened_by' => 'MJM'], $this->headers)
            ->assertCreated()
            ->assertJsonPath('data.vehicle.uuid', $vehicle->uuid)
            ->assertJsonPath('data.opened_by', 'Marie-Jeanne Martin');
        $this->assertSame($user->id, $this->inOrg(fn () => VehicleSession::query()->sole()->user_id));

        $this->postJson('/api/v1/services/close', ['external_id' => 'f1', 'vehicle_ref' => 'v10', 'closed_by' => 'MJM'], $this->headers)
            ->assertOk()->assertJsonPath('data.open', false);

        $this->postJson('/api/v1/services/open', ['external_id' => 'p2', 'vehicle_ref' => 'v12', 'opened_by' => 'MJM'], $this->headers)
            ->assertStatus(422)->assertJsonPath('code', 'vehicle_not_linked');
        $this->postJson('/api/v1/services/open', ['external_id' => 'p3', 'vehicle_ref' => 'inconnu', 'opened_by' => 'MJM'], $this->headers)
            ->assertNotFound();
    }

    public function test_publication_errors(): void
    {
        $this->putJson('/api/v1/intra-chatel/agents', ['data' => [['id' => 1], ['id' => 1, 'initials' => 'AJ']]], $this->headers)
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['data.0.initials', 'data.0.id']]);
        $this->putJson('/api/v1/intra-chatel/plannings', ['data' => []], $this->headers)->assertNotFound();
        $this->putJson('/api/v1/intra-chatel/agents', ['data' => []])->assertUnauthorized();
    }

    public function test_admin_links_a_record_by_hand(): void
    {
        [$admin, $vehicle] = $this->inOrg(function () {
            app(RoleProvisioner::class)->provision($this->org);
            $admin = User::factory()->create(['organisation_id' => $this->org->id]);
            $admin->assignRole(Rbac::ADMIN);

            return [$admin, Vehicle::factory()->create(['organisation_id' => $this->org->id, 'callsign' => 'AMB12'])];
        });
        $this->putJson('/api/v1/intra-chatel/vehicles', ['data' => [['id' => 'v12', 'callsign' => 'A12']]], $this->headers)->assertOk();
        $record = $this->record('vehicle', 'v12');

        $this->actingAs($admin)->get('http://ambu.localhost/settings/intra-chatel?kind=vehicle')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/IntraChatel')->where('counts.vehicle.unlinked', 1)->has('records', 1));

        // Un agent n'est pas un véhicule proposable.
        $this->actingAs($admin)->patch("http://ambu.localhost/settings/intra-chatel/{$record->id}", ['linked_id' => $admin->id + 999])
            ->assertSessionHasErrors('linked_id');

        $this->actingAs($admin)->patch("http://ambu.localhost/settings/intra-chatel/{$record->id}", ['linked_id' => $vehicle->id])
            ->assertSessionHasNoErrors();
        $this->assertSame([$vehicle->id, 'manual'], [$record->fresh()->linked_id, $record->fresh()->link_mode]);

        // Une nouvelle publication ne touche pas à l'association manuelle.
        $this->putJson('/api/v1/intra-chatel/vehicles', ['data' => [['id' => 'v12', 'callsign' => 'A12']]], $this->headers)->assertOk();
        $this->assertSame('manual', $record->fresh()->link_mode);
    }
}
