<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Zone = sous-plaque (FAY03 pour FAYA 03, ACA003 pour ARCADE 003).
 * C'est la « zone SRO » : « hub » chez Huawei, « SRO » chez Nokia (colonne vendor).
 * code_normalized sert à comparer des variantes d'écriture (FAY03 / FAY003).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plaque_id')->constrained('plaques')->restrictOnDelete();
            $table->string('code');
            $table->string('code_normalized');
            $table->string('name')->nullable();
            $table->string('vendor')->nullable();
            $table->string('status')->default('planned')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX zones_plaque_code_unique ON zones (plaque_id, code_normalized) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
