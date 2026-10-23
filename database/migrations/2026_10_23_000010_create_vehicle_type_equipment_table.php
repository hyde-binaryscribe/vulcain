<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Armement de référence par type de véhicule : la liste des équipements attendus
 * (relevage, ventilation/oxygène, monitorage…), chacun avec sa quantité et son
 * niveau d'obligation (obligatoire / conditionnel / facultatif). Base à partir
 * de l'armement légal (NF EN 1789 / arrêté du 12 décembre 2017), éditable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_type_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->constrained()->cascadeOnDelete();
            $table->string('category', 80);        // ex. « Ventilation / Oxygène »
            $table->string('designation', 160);    // ex. « Brancard principal (EN 1865) »
            $table->unsignedInteger('quantity')->default(1);
            $table->string('level', 20)->default('obligatoire'); // ArmamentLevel
            $table->string('note', 200)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['organisation_id', 'vehicle_type_id', 'display_order'], 'vte_type_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_type_equipment');
    }
};
