<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenant, VitrineController $vitrine)
    {
        // Tenant résolu (sous-domaine d'organisation) : espace applicatif.
        if ($tenant->check()) {
            return Auth::check()
                ? redirect()->route('dashboard')
                : redirect()->route('login');
        }

        // Hôte vitrine (vulkain.eu / www) : site public marketing.
        if (in_array($request->getHost(), config('tenancy.vitrine_domains', []), true)) {
            return $vitrine->home();
        }

        // Autre hôte central : Desk (exploitant / plateforme).
        return redirect()->route('platform.dashboard');
    }
}
