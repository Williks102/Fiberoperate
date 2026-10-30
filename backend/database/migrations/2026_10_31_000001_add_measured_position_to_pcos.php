<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Position mesurée d'un PCO (relevé de terrain), à côté de la position prévue (latitude/longitude).
 * Colonnes ajoutées vides : rien ne change pour les PCO existants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pcos', function (Blueprint $table) {
            $table->decimal('measured_latitude', 10, 7)->nullable();
            $table->decimal('measured_longitude', 10, 7)->nullable();
            $table->timestamp('measured_at')->nullable();
            $table->foreignUuid('measured_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pcos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('measured_by');
            $table->dropColumn(['measured_latitude', 'measured_longitude', 'measured_at']);
        });
    }
};
