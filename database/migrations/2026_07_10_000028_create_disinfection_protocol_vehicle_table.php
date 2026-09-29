<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Affectation des protocoles de désinfection aux véhicules.
 *
 * La périodicité de désinfection n'est plus portée par le type de véhicule
 * (vehicle_types.disinfection_interval_days, désormais obsolète) : chaque
 * protocole ARS a sa propre périodicité (disinfection_protocols.frequency_days),
 * et l'échéance d'un véhicule est calculée à partir des protocoles qui lui sont
 * affectés (protocole daté = une échéance ; sans périodicité = procédure à suivre
 * à l'usage, sans échéance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disinfection_protocol_vehicle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('disinfection_protocol_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['vehicle_id', 'disinfection_protocol_id'], 'disinf_proto_vehicle_unique');
            $table->index(['organisation_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disinfection_protocol_vehicle');
    }
};
