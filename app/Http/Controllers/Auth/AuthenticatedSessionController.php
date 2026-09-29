<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\Device;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Rotation de l'identifiant de session après connexion (anti-fixation).
        $request->session()->regenerate();

        // Contexte d'organisation issu du compte (pour la suite de la requête).
        $this->tenant->set($request->user()->organisation);

        $request->user()->forceFill(['last_login_at' => now()])->saveQuietly();

        // Depuis un téléphone, on ouvre directement l'application terrain ;
        // sur ordinateur, le tableau de bord complet (lien terrain dans le menu).
        $default = Device::isMobile($request) ? route('terrain.home') : route('dashboard');

        return redirect()->intended($default);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
