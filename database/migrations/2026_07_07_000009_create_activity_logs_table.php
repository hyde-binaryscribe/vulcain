<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'activité : historique des actions par ressource (véhicule, matériel,
 * exemplaire, lot, emplacement). Alimente aussi le journal d'audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->nullable(); // dénormalisé (survit à la suppression)
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action'); // created | updated | deleted | status_changed …
            $table->string('description')->nullable();
            $table->json('properties')->nullable(); // {old:{}, new:{}}
            $table->timestamp('created_at')->nullable();

            $table->index(['organisation_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
