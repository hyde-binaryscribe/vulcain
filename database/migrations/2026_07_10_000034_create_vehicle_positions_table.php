<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Positions télématiques d'un véhicule (transférées par Traccar) : géoloc +
 * attributs OBD (vitesse, codes défaut…). Horodatage boîtier et serveur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('speed')->nullable();   // km/h
            $table->unsignedSmallInteger('course')->nullable();  // cap 0-359
            $table->integer('altitude')->nullable();             // m
            $table->boolean('valid')->default(true);             // fix GPS valide
            $table->json('attributes')->nullable();              // attributs OBD bruts
            $table->timestamp('device_time')->nullable();        // horodatage boîtier
            $table->timestamp('server_time')->nullable();        // réception Traccar
            $table->timestamps();

            $table->index(['organisation_id', 'vehicle_id', 'device_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_positions');
    }
};
