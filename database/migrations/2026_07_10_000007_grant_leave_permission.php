<?php

use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use Illuminate\Database\Migrations\Migration;

/**
 * Diffuse la permission « leave.manage » (validation des congés) aux
 * organisations déjà provisionnées (rejoue le provisioning idempotent).
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
        // Permission conservée.
    }
};
