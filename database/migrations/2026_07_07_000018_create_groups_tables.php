<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Groupes (entreprises) regroupant plusieurs organisations. Un administrateur
 * plateforme rattaché à un groupe est un « gestionnaire de groupe » : il ne
 * voit que les organisations de son groupe. Sans groupe = exploitant global.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('id')->constrained('groups')->nullOnDelete();
        });

        Schema::table('platform_admins', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('id')->constrained('groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('platform_admins', fn (Blueprint $t) => $t->dropConstrainedForeignId('group_id'));
        Schema::table('organisations', fn (Blueprint $t) => $t->dropConstrainedForeignId('group_id'));
        Schema::dropIfExists('groups');
    }
};
