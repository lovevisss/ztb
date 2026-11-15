<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_role_defaults', function (Blueprint $table): void {
            $table->foreignId('updated_by')->nullable()->change();
        });
        Schema::table('procurement_department_approvers', function (Blueprint $table): void {
            $table->foreignId('tender_group_representative_id')->nullable()->change();
        });

        $this->backfill();
    }

    public function backfill(): void
    {
        if (DB::table('approval_role_defaults')->where('role_code', 'tender_group_representative')->exists()) {
            return;
        }

        $ids = DB::table('procurement_department_approvers')
            ->whereNotNull('tender_group_representative_id')
            ->distinct()->pluck('tender_group_representative_id');
        if ($ids->count() !== 1 || ! DB::table('users')->where('id', $ids->first())->where('is_active', true)->exists()) {
            return;
        }

        DB::table('approval_role_defaults')->insertOrIgnore([
            'role_code' => 'tender_group_representative',
            'user_id' => $ids->first(),
            'updated_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::table('procurement_department_approvers')->whereNull('tender_group_representative_id')->exists()) {
            throw new RuntimeException('Cannot restore required department representatives while supervisor-only mappings exist.');
        }

        DB::table('approval_role_defaults')->where('role_code', 'tender_group_representative')
            ->whereNull('updated_by')->delete();
        Schema::table('procurement_department_approvers', function (Blueprint $table): void {
            $table->foreignId('tender_group_representative_id')->nullable(false)->change();
        });
        Schema::table('approval_role_defaults', function (Blueprint $table): void {
            $table->foreignId('updated_by')->nullable(false)->change();
        });
    }
};
