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
                // Libellé de sous-domaine valide.
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                Rule::unique('organisations', 'slug'),
            ],
            'sector' => ['required', Rule::enum(Sector::class)],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'slug.regex' => 'Le sous-domaine ne peut contenir que des minuscules, chiffres et tirets.',
        ]);

        $provisioner->provision([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'sector' => Sector::from($validated['sector']),
        ], $validated['admin_email']);

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
                'app_url' => $this->tenantUrl($organisation->slug),
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

    /** Définit un mot de passe temporaire pour l'administrateur de l'organisation. */
    public function resetAdminPassword(Organisation $organisation): RedirectResponse
    {
        abort_unless(auth('platform')->user()->canManageOrganisation($organisation), 403);

        $temp = Str::password(14, symbols: false);
        $ok = $this->tenant->runFor($organisation, function () use ($temp) {
            $u = User::query()->role(Rbac::ADMIN)->where('is_active', true)->first();
            if ($u === null) {
                return false;
            }
            $u->password = $temp; // cast « hashed » (Argon2id)
            $u->save();

            return true;
        });

        if (! $ok) {
            return back()->with('error', 'Aucun administrateur actif pour cette organisation.');
        }

        return back()->with('status', "Mot de passe temporaire défini : {$temp} — communiquez-le, puis demandez son changement à la première connexion.");
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
        // Le cookie de session est partagé sur *.vulkain.eu (SESSION_DOMAIN).
        $request->session()->put('impersonator', [
            'name' => $platformAdmin->name,
            'return' => $request->getSchemeAndHttpHost().'/platform',
        ]);

        return redirect()->away($this->tenantUrl($organisation->slug));
    }

    /** URL du tableau de bord d'une organisation (hôte applicatif). */
    private function tenantUrl(string $slug): string
    {
        $base = config('tenancy.app_domains')[0]
            ?? config('tenancy.central_domains')[0]
            ?? request()->getHost();

        return 'https://'.$slug.'.'.$base.'/dashboard';
    }
}
