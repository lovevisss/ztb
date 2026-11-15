<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_head_assignments', function (Blueprint $table) {
            $table->foreignId('department_id')->primary()->constrained('departments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
        });
        Schema::create('department_head_assignment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->foreignId('old_user_id')->nullable()->constrained('users');
            $table->foreignId('new_user_id')->nullable()->constrained('users');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_head_assignment_events');
        Schema::dropIfExists('department_head_assignments');
    }
};
