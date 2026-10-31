<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** La référence de l'abonnement s'appelle « ID client » (anciennement « RAB ») : colonne `customer_ref`, même clé unique. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->renameColumn('rab', 'customer_ref');
        });

        DB::statement('ALTER INDEX customer_orders_rab_unique RENAME TO customer_orders_customer_ref_unique');
    }

    public function down(): void
    {
        DB::statement('ALTER INDEX customer_orders_customer_ref_unique RENAME TO customer_orders_rab_unique');

        Schema::table('customer_orders', function (Blueprint $table) {
            $table->renameColumn('customer_ref', 'rab');
        });
    }
};
