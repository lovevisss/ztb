<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_applications', function (Blueprint $table) {
            $table->string('application_form')->default('self_purchase');
            $table->string('project_leader')->nullable();
            $table->text('procurement_requirements')->nullable();
            $table->text('purchase_list')->nullable();
            $table->text('technical_parameters')->nullable();
            $table->text('service_requirements')->nullable();
            $table->text('acceptance_criteria')->nullable();
            $table->text('budget_basis_note')->nullable();
        });

        DB::table('procurement_applications')
            ->where('purchase_type', 'central_tender')
            ->update(['application_form' => 'central_tender']);

        Schema::create('procurement_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('procurement_applications')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('kind');
            $table->string('original_name');
            $table->string('disk');
            $table->string('path')->unique();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_attachments');

        Schema::table('procurement_applications', function (Blueprint $table) {
            $table->dropColumn([
                'application_form', 'project_leader', 'procurement_requirements',
                'purchase_list', 'technical_parameters', 'service_requirements',
                'acceptance_criteria', 'budget_basis_note',
            ]);
        });
    }
};
