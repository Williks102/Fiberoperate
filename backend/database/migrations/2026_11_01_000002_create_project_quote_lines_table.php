<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Devis de l'avant-projet : des lignes du catalogue de prix avec une quantité d'étude, puis la quantité réalisée.
 * La ligne copie le libellé et le prix du catalogue au moment de la saisie : une mise à jour du catalogue ne change pas un devis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_quote_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignUuid('price_item_id')->constrained('price_items')->restrictOnDelete();
            $table->string('kind', 10);
            $table->string('section');
            $table->string('ref', 40)->nullable();
            $table->text('designation');
            $table->string('unit', 20)->nullable();
            $table->unsignedBigInteger('unit_price')->nullable();
            $table->unsignedInteger('study_quantity')->default(0);
            $table->unsignedInteger('done_quantity')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX project_quote_lines_unique ON project_quote_lines (project_id, price_item_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('project_quote_lines');
    }
};
