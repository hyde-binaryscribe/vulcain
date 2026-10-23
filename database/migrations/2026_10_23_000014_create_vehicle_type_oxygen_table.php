<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dotation d'oxygène de référence par type de véhicule : la répartition attendue
 * en bouteilles (type de bouteille + quantité + emplacement). Le volume O2 requis
 * du type se déduit de cette répartition (Σ volume de bouteille × quantité).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_type_oxygen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('oxygen_bottle_type_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('label', 80)->nullable();   // emplacement, ex. « Arrière »
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['organisation_id', 'vehicle_type_id', 'display_order'], 'vto_type_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_type_oxygen');
    }
};
