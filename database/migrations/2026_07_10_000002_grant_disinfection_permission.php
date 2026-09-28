<?php

use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use Illuminate\Database\Migrations\Migration;

/**
 * Diffuse la nouvelle permission « disinfections.record » aux organisations déjà
 * provisionnées : on rejoue le provisioning des rôles (idempotent), qui crée la
 * permission et la synchronise sur les rôles concernés (admin, pharmacie,
 * vérificateur).
 */
return new class extends Migration
{
    public function up(): void
    {
        $provisioner = app(RoleProvisioner::class);

        Organisation::query()->each(function (Organisation $organisation) use ($provisioner) {
            $provisioner->provision($organisation);
        });
    }

    public function down(): void
    {
        // Permission conservée (retrait non nécessaire).
    }
};
