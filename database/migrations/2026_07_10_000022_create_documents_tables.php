<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents rattachés aux véhicules (agrément, CT, carte grise…) et au personnel
 * (diplômes, autorisations ARS, permis…), gérés par les administrateurs et
 * consultables pendant un service, avec journal de consultation (motif).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->morphs('documentable'); // vehicle ou user
            $table->string('category', 50);
            $table->string('title', 200);
            $table->string('file_path');
            $table->string('mime', 120)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->date('expires_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('document_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 200);
            $table->timestamp('consulted_at');
            $table->timestamps();

            $table->index(['organisation_id', 'document_id'], 'doc_access_org_doc_idx');
        });

        if (! Schema::hasColumn('users', 'documents_consent')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('documents_consent')->default(false)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'documents_consent')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('documents_consent');
            });
        }
        Schema::dropIfExists('document_accesses');
        Schema::dropIfExists('documents');
    }
};
