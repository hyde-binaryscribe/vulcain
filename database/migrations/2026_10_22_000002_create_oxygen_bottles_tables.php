<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi de l'oxygène embarqué : chaque véhicule porte une dotation de bouteilles
 * (emplacements déclarés par le gestionnaire), dont le niveau (pression bar →
 * pourcentage via min/max) est ajusté en service par l'équipage, et dont les
 * changements de bouteille sont tracés (journal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oxygen_bottles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80);                       // ex. « Principale », « Portable »
            $table->string('serial_number', 80)->nullable();   // n° de la bouteille en place
            $table->decimal('capacity_l', 6, 2)->nullable();   // capacité en litres
            $table->unsignedInteger('pressure_min')->default(0);
            $table->unsignedInteger('pressure_max')->default(200);
            $table->unsignedInteger('current_pressure')->nullable(); // niveau courant (bar)
            $table->string('status', 20)->default('en_service');     // en_service | a_remplacer | hors_service
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['organisation_id', 'vehicle_id', 'display_order'], 'oxy_bottle_veh_order_idx');
        });

        Schema::create('oxygen_bottle_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('oxygen_bottle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);                      // releve | remplacement | statut
            $table->unsignedInteger('pressure')->nullable();
            $table->unsignedTinyInteger('percent')->nullable();
            $table->string('serial_number', 80)->nullable();   // nouveau n° (remplacement)
            $table->string('previous_serial', 80)->nullable(); // ancien n° (remplacement)
            $table->string('note', 300)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['oxygen_bottle_id', 'recorded_at'], 'oxy_log_bottle_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oxygen_bottle_logs');
        Schema::dropIfExists('oxygen_bottles');
    }
};
