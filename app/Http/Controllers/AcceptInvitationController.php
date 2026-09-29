<?php

namespace App\Http\Controllers;

use App\Domain\Identity\InvitationService;
use App\Models\User;
use App\Support\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acceptation d'une invitation (hôte applicatif unique). L'organisation est
 * déduite du jeton ; l'invité définit son identité et son mot de passe.
 */
class AcceptInvitationController extends Controller
{
    public function create(Request $request, string $token, InvitationService $service): Response
    {
        $invitation = $service->pendingByToken($token);

        return Inertia::render('Auth/AcceptInvitation', [
            'token' => $token,
            'email' => $request->query('email') ?? $invitation?->email,
            'organisationName' => $invitation?->organisation?->name,
        ]);
    }

    public function store(Request $request, InvitationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user = $service->accept($validated['email'], $validated['token'], [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'password' => $validated['password'],
        ]);

        if ($user === null) {
            // Invitation déjà acceptée : le compte existe → on oriente vers la connexion.
            $email = mb_strtolower(trim($validated['email']));
            if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
                return redirect()->route('login')
                    ->with('status', 'Votre compte est déjà activé. Connectez-vous avec votre e-mail et votre mot de passe.');
            }

            throw ValidationException::withMessages([
                'email' => 'Cette invitation est invalide ou a expiré.',
            ]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        // Redirection adaptée à l'appareil (comme à la connexion) : les agents de
        // terrain sur mobile arrivent sur l'appli terrain, pas le dashboard web.
        $default = Device::isMobile($request) ? route('terrain.home') : route('dashboard');

        return redirect()->intended($default)->with('status', 'Bienvenue sur Vulkain ! Votre compte est activé.');
    }
}
