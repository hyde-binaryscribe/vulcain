<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prix au litre d'un plein : conservé et utilisé pour calculer le coût total
 * (coût = litres × prix au litre).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fuel_records', 'price_per_liter')) {
            Schema::table('fuel_records', function (Blueprint $table) {
                $table->decimal('price_per_liter', 6, 3)->nullable()->after('liters');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fuel_records', 'price_per_liter')) {
            Schema::table('fuel_records', function (Blueprint $table) {
                $table->dropColumn('price_per_liter');
            });
        }
    }
};
