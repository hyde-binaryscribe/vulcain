<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve les routes client (connexion, inscription, tableau de bord, métier) à
 * l'hôte applicatif (app.vulkain.eu). Un client n'accède jamais à autre chose
 * que l'application : toute requête sur un autre hôte est refusée (404).
 *
 * Sans configuration d'hôtes applicatifs (dev / tests), la contrainte est
 * neutre — la séparation stricte ne s'applique qu'en présence d'APP_APP_DOMAIN.
 */
class EnsureAppHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $appDomains = config('tenancy.app_domains', []);

        if ($appDomains !== [] && ! in_array($request->getHost(), $appDomains, true)) {
            abort(404);
        }

        return $next($request);
    }
}
