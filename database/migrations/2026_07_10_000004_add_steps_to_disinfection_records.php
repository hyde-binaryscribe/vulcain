<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réalisation d'un protocole de désinfection : on fige les étapes cochées au
 * moment de l'exécution (instantané indépendant des modifications ultérieures
 * du protocole), pour la traçabilité.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('disinfection_records', 'steps')) {
            Schema::table('disinfection_records', function (Blueprint $table) {
                $table->json('steps')->nullable()->after('disinfection_protocol_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('disinfection_records', function (Blueprint $table) {
            $table->dropColumn('steps');
        });
    }
};
