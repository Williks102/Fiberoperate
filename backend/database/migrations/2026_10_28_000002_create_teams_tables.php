<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->foreignUuid('leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['team_id', 'user_id']);
        });

        // Habilitations de mission d'un technicien.
        Schema::create('technician_skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('mission_type');
            $table->timestamps();
            $table->unique(['user_id', 'mission_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_skills');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
    }
};
