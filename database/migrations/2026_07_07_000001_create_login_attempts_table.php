<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tentatives de connexion : base du blocage temporaire après échecs
 * et de la traçabilité (réussies / échouées).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            // Nullable : une tentative peut cibler un contexte sans organisation résolue.
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('successful')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['organisation_id', 'email', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
