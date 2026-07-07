<?php

namespace App\Http\Controllers;

use App\Domain\Identity\InvitationService;
use App\Domain\Identity\Rbac;
use App\Models\Invitation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'grade' => $u->grade,
                'role' => $u->getRoleNames()->first(),
                'is_active' => $u->is_active,
                'last_login_at' => $u->last_login_at?->format('d/m/Y H:i'),
                'is_self' => $u->id === $request->user()->id,
            ])
            ->values();

        $pending = Invitation::query()
            ->where('organisation_id', $this->tenant->id())
            ->whereNull('accepted_at')
            ->latest()
            ->get()
            ->map(fn (Invitation $i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role,
                'role_label' => Rbac::ROLE_LABELS[$i->role] ?? $i->role,
                'expired' => $i->expires_at->isPast(),
                'expires_at' => $i->expires_at->format('d/m/Y'),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'pendingInvitations' => $pending,
            'roles' => $this->roleOptions(),
            'search' => $search,
            'status' => session('status'),
        ]);
    }

    public function store(Request $request, InvitationService $invitations): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::in(Rbac::roles())],
        ]);

        $email = mb_strtolower($validated['email']);

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Un utilisateur avec cet e-mail existe déjà dans cette organisation.',
            ]);
        }

        $invitations->invite($this->tenant->organisation(), $email, $validated['role']);

        return back()->with('status', "Invitation envoyée à {$email}.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'grade' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::in(Rbac::roles())],
            'is_active' => ['required', 'boolean'],
        ]);

        // Anti auto-verrouillage : on ne retire pas son propre accès administrateur.
        if ($user->id === $request->user()->id
            && ($validated['role'] !== Rbac::ADMIN || ! $validated['is_active'])) {
            throw ValidationException::withMessages([
                'role' => 'Vous ne pouvez pas retirer votre propre accès administrateur.',
            ]);
        }

        $user->grade = $validated['grade'] ?: null;
        $user->is_active = $validated['is_active'];
        $user->save();
        $user->syncRoles([$validated['role']]);

        return back()->with('status', 'Utilisateur mis à jour.');
    }

    public function cancelInvitation(Invitation $invitation): RedirectResponse
    {
        abort_unless($invitation->organisation_id === $this->tenant->id(), 404);

        $invitation->delete();

        return back()->with('status', 'Invitation annulée.');
    }

    public function resendInvitation(Invitation $invitation, InvitationService $invitations): RedirectResponse
    {
        abort_unless(
            $invitation->organisation_id === $this->tenant->id() && $invitation->accepted_at === null,
            404
        );

        $invitations->invite($this->tenant->organisation(), $invitation->email, $invitation->role);
        $invitation->delete();

        return back()->with('status', 'Invitation renvoyée.');
    }

    /** @return list<array{value:string,label:string}> */
    private function roleOptions(): array
    {
        return array_map(
            fn (string $role) => ['value' => $role, 'label' => Rbac::ROLE_LABELS[$role]],
            Rbac::roles(),
        );
    }
}
