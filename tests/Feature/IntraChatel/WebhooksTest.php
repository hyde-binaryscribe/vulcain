<?php

namespace Tests\Feature\IntraChatel;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Domain\Sectors\Sector;
use App\Domain\Webhooks\Webhooks;
use App\Models\ApiToken;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Notifications sortantes signées : prises et fins de service vers intra-Chatel. */
class WebhooksTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://intra-chatel.example/api/vulkain/webhook';

    private Organisation $org;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.central_domains' => ['localhost'], 'services.webhooks.allow_private_hosts' => true]);
        $this->org = Organisation::factory()->slug('ambu')->sector(Sector::AMBULANCE_PRIVEE)->create();
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

    /** @return array{0: Vehicle, 1: array, 2: WebhookEndpoint} */
    private function setUpLink(): array
    {
        return $this->tenant()->runFor($this->org, fn () => [
            Vehicle::factory()->create(['organisation_id' => $this->org->id, 'callsign' => 'A10']),
            ['Authorization' => 'Bearer '.ApiToken::issue('intra-Chatel', null)[1]],
            WebhookEndpoint::create(['name' => 'intra-Chatel', 'url' => self::URL, 'secret' => 'whsec_test', 'is_active' => true]),
        ]);
    }

    public function test_opening_and_closing_send_signed_notifications(): void
    {
        Http::fake([self::URL => Http::response('', 204)]);
        [$vehicle, $headers] = $this->setUpLink();

        $this->postJson('/api/v1/services/open', ['external_id' => 'p1', 'vehicle_callsign' => 'A10', 'opened_by' => 'AJ'], $headers)->assertCreated();
        $this->postJson('/api/v1/services/close', ['external_id' => 'f1', 'vehicle_callsign' => 'A10', 'closed_by' => 'AJ'], $headers)->assertOk();

        $sent = Http::recorded()->map(fn ($pair) => $pair[0]);
        $this->assertSame(['service.opened', 'service.closed'], $sent->map(fn (Request $r) => $r->header('X-Vulkain-Event')[0])->all());

        /** @var Request $closed */
        $closed = $sent[1];
        $this->assertSame('service.closed', $closed['event']);
        $this->assertSame($vehicle->uuid, $closed['data']['vehicle']['uuid']);
        $this->assertSame('intra_chatel', $closed['data']['source']);
        $this->assertSame('f1', $closed['data']['close_external_id']);
        $this->assertSame($closed['id'], $closed->header('X-Vulkain-Delivery')[0]);

        // Signature vérifiable par le destinataire : HMAC-SHA256(secret, "t.corps").
        preg_match('/^t=(\d+),v1=([0-9a-f]{64})$/', $closed->header('X-Vulkain-Signature')[0], $m);
        $this->assertSame(hash_hmac('sha256', $m[1].'.'.$closed->body(), 'whsec_test'), $m[2]);

        $this->assertSame(2, $this->tenant()->runFor($this->org, fn () => WebhookDelivery::query()->where('status', 'delivered')->count()));
    }

    public function test_a_failed_delivery_is_retried_later_then_abandoned(): void
    {
        Http::fake([self::URL => Http::sequence()->push('', 500)->push('', 200)]);
        [, $headers] = $this->setUpLink();

        $this->postJson('/api/v1/services/open', ['external_id' => 'p1', 'vehicle_callsign' => 'A10', 'opened_by' => 'AJ'], $headers)->assertCreated();

        $delivery = $this->tenant()->runFor($this->org, fn () => WebhookDelivery::query()->sole());
        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame('Réponse HTTP 500', $delivery->last_error);

        // Pas encore dû : rien n'est renvoyé.
        $this->artisan('vulcain:retry-webhooks')->assertSuccessful();
        $this->assertSame(1, $delivery->fresh()->attempts);

        $this->travel(2)->minutes();
        $this->artisan('vulcain:retry-webhooks')->assertSuccessful();
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertSame(2, $delivery->fresh()->attempts);

        // Un destinataire toujours en panne : abandon après la dernière tentative.
        Http::fake([self::URL => Http::response('', 503)]);
        $failing = $this->tenant()->runFor($this->org, fn () => Webhooks::queue(WebhookEndpoint::query()->sole(), 'ping', []));
        foreach (range(0, count(Webhooks::BACKOFF_MINUTES)) as $ignored) {
            Webhooks::send($failing->fresh());
        }
        $this->assertSame('failed', $failing->fresh()->status);
        $this->assertNull($failing->fresh()->next_attempt_at);
    }

    public function test_private_addresses_are_refused_unless_allowed(): void
    {
        config(['services.webhooks.allow_private_hosts' => false]);
        $this->assertFalse(Webhooks::hostAllowed('http://127.0.0.1/hook'));
        $this->assertFalse(Webhooks::hostAllowed('http://169.254.169.254/latest/meta-data'));
        $this->assertFalse(Webhooks::hostAllowed('http://10.0.0.4/hook'));
        $this->assertFalse(Webhooks::hostAllowed('http://[::1]/hook'));
        $this->assertTrue(Webhooks::hostAllowed('https://8.8.8.8/hook'));

        config(['services.webhooks.allow_private_hosts' => true]);
        $this->assertTrue(Webhooks::hostAllowed('http://10.0.0.4/hook'));
    }

    public function test_admin_adds_tests_and_deletes_an_address_from_settings(): void
    {
        Http::fake([self::URL => Http::response('ok', 200)]);
        $admin = $this->tenant()->runFor($this->org, function () {
            app(RoleProvisioner::class)->provision($this->org);
            $user = User::factory()->create(['organisation_id' => $this->org->id]);
            $user->assignRole(Rbac::ADMIN);

            return $user;
        });

        $this->actingAs($admin)
            ->post('http://ambu.localhost/settings/webhooks', ['name' => 'intra-Chatel', 'url' => self::URL])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('webhook_secret_plain', fn ($s) => str_starts_with($s, 'whsec_'));

        $endpoint = $this->tenant()->runFor($this->org, fn () => WebhookEndpoint::query()->sole());
        $this->assertNull($endpoint->events);

        $this->actingAs($admin)
            ->post("http://ambu.localhost/settings/webhooks/{$endpoint->id}/test")
            ->assertSessionHas('status', 'Test reçu par le destinataire (HTTP 200).');
        Http::assertSent(fn (Request $r) => $r->header('X-Vulkain-Event')[0] === 'ping');

        $this->actingAs($admin)->delete("http://ambu.localhost/settings/webhooks/{$endpoint->id}")->assertRedirect();
        $this->assertSame(0, $this->tenant()->runFor($this->org, fn () => WebhookEndpoint::query()->count()));

        config(['services.webhooks.allow_private_hosts' => false]);
        $this->actingAs($admin)
            ->post('http://ambu.localhost/settings/webhooks', ['name' => 'Interne', 'url' => 'http://127.0.0.1/hook'])
            ->assertSessionHasErrors('url');
    }
}
