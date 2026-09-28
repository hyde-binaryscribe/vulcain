<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bibliothèque de protocoles de désinfection (procédures documentées).
 * Pré-remplie selon les niveaux recommandés (type ARS) pour le secteur
 * « ambulance privée ». Éditable par l'organisation. Reliable à chaque
 * désinfection enregistrée (disinfection_records.disinfection_protocol_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disinfection_protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('desinfection'); // niveau : nettoyage_courant | desinfection | bio_nettoyage
            $table->string('cadence')->nullable();            // cadence lisible (« Après chaque transport », « Quotidien »…)
            $table->unsignedSmallInteger('frequency_days')->nullable(); // périodicité recommandée (jours), indicatif
            $table->text('procedure')->nullable();            // étapes de la procédure (une par ligne)
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organisation_id', 'is_active']);
        });

        Schema::table('disinfection_records', function (Blueprint $table) {
            $table->foreignId('disinfection_protocol_id')->nullable()->after('type')
                ->constrained('disinfection_protocols')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('disinfection_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disinfection_protocol_id');
        });

        Schema::dropIfExists('disinfection_protocols');
    }
};
