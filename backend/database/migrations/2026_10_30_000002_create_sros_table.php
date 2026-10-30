<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SRO (sous-répartiteur optique) : une par sous-plaque. Elle part de l'OLT (slot, port et brin de départ saisis ici)
 * et alimente les splitters de sa sous-plaque. Son code (ex. ABB456) porte le numéro donné par l'opérateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sros', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zone_id')->constrained('zones')->restrictOnDelete();
            $table->string('code');
            $table->string('code_normalized');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('olt_slot')->nullable();
            $table->string('olt_port')->nullable();
            $table->string('fiber_strand')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX sros_zone_unique ON sros (zone_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX sros_code_unique ON sros (code_normalized) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('sros');
    }
};
