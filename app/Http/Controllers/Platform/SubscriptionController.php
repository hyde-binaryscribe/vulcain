<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Billing\Plan;
use App\Domain\Billing\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function show(Organisation $organisation): Response
    {
        $subscription = $this->ensureSubscription($organisation);
        $plan = $subscription->plan;

        // Usage réel (lecture inter-tenant explicite, opération plateforme).
        $usage = [
            'users' => User::withoutGlobalScopes()->where('organisation_id', $organisation->id)->count(),
            'vehicles' => Vehicle::withoutGlobalScopes()->where('organisation_id', $organisation->id)->count(),
            'sites' => Site::withoutGlobalScopes()->where('organisation_id', $organisation->id)->count(),
        ];

        return Inertia::render('Platform/Organisations/Subscription', [
            'organisation' => [
                'id' => $organisation->id,
                'name' => $organisation->name,
                'slug' => $organisation->slug,
                'sector_label' => $organisation->profile()->label,
            ],
            'subscription' => [
                'plan' => $plan->value,
                'plan_label' => $plan->label(),
                'status' => $subscription->status->value,
                'status_label' => $subscription->status->label(),
                'trial_ends_at' => $subscription->trial_ends_at?->format('Y-m-d'),
                'current_period_end' => $subscription->current_period_end?->format('Y-m-d'),
                'notes' => $subscription->notes,
                'price' => $plan->monthlyPrice(),
            ],
            'usage' => [
                'users' => ['used' => $usage['users'], 'limit' => $plan->maxUsers()],
                'vehicles' => ['used' => $usage['vehicles'], 'limit' => $plan->maxVehicles()],
                'sites' => ['used' => $usage['sites'], 'limit' => $plan->maxSites()],
            ],
            'plans' => Plan::options(),
            'statuses' => SubscriptionStatus::options(),
            'status' => session('status'),
        ]);
    }

    public function update(Request $request, Organisation $organisation): RedirectResponse
    {
        $subscription = $this->ensureSubscription($organisation);

        $validated = $request->validate([
            'plan' => ['required', Rule::enum(Plan::class)],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'current_period_end' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $subscription->update([
            'plan' => $validated['plan'],
            'status' => $validated['status'],
            'current_period_end' => $validated['current_period_end'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('status', "Abonnement de « {$organisation->name} » mis à jour.");
    }

    private function ensureSubscription(Organisation $organisation): Subscription
    {
        return $organisation->subscription()->firstOrCreate([], [
            'plan' => Plan::DECOUVERTE->value,
            'status' => SubscriptionStatus::TRIAL->value,
            'trial_ends_at' => now()->addDays(30),
            'current_period_end' => now()->addDays(30),
        ]);
    }
}
