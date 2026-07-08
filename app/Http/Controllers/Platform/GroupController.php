<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Organisation;
use App\Models\PlatformAdmin;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestion des groupes (entreprises) — réservée à l'exploitant global.
 * Un gestionnaire de groupe n'y a pas accès.
 */
class GroupController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        $this->ensureGlobalOperator();

        $organisations = Organisation::query()->orderBy('name')->get(['id', 'name', 'slug', 'group_id']);

        $groups = Group::query()
            ->withCount('organisations')
            ->with('managers:id,group_id,name,email')
            ->orderBy('name')
            ->get()
            ->map(fn (Group $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'organisations_count' => $g->organisations_count,
                'organisations' => $organisations->where('group_id', $g->id)->pluck('name')->values(),
                'managers' => $g->managers->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'email' => $m->email])->values(),
            ]);

        return Inertia::render('Platform/Groups/Index', [
            'groups' => $groups,
            'organisations' => $organisations->map(fn ($o) => [
                'id' => $o->id, 'name' => $o->name, 'slug' => $o->slug, 'group_id' => $o->group_id,
            ])->values(),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureGlobalOperator();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        Group::create($validated);

        return back()->with('status', 'Groupe créé.');
    }

    /** Rattache (ou détache) une organisation à un groupe. */
    public function assign(Request $request, Organisation $organisation): RedirectResponse
    {
        $this->ensureGlobalOperator();

        $validated = $request->validate([
            'group_id' => ['nullable', Rule::exists('groups', 'id')],
        ]);

        $organisation->update(['group_id' => $validated['group_id'] ?? null]);

        return back()->with('status', "Organisation « {$organisation->name} » rattachée.");
    }

    /** Crée un compte gestionnaire de groupe (accès Desk limité à son groupe). */
    public function createManager(Request $request, Group $group): RedirectResponse
    {
        $this->ensureGlobalOperator();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', Rule::unique('platform_admins', 'email')],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        PlatformAdmin::create([
            'group_id' => $group->id,
            'name' => $validated['name'],
            'email' => mb_strtolower($validated['email']),
            'password' => $validated['password'],
        ]);

        return back()->with('status', "Gestionnaire créé pour le groupe « {$group->name} ».");
    }

    private function ensureGlobalOperator(): void
    {
        abort_if(auth('platform')->user()?->isGroupManager(), 403, 'Réservé à l’exploitant global.');
    }
}
