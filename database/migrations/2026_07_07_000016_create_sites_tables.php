<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sites d'une organisation (centres, dépôts). Les véhicules et emplacements
 * fixes peuvent être rattachés à un site. Le périmètre des utilisateurs « site »
 * est défini par la table pivot site_user (aucune ligne = accès à tous les sites).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind')->default('centre'); // centre | depot | autre
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organisation_id', 'is_active']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('organisation_id')->constrained()->nullOnDelete();
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('vehicle_id')->constrained()->nullOnDelete();
        });

        Schema::create('site_user', function (Blueprint $table) {
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['site_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_user');
        Schema::table('locations', fn (Blueprint $t) => $t->dropConstrainedForeignId('site_id'));
        Schema::table('vehicles', fn (Blueprint $t) => $t->dropConstrainedForeignId('site_id'));
        Schema::dropIfExists('sites');
    }
};
