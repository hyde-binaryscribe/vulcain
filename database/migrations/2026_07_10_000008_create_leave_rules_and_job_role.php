<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RH — rôle métier de l'utilisateur (ADE, auxiliaire…) et règles de congés par
 * métier (effectif simultané max + droits annuels).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'job_role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('job_role')->nullable()->after('grade');
            });
        }

        Schema::create('leave_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('job_role');
            $table->unsignedSmallInteger('max_simultaneous')->nullable(); // absences simultanées max
            $table->unsignedSmallInteger('annual_days')->nullable();       // droits de congés (jours/an)
            $table->timestamps();

            $table->unique(['organisation_id', 'job_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_rules');

        if (Schema::hasColumn('users', 'job_role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('job_role');
            });
        }
    }
};
