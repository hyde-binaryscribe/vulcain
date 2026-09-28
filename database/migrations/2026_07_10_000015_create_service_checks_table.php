<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prise de service : à l'ouverture de l'appli terrain, le personnel scanne le
 * QR (ou choisit un véhicule), relève le kilométrage et déroule la procédure
 * de prise de service avant d'accéder à la fiche du véhicule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('mileage')->nullable();
            $table->json('steps')->nullable();          // [{label, done}]
            $table->text('notes')->nullable();
            $table->timestamp('started_at');
            $table->timestamps();

            $table->index(['organisation_id', 'vehicle_id', 'started_at'], 'service_checks_org_veh_started_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_checks');
    }
};
