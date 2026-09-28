<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi mécanique des véhicules : opérations d'entretien / réparation, avec
 * relevé kilométrique et échéance suivante (date et/ou km) qui alimente les
 * alertes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('revision');
            $table->date('performed_at');
            $table->unsignedInteger('mileage')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->string('provider')->nullable();
            $table->text('notes')->nullable();
            $table->date('next_due_at')->nullable();
            $table->unsignedInteger('next_due_mileage')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'vehicle_id', 'type'], 'maint_rec_org_veh_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
