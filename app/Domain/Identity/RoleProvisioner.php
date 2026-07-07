<?php

namespace App\Domain\Identity;

use App\Models\Organisation;
use App\Support\Tenancy\TenantContext;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provisionne les rôles et permissions d'une organisation (idempotent).
 *
 * Les permissions sont globales ; les rôles sont cloisonnés par organisation
 * (mode « teams » de spatie, clé organisation_id).
 */
class RoleProvisioner
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function provision(Organisation $organisation): void
    {
        $this->ensurePermissionsExist();

        $this->tenant->runFor($organisation, function (): void {
            foreach (Rbac::rolePermissions() as $roleName => $permissions) {
                $role = Role::findOrCreate($roleName, 'web');
                $role->syncPermissions($permissions);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function ensurePermissionsExist(): void
    {
        foreach (Rbac::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}
