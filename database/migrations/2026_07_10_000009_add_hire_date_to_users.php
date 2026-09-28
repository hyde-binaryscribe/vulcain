<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Date d'arrivée (embauche) de l'utilisateur : sert à calculer automatiquement
 * les droits à congés au prorata de la présence dans l'année.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'hire_date')) {
            Schema::table('users', function (Blueprint $table) {
                $table->date('hire_date')->nullable()->after('job_role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'hire_date')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('hire_date');
            });
        }
    }
};
