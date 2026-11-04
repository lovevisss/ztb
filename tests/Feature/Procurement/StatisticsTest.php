<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $department, ?string $role = null): User
    {
        $departmentId = DB::table('departments')->insertGetId(['name' => $department, 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['department_id' => $departmentId]);
        if ($role !== null) {
            DB::table('role_user')->insert([
                'user_id' => $user->id,
                'role_id' => DB::table('roles')->where('code', $role)->value('id'),
            ]);
        }

        return $user;
    }

    private function project(User $applicant, string $title, string $submitted, int $budget, string $type, string $status, string $responsible, string $method, ?int $actual = null): int
    {
        $id = DB::table('procurement_applications')->insertGetId([
            'applicant_id' => $applicant->id,
            'department_id' => $applicant->department_id,
            'title' => $title, 'budget_cents' => $budget,
            'application_form' => $type, 'purchase_type' => $type,
            'status' => $status, 'submitted_at' => $submitted,
            'responsible_department' => $responsible, 'purchase_method' => $method,
            'organization_form' => '学校组织', 'funding_source' => '专项经费',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('procurement_applications')->where('id', $id)->update(['project_number' => 'CG2026-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT)]);
        if ($actual !== null) {
            DB::table('procurement_executions')->insert([
                'application_id' => $id, 'actual_amount_cents' => $actual,
                'supplier' => '示例公司', 'completed_on' => substr($submitted, 0, 10),
                'result_note' => '已完成', 'status' => 'completed',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $id;
    }

    public function test_manager_dashboard_aggregates_year_department_method_status_execution_and_approval_duration(): void
    {
        $manager = $this->user('采购处', 'procurement_manager');
        $a = $this->user('信息中心');
        $b = $this->user('后勤处');
        $old = $this->project($a, '旧年度设备', '2025-04-01 09:00:00', 9_000_000, 'self_purchase', 'approved', '信息中心', '询价');
        $current = $this->project($a, '本年服务器', '2026-03-02 09:00:00', 12_000_000, 'central_tender', 'execution_completed', '信息中心', '公开招标', 11_000_000);
        $this->project($b, '后勤家具', '2026-05-10 09:00:00', 5_000_000, 'self_purchase', 'archived', '后勤处', '询价', 4_000_000);
        $this->project($b, '无提交草稿', '2026-05-10 09:00:00', 100, 'self_purchase', 'draft', '后勤处', '询价');
        foreach ([[$old, '2025-04-02 09:00:00'], [$current, '2026-03-03 09:00:00']] as [$id, $actedAt]) {
            DB::table('approval_events')->insert([
                'application_id' => $id, 'step_id' => null, 'actor_id' => $manager->id,
                'event' => 'approved', 'details' => null, 'created_at' => $actedAt, 'updated_at' => $actedAt,
            ]);
        }

        $this->actingAs($manager)->get(route('procurement.statistics.index'))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->component('procurement/Statistics')
            ->where('summary.projectCount', 3)
            ->where('summary.budgetCents', 26_000_000)
            ->where('summary.actualCents', 15_000_000)
            ->where('summary.selfPurchaseCount', 2)
            ->where('summary.centralTenderCount', 1)
            ->where('summary.pendingArchiveCount', 1)
            ->where('summary.averageApprovalHours', 24)
            ->where('byDepartment.0.label', '信息中心')
            ->where('byDepartment.0.count', 2)
            ->has('yearlyTrend', 2)
            ->has('byPurchaseMethod', 2));
    }

    public function test_filters_and_data_scope_limit_dashboard_and_excel_consistently(): void
    {
        $manager = $this->user('采购处', 'procurement_manager');
        $a = $this->user('信息中心');
        $b = $this->user('后勤处');
        $this->project($a, '甲部门采购 & <优选>', '2026-03-02 09:00:00', 10_000_000, 'central_tender', 'approved', '设备处', '公开招标');
        $this->project($b, '乙部门采购', '2026-03-04 09:00:00', 5_000_000, 'self_purchase', 'approved', '后勤处', '询价');
        $this->project($a, '上一年采购', '2025-03-02 09:00:00', 2_000_000, 'self_purchase', 'approved', '设备处', '询价');

        $this->actingAs($a)->get(route('procurement.statistics.index', ['year' => 2026]))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->where('summary.projectCount', 1)
            ->where('byDepartment.0.label', '信息中心'));
        $this->actingAs($manager)->get(route('procurement.statistics.index', [
            'year' => 2026, 'department_id' => $b->department_id,
        ]))->assertSuccessful()->assertInertia(fn ($page) => $page->where('summary.projectCount', 1)
            ->where('byDepartment.0.label', '后勤处'));
        $this->actingAs($manager)->get(route('procurement.statistics.index', [
            'responsible_department' => '设备处', 'purchase_method' => '公开招标',
        ]))->assertSuccessful()->assertInertia(fn ($page) => $page->where('summary.projectCount', 1));

        $response = $this->actingAs($a)->get(route('procurement.statistics.export', ['year' => 2026]));
        $response->assertSuccessful()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($sheet);
        $this->assertStringContainsString('甲部门采购', $sheet);
        $this->assertStringContainsString('甲部门采购 &amp; &lt;优选&gt;', $sheet);
        $this->assertStringContainsString('<c r="K2"><v>100000</v></c>', $sheet);
        $this->assertNotFalse(simplexml_load_string($sheet));
        $this->assertStringNotContainsString('乙部门采购', $sheet);
        $this->assertStringNotContainsString('上一年采购', $sheet);
        $zip->close();
    }

    public function test_dashboard_exposes_all_requested_dimensions_and_rejects_invalid_filters(): void
    {
        $user = $this->user('信息中心');
        $this->project($user, '维度项目', '2026-07-01 09:00:00', 3_000_000, 'self_purchase', 'execution_completed', '资产处', '询价', 2_500_000);

        $this->actingAs($user)->get(route('procurement.statistics.index'))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->has('byResponsibleDepartment', 1)
            ->has('byPurchaseMethod', 1)->has('byOrganizationForm', 1)
            ->has('byFundingSource', 1)->has('byPurchaseType', 1)
            ->has('byStatus', 1)->has('monthlyTrend', 1)
            ->has('departmentRanking', 1));
        $this->actingAs($user)->get(route('procurement.statistics.index', ['year' => 1800]))
            ->assertSessionHasErrors('year');
        $this->actingAs($user)->get(route('procurement.statistics.export', ['status' => 'unknown']))
            ->assertSessionHasErrors('status');
    }

    public function test_execution_and_archive_status_filters_match_the_same_project_set(): void
    {
        $user = $this->user('信息中心');
        $this->project($user, '待归档设备', '2026-04-01 09:00:00', 2_000_000, 'self_purchase', 'execution_completed', '资产处', '询价', 1_800_000);
        $this->project($user, '已归档设备', '2026-05-01 09:00:00', 3_000_000, 'self_purchase', 'archived', '资产处', '询价', 2_800_000);
        $this->project($user, '审批通过设备', '2026-06-01 09:00:00', 4_000_000, 'self_purchase', 'approved', '资产处', '询价');

        $this->actingAs($user)->get(route('procurement.statistics.index', ['execution_status' => 'completed', 'archive_status' => 'not_archived']))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->where('summary.projectCount', 1)
            ->where('records.0.title', '待归档设备'));
        $this->actingAs($user)->get(route('procurement.statistics.index', ['archive_status' => 'archived']))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->where('summary.projectCount', 1)
            ->where('records.0.title', '已归档设备'));
    }

    public function test_partial_execution_amount_is_not_counted_as_final_purchase_spend(): void
    {
        $user = $this->user('信息中心');
        $id = $this->project($user, '执行中的设备', '2026-08-01 09:00:00', 4_000_000, 'self_purchase', 'executing', '资产处', '询价');
        DB::table('procurement_executions')->insert([
            'application_id' => $id, 'actual_amount_cents' => 3_000_000,
            'supplier' => '暂定供应商', 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('procurement.statistics.index'))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->where('summary.actualCents', 0)
            ->where('records.0.actual_amount_cents', null));
    }
}
