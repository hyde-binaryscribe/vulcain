<?php

namespace App\Domain\Identity;

use App\Domain\Billing\Plan;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Sectors\Sector;
use App\Models\Organisation;
use Illuminate\Support\Facades\DB;

/**
 * Provisionne une organisation complète : création, rôles/permissions, et
 * invitation du premier administrateur (aucun mot de passe par défaut).
 */
class OrganisationProvisioner
{
    public function __construct(
        private readonly RoleProvisioner $roleProvisioner,
        private readonly InvitationService $invitations,
    ) {}

    /**
     * @param  array{name:string,slug:string,sector:Sector}  $data
     */
    public function provision(array $data, string $adminEmail): Organisation
    {
        return DB::transaction(function () use ($data, $adminEmail) {
            $organisation = Organisation::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'sector' => $data['sector'],
                'status' => Organisation::STATUS_ACTIVE,
            ]);

            $this->roleProvisioner->provision($organisation);

            // Abonnement par défaut : essai sur le plan Découverte (30 jours).
            $organisation->subscription()->create([
                'plan' => Plan::DECOUVERTE->value,
                'status' => SubscriptionStatus::TRIAL->value,
                'trial_ends_at' => now()->addDays(30),
                'current_period_end' => now()->addDays(30),
            ]);

            $this->invitations->invite($organisation, $adminEmail, Rbac::ADMIN);

            return $organisation;
        });
    }
}
