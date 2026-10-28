<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Historique des changements de statut d'un PCO : qui, quand, depuis quel appareil. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pco_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pco_id')->constrained('pcos')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('observation')->nullable();
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['pco_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pco_status_history');
    }
};
