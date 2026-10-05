<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scellé (plomb numéroté) sur un emplacement/sac : si le scellé est intact,
 * le contenu est réputé complet (vérification du numéro au lieu du recomptage).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->boolean('is_sealable')->default(false)->after('is_active');
            $table->string('seal_number', 60)->nullable()->after('is_sealable');
            $table->timestamp('sealed_at')->nullable()->after('seal_number');
            $table->foreignId('sealed_by')->nullable()->after('sealed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sealed_by');
            $table->dropColumn(['is_sealable', 'seal_number', 'sealed_at']);
        });
    }
};
