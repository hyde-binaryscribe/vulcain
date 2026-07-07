<?php

use App\Domain\Sectors\Sector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secteur d'activité de l'organisation (verticalisation). Additif : les
 * organisations existantes prennent le secteur par défaut (SDIS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('sector')->default(Sector::default()->value)->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('sector');
        });
    }
};
