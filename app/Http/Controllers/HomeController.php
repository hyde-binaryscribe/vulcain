<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __invoke(Request $request, VitrineController $vitrine)
    {
        // Utilisateur connecté : son organisation est résolue depuis son compte.
        if (Auth::guard('web')->check()) {
            return redirect()->route('dashboard');
        }

        // Hôte vitrine (vulkain.eu / www) : site public marketing.
        if (in_array($request->getHost(), config('tenancy.vitrine_domains', []), true)) {
            return $vitrine->home();
        }

        // Hôte applicatif (app.vulkain.eu) : connexion.
        if (in_array($request->getHost(), config('tenancy.app_domains', []), true)) {
            return redirect()->route('login');
        }

        // Autre hôte central : Desk (exploitant / plateforme).
        return redirect()->route('platform.dashboard');
    }
}
