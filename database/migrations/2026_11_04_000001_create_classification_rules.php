<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        DB::table('roles')->insert([
            ['code' => 'staff', 'name' => '普通教职工'],
            ['code' => 'department_head', 'name' => '部门负责人'],
            ['code' => 'procurement_manager', 'name' => '采购管理人员'],
            ['code' => 'responsible_department', 'name' => '归口部门人员'],
            ['code' => 'finance', 'name' => '财务人员'],
            ['code' => 'school_leader', 'name' => '校领导'],
            ['code' => 'system_admin', 'name' => '系统管理员'],
        ]);

        Schema::create('procurement_classification_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('threshold_cents');
            $table->string('scope');
            $table->foreignId('department_id')->nullable()->constrained();
            $table->timestamp('effective_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        DB::table('procurement_classification_rules')->insert([
            'threshold_cents' => 10_000_000,
            'scope' => 'global',
            'department_id' => null,
            'effective_at' => '2000-01-01 00:00:00',
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('procurement_applications', function (Blueprint $table) {
            $table->foreignId('classification_rule_id')->nullable()->constrained('procurement_classification_rules');
            $table->unsignedBigInteger('classification_threshold_cents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classification_rule_id');
            $table->dropColumn('classification_threshold_cents');
        });
        Schema::dropIfExists('procurement_classification_rules');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
