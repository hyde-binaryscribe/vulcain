<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\Organisation;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API v1 : authentifie un jeton « Bearer » et pose l'organisation du jeton
 * comme tenant. Jeton absent, inconnu, révoqué ou organisation suspendue : 401.
 */
class AuthenticateApiToken
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plain = (string) $request->bearerToken();
        if ($plain === '' || ! str_starts_with($plain, ApiToken::PREFIX)) {
            return $this->unauthorized();
        }

        [$token, $organisation] = $this->tenant->runCrossTenant(function () use ($plain) {
            $token = ApiToken::query()->withoutOrganisationScope()
                ->where('token_hash', ApiToken::hash($plain))
                ->whereNull('revoked_at')
                ->first();

            return [$token, $token ? Organisation::find($token->organisation_id) : null];
        });

        if (! $token || ! $organisation || $organisation->status !== Organisation::STATUS_ACTIVE) {
            return $this->unauthorized();
        }

        $this->tenant->set($organisation);
        $request->attributes->set('api_token', $token);

        // Dernière utilisation, au plus une écriture par minute.
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute())) {
            ApiToken::query()->whereKey($token->id)->update(['last_used_at' => now()]);
        }

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json(['error' => 'Jeton d’accès invalide ou révoqué.', 'code' => 'unauthorized'], 401);
    }
}
