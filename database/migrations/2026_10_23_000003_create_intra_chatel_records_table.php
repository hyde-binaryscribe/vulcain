<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiels publiés par intra-Chatel (agents, véhicules, sacs, bouteilles
 * d'oxygène), copie en lecture seule : intra-Chatel fait référence, Vulkain ne
 * modifie jamais ces données. Chaque fiche peut être associée à son équivalent
 * Vulkain (linked_id, selon le type), automatiquement ou à la main.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intra_chatel_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20); // agent | vehicle | bag | oxygen_bottle
            $table->string('external_id', 100); // identifiant intra-Chatel
            $table->string('label', 150);
            $table->json('data'); // fiche telle que publiée
            $table->unsignedBigInteger('linked_id')->nullable(); // users / vehicles / locations / oxygen_bottles
            $table->string('link_mode', 10)->nullable(); // auto | manual
            $table->timestamp('synced_at');
            $table->timestamp('missing_since')->nullable(); // absente de la dernière publication
            $table->timestamps();

            $table->unique(['organisation_id', 'kind', 'external_id'], 'intra_chatel_records_unique');
            $table->index(['organisation_id', 'kind', 'linked_id'], 'intra_chatel_records_link_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intra_chatel_records');
    }
};
