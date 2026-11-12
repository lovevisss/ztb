<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('budget_indicators', 'charge_sno')) {
            Schema::table('budget_indicators', fn (Blueprint $table) => $table->string('charge_sno')->nullable());
        }
        if (! Schema::hasColumn('procurement_budget_allocations', 'charge_sno')) {
            Schema::table('procurement_budget_allocations', fn (Blueprint $table) => $table->string('charge_sno')->nullable());
        }
        if (! Schema::hasTable('procurement_department_approvers')) {
            Schema::create('procurement_department_approvers', function (Blueprint $table) {
                $table->foreignId('department_id')->primary()->constrained('departments')->cascadeOnDelete();
                $table->foreignId('supervising_leader_id')->constrained('users', 'id', 'pda_supervising_fk');
                $table->foreignId('tender_group_representative_id')->constrained('users', 'id', 'pda_group_fk');
                $table->timestamps();
            });
        }
        foreach (['procurement_head' => '采购管理部门领导', 'asset_head' => '资产管理部领导'] as $code => $name) {
            DB::table('roles')->updateOrInsert(['code' => $code], ['name' => $name]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('code', ['procurement_head', 'asset_head'])->delete();
        Schema::dropIfExists('procurement_department_approvers');
        Schema::table('procurement_budget_allocations', fn (Blueprint $table) => $table->dropColumn('charge_sno'));
        Schema::table('budget_indicators', fn (Blueprint $table) => $table->dropColumn('charge_sno'));
    }
};
