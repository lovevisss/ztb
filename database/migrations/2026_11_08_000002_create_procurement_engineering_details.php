<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_engineering_details', function (Blueprint $table) {
            $table->foreignId('application_id')->primary()->constrained('procurement_applications')->cascadeOnDelete();
            $table->string('project_name')->nullable();
            $table->text('construction_content')->nullable();
            $table->text('project_overview')->nullable();
            $table->text('schedule_requirements')->nullable();
            $table->unsignedBigInteger('estimated_cost_cents')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_engineering_details');
    }
};
