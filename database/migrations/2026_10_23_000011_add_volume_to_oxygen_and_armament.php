<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Volume d'oxygène (litres de gaz) : porté par chaque bouteille (volume embarqué)
 * et par une ligne d'armement de référence (volume total obligatoire d'un type).
 * Permet une conformité O2 calculée : somme des bouteilles vs volume requis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oxygen_bottles', function (Blueprint $table) {
            if (! Schema::hasColumn('oxygen_bottles', 'volume_l')) {
                $table->unsignedInteger('volume_l')->nullable()->after('capacity_l');
            }
        });

        Schema::table('vehicle_type_equipment', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicle_type_equipment', 'volume_l')) {
                $table->unsignedInteger('volume_l')->nullable()->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('oxygen_bottles', function (Blueprint $table) {
            if (Schema::hasColumn('oxygen_bottles', 'volume_l')) {
                $table->dropColumn('volume_l');
            }
        });
        Schema::table('vehicle_type_equipment', function (Blueprint $table) {
            if (Schema::hasColumn('vehicle_type_equipment', 'volume_l')) {
                $table->dropColumn('volume_l');
            }
        });
    }
};
