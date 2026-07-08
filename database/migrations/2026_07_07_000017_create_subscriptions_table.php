<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abonnement d'une organisation (1:1). Table centrale (gérée depuis le Desk).
 * La facturation (Stripe) est différée : on gère plan, statut et échéances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan')->default('decouverte');   // decouverte | standard | pro
            $table->string('status')->default('trial');       // trial | active | past_due | suspended | cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_end')->nullable(); // prochaine échéance / renouvellement
            $table->text('notes')->nullable();                 // notes commerciales (Desk)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
