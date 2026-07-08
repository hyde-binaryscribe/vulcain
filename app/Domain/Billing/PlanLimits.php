<?php

namespace App\Domain\Billing;

use App\Support\Tenancy\TenantContext;

/**
 * Applique les limites du plan de l'organisation courante. Sans abonnement
 * (ou plan illimité), aucune limite n'est appliquée.
 */
class PlanLimits
{
    public function __construct(private readonly TenantContext $tenant) {}

    private const NOUNS = [
        'users' => 'utilisateurs',
        'vehicles' => 'véhicules',
        'sites' => 'sites',
    ];

    public function limitFor(string $resource): ?int
    {
        $plan = $this->tenant->organisation()?->subscription?->plan;
        if ($plan === null) {
            return null;
        }

        return match ($resource) {
            'users' => $plan->maxUsers(),
            'vehicles' => $plan->maxVehicles(),
            'sites' => $plan->maxSites(),
            default => null,
        };
    }

    /**
     * Message d'erreur si l'ajout dépasse le quota, sinon null.
     */
    public function check(string $resource, int $currentCount): ?string
    {
        $limit = $this->limitFor($resource);
        if ($limit === null || $currentCount < $limit) {
            return null;
        }

        $plan = $this->tenant->organisation()->subscription->plan;
        $noun = self::NOUNS[$resource] ?? $resource;

        return "Limite du plan {$plan->label()} atteinte ({$limit} {$noun}). "
            .'Faites évoluer l’abonnement pour en ajouter davantage.';
    }
}
