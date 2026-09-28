<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Détail d'un modèle de véhicule : marque, modèle commercial, année et
 * carrossier (aménageur). Le libellé (name) reste stocké, composé à partir
 * de ces champs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_models', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicle_models', 'brand')) {
                $table->string('brand', 100)->nullable()->after('name');
            }
            if (! Schema::hasColumn('vehicle_models', 'model')) {
                $table->string('model', 100)->nullable()->after('brand');
            }
            if (! Schema::hasColumn('vehicle_models', 'year')) {
                $table->unsignedSmallInteger('year')->nullable()->after('model');
            }
            if (! Schema::hasColumn('vehicle_models', 'coachbuilder')) {
                $table->string('coachbuilder', 100)->nullable()->after('year');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_models', function (Blueprint $table) {
            foreach (['brand', 'model', 'year', 'coachbuilder'] as $column) {
                if (Schema::hasColumn('vehicle_models', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
