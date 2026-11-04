<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('purchase_type');
            $table->unsignedBigInteger('min_budget_cents')->default(0);
            $table->unsignedBigInteger('max_budget_cents')->nullable();
            $table->foreignId('applicant_department_id')->nullable()->constrained('departments');
            $table->string('responsible_department')->nullable();
            $table->unsignedInteger('version');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('approval_template_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('approval_templates')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('role_code');
            $table->foreignId('assignee_id')->nullable()->constrained('users');
            $table->unique(['template_id', 'position']);
        });

        Schema::table('procurement_applications', function (Blueprint $table) {
            $table->foreignId('approval_template_id')->nullable()->constrained('approval_templates');
            $table->unsignedInteger('approval_attempt')->default(0);
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('procurement_applications')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('approval_templates');
            $table->unsignedInteger('attempt');
            $table->unsignedInteger('position');
            $table->string('role_code');
            $table->foreignId('assignee_id')->constrained('users');
            $table->string('status');
            $table->text('opinion')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->unique(['application_id', 'attempt', 'position']);
            $table->index(['assignee_id', 'status']);
        });

        Schema::create('approval_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('procurement_applications')->cascadeOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('approval_steps');
            $table->foreignId('actor_id')->constrained('users');
            $table->string('event');
            $table->text('details')->nullable();
            $table->timestamps();
        });

        foreach (['self_purchase' => '部门自行采购默认流程', 'central_tender' => '统一招标默认流程'] as $type => $name) {
            $templateId = DB::table('approval_templates')->insertGetId([
                'name' => $name,
                'purchase_type' => $type,
                'min_budget_cents' => 0,
                'max_budget_cents' => null,
                'applicant_department_id' => null,
                'responsible_department' => null,
                'version' => 1,
                'is_active' => true,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('approval_template_steps')->insert([
                ['template_id' => $templateId, 'position' => 1, 'role_code' => 'department_head', 'assignee_id' => null],
                ['template_id' => $templateId, 'position' => 2, 'role_code' => 'procurement_manager', 'assignee_id' => null],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_events');
        Schema::dropIfExists('approval_steps');
        Schema::table('procurement_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approval_template_id');
            $table->dropColumn('approval_attempt');
        });
        Schema::dropIfExists('approval_template_steps');
        Schema::dropIfExists('approval_templates');
    }
};
