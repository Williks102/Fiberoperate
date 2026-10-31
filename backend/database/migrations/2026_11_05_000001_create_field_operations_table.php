<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des opérations envoyées par le terrain (résultats d'étude et d'installation saisis, parfois hors ligne). Chaque opération
 * porte un `client_uuid` unique : un renvoi, même simultané, ne l'applique jamais deux fois. Les conflits et les rejets sont gardés
 * avec leur contenu : rien n'est écrasé ni perdu en silence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_uuid')->unique();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('type', 30); // study.result, installation.result
            $table->uuid('customer_order_id')->nullable()->index();
            $table->jsonb('payload');
            $table->string('device', 60)->nullable();
            $table->timestamp('occurred_at'); // quand la saisie a eu lieu sur le terrain
            $table->string('status', 12); // applied, conflict, rejected
            $table->string('error_code', 40)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_operations');
    }
};
