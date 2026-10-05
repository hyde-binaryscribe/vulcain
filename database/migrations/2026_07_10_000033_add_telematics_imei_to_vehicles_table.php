<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMEI du boîtier télématique (FMC003…) rattaché au véhicule : sert à résoudre
 * IMEI → véhicule lors de l'ingestion des positions transférées par Traccar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('telematics_imei', 20)->nullable()->after('registration');
            $table->index('telematics_imei');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['telematics_imei']);
            $table->dropColumn('telematics_imei');
        });
    }
};
