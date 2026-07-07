<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\PasswordResetService;
use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request, PasswordResetService $service, TenantContext $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $service->sendResetLink($tenant->id(), $validated['email']);

        // Réponse uniforme (pas d'énumération de comptes).
        return back()->with('status', 'Si un compte correspond à cette adresse, un e-mail de réinitialisation vient d’être envoyé.');
    }
}
