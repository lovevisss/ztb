<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Models\ProcurementApplication;
use App\Services\ApprovalWorkflow;
use App\Services\ManualRoleAssignments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function department(string $name = '信息中心'): int
    {
        return DB::table('departments')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function user(int $departmentId, ?string $role = null): User
    {
        $user = User::factory()->create(['department_id' => $departmentId]);
        if ($role !== null) {
            DB::table('role_user')->insert([
                'user_id' => $user->id,
                'role_id' => DB::table('roles')->where('code', $role)->value('id'),
            ]);
        }

        return $user;
    }

    private function submit(User $applicant, int $budget = 90000): int
    {
        $this->actingAs($applicant)->post(route('procurement.applications.store'), [
            'intent' => 'submit', 'title' => '教学设备采购', 'budget' => $budget,
            'funding_source' => '教学经费', 'organization_form' => '部门自行组织',
            'purchase_method' => '询价', 'reason' => '教学需要',
            'responsible_department' => '教务处', 'is_fixed_asset' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        return (int) DB::table('procurement_applications')->max('id');
    }

    public function test_managers_can_search_all_approval_progress_and_reassign_from_results(): void
    {
        $department = $this->department();
        $applicant = $this->user($department);
        $head = $this->user($department, 'department_head');
        $replacement = $this->user($department, 'department_head');
        $manager = $this->user($this->department('采购处'), 'procurement_manager');
        $admin = $this->user($this->department('系统管理处'), 'system_admin');
        $outsider = $this->user($this->department('其他部门'));
        $id = $this->submit($applicant);
        $number = DB::table('procurement_applications')->where('id', $id)->value('project_number');
        $currentStep = DB::table('approval_steps')->where('application_id', $id)->where('status', 'pending')->first();
        $newAssignee = $currentStep->assignee_id === $head->id ? $replacement : $head;

        $this->actingAs($outsider)->get(route('procurement.approvals.progress'))->assertForbidden();
        foreach ([$manager, $admin] as $viewer) {
            foreach ([$number, '教学设备采购', $applicant->name] as $search) {
                $this->actingAs($viewer)->get(route('procurement.approvals.progress', ['search' => $search]))
                    ->assertOk()->assertInertia(fn ($page) => $page->component('procurement/ApprovalProgress')
                        ->where('projects.total', 1)->where('projects.data.0.id', $id)
                        ->where('projects.data.0.current_step_status', 'pending'));
            }
        }

        $this->actingAs($manager)->put(route('procurement.approvals.reassign', $currentStep->id).'?from=workflow&search='.urlencode($number), [
            'assignee_id' => $newAssignee->id,
        ])->assertRedirect(route('procurement.applications.show', ['application' => $id, 'from' => 'workflow', 'search' => $number]));
        $this->actingAs($manager)->get(route('procurement.approvals.progress', ['search' => $number]))
            ->assertInertia(fn ($page) => $page->where('projects.data.0.current_assignee_name', $newAssignee->name));
        $this->actingAs($manager)->get(route('procurement.applications.show', ['application' => $id, 'from' => 'workflow', 'search' => $number]))
            ->assertInertia(fn ($page) => $page->where('workflowReturnUrl', route('procurement.approvals.progress', ['search' => $number])));

        $this->actingAs($newAssignee)->post(route('procurement.approvals.decide', $currentStep->id), [
            'action' => 'approve',
        ])->assertRedirect();
        $this->actingAs($manager)->put(route('procurement.approvals.reassign', $currentStep->id), [
            'assignee_id' => $head->id,
        ])->assertForbidden();
        $this->actingAs($manager)->get(route('procurement.approvals.progress', ['search' => $number]))
            ->assertInertia(fn ($page) => $page->where('projects.data.0.current_role_code', 'procurement_manager')
                ->where('projects.data.0.current_step_status', 'pending'));
    }

    public function test_department_head_then_procurement_manager_approve_in_order(): void
    {
        $department = $this->department();
        $applicant = $this->user($department);
        $head = $this->user($department, 'department_head');
        $manager = $this->user($this->department('采购处'), 'procurement_manager');
        $id = $this->submit($applicant);

        $steps = DB::table('approval_steps')->where('application_id', $id)->orderBy('position')->get();
        $this->assertCount(2, $steps);
        $this->assertSame($head->id, $steps[0]->assignee_id);
        $this->assertSame('pending', $steps[0]->status);
        $this->assertSame($manager->id, $steps[1]->assignee_id);
        $this->assertSame('queued', $steps[1]->status);

        $this->actingAs($manager)->post(route('procurement.approvals.decide', $steps[1]->id), ['action' => 'approve'])->assertForbidden();
        $this->actingAs($head)->post(route('procurement.approvals.decide', $steps[0]->id), ['action' => 'approve', 'opinion' => '同意'])->assertRedirect();
        $this->assertDatabaseHas('approval_steps', ['id' => $steps[1]->id, 'status' => 'pending']);

        $this->actingAs($manager)->post(route('procurement.approvals.decide', $steps[1]->id), ['action' => 'approve'])->assertRedirect();
        $this->assertDatabaseHas('procurement_applications', ['id' => $id, 'status' => 'approved']);
        $this->assertDatabaseHas('approval_steps', ['id' => $steps[1]->id, 'status' => 'approved']);
    }

    public function test_named_school_accounts_resolve_to_department_head_and_manager(): void
    {
        $department = $this->department('信息技术部、实验室建设管理办公室');
        $applicant = User::factory()->create(['department_id' => $department, 'school_account' => '20150301']);
        $head = User::factory()->create(['department_id' => $department, 'school_account' => '20040093']);
        $roles = app(ManualRoleAssignments::class);
        $roles->replace($applicant, ['system_admin', 'procurement_manager'], null, 'console');
        $roles->replace($head, ['department_head'], $applicant);

        $application = ProcurementApplication::create([
            'applicant_id' => $applicant->id, 'department_id' => $department,
            'purchase_type' => 'central_tender', 'application_form' => 'central_tender',
            'status' => 'draft', 'title' => '教学设备采购', 'budget_cents' => 23000000,
            'has_usage_department' => false,
        ]);
        app(ApprovalWorkflow::class)->start($application);

        $steps = DB::table('approval_steps')->where('application_id', $application->id)->orderBy('position')->get();
        $this->assertCount(2, $steps);
        $this->assertSame('department_head', $steps[0]->role_code);
        $this->assertSame($head->id, $steps[0]->assignee_id);
        $this->assertSame('procurement_manager', $steps[1]->role_code);
        $this->assertSame($applicant->id, $steps[1]->assignee_id);
    }

    public function test_inactive_school_person_is_not_selected_as_an_approver(): void
    {
        $department = $this->department();
        $applicant = $this->user($department);
        $head = $this->user($department, 'department_head');
        $head->update(['is_active' => false]);
        $this->user($this->department('采购处'), 'procurement_manager');

        $this->actingAs($applicant)->post(route('procurement.applications.store'), [
            'intent' => 'submit', 'title' => '教学设备采购', 'budget' => 90000,
            'funding_source' => '教学经费', 'organization_form' => '部门自行组织',
            'purchase_method' => '询价', 'reason' => '教学需要',
            'responsible_department' => '教务处', 'is_fixed_asset' => false,
        ])->assertSessionHasErrors('budget');
        $this->assertDatabaseCount('approval_steps', 0);
    }

    public function test_explicit_template_assignee_must_remain_active_at_submission(): void
    {
        $department = $this->department();
        $applicant = $this->user($department);
        $head = $this->user($department, 'department_head');
        $manager = $this->user($this->department('采购处'), 'procurement_manager');

        $this->actingAs($manager)->post(route('procurement.approval-templates.store'), [
            'name' => '指定审批人', 'purchase_type' => 'self_purchase',
            'min_budget_yuan' => 0, 'steps' => [
                ['role_code' => 'department_head', 'assignee_id' => $head->id],
                ['role_code' => 'procurement_manager', 'assignee_id' => $manager->id],
            ],
        ])->assertSessionHasNoErrors();
        $head->update(['is_active' => false]);

        $this->actingAs($applicant)->post(route('procurement.applications.store'), [
            'intent' => 'submit', 'title' => '教学设备采购', 'budget' => 90000,
            'funding_source' => '教学经费', 'organization_form' => '部门自行组织',
            'purchase_method' => '询价', 'reason' => '教学需要',
            'responsible_department' => '教务处', 'is_fixed_asset' => false,
        ])->assertSessionHasErrors('budget');
        $this->assertDatabaseCount('approval_steps', 0);
    }

    public function test_return_preserves_history_and_resubmission_starts_a_new_attempt(): void
    {
        $department = $this->department();
        $applicant = $this->user($department);
        $head = $this->user($department, 'department_head');
        $this->user($this->department('采购处'), 'procurement_manager');
        $id = $this->submit($applicant);
        $first = DB::table('approval_steps')->where('application_id', $id)->where('position', 1)->first();

        $this->actingAs($head)->post(route('procurement.approvals.decide', $first->id), [
            'action' => 'return', 'opinion' => '请补充说明',
        ])->assertRedirect();
        $this->assertDatabaseHas('procurement_applications', ['id' => $id, 'status' => 'returned']);
        $this->actingAs($applicant)->get(route('procurement.applications.show', $id))
            ->assertInertia(fn ($page) => $page->component('procurement/ApprovalDetail')
                ->where('applicantName', $applicant->name)
                ->where('applicantDepartmentName', '信息中心')
                ->where('steps.0.status', 'returned')->where('steps.0.assignee_name', $head->name)
                ->whereNot('steps.0.acted_at', null)->where('events.0.event', 'submitted')
                ->where('events.1.actor_name', $head->name));
        $this->actingAs($applicant)->get(route('procurement.applications.edit', $id))->assertSuccessful();

        $this->actingAs($applicant)->put(route('procurement.applications.update', $id), [
            'intent' => 'submit', 'title' => '补充后的项目', 'budget' => 90000,
            'funding_source' => '教学经费', 'organization_form' => '部门自行组织',
            'purchase_method' => '询价', 'reason' => '已补充采购说明',
            'responsible_department' => '教务处', 'is_fixed_asset' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('approval_steps', ['id' => $first->id, 'attempt' => 1, 'status' => 'returned', 'opinion' => '请补充说明']);
        $this->assertDatabaseHas('approval_steps', ['application_id' => $id, 'attempt' => 2, 'position' => 1, 'status' => 'pending']);
        $this->assertSame(4, DB::table('approval_steps')->where('application_id', $id)->count());
        $this->actingAs($applicant)->get(route('procurement.applications.show', $id))
            ->assertInertia(fn ($page) => $page->has('steps', 4)->where('steps.2.attempt', 2)
                ->where('events.2.event', 'resubmitted'));

        $secondStep = DB::table('approval_steps')->where('application_id', $id)->where('attempt', 2)->where('position', 1)->value('id');
        $this->actingAs($head)->post(route('procurement.approvals.decide', $secondStep), [
            'action' => 'return', 'opinion' => '请再次补充',
        ])->assertRedirect();
        $this->actingAs($head)->get(route('procurement.applications.edit', $id))->assertForbidden();
        $this->actingAs($applicant)->put(route('procurement.applications.update', $id), [
            'intent' => 'submit', 'title' => '再次补充后的项目', 'budget' => 90000,
            'funding_source' => '教学经费', 'organization_form' => '部门自行组织',
            'purchase_method' => '询价', 'reason' => '再次补充采购说明',
            'responsible_department' => '教务处', 'is_fixed_asset' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('approval_steps', ['id' => $secondStep, 'attempt' => 2, 'status' => 'returned', 'opinion' => '请再次补充']);
        $this->actingAs($applicant)->get(route('procurement.applications.show', $id))
            ->assertInertia(fn ($page) => $page->has('steps', 6)->where('steps.4.attempt', 3)
                ->where('events.4.event', 'resubmitted'));
    }

    public function test_rejection_terminates_current_application_and_requires_new_one(): void
    {
        $department = $this->department();
        $applicant = $this->user($department);
        $head = $this->user($department, 'department_head');
        $this->user($this->department('采购处'), 'procurement_manager');
        $id = $this->submit($applicant);
        $stepId = DB::table('approval_steps')->where('application_id', $id)->where('position', 1)->value('id');

        $this->actingAs($head)->post(route('procurement.approvals.decide', $stepId), [
            'action' => 'reject', 'opinion' => '不符合要求',
        ])->assertRedirect();
        $this->assertDatabaseHas('procurement_applications', ['id' => $id, 'status' => 'rejected']);

        $this->actingAs($applicant)->put(route('procurement.applications.update', $id), ['intent' => 'draft', 'title' => '修改被驳回申请'])->assertForbidden();
        $newId = $this->submit($applicant);
        $this->assertNotSame($id, $newId);
    }

    public function test_only_assignee_can_decide_and_duplicate_decisions_are_rejected(): void
    {
        $department = $this->department();
        $applicant = $this->user($department);
        $head = $this->user($department, 'department_head');
        $outsider = $this->user($department);
        $this->user($this->department('采购处'), 'procurement_manager');
        $id = $this->submit($applicant);
        $stepId = DB::table('approval_steps')->where('application_id', $id)->where('position', 1)->value('id');

        $this->actingAs($outsider)->post(route('procurement.approvals.decide', $stepId), ['action' => 'approve'])->assertForbidden();
        $this->actingAs($head)->post(route('procurement.approvals.decide', $stepId), ['action' => 'return'])->assertSessionHasErrors('opinion');
        $this->actingAs($head)->post(route('procurement.approvals.decide', $stepId), ['action' => 'approve'])->assertRedirect();
        $this->actingAs($head)->post(route('procurement.approvals.decide', $stepId), ['action' => 'approve'])->assertForbidden();
    }

    public function test_manager_can_reassign_pending_step_and_old_assignee_loses_access(): void
    {
        $department = $this->department();
        $applicant = $this->user($department);
        $oldHead = $this->user($department, 'department_head');
        $newHead = $this->user($department, 'department_head');
        $manager = $this->user($this->department('采购处'), 'procurement_manager');
        $id = $this->submit($applicant);
        $stepId = DB::table('approval_steps')->where('application_id', $id)->where('position', 1)->value('id');
        $originalAssignee = DB::table('approval_steps')->where('id', $stepId)->value('assignee_id');
        $replacement = $originalAssignee === $oldHead->id ? $newHead : $oldHead;

        $inactive = $this->user($department, 'department_head');
        $inactive->update(['is_active' => false]);
        $this->actingAs($manager)->put(route('procurement.approvals.reassign', $stepId), ['assignee_id' => $inactive->id])
            ->assertSessionHasErrors('assignee_id');

        $this->actingAs($applicant)->put(route('procurement.approvals.reassign', $stepId), ['assignee_id' => $replacement->id])->assertForbidden();
        $this->actingAs($manager)->put(route('procurement.approvals.reassign', $stepId), ['assignee_id' => $replacement->id])->assertRedirect();
        $this->assertDatabaseHas('approval_steps', ['id' => $stepId, 'assignee_id' => $replacement->id]);
        $this->actingAs($originalAssignee === $oldHead->id ? $oldHead : $newHead)
            ->post(route('procurement.approvals.decide', $stepId), ['action' => 'approve'])->assertForbidden();
        $this->actingAs($replacement)->post(route('procurement.approvals.decide', $stepId), ['action' => 'approve'])->assertRedirect();
    }

    public function test_new_template_applies_only_to_new_applications(): void
    {
        $department = $this->department();
        $applicant = $this->user($department);
        $this->user($department, 'department_head');
        $manager = $this->user($this->department('采购处'), 'procurement_manager');
        $responsible = $this->user($this->department('教务处'), 'responsible_department');
        $oldId = $this->submit($applicant);

        $this->actingAs($manager)->post(route('procurement.approval-templates.store'), [
            'name' => '增加归口部门审核',
            'purchase_type' => 'self_purchase',
            'min_budget_yuan' => 50000,
            'max_budget_yuan' => 100000,
            'applicant_department_id' => $department,
            'responsible_department' => '教务处',
            'steps' => [
                ['role_code' => 'department_head'],
                ['role_code' => 'responsible_department'],
                ['role_code' => 'procurement_manager'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $newId = $this->submit($applicant);
        $outsideAmountId = $this->submit($applicant, 40000);
        $otherDepartment = $this->department('外国语学院');
        $otherApplicant = $this->user($otherDepartment);
        $this->user($otherDepartment, 'department_head');
        $outsideDepartmentId = $this->submit($otherApplicant);
        $this->assertSame(2, DB::table('approval_steps')->where('application_id', $oldId)->count());
        $this->assertSame(3, DB::table('approval_steps')->where('application_id', $newId)->count());
        $this->assertSame(2, DB::table('approval_steps')->where('application_id', $outsideAmountId)->count());
        $this->assertSame(2, DB::table('approval_steps')->where('application_id', $outsideDepartmentId)->count());
        $this->assertDatabaseHas('approval_steps', ['application_id' => $newId, 'position' => 2, 'assignee_id' => $responsible->id]);
    }

    public function test_approver_sees_inbox_and_project_history_while_outsider_is_denied(): void
    {
        $department = $this->department();
        $applicant = $this->user($department);
        $head = $this->user($department, 'department_head');
        $outsider = $this->user($this->department('外国语学院'));
        $this->user($this->department('采购处'), 'procurement_manager');
        $id = $this->submit($applicant);

        $this->actingAs($head)->get(route('procurement.approvals.inbox'))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->component('procurement/ApprovalInbox')->has('steps', 1));
        $this->actingAs($head)->get(route('procurement.applications.show', $id))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->component('procurement/ApprovalDetail')->has('steps', 2)->has('events', 1));
        $this->actingAs($outsider)->get(route('procurement.applications.show', $id))->assertForbidden();
    }

    public function test_missing_approver_blocks_submission_without_leaving_a_partial_project(): void
    {
        $applicant = $this->user($this->department());

        $this->actingAs($applicant)->post(route('procurement.applications.store'), [
            'intent' => 'submit', 'title' => '无法流转的项目', 'budget' => 90000,
            'funding_source' => '教学经费', 'organization_form' => '部门自行组织',
            'purchase_method' => '询价', 'reason' => '教学需要',
            'responsible_department' => '教务处', 'is_fixed_asset' => false,
        ])->assertSessionHasErrors('budget');

        $this->assertDatabaseCount('procurement_applications', 0);
    }

    public function test_unprivileged_user_cannot_configure_templates(): void
    {
        $user = $this->user($this->department());

        $this->actingAs($user)->get(route('procurement.approval-templates.index'))->assertForbidden();
        $this->actingAs($user)->post(route('procurement.approval-templates.store'), [
            'name' => '越权流程', 'purchase_type' => 'self_purchase', 'min_budget_yuan' => 0,
            'steps' => [['role_code' => 'procurement_manager']],
        ])->assertForbidden();
        $this->assertDatabaseCount('approval_templates', 2);
    }
}
