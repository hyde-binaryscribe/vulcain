<?php

namespace App\Http\Controllers;

use App\Domain\Webhooks\Webhooks;
use App\Models\ActivityLog;
use App\Models\WebhookEndpoint;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Réglages › Notifications sortantes : adresses qui reçoivent les prises et fins
 * de service (lien intra-Chatel). Le secret de signature n'est montré qu'une fois.
 */
class WebhookEndpointController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function store(Request $request): RedirectResponse
    {
        abort_unless(ApiTokenController::available($this->tenant), 404);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:500', 'url:http,https'],
            'events' => ['nullable', 'array'],
            'events.*' => [Rule::in(WebhookEndpoint::EVENTS)],
        ]);

        if (! Webhooks::hostAllowed($validated['url'])) {
            return back()->withErrors(['url' => 'Adresse refusée : elle doit être publique (le réseau privé est fermé sur cet hébergement).']);
        }

        $secret = WebhookEndpoint::generateSecret();
        $endpoint = WebhookEndpoint::create([
            'name' => trim($validated['name']),
            'url' => $validated['url'],
            'secret' => $secret,
            'events' => ($validated['events'] ?? []) ?: null, // aucun choix = tous les événements
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);
        $this->log($endpoint, 'created');

        return back()
            ->with('status', 'Adresse enregistrée. Copiez le secret de signature maintenant : il ne sera plus affiché.')
            ->with('webhook_secret_plain', $secret);
    }

    /** Envoie un « ping » signé pour vérifier la configuration côté destinataire. */
    public function test(WebhookEndpoint $endpoint): RedirectResponse
    {
        abort_unless(ApiTokenController::available($this->tenant), 404);
        $delivery = Webhooks::queue($endpoint, 'ping', ['message' => 'Test de notification Vulkain.']);
        $ok = Webhooks::send($delivery);
        $delivery->refresh();

        return back()->with('status', $ok
            ? 'Test reçu par le destinataire (HTTP '.$delivery->response_status.').'
            : 'Échec du test : '.($delivery->last_error ?? 'erreur inconnue').'.');
    }

    public function destroy(WebhookEndpoint $endpoint): RedirectResponse
    {
        abort_unless(ApiTokenController::available($this->tenant), 404);
        $this->log($endpoint, 'deleted');
        $endpoint->delete();

        return back()->with('status', 'Adresse supprimée : plus aucune notification n’y sera envoyée.');
    }

    private function log(WebhookEndpoint $endpoint, string $action): void
    {
        $actor = Auth::user();
        ActivityLog::create([
            'organisation_id' => $this->tenant->id(),
            'user_id' => $actor?->getKey(),
            'actor_name' => $actor?->name ?? 'Système',
            'subject_type' => $endpoint->getMorphClass(),
            'subject_id' => $endpoint->id,
            'action' => $action,
            'description' => 'Notifications sortantes « '.$endpoint->name.' » ('.$endpoint->url.')',
            'properties' => null,
            'created_at' => now(),
        ]);
    }
}
