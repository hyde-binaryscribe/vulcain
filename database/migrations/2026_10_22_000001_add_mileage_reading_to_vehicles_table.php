<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dernier relevé kilométrique appliqué au véhicule : quand, d'où (prise ou fin
 * de service, plein, entretien, fiche) et par qui. Transmis à Itineris avec le
 * kilométrage (docs/LIEN-ITINERIS.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->timestamp('mileage_at')->nullable()->after('mileage');
            $table->string('mileage_source', 20)->nullable()->after('mileage_at');
            $table->string('mileage_by', 100)->nullable()->after('mileage_source');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['mileage_at', 'mileage_source', 'mileage_by']);
        });
    }
};
