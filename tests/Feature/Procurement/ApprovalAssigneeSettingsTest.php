<?php

namespace Tests\Feature\Procurement;

use App\Models\ProcurementApplication;
use App\Models\User;
use App\Services\ApprovalWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovalAssigneeSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function role(User $user, string $code): void
    {
        DB::table('role_user')->insert([
            'user_id' => $user->id,
            'role_id' => DB::table('roles')->where('code', $code)->value('id'),
            'source' => 'manual',
        ]);
    }

    public function test_only_system_admin_can_view_and_change_default_approvers(): void
    {
        $department = DB::table('departments')->insertGetId(['name' => '测试部门', 'created_at' => now(), 'updated_at' => now()]);
        $admin = User::factory()->create(['department_id' => $department]);
        $manager = User::factory()->create(['department_id' => $department]);
        $candidate = User::factory()->create(['department_id' => $department]);
        $inactive = User::factory()->create(['department_id' => $department, 'is_active' => false]);
        $this->role($admin, 'system_admin');
        $this->role($manager, 'procurement_manager');

        $this->actingAs($manager)->get(route('approval-assignees.index'))->assertForbidden();
        $this->actingAs($manager)->put(route('approval-assignees.update', 'procurement_head'), ['user_id' => $candidate->id])->assertForbidden();
        $this->actingAs($admin)->get(route('approval-assignees.index'))
            ->assertInertia(fn ($page) => $page->component('admin/ApprovalAssignees')->has('roles', 3));
        $this->actingAs($admin)->put(route('approval-assignees.update', 'procurement_head'), ['user_id' => $inactive->id])
            ->assertSessionHasErrors('user_id');
        $this->actingAs($admin)->put(route('approval-assignees.update', 'unknown'), ['user_id' => $candidate->id])->assertNotFound();
        $this->actingAs($admin)->put(route('approval-assignees.update', 'procurement_head'), ['user_id' => $candidate->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('approval_role_defaults', ['role_code' => 'procurement_head', 'user_id' => $candidate->id]);
        $this->assertDatabaseHas('operation_records', ['action' => 'approval_default_updated', 'subject_id' => 'procurement_head']);
        $candidate->update(['is_active' => false]);
        $this->actingAs($admin)->get(route('approval-assignees.index'))
            ->assertInertia(fn ($page) => $page->component('admin/ApprovalAssignees')
                ->where('roles.0.default_user_name', $candidate->name)
                ->where('roles.0.default_user_active', false));
        $this->actingAs($admin)->put(route('approval-assignees.update', 'procurement_head'), ['user_id' => null])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('approval_role_defaults', ['role_code' => 'procurement_head']);

        $this->actingAs($manager)->from(route('procurement.approval-templates.index'))
            ->post(route('procurement.department-approvers.store'), [
                'department_id' => $department,
                'supervising_leader_id' => $admin->id,
                'tender_group_representative_id' => $manager->id,
            ])->assertRedirect(route('procurement.approval-templates.index'));
        $this->assertDatabaseHas('procurement_department_approvers', ['department_id' => $department]);
    }

    public function test_workflow_prefers_template_then_default_then_unique_role_without_changing_old_steps(): void
    {
        $department = DB::table('departments')->insertGetId(['name' => '测试部门', 'created_at' => now(), 'updated_at' => now()]);
        $applicant = User::factory()->create(['department_id' => $department]);
        $head = User::factory()->create(['department_id' => $department, 'school_account' => 'HEAD-001']);
        $roleCandidate = User::factory()->create(['department_id' => $department]);
        $anotherCandidate = User::factory()->create(['department_id' => $department]);
        $defaultCandidate = User::factory()->create(['department_id' => $department]);
        $templateCandidate = User::factory()->create(['department_id' => $department]);
        $manager = User::factory()->create(['department_id' => $department]);
        $asset = User::factory()->create(['department_id' => $department]);
        $supervisor = User::factory()->create(['department_id' => $department]);
        $representative = User::factory()->create(['department_id' => $department]);
        foreach ([[$head, 'department_head'], [$roleCandidate, 'procurement_head'], [$anotherCandidate, 'procurement_head'], [$manager, 'procurement_manager'], [$asset, 'asset_head']] as [$user, $role]) {
            $this->role($user, $role);
        }
        DB::table('procurement_department_approvers')->insert([
            'department_id' => $department, 'supervising_leader_id' => $supervisor->id,
            'tender_group_representative_id' => $representative->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('approval_role_defaults')->insert([
            'role_code' => 'procurement_head', 'user_id' => $defaultCandidate->id,
            'updated_by' => $manager->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $application = ProcurementApplication::create([
            'applicant_id' => $applicant->id, 'department_id' => $department,
            'application_form' => 'self_purchase', 'purchase_type' => 'self_purchase',
            'title' => '测试项目', 'budget_cents' => 10000, 'status' => 'draft',
        ]);
        $indicator = DB::table('budget_indicators')->insertGetId([
            'source_id' => 'B001', 'source' => 'school_source', 'code' => 'B001',
            'name' => '测试经费', 'charge_sno' => 'HEAD-001', 'status' => '1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('procurement_budget_allocations')->insert([
            'application_id' => $application->id, 'budget_indicator_id' => $indicator,
            'code' => 'B001', 'name' => '测试经费',
            'charge_sno' => 'HEAD-001', 'amount_cents' => 10000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(ApprovalWorkflow::class)->start($application);
        $this->assertDatabaseHas('approval_steps', [
            'application_id' => $application->id, 'attempt' => 1,
            'role_code' => 'procurement_head', 'assignee_id' => $defaultCandidate->id,
        ]);
        $defaultCandidate->update(['is_active' => false]);
        try {
            app(ApprovalWorkflow::class)->start($application->fresh());
            $this->fail('停用的默认审批人应阻止提交。');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertStringContainsString('默认审批人账号已停用', $exception->getMessage());
        }
        $defaultCandidate->update(['is_active' => true]);

        $template = DB::table('approval_templates')->where('purchase_type', 'self_purchase')->value('id');
        DB::table('approval_template_steps')->insert([
            'template_id' => $template, 'position' => 3,
            'role_code' => 'procurement_head', 'assignee_id' => $templateCandidate->id,
        ]);
        app(ApprovalWorkflow::class)->start($application->fresh());
        $this->assertDatabaseHas('approval_steps', [
            'application_id' => $application->id, 'attempt' => 2,
            'role_code' => 'procurement_head', 'assignee_id' => $templateCandidate->id,
        ]);
        $this->assertDatabaseHas('approval_steps', [
            'application_id' => $application->id, 'attempt' => 1,
            'role_code' => 'procurement_head', 'assignee_id' => $defaultCandidate->id,
        ]);

        DB::table('approval_template_steps')->where('template_id', $template)->where('role_code', 'procurement_head')->delete();
        DB::table('approval_role_defaults')->where('role_code', 'procurement_head')->delete();
        DB::table('role_user')->where('user_id', $anotherCandidate->id)
            ->where('role_id', DB::table('roles')->where('code', 'procurement_head')->value('id'))->delete();
        app(ApprovalWorkflow::class)->start($application->fresh());
        $this->assertDatabaseHas('approval_steps', [
            'application_id' => $application->id, 'attempt' => 3,
            'role_code' => 'procurement_head', 'assignee_id' => $roleCandidate->id,
        ]);
        $this->role($anotherCandidate, 'procurement_head');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('采购管理部门领导匹配到多名有效人员');
        app(ApprovalWorkflow::class)->start($application->fresh());
    }
}
