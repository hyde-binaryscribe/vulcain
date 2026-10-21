<?php

namespace App\Http\Controllers;

use App\Domain\Sectors\Sector;
use App\Models\ActivityLog;
use App\Models\ApiToken;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Réglages › Lien Itineris : création et révocation des jetons d'accès à
 * l'API v1. Réservé aux organisations de transport sanitaire (Itineris ne
 * gère que ce secteur). Le jeton en clair n'est montré qu'une fois.
 */
class ApiTokenController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public static function available(TenantContext $tenant): bool
    {
        return $tenant->organisation()?->sector === Sector::AMBULANCE_PRIVEE;
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(self::available($this->tenant), 404);
        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);

        [$token, $plain] = ApiToken::issue(trim($validated['name']), Auth::id());
        $this->log($token, 'created');

        return back()
            ->with('status', 'Jeton créé. Copiez-le maintenant : il ne sera plus affiché.')
            ->with('api_token_plain', $plain);
    }

    public function destroy(ApiToken $token): RedirectResponse
    {
        abort_unless(self::available($this->tenant), 404);
        if ($token->isActive()) {
            $token->update(['revoked_at' => now()]);
            $this->log($token, 'revoked');
        }

        return back()->with('status', 'Jeton révoqué : Itineris ne peut plus lire les véhicules avec ce jeton.');
    }

    private function log(ApiToken $token, string $action): void
    {
        $actor = Auth::user();
        ActivityLog::create([
            'organisation_id' => $this->tenant->id(),
            'user_id' => $actor?->getKey(),
            'actor_name' => $actor?->name ?? 'Système',
            'subject_type' => $token->getMorphClass(),
            'subject_id' => $token->id,
            'action' => $action,
            'description' => 'Jeton Itineris « '.$token->name.' » (…'.$token->last_four.')',
            'properties' => null,
            'created_at' => now(),
        ]);
    }
}
