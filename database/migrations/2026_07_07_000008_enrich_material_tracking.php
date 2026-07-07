<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrichit le suivi du matériel :
 *  - quantité      -> stock courant sur le matériel (current_qty)
 *  - unitaire      -> exemplaires physiques (material_items, n° de série)
 *  - consommable   -> lots périssables (stock_lots, n° de lot + péremption)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Stock courant pour les matériels suivis par quantité.
        Schema::table('materials', function (Blueprint $table) {
            $table->unsignedInteger('current_qty')->default(0)->after('minimum_qty');
        });

        // Exemplaires physiques (mode unitaire).
        Schema::create('material_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('serial_number')->nullable();
            $table->string('status')->default('conforme');
            $table->date('next_check_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organisation_id', 'material_id']);
        });

        // Lots périssables (mode consommable) — base de la gestion FEFO.
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('lot_number')->nullable();
            $table->unsignedInteger('quantity')->default(0);
            $table->date('received_at')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('conforme');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organisation_id', 'material_id']);
            // Sortie FEFO : la péremption la plus proche en premier.
            $table->index(['material_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
        Schema::dropIfExists('material_items');
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('current_qty');
        });
    }
};
