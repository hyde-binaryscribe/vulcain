<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\PasswordResetService;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'email' => $request->query('email'),
            'token' => $token,
        ]);
    }

    public function store(Request $request, PasswordResetService $service, TenantContext $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $ok = $service->reset($tenant->id(), $validated['email'], $validated['token'], $validated['password']);

        if (! $ok) {
            throw ValidationException::withMessages([
                'email' => 'Ce lien de réinitialisation est invalide ou a expiré.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
    }
}
