<?php

namespace App\Http\Controllers;

use App\Domain\Identity\InvitationService;
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
            throw ValidationException::withMessages([
                'email' => 'Cette invitation est invalide ou a expiré.',
            ]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Bienvenue ! Votre compte administrateur est activé.');
    }
}
