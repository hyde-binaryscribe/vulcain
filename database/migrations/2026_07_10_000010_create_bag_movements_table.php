<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traçabilité des mouvements de sacs entre véhicules (un sac est un emplacement
 * de nature « sac » rattaché à un véhicule ; le transférer réaffecte le sac et
 * son contenu à une autre ambulance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bag_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete(); // le sac
            $table->foreignId('from_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('to_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moved_at');
            $table->timestamps();

            $table->index(['organisation_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bag_movements');
    }
};
