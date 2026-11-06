<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_sync_runs', function (Blueprint $table) {
            $table->foreignId('actor_id')->nullable()->change();
            $table->string('source')->default('manual');
            $table->string('status')->default('success');
            $table->text('error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organization_sync_runs', function (Blueprint $table) {
            $table->dropColumn(['source', 'status', 'error']);
            $table->foreignId('actor_id')->nullable(false)->change();
        });
    }
};
