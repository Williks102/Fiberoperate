<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Référentiel des motifs de blocage d'un PCO (syndic, accès, autorisation…). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('block_reasons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('block_reasons');
    }
};
