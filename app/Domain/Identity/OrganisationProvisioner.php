<?php

namespace App\Domain\Identity;

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
            $this->invitations->invite($organisation, $adminEmail, Rbac::ADMIN);

            return $organisation;
        });
    }
}
