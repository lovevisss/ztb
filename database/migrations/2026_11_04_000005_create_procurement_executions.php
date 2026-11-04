<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_applications', function (Blueprint $table) {
            $table->foreignId('executor_id')->nullable()->constrained('users');
        });

        Schema::create('procurement_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained('procurement_applications')->cascadeOnDelete();
            $table->unsignedBigInteger('actual_amount_cents')->nullable();
            $table->string('supplier')->nullable();
            $table->date('completed_on')->nullable();
            $table->text('result_note')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_execution_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('procurement_applications')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('event');
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_execution_events');
        Schema::dropIfExists('procurement_executions');
        Schema::table('procurement_applications', fn (Blueprint $table) => $table->dropConstrainedForeignId('executor_id'));
    }
};
