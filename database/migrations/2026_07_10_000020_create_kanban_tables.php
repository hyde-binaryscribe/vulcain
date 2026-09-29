<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tableaux Kanban personnalisables : plusieurs tableaux, colonnes configurables.
 * Les événements référencent une colonne (kanban_column_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['organisation_id', 'display_order'], 'kanban_boards_org_order_idx');
        });

        Schema::create('kanban_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kanban_board_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_done')->default(false); // colonne « terminé » (clôt l'événement)
            $table->timestamps();

            $table->index(['kanban_board_id', 'display_order'], 'kanban_columns_board_order_idx');
        });

        if (! Schema::hasColumn('events', 'kanban_column_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->foreignId('kanban_column_id')->nullable()->after('status')
                    ->constrained('kanban_columns')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'kanban_column_id')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropConstrainedForeignId('kanban_column_id');
            });
        }

        Schema::dropIfExists('kanban_columns');
        Schema::dropIfExists('kanban_boards');
    }
};
