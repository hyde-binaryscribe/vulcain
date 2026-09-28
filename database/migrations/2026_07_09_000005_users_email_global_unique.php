<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accès par compte (hôte unique app.vulkain.eu) : l'e-mail identifie
 * l'utilisateur à la connexion, sans sous-domaine pour désigner l'organisation.
 * Il devient donc unique au global (un compte = une organisation) au lieu
 * d'unique par organisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_organisation_id_email_unique');
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->unique(['organisation_id', 'email']);
        });
    }
};
