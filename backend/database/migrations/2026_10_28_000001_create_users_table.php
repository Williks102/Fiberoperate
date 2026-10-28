<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remplace la migration « users » fournie par Laravel (à supprimer).
 * Identifiants UUID ; rôle en chaîne (cast vers App\Enums\Role) ;
 * manager_id = supérieur hiérarchique (technicien → superviseur ou coordinateur → DT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('password');
            $table->string('role')->index();
            $table->foreignUuid('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Unicité limitée aux lignes non supprimées (suppression logique).
        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
