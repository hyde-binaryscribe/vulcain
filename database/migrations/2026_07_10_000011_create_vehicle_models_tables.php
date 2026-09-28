<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modèles de véhicule : on configure une fois un gabarit d'emplacements
 * (mobile / sac, hiérarchie possible) et, à la création d'un véhicule sur ce
 * modèle, ses emplacements sont générés automatiquement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'name'], 'veh_models_org_name_uq');
            $table->index(['organisation_id', 'is_active'], 'veh_models_org_active_idx');
        });

        Schema::create('vehicle_model_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_model_id')->constrained()->cascadeOnDelete();
            // Emplacement parent au sein du même gabarit (sous-emplacement).
            $table->foreignId('parent_id')->nullable()->constrained('vehicle_model_locations')->nullOnDelete();
            $table->string('name', 100);
            $table->string('kind', 20)->default('mobile');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['vehicle_model_id', 'display_order'], 'veh_model_loc_model_order_idx');
        });

        if (! Schema::hasColumn('vehicles', 'vehicle_model_id')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->foreignId('vehicle_model_id')->nullable()->after('type')->constrained('vehicle_models')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vehicles', 'vehicle_model_id')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropConstrainedForeignId('vehicle_model_id');
            });
        }

        Schema::dropIfExists('vehicle_model_locations');
        Schema::dropIfExists('vehicle_models');
    }
};
