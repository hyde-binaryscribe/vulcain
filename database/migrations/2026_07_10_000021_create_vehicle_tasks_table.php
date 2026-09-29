<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tâches persistantes rattachées à un véhicule : ajoutées par un responsable,
 * réalisées (cochées) par l'agent sur le terrain. Restent tant qu'elles ne sont
 * pas faites, quel que soit l'agent ou le service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('notes')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organisation_id', 'vehicle_id', 'done_at'], 'veh_tasks_org_veh_done_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_tasks');
    }
};
