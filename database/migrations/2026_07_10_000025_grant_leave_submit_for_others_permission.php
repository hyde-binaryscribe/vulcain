<?php

use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use Illuminate\Database\Migrations\Migration;

/**
 * Diffuse le rôle « Modérateur » et la permission « leave.submit_for_others »
 * (dépôt de demandes de congés pour un autre agent, sans validation) aux
 * organisations déjà provisionnées.
 */
return new class extends Migration
{
    public function up(): void
    {
        $provisioner = app(RoleProvisioner::class);

        Organisation::query()->each(fn (Organisation $organisation) => $provisioner->provision($organisation));
    }

    public function down(): void
    {
        // Rôle et permission conservés.
    }
};
