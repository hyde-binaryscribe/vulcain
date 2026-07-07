<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Emplacements : nature mobile (véhicule) / fixe (dépôt, pièce de stock) et
 * possibilité qu'un matériel héberge un emplacement (ex. pochette d'un Lifepak).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('kind')->default('mobile')->after('vehicle_id'); // mobile | fixe
            // Matériel hôte : cet emplacement est physiquement porté par un matériel.
            $table->foreignId('holder_material_id')->nullable()->after('parent_id')
                ->constrained('materials')->nullOnDelete();
        });

        // Rétro-compatibilité : sans véhicule => fixe, avec véhicule => mobile.
        DB::table('locations')->whereNull('vehicle_id')->update(['kind' => 'fixe']);
        DB::table('locations')->whereNotNull('vehicle_id')->update(['kind' => 'mobile']);
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('holder_material_id');
            $table->dropColumn('kind');
        });
    }
};
