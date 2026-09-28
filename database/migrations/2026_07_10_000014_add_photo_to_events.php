<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo optionnelle attachée à un événement / une anomalie (stockée sur le
 * disque privé ; servie via une route authentifiée).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'photo_path')) {
            Schema::table('events', function (Blueprint $table) {
                $table->string('photo_path')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'photo_path')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('photo_path');
            });
        }
    }
};
