<?php

namespace App\Console\Commands;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Attribue un rôle à un utilisateur existant dans une organisation.
 */
class GrantRoleCommand extends Command
{
    protected $signature = 'vulcain:grant-role
        {email : Adresse e-mail de l’utilisateur}
        {--organisation= : Slug (sous-domaine) de l’organisation}
        {--role= : Rôle à attribuer}';

    protected $description = 'Attribue un rôle à un utilisateur existant.';

    public function handle(TenantContext $tenant, RoleProvisioner $provisioner): int
    {
        $slug = $this->option('organisation') ?: $this->ask('Slug de l’organisation (sous-domaine)');
        $organisation = Organisation::query()->where('slug', $slug)->first();

        if ($organisation === null) {
            $this->error("Organisation « {$slug} » introuvable.");

            return self::FAILURE;
        }

        $email = mb_strtolower(trim($this->argument('email')));
        $role = $this->option('role') ?: $this->choice('Rôle', Rbac::roles(), Rbac::ADMIN);

        if (! in_array($role, Rbac::roles(), true)) {
            $this->error("Rôle « {$role} » invalide.");

            return self::FAILURE;
        }

        return $tenant->runFor($organisation, function () use ($organisation, $email, $role, $provisioner) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $this->error("Utilisateur « {$email} » introuvable dans cette organisation.");

                return self::FAILURE;
            }

            $provisioner->provision($organisation);
            $user->syncRoles([$role]);

            $this->info("Rôle « {$role} » attribué à {$email}.");

            return self::SUCCESS;
        });
    }
}
