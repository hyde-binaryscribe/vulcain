<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lien intra-Chatel : une prise ou fin de service peut venir d'une application
 * externe. L'agent y est connu par ses initiales, pas toujours par un compte
 * Vulkain : user_id devient facultatif et les noms saisis sont gardés tels quels.
 * external_id / close_external_id rendent l'envoi idempotent (un renvoi ne
 * crée pas une seconde session).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_sessions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('source', 20)->default('vulkain')->after('vehicle_id');
            $table->string('external_id', 100)->nullable()->after('source');
            $table->string('close_external_id', 100)->nullable()->after('external_id');
            $table->string('opened_by_name', 100)->nullable()->after('user_id');
            $table->string('partner_name', 100)->nullable()->after('partner_user_id');
            $table->string('closed_by_name', 100)->nullable()->after('closed_by');

            $table->unique(['organisation_id', 'external_id'], 'veh_sessions_org_external_unique');
            $table->unique(['organisation_id', 'close_external_id'], 'veh_sessions_org_close_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_sessions', function (Blueprint $table) {
            $table->dropUnique('veh_sessions_org_external_unique');
            $table->dropUnique('veh_sessions_org_close_external_unique');
            $table->dropColumn(['source', 'external_id', 'close_external_id', 'opened_by_name', 'partner_name', 'closed_by_name']);
        });
    }
};
