<?php

namespace Tests\Feature\Itineris;

use App\Domain\Sectors\Sector;
use App\Models\ApiToken;
use App\Models\DisinfectionRecord;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API v1 : vérifications des véhicules pour Itineris (lecture seule). */
class ChecksTest extends TestCase
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

    public function test_checks_give_last_service_disinfection_and_open_anomalies(): void
    {
        $org = Organisation::factory()->slug('ambu')->sector(Sector::AMBULANCE_PRIVEE)->create();
        $other = Organisation::factory()->slug('autre')->sector(Sector::AMBULANCE_PRIVEE)->create();
        $tenant = app(TenantContext::class);

        [$vehicle, $plain] = $tenant->runFor($org, function () use ($org) {
            $julien = User::factory()->create(['organisation_id' => $org->id, 'name' => 'Julien D.']);
            $sarah = User::factory()->create(['organisation_id' => $org->id, 'name' => 'Sarah M.']);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id, 'name' => 'AMB 1']);
            VehicleSession::create([
                'vehicle_id' => $vehicle->id, 'user_id' => $julien->id, 'partner_user_id' => $sarah->id,
                'opened_at' => now()->subDay(), 'closed_at' => now()->subDay()->addHours(10), 'closed_by' => $julien->id,
            ]);
            VehicleSession::create([
                'vehicle_id' => $vehicle->id, 'user_id' => $julien->id, 'partner_user_id' => $sarah->id, 'opened_at' => now(),
                'open_responses' => [
                    ['label' => 'Oxygène', 'type' => 'gauge', 'value' => '80', 'photo_path' => null, 'alert' => null],
                    ['label' => 'Sac de soins', 'type' => 'tristate', 'value' => 'nok', 'photo_path' => null,
                        'alert' => ['severity' => 'warning', 'message' => 'Gants M à réassortir']],
                    ['label' => 'Carrosserie', 'type' => 'photo', 'value' => null, 'photo_path' => 'protocols/1/x.jpg', 'alert' => null],
                ],
            ]);
            DisinfectionRecord::factory()->create(['vehicle_id' => $vehicle->id, 'user_id' => $sarah->id, 'type' => 'desinfection', 'performed_at' => now()->subHour()]);
            Event::factory()->create(['organisation_id' => $org->id, 'vehicle_id' => $vehicle->id, 'title' => 'Bruit train avant']);
            Event::factory()->create(['organisation_id' => $org->id, 'vehicle_id' => $vehicle->id, 'title' => 'Réparé', 'status' => 'resolu']);

            return [$vehicle, ApiToken::issue('Itineris', null)[1]];
        });
        $tenant->runFor($other, fn () => Vehicle::factory()->create(['organisation_id' => $other->id]));

        $response = $this->getJson('/api/v1/vehicles/checks', ['Authorization' => "Bearer {$plain}"])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $vehicle->uuid)
            ->assertJsonPath('data.0.service.open', true)
            ->assertJsonPath('data.0.service.opened_by', 'Julien D.')
            ->assertJsonPath('data.0.service.partner', 'Sarah M.')
            ->assertJsonPath('data.0.service.checks.1.alert.message', 'Gants M à réassortir')
            ->assertJsonPath('data.0.disinfection.last.0.label', 'Désinfection')
            ->assertJsonPath('data.0.disinfection.last.0.by', 'Sarah M.')
            ->assertJsonCount(1, 'data.0.anomalies')
            ->assertJsonPath('data.0.anomalies.0.title', 'Bruit train avant');

        // Niveau d'oxygène reconnu au libellé de la jauge (aucun réglage), relevé de la prise de service.
        $response->assertJsonPath('data.0.service.checks.0.key', 'o2')
            ->assertJsonPath('data.0.o2.value', '80')
            ->assertJsonPath('data.0.o2.label', 'Oxygène');
        $this->assertNotNull($response->json('data.0.o2.at'));

        // Jamais de photo ni de chemin de fichier.
        $this->assertStringNotContainsString('protocols/', $response->getContent());
    }

    public function test_checks_require_a_token(): void
    {
        $this->getJson('/api/v1/vehicles/checks')->assertUnauthorized();
    }

    public function test_o2_marked_on_a_field_wins_and_end_of_service_is_more_recent(): void
    {
        $org = Organisation::factory()->slug('ambu')->sector(Sector::AMBULANCE_PRIVEE)->create();
        $tenant = app(TenantContext::class);
        $plain = $tenant->runFor($org, function () use ($org) {
            $user = User::factory()->create(['organisation_id' => $org->id]);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            VehicleSession::create([
                'vehicle_id' => $vehicle->id, 'user_id' => $user->id, 'opened_at' => now()->subHours(10), 'closed_at' => now(), 'closed_by' => $user->id,
                'open_responses' => [['label' => 'Bouteille B15', 'type' => 'gauge', 'value' => '180', 'percent' => 90, 'unit' => 'bar', 'share' => 'o2']],
                'close_responses' => [['label' => 'Bouteille B15', 'type' => 'gauge', 'value' => '60', 'percent' => 30, 'unit' => 'bar', 'share' => 'o2']],
            ]);

            return ApiToken::issue('Itineris', null)[1];
        });

        $this->getJson('/api/v1/vehicles/checks', ['Authorization' => "Bearer {$plain}"])
            ->assertOk()
            ->assertJsonPath('data.0.o2.value', '60')
            ->assertJsonPath('data.0.o2.percent', 30)
            ->assertJsonPath('data.0.o2.unit', 'bar');
    }
}
