<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des consommables sortis d'un véhicule pendant un service (traçabilité).
 * Chaque sortie décrémente le stock du matériel (quantité, lot FEFO, ou exemplaire
 * à n° de série). Sert aussi au réarmement (remise au niveau théorique) et à
 * l'alerte en cas de manquement non réarmé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_item_id')->nullable()->constrained('material_items')->nullOnDelete();
            $table->foreignId('stock_lot_id')->nullable()->constrained('stock_lots')->nullOnDelete();
            $table->foreignId('vehicle_session_id')->nullable()->constrained('vehicle_sessions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('serial_number')->nullable(); // instantané du n° de série sorti
            $table->timestamp('consumed_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'vehicle_id']);
            $table->index('vehicle_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_consumptions');
    }
};
