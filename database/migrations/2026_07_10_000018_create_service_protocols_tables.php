<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Protocoles de service configurables (ouverture / fermeture) par type de
 * véhicule, avec des champs typés (texte, chiffre, jauge, photo, contrôle
 * vide/OK/NOK…) et des déclencheurs d'alerte par champ. Les réponses sont
 * stockées sur la session (vehicle_sessions.open_responses / close_responses).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('vehicle_type', 50)->nullable(); // null = protocole par défaut (tous types)
            $table->string('phase', 20);                     // ProtocolPhase
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organisation_id', 'phase', 'vehicle_type'], 'svc_proto_org_phase_type_idx');
        });

        Schema::create('service_protocol_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_protocol_id')->constrained()->cascadeOnDelete();
            $table->string('label', 200);
            $table->string('type', 20);            // ProtocolFieldType
            $table->json('config')->nullable();    // unité, min/max, multiligne…
            $table->boolean('required')->default(false);
            $table->json('alert')->nullable();     // { enabled, operator, threshold, severity, message }
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['service_protocol_id', 'display_order'], 'svc_proto_field_order_idx');
        });

        Schema::table('vehicle_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicle_sessions', 'open_responses')) {
                $table->json('open_responses')->nullable()->after('open_steps');
            }
            if (! Schema::hasColumn('vehicle_sessions', 'close_responses')) {
                $table->json('close_responses')->nullable()->after('close_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_sessions', function (Blueprint $table) {
            foreach (['open_responses', 'close_responses'] as $col) {
                if (Schema::hasColumn('vehicle_sessions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('service_protocol_fields');
        Schema::dropIfExists('service_protocols');
    }
};
