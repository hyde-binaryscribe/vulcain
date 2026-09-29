<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode binôme : un équipier peut être rattaché à la session ouverte par l'agent.
 * Il partage la même session (mêmes droits d'action) sans en ouvrir une seconde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_sessions', function (Blueprint $table) {
            $table->foreignId('partner_user_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
            $table->index(['organisation_id', 'partner_user_id', 'closed_at'], 'veh_sessions_org_partner_closed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_sessions', function (Blueprint $table) {
            $table->dropIndex('veh_sessions_org_partner_closed_idx');
            $table->dropConstrainedForeignId('partner_user_id');
        });
    }
};
