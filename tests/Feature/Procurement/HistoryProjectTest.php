<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoryProjectTest extends TestCase
{
    use RefreshDatabase;

    private function department(string $name): int
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

    private function project(User $applicant, string $title, string $submittedAt = '2026-11-04 10:00:00', string $method = '询价', string $status = 'approved'): int
    {
        $id = DB::table('procurement_applications')->insertGetId([
            'applicant_id' => $applicant->id,
            'department_id' => $applicant->department_id,
            'title' => $title,
            'budget_cents' => 5_000_000,
            'funding_source' => '教学经费',
            'organization_form' => '部门自行组织',
            'purchase_method' => $method,
            'reason' => '教学需要',
            'responsible_department' => '教务处',
            'is_fixed_asset' => false,
            'application_form' => 'self_purchase',
            'purchase_type' => 'self_purchase',
            'status' => $status,
            'submitted_at' => $submittedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('procurement_applications')->where('id', $id)->update(['project_number' => 'CG2026-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT)]);

        return $id;
    }

    public function test_staff_can_search_and_filter_submitted_projects_in_their_department(): void
    {
        $department = $this->department('信息中心');
        $viewer = $this->user($department);
        $applicant = $this->user($department);
        $other = $this->user($this->department('外国语学院'));
        $matching = $this->project($applicant, '教学电脑采购');
        $this->project($applicant, '办公桌采购', '2025-11-04 10:00:00', '竞价');
        $this->project($other, '教学电脑采购');

        $this->actingAs($viewer)->get(route('procurement.history.index', [
            'search' => '电脑', 'year' => 2026, 'purchase_method' => '询价', 'status' => 'approved',
        ]))->assertSuccessful()->assertInertia(fn ($page) => $page->component('procurement/History')
            ->has('projects.data', 1)->where('projects.data.0.id', $matching));
        $this->actingAs($viewer)->get(route('procurement.applications.show', $matching))->assertSuccessful();
    }

    public function test_cross_department_detail_and_copy_are_denied_but_manager_can_view_schoolwide(): void
    {
        $staff = $this->user($this->department('信息中心'));
        $other = $this->user($this->department('外国语学院'));
        $manager = $this->user($this->department('采购处'), 'procurement_manager');
        $id = $this->project($other, '学院设备采购');

        $this->actingAs($staff)->get(route('procurement.applications.show', $id))->assertForbidden();
        $this->actingAs($staff)->post(route('procurement.history.copy', $id))->assertForbidden();
        $this->actingAs($staff)->get(route('procurement.history.index'))
            ->assertInertia(fn ($page) => $page->has('projects.data', 0));
        $this->actingAs($manager)->get(route('procurement.history.index'))
            ->assertInertia(fn ($page) => $page->has('projects.data', 1));
    }

    public function test_copy_creates_editable_new_project_without_old_approval_and_resubmits_through_full_flow(): void
    {
        $department = $this->department('信息中心');
        $originalApplicant = $this->user($department);
        $copier = $this->user($department);
        $this->user($department, 'department_head');
        $this->user($this->department('采购处'), 'procurement_manager');
        $sourceId = $this->project($originalApplicant, '旧设备采购');
        DB::table('approval_events')->insert([
            'application_id' => $sourceId, 'actor_id' => $originalApplicant->id,
            'event' => 'approved', 'details' => '旧审批意见', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($copier)->post(route('procurement.history.copy', $sourceId))->assertRedirect();
        $copied = DB::table('procurement_applications')->where('source_application_id', $sourceId)->first();
        $this->assertNotNull($copied);
        $this->assertSame($copier->id, $copied->applicant_id);
        $this->assertSame($department, $copied->department_id);
        $this->assertSame('旧设备采购', $copied->title);
        $this->assertSame('draft', $copied->status);
        $this->assertNotSame(DB::table('procurement_applications')->find($sourceId)->project_number, $copied->project_number);
        $this->assertNull($copied->approval_template_id);
        $this->assertSame(0, DB::table('approval_events')->where('application_id', $copied->id)->count());
        $this->assertSame(0, DB::table('approval_steps')->where('application_id', $copied->id)->count());

        $this->actingAs($copier)->get(route('procurement.applications.show', $copied->id))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->component('procurement/ApprovalDetail')
            ->where('sourceApplication.id', $sourceId));

        $this->actingAs($copier)->put(route('procurement.applications.update', $copied->id), [
            'intent' => 'submit', 'title' => '新设备采购', 'budget' => 60000,
            'funding_source' => '教学经费', 'organization_form' => '部门自行组织',
            'purchase_method' => '询价', 'reason' => '更新设备',
            'responsible_department' => '教务处', 'is_fixed_asset' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('procurement_applications', [
            'id' => $copied->id, 'title' => '新设备采购', 'budget_cents' => 6_000_000,
            'status' => 'submitted', 'source_application_id' => $sourceId,
        ]);
        $this->assertDatabaseCount('approval_steps', 2);
        $this->assertSame(1, DB::table('approval_events')->where('application_id', $copied->id)->count());
    }

    public function test_drafts_are_not_in_history_and_cannot_be_copied_by_colleagues(): void
    {
        $department = $this->department('信息中心');
        $owner = $this->user($department);
        $colleague = $this->user($department);
        $draftId = DB::table('procurement_applications')->insertGetId([
            'applicant_id' => $owner->id, 'department_id' => $department,
            'application_form' => 'self_purchase', 'title' => '未提交草稿',
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($colleague)->get(route('procurement.history.index'))
            ->assertInertia(fn ($page) => $page->has('projects.data', 0));
        $this->actingAs($colleague)->get(route('procurement.applications.show', $draftId))->assertForbidden();
        $this->actingAs($colleague)->post(route('procurement.history.copy', $draftId))->assertForbidden();
    }
}
