<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Plan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Site vitrine public (marketing) servi sur le domaine racine (vulkain.eu).
 * Pages accessibles sans authentification ni tenant.
 */
class VitrineController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('Vitrine/Home', [
            'plans' => Plan::options(),
            'appUrl' => $this->appLoginUrl(),
        ]);
    }

    /** URL d'entrée de l'application (login / inscription) pour les CTA. */
    private function appLoginUrl(): string
    {
        $appDomains = config('tenancy.app_domains', []);

        if ($appDomains !== []) {
            return 'https://'.$appDomains[0].'/login';
        }

        return route('login');
    }
}
