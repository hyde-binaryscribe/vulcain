<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protocoles réalisés + leurs lignes.
 *
 * Au démarrage, un INSTANTANÉ (snapshot) des éléments du modèle est copié dans
 * protocol_items : les modifications ultérieures du catalogue ou du modèle ne
 * changent JAMAIS un protocole existant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            // Véhicule cible (null pour un protocole sur emplacement fixe / dépôt).
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('protocol_template_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('template_version')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // vérificateur

            // Instantané d'en-tête (survit à la suppression des références).
            $table->string('vehicle_name');
            $table->string('template_name')->nullable();
            $table->json('types')->nullable(); // instantané des types du modèle

            $table->string('status')->default('draft'); // draft | validated
            $table->timestamp('started_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->timestamps();

            $table->index(['organisation_id', 'vehicle_id', 'status']);
        });

        Schema::create('protocol_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('protocol_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();

            // Instantané figé au démarrage.
            $table->string('material_name');
            $table->string('reference')->nullable();
            $table->string('location_name')->nullable();
            $table->string('tracking_mode')->default('quantity');
            $table->unsignedInteger('expected_qty')->default(0);
            $table->boolean('photo_required')->default(false);
            $table->unsignedInteger('display_order')->default(0);

            // Saisie du contrôle.
            $table->unsignedInteger('observed_qty')->nullable();
            $table->string('state')->nullable();       // conforme | manquant | hs | a_remplacer
            $table->text('observation')->nullable();
            $table->boolean('checked')->default(false); // contrôlé ?

            // Verrouillage optimiste (autosave / accès concurrents).
            $table->unsignedInteger('row_version')->default(0);

            $table->timestamps();

            $table->index(['protocol_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_items');
        Schema::dropIfExists('protocols');
    }
};
