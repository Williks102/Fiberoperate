<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Résultat d'une étude de terrain : poteaux par type (existants / à installer), distance, PCO de rattachement, faisabilité. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_uuid')->nullable()->unique(); // saisie hors ligne (lot 4) : idempotence
            $table->foreignUuid('intervention_id')->unique()->constrained('interventions');
            $table->foreignUuid('customer_order_id')->constrained('customer_orders');
            $table->string('result', 20); // feasible, not_feasible, customer_absent
            $table->foreignUuid('pco_id')->nullable()->constrained('pcos')->nullOnDelete();
            $table->unsignedSmallInteger('concrete_poles_existing')->default(0);
            $table->unsignedSmallInteger('concrete_poles_to_install')->default(0);
            $table->unsignedSmallInteger('metal_poles_existing')->default(0);
            $table->unsignedSmallInteger('metal_poles_to_install')->default(0);
            $table->unsignedInteger('distance_m')->nullable();
            $table->text('problem')->nullable();
            $table->text('field_comments')->nullable();
            $table->text('internal_comments')->nullable();
            $table->decimal('field_latitude', 10, 7)->nullable();
            $table->decimal('field_longitude', 10, 7)->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studies');
    }
};
