<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commandes clients (une par abonnement, clé : référence RAB) et leur historique de statut. Structure issue du cahier §8 et
 * de l'analyse du fichier « Suivi raccordement » : les valeurs d'origine (adresse, téléphones, étude initiale, état de ligne
 * de l'opérateur) sont toujours conservées à côté des valeurs normalisées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_uuid')->nullable()->unique(); // créations hors ligne : idempotence

            // Identification de l'abonnement (aucune longueur supposée : les données réelles varient)
            $table->string('rab', 40);
            $table->string('account_number', 40)->nullable();
            $table->string('ft_line', 40)->nullable();
            $table->string('name');

            // Contacts : valeur normalisée (+225 + 10 chiffres) et texte d'origine
            $table->string('phone_1', 20)->nullable();
            $table->string('phone_1_raw', 40)->nullable();
            $table->string('phone_2', 20)->nullable();
            $table->string('phone_2_raw', 40)->nullable();

            // Adresse : champs découpés, et adresse brute toujours conservée
            $table->text('address_raw')->nullable();
            $table->string('city')->nullable();
            $table->string('commune')->nullable();
            $table->string('district')->nullable();
            $table->string('street')->nullable();
            $table->string('address_detail')->nullable();

            // GPS déclaré (fichier du client) et GPS relevé sur le terrain
            $table->decimal('declared_latitude', 10, 7)->nullable();
            $table->decimal('declared_longitude', 10, 7)->nullable();
            $table->decimal('field_latitude', 10, 7)->nullable();
            $table->decimal('field_longitude', 10, 7)->nullable();

            // Rattachement au réseau
            $table->foreignUuid('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignUuid('pco_id')->nullable()->constrained('pcos')->nullOnDelete();
            $table->unsignedInteger('distance_m')->nullable();

            // Box (ONT) attribuée après l'étude : numéro de série et numéro fixe
            $table->string('ont_serial', 40)->nullable();
            $table->string('fixed_line', 20)->nullable();

            // Étude technique initiale : libellé de l'opérateur et état normalisé
            $table->string('initial_study_raw', 60)->nullable();
            $table->string('initial_study', 10)->nullable(); // done | to_do
            $table->date('registered_at')->nullable();
            $table->date('sent_to_contractor_at')->nullable();

            // Statut interne et état de ligne côté opérateur (champ distinct, avec sa date)
            $table->string('status', 20)->default('received');
            $table->string('study_result', 20)->nullable();
            $table->string('operator_line_status')->nullable();
            $table->date('operator_line_status_at')->nullable();
            $table->timestamp('operator_synced_at')->nullable();
            $table->text('observation')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('zone_id');
        });

        // Réimport d'un même RAB = mise à jour, jamais de doublon.
        DB::statement('CREATE UNIQUE INDEX customer_orders_rab_unique ON customer_orders (rab) WHERE deleted_at IS NULL');

        // Historique : jamais modifié ni supprimé.
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_order_id')->constrained('customer_orders')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('observation')->nullable();
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['customer_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('customer_orders');
    }
};
