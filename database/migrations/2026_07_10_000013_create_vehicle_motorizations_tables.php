<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motorisations d'un modèle de véhicule et plans d'entretien associés.
 * À la création d'un véhicule (modèle + motorisation), les emplacements sont
 * générés depuis le gabarit du modèle et les échéances d'entretien depuis les
 * plans de la motorisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_motorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_model_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);            // ex. « 2.3 dCi 145 ch »
            $table->string('fuel', 30)->nullable(); // diesel, essence, électrique…
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['vehicle_model_id', 'display_order'], 'veh_motor_model_order_idx');
        });

        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_motorization_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);             // MaintenanceType
            $table->string('label', 100)->nullable();
            $table->unsignedInteger('interval_km')->nullable();
            $table->unsignedSmallInteger('interval_months')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['vehicle_motorization_id', 'display_order'], 'maint_plan_motor_order_idx');
        });

        if (! Schema::hasColumn('vehicles', 'vehicle_motorization_id')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->foreignId('vehicle_motorization_id')->nullable()->after('vehicle_model_id')
                    ->constrained('vehicle_motorizations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vehicles', 'vehicle_motorization_id')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropConstrainedForeignId('vehicle_motorization_id');
            });
        }

        Schema::dropIfExists('maintenance_plans');
        Schema::dropIfExists('vehicle_motorizations');
    }
};
