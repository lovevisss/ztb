<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_identity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('old_school_account')->nullable();
            $table->string('new_school_account')->nullable();
            $table->boolean('old_active');
            $table->boolean('new_active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_identity_events');
    }
};
