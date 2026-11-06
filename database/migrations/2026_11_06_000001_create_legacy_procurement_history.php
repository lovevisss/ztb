<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_procurement_records', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 64);
            $table->string('source_id', 64);
            $table->string('application_source_id', 64)->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->string('creator_account')->nullable();
            $table->string('creator_name')->nullable();
            $table->string('department_code')->nullable();
            $table->string('number')->nullable()->index();
            $table->string('title')->nullable()->index();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->json('details');
            $table->timestamps();
            $table->unique(['source_table', 'source_id']);
        });

        Schema::create('legacy_procurement_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 64);
            $table->string('source_id', 64);
            $table->string('owner_table', 64);
            $table->string('owner_source_id', 64);
            $table->string('application_source_id', 64)->nullable()->index();
            $table->string('name')->nullable();
            $table->text('legacy_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('migration_status')->default('pending_file');
            $table->timestamps();
            $table->unique(['source_table', 'source_id', 'owner_table', 'owner_source_id'], 'legacy_attachment_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_procurement_attachments');
        Schema::dropIfExists('legacy_procurement_records');
    }
};
