<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photos prises sur le terrain (preuve d'une étude ou d'une installation). La photo elle-même est chez Cloudinary (mode
 * « authenticated ») ; en base, seulement son identifiant et ses caractéristiques, jamais d'URL. `client_uuid` rend l'enregistrement
 * idempotent : un renvoi (réseau coupé juste après l'envoi) ne crée pas de doublon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_uuid')->unique();
            $table->foreignUuid('customer_order_id')->constrained('customer_orders');
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('public_id')->unique();
            $table->string('format', 10)->nullable();
            $table->unsignedInteger('bytes')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('hash', 64)->nullable(); // SHA-256 de la photo compressée
            $table->timestamp('taken_at'); // quand elle a été prise sur le terrain
            $table->timestamps();

            $table->index(['customer_order_id', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};
