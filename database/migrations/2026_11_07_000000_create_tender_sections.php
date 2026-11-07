<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('procurement_number_sequences', function (Blueprint $table) {
            $table->date('day')->primary();
            $table->unsignedInteger('last_number');
        });
        Schema::table('procurement_applications', function (Blueprint $table) {
            $table->string('category')->nullable();
            $table->string('contact_phone')->nullable();
            $table->boolean('is_special_purchase')->default(false);
            $table->boolean('is_non_expenditure')->default(false);
            $table->boolean('has_usage_department')->default(false);
            $table->foreignId('usage_department_id')->nullable()->constrained('departments');
            $table->text('remarks')->nullable();
        });
        Schema::create('personal_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('specification')->nullable();
            $table->string('unit')->nullable();
            $table->unsignedBigInteger('unit_price_cents')->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'name']);
        });
        Schema::create('procurement_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('procurement_applications')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('name');
            $table->text('specification')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->string('unit')->nullable();
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('total_cents');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
        Schema::create('budget_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('source_id')->unique();
            $table->string('code')->nullable()->index();
            $table->string('name');
            $table->string('director')->nullable();
            $table->string('department_code')->nullable()->index();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('available_cents')->nullable();
            $table->timestamps();
        });
        Schema::create('procurement_budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('procurement_applications')->cascadeOnDelete();
            $table->foreignId('budget_indicator_id')->constrained()->restrictOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('director')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->timestamps();
            $table->unique(['application_id', 'budget_indicator_id'], 'allocation_indicator_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_budget_allocations');
        Schema::dropIfExists('budget_indicators');
        Schema::dropIfExists('procurement_line_items');
        Schema::dropIfExists('personal_catalog_items');
        Schema::table('procurement_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('usage_department_id');
            $table->dropColumn(['category', 'contact_phone', 'is_special_purchase', 'is_non_expenditure', 'has_usage_department', 'remarks']);
        });
        Schema::dropIfExists('procurement_number_sequences');
    }
};
