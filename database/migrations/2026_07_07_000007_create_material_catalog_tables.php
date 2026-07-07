<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue matériel : catégories + matériels (suivi par quantité ou à l'unité,
 * statuts, emplacement, péremption/contrôle). Architecture prête pour QR/codes-barres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['organisation_id', 'name']);
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('material_categories')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();

            $table->string('reference')->nullable();  // référence interne
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('tracking_mode')->default('quantity'); // quantity | unit
            $table->unsignedInteger('theoretical_qty')->default(0);
            $table->unsignedInteger('minimum_qty')->default(0);
            $table->string('serial_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('next_check_date')->nullable();
            $table->string('status')->default('conforme');
            $table->text('observations')->nullable();
            $table->string('photo_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organisation_id', 'category_id']);
            $table->index(['organisation_id', 'status']);
            $table->index(['organisation_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
        Schema::dropIfExists('material_categories');
    }
};
