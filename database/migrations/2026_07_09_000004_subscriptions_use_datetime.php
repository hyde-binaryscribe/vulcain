<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passe les échéances d'abonnement de TIMESTAMP à DATETIME.
 * Sous MySQL, TIMESTAMP est limité à 2038-01-19 : une échéance lointaine
 * (ex. 2050) provoquait « Incorrect datetime value ». DATETIME couvre 1000–9999
 * et reste équivalent sous PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dateTime('trial_ends_at')->nullable()->change();
            $table->dateTime('current_period_end')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable()->change();
            $table->timestamp('current_period_end')->nullable()->change();
        });
    }
};
