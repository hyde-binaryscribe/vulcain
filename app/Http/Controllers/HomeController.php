<?php

namespace App\Http\Controllers;

use App\Support\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __invoke(Request $request, VitrineController $vitrine)
    {
        $host = $request->getHost();

        // L'aiguillage se fait sur l'HÔTE d'abord (avant l'état de connexion) :
        // le cookie de session est partagé sur *.vulkain.eu, donc une session
        // « web » peut coexister sur le Desk ; sans cette priorité, desk.vulkain.eu
        // renverrait un utilisateur connecté vers le dashboard de l'application.

        // Hôte vitrine (vulkain.eu / www) : site public marketing.
        if (in_array($host, config('tenancy.vitrine_domains', []), true)) {
            return $vitrine->home();
        }

        // Hôte Desk : central mais ni vitrine ni application (desk.vulkain.eu).
        $isAppHost = in_array($host, config('tenancy.app_domains', []), true);
        $isCentral = in_array($host, config('tenancy.central_domains', []), true);
        if ($isCentral && ! $isAppHost) {
            return redirect()->route('platform.dashboard');
        }

        // Hôte applicatif (ou tout autre) : si connecté, l'appli terrain sur
        // téléphone (accès PWA direct), sinon le tableau de bord ; puis connexion.
        if (Auth::guard('web')->check()) {
            return redirect()->route(Device::isMobile($request) ? 'terrain.home' : 'dashboard');
        }

        return redirect()->route('login');
    }
}
