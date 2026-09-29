<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * État carrosserie : points d'anomalie positionnés sur un schéma du véhicule
 * (vues avant / arrière / côtés / toit), avec description et photo. Pointés
 * lors des vérifications de prise et fin de service ; les points existants
 * restent visibles jusqu'à leur résolution.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_body_damages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('view', 20);              // avant, arriere, gauche, droite, dessus
            $table->decimal('pos_x', 5, 2);          // position en % (0–100) sur le schéma
            $table->decimal('pos_y', 5, 2);
            $table->string('description', 500);
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('ouverte'); // ouverte, resolue
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'vehicle_id', 'status'], 'body_dmg_org_veh_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_body_damages');
    }
};
