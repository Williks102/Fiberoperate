<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versions des documents d'un projet (plans, KMZ, AVP, justificatif du client). Jamais modifiées ni supprimées :
 * une nouvelle version s'ajoute. Fichiers de 2 Mo au plus, gardés en base (base64) : le disque de Render est
 * éphémère et aucun stockage d'objets n'est branché ; à migrer vers un stockage d'objets si les volumes grossissent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->restrictOnDelete();
            $table->string('type', 30);
            $table->foreignUuid('zone_id')->nullable()->constrained('zones')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('file_name');
            $table->unsignedInteger('size');
            $table->string('checksum', 64);
            $table->text('content');
            $table->text('comment')->nullable();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'type']);
        });

        DB::statement("CREATE UNIQUE INDEX project_document_versions_unique ON project_document_versions (project_id, type, COALESCE(zone_id, '00000000-0000-0000-0000-000000000000'::uuid), version)");
    }

    public function down(): void
    {
        Schema::dropIfExists('project_document_versions');
    }
};
