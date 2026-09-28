<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vues transverses du Desk : recherche d'utilisateurs et journal d'activité
 * à l'échelle de la plateforme. Lecture inter-tenant explicite ; un
 * gestionnaire de groupe reste limité aux organisations de son groupe.
 */
class InsightsController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function users(Request $request): Response
    {
        $admin = auth('platform')->user();
        $q = trim((string) $request->query('q', ''));

        $users = $this->tenant->runCrossTenant(fn () => User::query()
            ->with('organisation:id,name')
            ->when($admin->group_id, fn ($x) => $x->whereHas('organisation', fn ($o) => $o->where('group_id', $admin->group_id)))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")))
            ->orderBy('name')
            ->limit(200)
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'grade' => $u->grade,
                'is_active' => $u->is_active,
                'last_login' => $u->last_login_at?->format('d/m/Y H:i'),
                'org' => $u->organisation?->name,
                'org_id' => $u->organisation_id,
            ]));

        return Inertia::render('Platform/Users', ['users' => $users, 'search' => $q]);
    }

    public function activity(): Response
    {
        $admin = auth('platform')->user();

        $logs = $this->tenant->runCrossTenant(fn () => ActivityLog::query()
            ->with('organisation:id,name')
            ->when($admin->group_id, fn ($x) => $x->whereHas('organisation', fn ($o) => $o->where('group_id', $admin->group_id)))
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (ActivityLog $l) => [
                ...ActivityController::format($l),
                'org' => $l->organisation?->name,
            ]));

        return Inertia::render('Platform/Activity', ['logs' => $logs]);
    }
}
