<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Projet de déploiement : une plaque, une phase courante, un historique des changements de phase. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plaque_id')->constrained('plaques')->restrictOnDelete();
            $table->string('phase')->default('pre_project')->index();
            $table->timestamp('phase_changed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Un seul projet par plaque (parmi les non supprimés).
        DB::statement('CREATE UNIQUE INDEX projects_plaque_unique ON projects (plaque_id) WHERE deleted_at IS NULL');

        // Historique : jamais modifié ni supprimé.
        Schema::create('project_phase_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('from_phase')->nullable();
            $table->string('to_phase');
            $table->string('decision')->nullable(); // approved | rejected
            $table->text('reason')->nullable();
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_phase_history');
        Schema::dropIfExists('projects');
    }
};
