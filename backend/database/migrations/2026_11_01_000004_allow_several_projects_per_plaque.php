<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une plaque peut avoir plusieurs projets dans le temps (le déploiement, puis des désaturations). Un seul est en cours
 * à la fois : l'ancienne règle « un projet par plaque » devient « un seul projet qui n'est pas encore en exploitation ».
 * Les projets existants sont des déploiements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('kind', 20)->default('deployment');
        });

        DB::statement('DROP INDEX projects_plaque_unique');
        DB::statement("CREATE UNIQUE INDEX projects_plaque_active_unique ON projects (plaque_id) WHERE deleted_at IS NULL AND phase <> 'in_operation'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX projects_plaque_active_unique');
        DB::statement('CREATE UNIQUE INDEX projects_plaque_unique ON projects (plaque_id) WHERE deleted_at IS NULL');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
