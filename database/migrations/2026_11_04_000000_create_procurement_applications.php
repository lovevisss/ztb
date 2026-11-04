<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->constrained();
        });

        Schema::create('procurement_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('users');
            $table->foreignId('department_id')->constrained();
            $table->string('project_number')->nullable()->unique();
            $table->string('title')->nullable();
            $table->unsignedBigInteger('budget_cents')->nullable();
            $table->string('funding_source')->nullable();
            $table->string('organization_form')->nullable();
            $table->string('purchase_method')->nullable();
            $table->text('reason')->nullable();
            $table->string('responsible_department')->nullable();
            $table->boolean('is_fixed_asset')->nullable();
            $table->string('purchase_type')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_applications');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('department_id'));
        Schema::dropIfExists('departments');
    }
};
