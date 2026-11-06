<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('external_code')->nullable()->unique();
            $table->foreignId('parent_id')->nullable()->constrained('departments');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('school_positions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('school_position_id')->nullable()->constrained('school_positions');
            $table->timestamp('school_synced_at')->nullable();
        });
        Schema::table('role_user', function (Blueprint $table) {
            $table->string('source')->default('manual');
        });
        Schema::create('school_position_roles', function (Blueprint $table) {
            $table->foreignId('school_position_id')->constrained('school_positions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['school_position_id', 'role_id']);
        });
        Schema::create('organization_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users');
            $table->boolean('complete_snapshot');
            $table->unsignedInteger('departments_count');
            $table->unsignedInteger('positions_count');
            $table->unsignedInteger('people_count');
            $table->unsignedInteger('deactivated_count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_sync_runs');
        Schema::dropIfExists('school_position_roles');
        Schema::table('role_user', fn (Blueprint $table) => $table->dropColumn('source'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_position_id');
            $table->dropColumn('school_synced_at');
        });
        Schema::dropIfExists('school_positions');
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropUnique(['external_code']);
            $table->dropColumn(['external_code', 'is_active']);
        });
    }
};
