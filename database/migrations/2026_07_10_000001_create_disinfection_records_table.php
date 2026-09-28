<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traçabilité des désinfections / nettoyages des véhicules (secours/ambulance).
 * Chaque opération est horodatée, typée et attribuée. La périodicité imposée est
 * portée par le type de véhicule (vehicle_types.disinfection_interval_days).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disinfection_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('desinfection'); // nettoyage_courant | desinfection | bio_nettoyage
            $table->dateTime('performed_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'vehicle_id', 'performed_at']);
        });

        Schema::table('vehicle_types', function (Blueprint $table) {
            // Périodicité de désinfection imposée (jours). Null = pas d'échéance.
            $table->unsignedSmallInteger('disinfection_interval_days')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->dropColumn('disinfection_interval_days');
        });

        Schema::dropIfExists('disinfection_records');
    }
};
