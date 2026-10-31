<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Issue d'une visite : installé, reporté ou en échec, avec le motif du référentiel (ajout de colonnes, rien n'est modifié). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->string('outcome', 20)->nullable();
            $table->foreignUuid('reason_id')->nullable()->constrained('outcome_reasons');
        });
    }

    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reason_id');
            $table->dropColumn('outcome');
        });
    }
};
