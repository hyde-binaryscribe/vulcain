<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve l'espace exploitant (Desk) à l'hôte de management (desk.vulkain.eu).
 * Le Desk n'est jamais servi sur l'hôte client : toute requête sur un autre
 * hôte est refusée (404). L'accès reste par ailleurs protégé par le guard
 * « platform ».
 *
 * La liste desk_domains dispose d'un repli automatique (voir config/tenancy.php),
 * elle est donc rarement vide ; si elle l'était, la contrainte serait neutre.
 */
class EnsureDeskHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $deskDomains = $this->deskDomains();

        // Aucune séparation configurée (dev / tests) : contrainte neutre.
        if ($deskDomains === []) {
            return $next($request);
        }

        if (! in_array($request->getHost(), $deskDomains, true)) {
            abort(404);
        }

        return $next($request);
    }

    /**
     * Hôtes Desk, calculés au runtime (jamais un instantané figé au boot) :
     * APP_DESK_DOMAIN explicite s'il est défini, sinon les hôtes centraux qui ne
     * sont ni la vitrine ni l'application.
     *
     * @return list<string>
     */
    private function deskDomains(): array
    {
        $explicit = config('tenancy.desk_domains', []);
        if ($explicit !== []) {
            return $explicit;
        }

        return array_values(array_diff(
            config('tenancy.central_domains', []),
            config('tenancy.vitrine_domains', []),
            config('tenancy.app_domains', []),
        ));
    }
}
