<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Événements de gestion : anomalies, réparations, tâches. Alimentés à la
 * validation d'un protocole (une anomalie → un événement) ou déclarés à la
 * main, puis suivis sur un tableau Kanban (colonnes = statut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            $table->string('type')->default('anomalie');   // anomalie | reparation | autre
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('a_traiter'); // a_traiter | en_cours | resolu | ferme
            $table->string('priority')->default('normale'); // basse | normale | haute

            // Rattachements (facultatifs, coupés proprement si la cible disparaît).
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('protocol_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('protocol_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
