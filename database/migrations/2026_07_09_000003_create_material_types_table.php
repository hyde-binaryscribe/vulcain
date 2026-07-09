<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Type de matériel (Thermomètre, Scope, Compresse 5×5…) : niveau intermédiaire
 * entre la catégorie (regroupement large) et le modèle (marque + modèle). Le
 * type porte le mode de suivi : un thermomètre est durable (n° de série), une
 * compresse est consommable (lot + péremption).
 *
 * Le « modèle » est la table `materials` existante, enrichie de material_type_id
 * et brand (marque).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('material_categories')->nullOnDelete();
            $table->string('name', 150);
            $table->string('tracking_mode')->default('serial'); // serial | lot | quantity
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'name']);
            $table->index(['organisation_id', 'is_active']);
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreignId('material_type_id')->nullable()->after('category_id')->constrained('material_types')->nullOnDelete();
            $table->string('brand')->nullable()->after('material_type_id'); // marque
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_type_id');
            $table->dropColumn('brand');
        });

        Schema::dropIfExists('material_types');
    }
};
