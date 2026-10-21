<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lien avec Itineris (régulation de transport sanitaire) : identité commune
 * du véhicule. Voir docs/LIEN-ITINERIS.md.
 *  - uuid : identifiant public, seul identifiant exposé par l'API (jamais l'id interne) ;
 *  - vin : numéro de série (17 caractères), seule identité physique qui ne change pas ;
 *  - version : incrémentée à chaque modification d'un champ partagé (concurrence optimiste).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('vin', 17)->nullable()->after('registration');
            $table->unsignedInteger('version')->default(1)->after('observations');
        });

        // Véhicules existants : un uuid chacun (en PHP pour rester compatible SQLite).
        DB::table('vehicles')->whereNull('uuid')->orderBy('id')->select('id')
            ->each(fn ($v) => DB::table('vehicles')->where('id', $v->id)->update(['uuid' => (string) Str::uuid()]));

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'vin', 'version']);
        });
    }
};
