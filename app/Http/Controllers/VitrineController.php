<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Plan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Site vitrine public (marketing) servi sur le domaine racine (vulkain.eu),
 * et page d'entrée de l'application (app.vulkain.eu) : recherche d'organisation.
 * Accessibles sans authentification ni tenant.
 */
class VitrineController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('Vitrine/Home', [
            'plans' => Plan::options(),
            'registerUrl' => $this->registerUrl(),
            'appHomeUrl' => $this->appBaseUrl(),
        ]);
    }

    /** Page d'entrée de l'app : le client saisit l'identifiant de son organisation. */
    public function entry(): Response
    {
        return Inertia::render('App/Entry', [
            'baseDomain' => $this->appBaseDomain(),
            'registerUrl' => $this->registerUrl(),
        ]);
    }

    private function appBaseDomain(): string
    {
        return config('tenancy.app_domains')[0]
            ?? config('tenancy.central_domains')[0]
            ?? 'app.vulkain.eu';
    }

    /** URL de base de l'entrée applicative (https://app.vulkain.eu). */
    private function appBaseUrl(): string
    {
        $appDomains = config('tenancy.app_domains', []);

        return $appDomains !== [] ? 'https://'.$appDomains[0] : url('/');
    }

    /** URL du parcours d'inscription (sur l'hôte app en prod, relatif en dev). */
    private function registerUrl(): string
    {
        $appDomains = config('tenancy.app_domains', []);

        return $appDomains !== [] ? 'https://'.$appDomains[0].'/inscription' : url('/inscription');
    }
}
