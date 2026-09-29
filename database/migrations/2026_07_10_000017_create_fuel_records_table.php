<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pleins de carburant : litrage, kilométrage, coût optionnel. Sert au suivi de
 * la consommation (L/100 km calculée entre deux pleins « à ras »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('filled_at');
            $table->unsignedInteger('mileage');
            $table->decimal('liters', 6, 2);
            $table->decimal('cost', 8, 2)->nullable();
            $table->boolean('full_tank')->default(true); // plein complet (fiable pour la conso)
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'vehicle_id', 'mileage'], 'fuel_org_veh_mileage_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_records');
    }
};
