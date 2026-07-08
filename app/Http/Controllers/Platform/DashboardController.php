<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        $admin = auth('platform')->user();

        // Lecture inter-tenant explicite (opération plateforme légitime).
        // Un gestionnaire de groupe ne voit que les organisations de son groupe.
        $organisations = $this->tenant->runCrossTenant(
            fn () => Organisation::query()
                ->when($admin->group_id, fn ($q) => $q->where('group_id', $admin->group_id))
                ->withCount('users')
                ->with('subscription')
                ->orderBy('name')
                ->get()
                ->map(fn (Organisation $o) => [
                    'id' => $o->id,
                    'name' => $o->name,
                    'slug' => $o->slug,
                    'sector' => $o->sector->value,
                    'sector_label' => $o->profile()->label,
                    'theme' => $o->profile()->themeColor,
                    'status' => $o->status,
                    'users_count' => $o->users_count,
                    'plan_label' => $o->subscription?->plan->label(),
                    'subscription_status' => $o->subscription?->status->value,
                    'subscription_status_label' => $o->subscription?->status->label(),
                    'created_at' => $o->created_at?->format('Y-m-d'),
                ])
                ->values()
        );

        return Inertia::render('Platform/Dashboard', [
            'organisations' => $organisations,
            'stats' => [
                'total' => $organisations->count(),
                'active' => $organisations->where('status', Organisation::STATUS_ACTIVE)->count(),
            ],
            'viewer' => [
                'is_group_manager' => $admin->isGroupManager(),
                'group' => $admin->group?->name,
            ],
        ]);
    }
}
