<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schémas de carrosserie propres à un modèle de véhicule : une image par vue
 * (avant / arrière / côtés / toit). Le pointage des anomalies s'affiche sur
 * ces schémas précis (repli sur un schéma générique si absent).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_model_schematics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_model_id')->constrained()->cascadeOnDelete();
            $table->string('view', 20); // avant, arriere, gauche, droite, dessus
            $table->string('image_path');
            $table->timestamps();

            $table->unique(['vehicle_model_id', 'view'], 'veh_model_schema_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_model_schematics');
    }
};
