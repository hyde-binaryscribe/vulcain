<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Identity\InvitationService;
use App\Domain\Identity\OrganisationProvisioner;
use App\Domain\Identity\Rbac;
use App\Domain\Sectors\Sector;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganisationController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function create(): Response
    {
        // Le provisioning d'une nouvelle organisation est réservé à l'exploitant global.
        abort_if(auth('platform')->user()->isGroupManager(), 403);

        return Inertia::render('Platform/Organisations/Create', [
            'sectors' => Sector::options(),
        ]);
    }

    public function store(Request $request, OrganisationProvisioner $provisioner): RedirectResponse
    {
        abort_if(auth('platform')->user()->isGroupManager(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required', 'string', 'max:63',
                // Identifiant court (minuscules, chiffres, tirets) — unique.
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                Rule::unique('organisations', 'slug'),
            ],
            'sector' => ['required', Rule::enum(Sector::class)],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
            // Mode de mise en route : invitation par e-mail ou identifiants générés.
            'provisioning_mode' => ['required', Rule::in(['invitation', 'credentials'])],
        ], [
            'slug.regex' => "L'identifiant ne peut contenir que des minuscules, chiffres et tirets.",
        ]);

        $data = [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'sector' => Sector::from($validated['sector']),
        ];

        // Identifiants générés directement (utile tant que l'e-mail n'est pas configuré).
        if ($validated['provisioning_mode'] === 'credentials') {
            $email = mb_strtolower($validated['admin_email']);

            // L'e-mail est unique au niveau global (un compte = une organisation).
            if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
                return back()->withErrors(['admin_email' => "Un compte existe déjà avec l'adresse {$email}."])->withInput();
            }

            $temp = Str::password(14, symbols: false);
            $label = (string) Str::of($email)->before('@')->replace(['.', '-', '_'], ' ')->squish()->headline();

            [$organisation] = $provisioner->provisionWithAdmin($data, [
                'name' => $label !== '' ? $label : 'Administrateur',
                'email' => $email,
                'password' => $temp,
            ]);

            return redirect()->route('platform.organisations.show', $organisation)->with('credentials', [
                'email' => $email,
                'password' => $temp,
                'message' => "Organisation créée et compte administrateur généré. Communiquez ces identifiants ; le mot de passe pourra être changé après connexion.",
            ]);
        }

        // Invitation par e-mail (comportement historique).
        $provisioner->provision($data, $validated['admin_email']);

        return redirect()->route('platform.dashboard')->with(
            'status',
            "Organisation « {$validated['name']} » créée. Invitation envoyée à {$validated['admin_email']}."
        );
    }

    public function toggle(Organisation $organisation): RedirectResponse
    {
        abort_unless(auth('platform')->user()->canManageOrganisation($organisation), 403);

        $organisation->update([
            'status' => $organisation->isActive()
                ? Organisation::STATUS_SUSPENDED
                : Organisation::STATUS_ACTIVE,
        ]);

        return back()->with('status', "Statut de « {$organisation->name} » mis à jour.");
    }

    /** Fiche détaillée d'une organisation (compteurs, admin, activité). */
    public function show(Organisation $organisation): Response
    {
        abort_unless(auth('platform')->user()->canManageOrganisation($organisation), 403);

        $data = $this->tenant->runFor($organisation, function () {
            $adminUser = User::query()->role(Rbac::ADMIN)->where('is_active', true)->first();

            return [
                'counts' => [
                    'users' => User::query()->count(),
                    'vehicles' => Vehicle::query()->count(),
                    'sites' => Site::query()->count(),
                    'materials' => Material::query()->count(),
                ],
                'admin' => $adminUser ? [
                    'name' => $adminUser->name,
                    'email' => $adminUser->email,
                    'last_login' => $adminUser->last_login_at?->format('d/m/Y H:i'),
                ] : null,
                'pending_invitation' => Invitation::query()->whereNull('accepted_at')->latest()->value('email'),
                'activity' => ActivityLog::query()->latest()->limit(10)->get()
                    ->map(fn (ActivityLog $l) => ActivityController::format($l)),
            ];
        });

        $sub = $organisation->subscription;

        return Inertia::render('Platform/Organisations/Show', [
            'org' => [
                'id' => $organisation->id,
                'name' => $organisation->name,
                'slug' => $organisation->slug,
                'sector_label' => $organisation->profile()->label,
                'theme' => $organisation->profile()->themeColor,
                'status' => $organisation->status,
                'group' => $organisation->group?->name,
                'created_at' => $organisation->created_at?->format('d/m/Y'),
                'app_url' => $this->tenantUrl(),
            ],
            'subscription' => $sub ? [
                'plan_label' => $sub->plan->label(),
                'status' => $sub->status->value,
                'status_label' => $sub->status->label(),
                'trial_ends_at' => $sub->trial_ends_at?->format('d/m/Y'),
                'current_period_end' => $sub->current_period_end?->format('d/m/Y'),
            ] : null,
            'counts' => $data['counts'],
            'admin' => $data['admin'],
            'pendingInvitation' => $data['pending_invitation'],
            'activity' => $data['activity'],
        ]);
    }

    /** Renvoie l'invitation d'administrateur en attente. */
    public function resendInvitation(Organisation $organisation, InvitationService $invitations): RedirectResponse
    {
        abort_unless(auth('platform')->user()->canManageOrganisation($organisation), 403);

        $email = $this->tenant->runFor($organisation, fn () => Invitation::query()->whereNull('accepted_at')->latest()->value('email'));
        if ($email === null) {
            return back()->with('error', 'Aucune invitation en attente pour cette organisation.');
        }

        $invitations->invite($organisation, $email, Rbac::ADMIN);

        return back()->with('status', "Invitation renvoyée à {$email}.");
    }

    /**
     * Génère (ou régénère) les identifiants de l'administrateur de l'organisation.
     *
     * Utile tant que l'envoi d'e-mails n'est pas configuré : l'exploitant crée
     * directement le compte administrateur — à partir de l'invitation en attente —
     * avec un mot de passe temporaire à communiquer manuellement. Si le compte
     * existe déjà, seul le mot de passe est réinitialisé.
     */
    public function generateAdminCredentials(Organisation $organisation): RedirectResponse
    {
        abort_unless(auth('platform')->user()->canManageOrganisation($organisation), 403);

        $temp = Str::password(14, symbols: false);

        $result = $this->tenant->runFor($organisation, function () use ($organisation, $temp) {
            $admin = User::query()->role(Rbac::ADMIN)->where('is_active', true)->first();

            // Compte existant : simple réinitialisation du mot de passe.
            if ($admin !== null) {
                $admin->password = $temp; // cast « hashed » (Argon2id)
                $admin->save();

                return ['email' => $admin->email, 'created' => false];
            }

            // Aucun compte : on le crée depuis l'invitation d'administrateur en attente.
            $invitation = Invitation::query()
                ->whereNull('accepted_at')
                ->where('role', Rbac::ADMIN)
                ->latest()
                ->first();

            if ($invitation === null) {
                return ['error' => 'Aucun administrateur ni invitation en attente pour cette organisation.'];
            }

            $email = mb_strtolower($invitation->email);

            // L'e-mail est unique au niveau global (un compte = une organisation).
            if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
                return ['error' => "Un compte existe déjà avec l'adresse {$email}."];
            }

            $label = (string) Str::of($email)->before('@')->replace(['.', '-', '_'], ' ')->squish()->headline();
            $firstName = $label !== '' ? $label : 'Administrateur';

            $user = new User;
            $user->forceFill([
                'organisation_id' => $organisation->id,
                'first_name' => $firstName,
                'last_name' => '',
                'name' => $firstName,
                'email' => $email,
                'password' => $temp, // cast « hashed » (Argon2id)
                'is_active' => true,
            ])->save();
            $user->assignRole(Rbac::ADMIN);

            // L'invitation est consommée : pas de compte en double possible ensuite.
            $invitation->forceFill(['accepted_at' => now()])->save();

            return ['email' => $email, 'created' => true];
        });

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        $verb = $result['created'] ? 'Compte administrateur créé' : 'Mot de passe réinitialisé';

        return back()->with('credentials', [
            'email' => $result['email'],
            'password' => $temp,
            'message' => "{$verb}. Communiquez ces identifiants à l'organisation ; le mot de passe pourra être changé après connexion.",
        ]);
    }

    /** Se connecter en tant qu'administrateur de l'organisation (support). */
    public function impersonate(Request $request, Organisation $organisation): RedirectResponse
    {
        $platformAdmin = auth('platform')->user();
        abort_unless($platformAdmin->canManageOrganisation($organisation), 403);
        abort_unless($organisation->isActive(), 403, 'Organisation suspendue.');

        $user = $this->tenant->runFor($organisation, fn () => User::query()->role(Rbac::ADMIN)->where('is_active', true)->first()
            ?? User::query()->where('is_active', true)->first());

        if ($user === null) {
            return back()->with('error', 'Aucun utilisateur actif à incarner.');
        }

        Auth::guard('web')->login($user);
        // Desk (desk.vulkain.eu) et application (app.vulkain.eu) partagent la
        // session via SESSION_DOMAIN=.vulkain.eu : la connexion « web » posée ici
        // est reconnue à l'arrivée sur l'hôte applicatif.
        $request->session()->put('impersonator', [
            'name' => $platformAdmin->name,
            'return' => $request->getSchemeAndHttpHost().'/platform',
        ]);

        return redirect()->away($this->tenantUrl());
    }

    /**
     * URL du tableau de bord de l'application (hôte unique).
     *
     * Depuis le passage à l'accès par compte, toutes les organisations partagent
     * l'hôte applicatif (app.vulkain.eu) : l'organisation est déduite du compte
     * connecté, plus d'un sous-domaine par organisation.
     */
    private function tenantUrl(): string
    {
        $base = config('tenancy.app_domains')[0]
            ?? config('tenancy.central_domains')[0]
            ?? request()->getHost();

        return 'https://'.$base.'/dashboard';
    }
}
