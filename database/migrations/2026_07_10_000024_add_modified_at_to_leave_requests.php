<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marque une demande de congé éditée par son auteur après soumission : elle
 * repasse en attente de validation et le responsable voit une mention claire
 * « Modifiée » pour éviter les validations par erreur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->timestamp('modified_at')->nullable()->after('decision_note');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('modified_at');
        });
    }
};
