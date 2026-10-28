<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** JDV = splitter (SPL001, SPL002…). Aucun nombre de PCO imposé par JDV. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jdvs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zone_id')->constrained('zones')->restrictOnDelete();
            $table->string('code');
            $table->string('code_normalized');
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX jdvs_zone_code_unique ON jdvs (zone_id, code_normalized) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('jdvs');
    }
};
