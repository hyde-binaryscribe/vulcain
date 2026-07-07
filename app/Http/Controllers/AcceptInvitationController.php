<?php

namespace App\Http\Controllers;

use App\Domain\Identity\InvitationService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acceptation d'une invitation, sur le sous-domaine de l'organisation.
 * L'invité définit son identité et son mot de passe (aucun défaut).
 */
class AcceptInvitationController extends Controller
{
    public function create(Request $request, string $token, TenantContext $tenant): Response
    {
        return Inertia::render('Auth/AcceptInvitation', [
            'token' => $token,
            'email' => $request->query('email'),
            'organisationName' => $tenant->organisation()?->name,
        ]);
    }

    public function store(Request $request, InvitationService $service, TenantContext $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user = $service->accept($tenant->organisation(), $validated['email'], $validated['token'], [
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
