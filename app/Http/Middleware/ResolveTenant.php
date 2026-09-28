<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout l'organisation courante à partir du COMPTE de l'utilisateur connecté
 * (accès par hôte unique app.vulkain.eu — plus de sous-domaine par organisation).
 *
 * S'exécute après le démarrage de session : pour un invité, aucun tenant n'est
 * défini (page de connexion) ; l'espace plateforme (guard « platform ») n'est
 * jamais un tenant.
 */
class ResolveTenant
{
    public function __construct(protected TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Chargement de l'utilisateur hors scope d'organisation (sinon le scope
        // exigerait un tenant déjà défini — impasse au moment de le déterminer).
        $user = $this->tenant->runCrossTenant(fn () => Auth::guard('web')->user());

        if ($user !== null && $user->organisation_id !== null) {
            // Chargement frais de l'organisation (jamais la relation en cache,
            // pour refléter ses réglages à jour à chaque requête).
            $organisation = $this->tenant->runCrossTenant(fn () => Organisation::find($user->organisation_id));

            if ($organisation !== null) {
                $this->tenant->set($organisation);
            }
        }

        return $next($request);
    }
}
