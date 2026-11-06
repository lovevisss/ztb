<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cas_auth_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->char('account_hash', 64);
            $table->string('outcome');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cas_auth_events');
    }
};
