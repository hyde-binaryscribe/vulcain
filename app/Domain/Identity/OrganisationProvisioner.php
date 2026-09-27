<?php

namespace App\Domain\Identity;

use App\Domain\Billing\Plan;
use App\Domain\Billing\SubscriptionStatus;
use App\Domain\Sectors\Sector;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
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
        private readonly TenantContext $tenant,
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

    /**
     * Provisioning self-service : crée l'organisation, ses rôles, un essai, et
     * l'administrateur avec son mot de passe (compte immédiatement utilisable).
     *
     * @param  array{name:string,slug:string,sector:Sector}  $data
     * @param  array{name:string,email:string,password:string}  $admin
     * @return array{0:Organisation,1:User}
     */
    public function provisionWithAdmin(array $data, array $admin): array
    {
        return DB::transaction(function () use ($data, $admin) {
            $organisation = Organisation::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'sector' => $data['sector'],
                'status' => Organisation::STATUS_ACTIVE,
            ]);

            $this->roleProvisioner->provision($organisation);

            $organisation->subscription()->create([
                'plan' => Plan::DECOUVERTE->value,
                'status' => SubscriptionStatus::TRIAL->value,
                'trial_ends_at' => now()->addDays(30),
                'current_period_end' => now()->addDays(30),
            ]);

            // Création + rôle dans le contexte « team » de l'organisation.
            $user = $this->tenant->runFor($organisation, function () use ($organisation, $admin) {
                // Décompose le nom saisi en prénom / nom (colonnes non nullables).
                $parts = preg_split('/\s+/', trim($admin['name']), 2) ?: [];
                $firstName = $parts[0] ?? $admin['name'];
                $lastName = $parts[1] ?? '';

                $u = new User;
                $u->forceFill([
                    'organisation_id' => $organisation->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'name' => trim($admin['name']),
                    'email' => mb_strtolower($admin['email']),
                    'password' => $admin['password'], // cast « hashed » (Argon2id)
                    'is_active' => true,
                ])->save();
                $u->assignRole(Rbac::ADMIN);

                return $u;
            });

            return [$organisation, $user];
        });
    }
}
