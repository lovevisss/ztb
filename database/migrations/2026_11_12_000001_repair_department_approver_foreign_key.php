<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $exists = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'procurement_department_approvers')
            ->where('COLUMN_NAME', 'tender_group_representative_id')
            ->where('REFERENCED_TABLE_NAME', 'users')->exists();
        if (! $exists) {
            Schema::table('procurement_department_approvers', function (Blueprint $table) {
                $table->foreign('tender_group_representative_id', 'pda_group_fk')->references('id')->on('users');
            });
        }
    }

    public function down(): void {}
};
