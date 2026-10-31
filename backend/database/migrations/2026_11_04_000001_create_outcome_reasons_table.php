<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Motifs de report ou d'échec d'une installation (référentiel). Liste de départ du cahier §3.4, à compléter avec le client. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outcome_reasons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        foreach ([
            'customer_absent' => 'Client absent',
            'access_impossible' => 'Accès impossible',
            'missing_material' => 'Câble ou poteau manquant',
            'pco_saturated' => 'PCO saturé',
        ] as $code => $label) {
            DB::table('outcome_reasons')->insert(['id' => (string) Str::orderedUuid(), 'code' => $code, 'label' => $label, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outcome_reasons');
    }
};
