<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_applications', function (Blueprint $table) {
            $table->foreignId('source_application_id')->nullable()
                ->constrained('procurement_applications');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_applications', fn (Blueprint $table) => $table->dropConstrainedForeignId('source_application_id'));
    }
};
