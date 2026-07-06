<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table centrale (landlord) : les organisations clientes du SaaS.
 * NON soumise au cloisonnement multi-tenant (c'est elle qui le définit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Sous-domaine de l'organisation : caserne.vulcain.app
            $table->string('slug')->unique();
            $table->string('status')->default('active'); // active | suspended
            // Personnalisation par organisation (logo, thème, options métier…)
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
