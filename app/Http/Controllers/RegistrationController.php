<?php

namespace App\Http\Controllers;

use App\Domain\Identity\OrganisationProvisioner;
use App\Domain\Sectors\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inscription self-service : un prospect crée son organisation et son compte
 * administrateur, démarre en essai gratuit, puis rejoint son espace.
 * Accessible sur les hôtes centraux (vitrine / app), jamais sur un tenant.
 */
class RegistrationController extends Controller
{
    /** Sous-domaines réservés (infra / marque) — jamais attribuables à une org. */
    private const RESERVED = [
        'www', 'app', 'desk', 'api', 'admin', 'mail', 'ftp', 'static',
        'assets', 'cdn', 'status', 'support', 'blog', 'vulkain', 'vulcain',
    ];

    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'sectors' => Sector::options(),
            'baseDomain' => config('tenancy.app_domains')[0]
                ?? config('tenancy.central_domains')[0]
                ?? 'app.vulkain.eu',
        ]);
    }

    public function store(Request $request, OrganisationProvisioner $provisioner): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required', 'string', 'max:63',
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                Rule::notIn(self::RESERVED),
                Rule::unique('organisations', 'slug'),
            ],
            'sector' => ['required', Rule::enum(Sector::class)],
            'admin_name' => ['required', 'string', 'max:150'],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ], [
            'slug.regex' => 'Le sous-domaine ne peut contenir que des minuscules, chiffres et tirets.',
            'slug.not_in' => 'Ce sous-domaine est réservé.',
            'slug.unique' => 'Ce sous-domaine est déjà pris.',
        ]);

        [$organisation, $user] = $provisioner->provisionWithAdmin(
            [
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'sector' => Sector::from($validated['sector']),
            ],
            [
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => $validated['password'],
            ],
        );

        // Connexion immédiate ; le cookie de session est partagé sur *.vulkain.eu
        // en production (SESSION_DOMAIN), l'utilisateur atterrit connecté.
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->away($this->tenantDashboardUrl($request, $organisation->slug));
    }

    /** Construit l'URL du tableau de bord de l'organisation fraîchement créée. */
    private function tenantDashboardUrl(Request $request, string $slug): string
    {
        $base = config('tenancy.app_domains')[0]
            ?? config('tenancy.central_domains')[0]
            ?? $request->getHost();

        $port = $request->getPort();
        $suffix = in_array($port, [80, 443, null], true) ? '' : ':'.$port;

        return $request->getScheme().'://'.$slug.'.'.$base.$suffix.'/dashboard';
    }
}
