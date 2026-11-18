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
            $table->foreignId('responsible_department_id')->nullable()->constrained('departments');
            $table->string('reason_category')->nullable();
            $table->boolean('has_furniture')->default(false);
            $table->boolean('has_equipment_asset')->default(false);
        });
        DB::table('roles')->updateOrInsert(['code' => 'asset_manager'], ['name' => '资产管理员']);
    }

    public function down(): void
    {
        Schema::table('procurement_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_department_id');
            $table->dropColumn(['reason_category', 'has_furniture', 'has_equipment_asset']);
        });
        DB::table('roles')->where('code', 'asset_manager')->delete();
    }
};
