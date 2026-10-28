<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plaque = grande zone géographique (FAYA, ARCADE…). Un superviseur couvre
 * une plaque entière. L'OLT peut être renseigné plus tard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plaques', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->foreignUuid('olt_id')->nullable()->constrained('olts')->nullOnDelete();
            $table->foreignUuid('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX plaques_name_unique ON plaques (name) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('plaques');
    }
};
