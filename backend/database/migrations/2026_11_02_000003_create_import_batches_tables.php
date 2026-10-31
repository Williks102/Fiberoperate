<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Imports en deux temps (analyse, revue, validation) : un lot et ses lignes. L'analyse ne crée aucune commande ;
 * chaque ligne reçoit un statut ok / à corriger / refusée avec ses anomalies, et la revue peut en exclure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 20); // customers
            $table->string('file_name');
            $table->string('status', 20)->default('analysed'); // analysed | validated
            $table->jsonb('counts');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('line');
            $table->string('status', 10); // ok | to_fix | rejected
            $table->string('action', 10); // create | update | none
            $table->boolean('excluded')->default(false);
            $table->jsonb('data'); // valeurs normalisées, prêtes à être enregistrées
            $table->jsonb('anomalies'); // [{level: info|warning|error, message}]
            $table->timestamps();

            $table->index(['import_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
