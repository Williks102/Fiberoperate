<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Interventions planifiées sur une commande client (étude, puis installation) : qui, quoi, quand. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_uuid')->nullable()->unique(); // résultats saisis hors ligne (lot 4) : idempotence
            $table->string('type', 30); // study, customer_connection
            $table->foreignUuid('customer_order_id')->constrained('customer_orders');
            $table->foreignUuid('technician_id')->constrained('users');
            $table->date('planned_date');
            $table->string('status', 20)->default('planned'); // planned, done, cancelled
            $table->text('observation')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['technician_id', 'planned_date']);
        });

        // Une seule intervention d'un type en cours par commande : pas de double planification.
        DB::statement("create unique index interventions_one_planned_per_order_type on interventions (customer_order_id, type) where status = 'planned' and deleted_at is null");
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
