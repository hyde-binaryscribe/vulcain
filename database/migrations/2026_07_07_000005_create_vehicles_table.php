<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Véhicules (engins) d'une organisation + affectations des utilisateurs autorisés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name');                    // VSAV 01
            $table->string('type')->nullable();        // VSAV, FPT, VTU, VL, ASSU…
            $table->string('callsign')->nullable();    // indicatif
            $table->string('registration')->nullable(); // immatriculation
            $table->string('center')->nullable();      // centre de secours / rattachement
            $table->string('photo_path')->nullable();
            $table->string('status')->default('disponible');
            $table->date('commissioned_at')->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organisation_id', 'status']);
        });

        // Affectations : utilisateurs autorisés sur un véhicule.
        Schema::create('vehicle_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['vehicle_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_user');
        Schema::dropIfExists('vehicles');
    }
};
