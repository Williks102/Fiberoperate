<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue de prix de l'opérateur (bordereau) : commun à tous les projets. La référence n'est ni obligatoire ni unique
 * dans le fichier d'origine (« RC01 » revient sous deux rubriques) : une ligne est identifiée par rubrique + référence + désignation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 10); // supply (fourniture) | service (prestation)
            $table->string('section');
            $table->string('ref', 40)->nullable();
            $table->text('designation');
            $table->string('unit', 20)->nullable();
            $table->unsignedBigInteger('unit_price')->nullable(); // FCFA entiers ; vide = prix non renseigné
            $table->string('item_key');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['kind', 'section']);
        });

        DB::statement('CREATE UNIQUE INDEX price_items_key_unique ON price_items (item_key) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('price_items');
    }
};
