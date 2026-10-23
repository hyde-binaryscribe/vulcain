<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue des types de bouteilles d'oxygène (B2, B5, B11, B15…), avec leurs
 * caractéristiques propres (capacité en eau, pression min/max, volume de gaz).
 * À la déclaration d'une bouteille, choisir le type pré-remplit ces valeurs :
 * l'agent ne saisit plus que le type, le n° de série et le niveau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oxygen_bottle_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);                     // ex. « B5 »
            $table->decimal('capacity_l', 6, 2)->nullable(); // capacité en eau (L)
            $table->unsignedInteger('pressure_min')->default(0);
            $table->unsignedInteger('pressure_max')->default(200);
            $table->unsignedInteger('volume_l')->nullable(); // volume de gaz (L)
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organisation_id', 'display_order'], 'oxy_type_org_order_idx');
        });

        Schema::table('oxygen_bottles', function (Blueprint $table) {
            if (! Schema::hasColumn('oxygen_bottles', 'oxygen_bottle_type_id')) {
                $table->foreignId('oxygen_bottle_type_id')->nullable()->after('vehicle_id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('oxygen_bottles', function (Blueprint $table) {
            if (Schema::hasColumn('oxygen_bottles', 'oxygen_bottle_type_id')) {
                $table->dropConstrainedForeignId('oxygen_bottle_type_id');
            }
        });

        Schema::dropIfExists('oxygen_bottle_types');
    }
};
