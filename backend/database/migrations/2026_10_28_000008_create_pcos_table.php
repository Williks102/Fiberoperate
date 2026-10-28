<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PCO = PBO = « boîtier ». Colonnes reprises du fichier de suivi de déploiement :
 * latitude/longitude, slot, port et brin de fibre (souvent vides), statut, commentaire.
 *
 * - status       : statut normalisé (nul tant que le libellé d'origine n'est pas associé).
 * - status_raw   : libellé d'origine, toujours conservé.
 * - is_blocked   : indicateur de blocage, avec motif (block_reason_id), indépendant du statut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pcos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zone_id')->constrained('zones')->restrictOnDelete();
            $table->foreignUuid('jdv_id')->constrained('jdvs')->restrictOnDelete();
            $table->string('code');
            $table->string('code_normalized');
            $table->unsignedInteger('number')->nullable();
            $table->string('status')->nullable()->index();
            $table->string('status_raw')->nullable();
            $table->boolean('is_blocked')->default(false)->index();
            $table->foreignUuid('block_reason_id')->nullable()->constrained('block_reasons')->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('olt_slot')->nullable();
            $table->string('olt_port')->nullable();
            $table->string('fiber_strand')->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->text('comment')->nullable();
            $table->text('observation')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX pcos_zone_code_unique ON pcos (zone_id, code_normalized) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('pcos');
    }
};
