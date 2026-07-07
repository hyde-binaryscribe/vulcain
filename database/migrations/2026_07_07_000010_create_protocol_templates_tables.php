<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modèles de protocole : définissent, par véhicule, le matériel à contrôler,
 * les quantités attendues, l'ordre, les photos obligatoires et la fréquence.
 * Un modèle porte un ou plusieurs types (inventaire / vérification / contrôle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('types')->nullable(); // ['inventaire','verification','controle_vehicule']
            $table->string('frequency')->default('weekly'); // daily|weekly|monthly|quarterly|custom
            $table->unsignedInteger('custom_days')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organisation_id', 'vehicle_id']);
        });

        Schema::create('protocol_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('protocol_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->unsignedInteger('expected_qty')->default(0);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('photo_required')->default(false);
            $table->timestamps();

            $table->unique(['protocol_template_id', 'material_id']);
            $table->index(['organisation_id', 'protocol_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_template_items');
        Schema::dropIfExists('protocol_templates');
    }
};
