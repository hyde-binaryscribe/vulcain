<?php

namespace App\Domain\Webhooks;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Notifications sortantes signées. Un événement crée un envoi par adresse
 * active de l'organisation ; l'envoi part après la réponse HTTP (le terrain
 * n'attend jamais le destinataire). Un échec est retenté avec un délai
 * croissant (vulcain:retry-webhooks, toutes les 5 minutes), puis abandonné.
 *
 * Signature : en-tête X-Vulkain-Signature « t=<horodatage>,v1=<hex> » où
 * v1 = HMAC-SHA256(secret, "<horodatage>.<corps brut>").
 */
class Webhooks
{
    /** Délai avant chaque nouvelle tentative, en minutes (6 tentatives au total). */
    public const BACKOFF_MINUTES = [1, 5, 15, 60, 240];

    public const TIMEOUT_SECONDS = 5;

    /** Crée les envois de l'événement pour l'organisation courante et les programme après la réponse. */
    public static function dispatch(string $event, array $data): void
    {
        $tenant = app(TenantContext::class);
        if (! $tenant->check()) {
            return;
        }

        $ids = WebhookEndpoint::query()->where('is_active', true)->get()
            ->filter(fn (WebhookEndpoint $e) => $e->listensTo($event))
            ->map(fn (WebhookEndpoint $e) => self::queue($e, $event, $data)->id)
            ->values()->all();

        if ($ids !== []) {
            app()->terminating(fn () => self::sendMany($ids));
        }
    }

    /** Crée un envoi (en attente) pour une adresse. */
    public static function queue(WebhookEndpoint $endpoint, string $event, array $data): WebhookDelivery
    {
        $uuid = (string) Str::uuid();

        return WebhookDelivery::create([
            'organisation_id' => $endpoint->organisation_id,
            'webhook_endpoint_id' => $endpoint->id,
            'uuid' => $uuid,
            'event' => $event,
            'payload' => [
                'id' => $uuid,
                'event' => $event,
                'created_at' => now()->toIso8601String(),
                'data' => $data,
            ],
            'status' => WebhookDelivery::STATUS_PENDING,
            'next_attempt_at' => now(),
        ]);
    }

    /** Envoie les envois encore dus parmi ces ids (sans effet sur un envoi déjà traité). */
    public static function sendMany(array $ids): void
    {
        app(TenantContext::class)->runCrossTenant(function () use ($ids) {
            WebhookDelivery::query()->withoutOrganisationScope()
                ->whereIn('id', $ids)
                ->where('status', WebhookDelivery::STATUS_PENDING)
                ->where('next_attempt_at', '<=', now())
                ->get()
                ->each(fn (WebhookDelivery $d) => self::send($d));
        });
    }

    /** Nouvelles tentatives arrivées à échéance, toutes organisations. Renvoie le nombre traité. */
    public static function retryDue(int $limit = 100): int
    {
        return app(TenantContext::class)->runCrossTenant(function () use ($limit) {
            $due = WebhookDelivery::query()->withoutOrganisationScope()
                ->where('status', WebhookDelivery::STATUS_PENDING)
                ->where('next_attempt_at', '<=', now())
                ->orderBy('next_attempt_at')
                ->limit($limit)
                ->get();
            $due->each(fn (WebhookDelivery $d) => self::send($d));

            return $due->count();
        });
    }

    /** Une tentative d'envoi. Renvoie vrai si le destinataire a répondu 2xx. */
    public static function send(WebhookDelivery $delivery): bool
    {
        $endpoint = WebhookEndpoint::query()->withoutOrganisationScope()->find($delivery->webhook_endpoint_id);
        if ($endpoint === null || ! $endpoint->is_active) {
            $delivery->update(['status' => WebhookDelivery::STATUS_FAILED, 'last_error' => 'Adresse supprimée ou désactivée.', 'next_attempt_at' => null]);

            return false;
        }

        if (! self::hostAllowed($endpoint->url)) {
            $delivery->update(['status' => WebhookDelivery::STATUS_FAILED, 'attempts' => $delivery->attempts + 1, 'last_error' => 'Adresse du réseau privé refusée.', 'next_attempt_at' => null]);
            $endpoint->update(['last_failure_at' => now(), 'last_error' => 'Adresse du réseau privé refusée.']);

            return false;
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = now()->getTimestamp();
        $status = null;
        $error = null;

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(3)
                // Pas de redirection : elle contournerait le contrôle d'adresse.
                ->withOptions(['allow_redirects' => false])
                ->withHeaders([
                    'User-Agent' => 'Vulkain-Webhooks/1',
                    'X-Vulkain-Event' => $delivery->event,
                    'X-Vulkain-Delivery' => $delivery->uuid,
                    'X-Vulkain-Signature' => self::signature($endpoint->secret, $timestamp, $body),
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);
            $status = $response->status();
            if (! $response->successful()) {
                $error = 'Réponse HTTP '.$status;
            }
        } catch (Throwable $e) {
            $error = Str::limit($e->getMessage(), 480);
        }

        $attempts = $delivery->attempts + 1;

        if ($error === null) {
            $delivery->update([
                'status' => WebhookDelivery::STATUS_DELIVERED, 'attempts' => $attempts, 'response_status' => $status,
                'last_error' => null, 'next_attempt_at' => null, 'delivered_at' => now(),
            ]);
            $endpoint->update(['last_success_at' => now()]);

            return true;
        }

        $delay = self::BACKOFF_MINUTES[$attempts - 1] ?? null;
        $delivery->update([
            'status' => $delay === null ? WebhookDelivery::STATUS_FAILED : WebhookDelivery::STATUS_PENDING,
            'attempts' => $attempts,
            'response_status' => $status,
            'last_error' => $error,
            'next_attempt_at' => $delay === null ? null : now()->addMinutes($delay),
        ]);
        $endpoint->update(['last_failure_at' => now(), 'last_error' => $error]);

        return false;
    }

    /**
     * L'adresse vise-t-elle un hôte public ? Toutes les IP résolues sont vérifiées
     * (privées, boucle locale, lien local, réservées : refusées), sauf si la
     * configuration autorise le réseau privé (intranet).
     */
    public static function hostAllowed(string $url): bool
    {
        if (config('services.webhooks.allow_private_hosts')) {
            return true;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }
        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(
            gethostbynamel($host) ?: [],
            array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'),
        );
        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    public static function signature(string $secret, int $timestamp, string $body): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
