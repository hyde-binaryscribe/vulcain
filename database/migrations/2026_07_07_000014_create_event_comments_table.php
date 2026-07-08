<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commentaires / suivi d'un événement (fil de discussion sur une carte Kanban).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['organisation_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_comments');
    }
};
