<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions de véhicule (prise / fin de service). Une session est ouverte par
 * un agent après la vérification de prise de service ; l'accès à la fiche du
 * véhicule est conditionné à une session ouverte. Une seule session ouverte
 * par véhicule : l'ouverture par un autre agent clôture la précédente
 * (passation). Remplace la table service_checks (état sans cycle de vie).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // agent qui prend le service

            $table->timestamp('opened_at');
            $table->unsignedInteger('open_mileage')->nullable();
            $table->json('open_steps')->nullable();   // vérification de prise de service
            $table->text('open_notes')->nullable();

            $table->timestamp('closed_at')->nullable(); // null = session ouverte
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('close_mileage')->nullable();
            $table->text('close_notes')->nullable();
            $table->string('close_reason', 20)->nullable(); // manual | handover

            $table->timestamps();

            $table->index(['organisation_id', 'vehicle_id', 'closed_at'], 'veh_sessions_org_veh_closed_idx');
            $table->index(['organisation_id', 'user_id', 'closed_at'], 'veh_sessions_org_user_closed_idx');
        });

        // La table service_checks (tour précédent) est remplacée par ce modèle à état.
        Schema::dropIfExists('service_checks');
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_sessions');
    }
};
