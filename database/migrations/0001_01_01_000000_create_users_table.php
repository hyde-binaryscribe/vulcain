<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Cloisonnement multi-tenant : chaque utilisateur appartient à une organisation.
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();

            // Identité
            $table->string('first_name');
            $table->string('last_name');
            $table->string('name'); // nom d'affichage (prénom + nom)
            $table->string('username')->nullable(); // identifiant interne
            $table->string('grade')->nullable();
            $table->string('email');
            $table->string('avatar_path')->nullable();

            // Accès
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();

            $table->timestamps();
            $table->softDeletes(); // suppression logique (intégrité des historiques)

            // Unicité au sein d'une organisation (jamais globale).
            $table->unique(['organisation_id', 'email']);
            $table->unique(['organisation_id', 'username']);
        });

        // Jetons de réinitialisation : cloisonnés par organisation, stockés hachés.
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('token'); // haché (jamais en clair)
            $table->timestamp('created_at')->nullable();

            $table->primary(['organisation_id', 'email']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
