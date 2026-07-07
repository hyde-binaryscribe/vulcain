<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modèles de protocole : définissent le PÉRIMÈTRE à contrôler (un véhicule ou
 * un emplacement, avec ou sans ses emplacements enfants), la fréquence et un
 * ou plusieurs types (inventaire / vérification / contrôle). Le contenu (les
 * matériels) est déduit du périmètre à l'exécution, moins les exclusions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            // Véhicule de rattachement (déduit du périmètre ; null pour un dépôt fixe).
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->json('types')->nullable(); // ['inventaire','verification','controle_vehicule']

            // Périmètre : cible = un véhicule OU un emplacement (+ enfants).
            $table->string('scope_type')->default('vehicle'); // vehicle | location
            $table->unsignedBigInteger('scope_id')->nullable(); // id du véhicule ou de l'emplacement
            $table->boolean('include_children')->default(true);
            $table->json('excluded_material_ids')->nullable(); // matériels retirés du périmètre

            $table->string('frequency')->default('weekly'); // daily|weekly|monthly|quarterly|custom
            $table->unsignedInteger('custom_days')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organisation_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_templates');
    }
};
