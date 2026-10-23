<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rattache (optionnellement) une ligne d'armement de référence à un type de
 * matériel du catalogue. Quand le lien est posé, la conformité devient exacte :
 * on compte le matériel embarqué de ce type plutôt qu'un rapprochement par nom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_type_equipment', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicle_type_equipment', 'material_type_id')) {
                $table->foreignId('material_type_id')->nullable()->after('designation')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_type_equipment', function (Blueprint $table) {
            if (Schema::hasColumn('vehicle_type_equipment', 'material_type_id')) {
                $table->dropConstrainedForeignId('material_type_id');
            }
        });
    }
};
