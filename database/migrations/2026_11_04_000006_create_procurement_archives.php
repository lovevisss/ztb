<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_archive_rules', function (Blueprint $table) {
            $table->id();
            $table->string('application_form');
            $table->string('kind');
            $table->boolean('required')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['application_form', 'kind']);
        });

        foreach (['self_purchase', 'central_tender'] as $form) {
            foreach (['contract', 'result_announcement', 'acceptance', 'invoice', 'other_execution'] as $kind) {
                DB::table('procurement_archive_rules')->insert([
                    'application_form' => $form,
                    'kind' => $kind,
                    'required' => $kind !== 'other_execution',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::create('procurement_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained('procurement_applications')->cascadeOnDelete();
            $table->foreignId('archived_by')->constrained('users');
            $table->json('required_kinds_snapshot');
            $table->json('attachment_ids_snapshot');
            $table->timestamp('archived_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_archives');
        Schema::dropIfExists('procurement_archive_rules');
    }
};
