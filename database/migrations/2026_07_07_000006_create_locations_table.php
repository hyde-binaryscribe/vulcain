<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emplacements et sous-emplacements (hiérarchie) : organisent le matériel par
 * véhicule (ou en réserve globale).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            // Véhicule de rattachement (null = réserve / global).
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            // Sous-emplacement d'un emplacement parent.
            $table->foreignId('parent_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organisation_id', 'vehicle_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
