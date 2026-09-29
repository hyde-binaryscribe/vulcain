<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clé de source pour les événements générés automatiquement (échéances,
 * signalements carrosserie). Permet la déduplication (un seul événement ouvert
 * par échéance/objet) et la clôture automatique quand l'échéance est levée.
 * Les événements saisis manuellement gardent source_key = null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('source_key')->nullable()->after('type');
            $table->index(['organisation_id', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['organisation_id', 'source_key']);
            $table->dropColumn('source_key');
        });
    }
};
