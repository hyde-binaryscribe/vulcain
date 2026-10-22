<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étend la portée des protocoles de service au-delà du type de véhicule : un
 * protocole peut désormais cibler un modèle précis (vehicle_model_id) ou un
 * véhicule précis (vehicle_id). La résolution se fait en cascade, du plus
 * précis au plus général : véhicule → modèle → type → défaut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_protocols', function (Blueprint $table) {
            if (! Schema::hasColumn('service_protocols', 'vehicle_model_id')) {
                $table->foreignId('vehicle_model_id')->nullable()->after('vehicle_type')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('service_protocols', 'vehicle_id')) {
                $table->foreignId('vehicle_id')->nullable()->after('vehicle_model_id')->constrained()->cascadeOnDelete();
            }

            $table->index(['organisation_id', 'phase', 'vehicle_model_id'], 'svc_proto_org_phase_model_idx');
            $table->index(['organisation_id', 'phase', 'vehicle_id'], 'svc_proto_org_phase_vehicle_idx');
        });
    }

    public function down(): void
    {
        Schema::table('service_protocols', function (Blueprint $table) {
            $table->dropIndex('svc_proto_org_phase_model_idx');
            $table->dropIndex('svc_proto_org_phase_vehicle_idx');

            if (Schema::hasColumn('service_protocols', 'vehicle_id')) {
                $table->dropConstrainedForeignId('vehicle_id');
            }
            if (Schema::hasColumn('service_protocols', 'vehicle_model_id')) {
                $table->dropConstrainedForeignId('vehicle_model_id');
            }
        });
    }
};
