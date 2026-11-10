<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class MyApplicationsListTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabs_filters_and_pagination_include_only_the_owners_new_and_legacy_requests(): void
    {
        [$owner, $other, $departmentId] = $this->people();
        $this->newRequest($owner, $departmentId, 'draft', 'D-001', '个人草稿');
        $this->newRequest($owner, $departmentId, 'returned', 'R-001', '退回项目');
        $this->newRequest($owner, $departmentId, 'submitted', 'S-001', '已提交项目');
        $this->newRequest($other, $departmentId, 'submitted', 'S-OTHER', '他人项目');
        $this->legacyRequest('old-mine', $owner->school_account, '未提交', 'L-001', '历史草稿');
        $this->legacyRequest('old-other', $other->school_account, '审批通过', 'L-OTHER', '他人档案');

        $this->actingAs($owner)->get(route('procurement.applications.index', ['tab' => 'draft']))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->has('records', 3)
            ->where('counts.draft', 3)->where('counts.submitted', 1));
        $this->actingAs($owner)->get(route('procurement.applications.index', [
            'tab' => 'draft', 'number' => 'R-0', 'title' => '退回',
            'department_name' => '测试部门', 'applicant_name' => $owner->name, 'status' => '退回',
        ]))->assertSuccessful()->assertInertia(fn ($page) => $page->has('records', 1)
            ->where('records.0.number', 'R-001')->where('records.0.tab', 'draft'));
        $this->actingAs($owner)->get(route('procurement.applications.index', ['tab' => 'submitted']))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->has('records', 1)
            ->where('records.0.number', 'S-001'));

        for ($index = 0; $index < 16; $index++) {
            $this->newRequest($owner, $departmentId, 'submitted', sprintf('P-%03d', $index), '分页项目');
        }
        $this->actingAs($owner)->get(route('procurement.applications.index', ['tab' => 'submitted', 'page' => 2]))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->has('records', 2)
            ->where('total', 17)->where('page', 2)->where('lastPage', 2));
    }

    public function test_excel_export_uses_the_same_filters_and_all_pages_without_leaking_other_users(): void
    {
        [$owner, $other, $departmentId] = $this->people();
        for ($index = 0; $index < 17; $index++) {
            $this->newRequest($owner, $departmentId, 'submitted', sprintf('OWN-%03d', $index), '可导出项目');
        }
        $this->newRequest($other, $departmentId, 'submitted', 'SECRET-001', '他人秘密');
        $this->legacyRequest('legacy-mine', $owner->school_account, '审批通过', 'OLD-001', '可导出旧项目');
        $this->legacyRequest('legacy-other', $other->school_account, '审批通过', 'SECRET-OLD', '他人旧档案');

        $response = $this->actingAs($owner)->get(route('procurement.applications.export', ['tab' => 'submitted']));
        $response->assertSuccessful()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertIsString($sheet);
        $this->assertSame(18, substr_count($sheet, '<row r="') - 1);
        $this->assertStringContainsString('OLD-001', $sheet);
        $this->assertStringContainsString('OWN-016', $sheet);
        $this->assertStringNotContainsString('SECRET', $sheet);

        $filtered = $this->actingAs($owner)->get(route('procurement.applications.export', [
            'tab' => 'submitted', 'number' => 'OLD-001',
        ]));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($filtered->baseResponse->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertSame(1, substr_count($sheet, '<row r="') - 1);
        $this->assertStringContainsString('OLD-001', $sheet);
    }

    public function test_regular_projects_match_across_systems_and_years_before_filtering(): void
    {
        [$owner, $other, $departmentId] = $this->people();
        $first = $this->newRequest($owner, $departmentId, 'approved', 'NEW-2024', '旧年度', '2024-05-02');
        $second = $this->newRequest($owner, $departmentId, 'executing', 'NEW-2025', '新年度', '2025-05-02');
        $sameYear = $this->newRequest($owner, $departmentId, 'approved', 'SAME-2025', '同年重复', '2025-06-02');
        $this->newLine($first, ' 纸  张 ', 'A4', 2, '包', 3000, 6000);
        $this->newLine($first, '笔', null, 4, '支', 1000, 4000);
        $this->newLine($second, '笔', '', 4, '支', 1000, 4000);
        $this->newLine($second, '纸 张', 'A4', 2, '包', 3000, 6000);
        $this->newLine($sameYear, '笔', '', 4, '支', 1000, 4000);
        $this->newLine($sameYear, '纸 张', 'A4', 2, '包', 3000, 6000);
        $this->legacyRequest('old-2026', $owner->school_account, '审批通过', 'OLD-2026', '历史年度', '2026-05-02', 10000);
        $this->legacyLine('old-2026', '笔', '', 4, '支', 10, 40);
        $this->legacyLine('old-2026', '纸 张', 'A4', 2, '包', 30, 60);
        $this->legacyRequest('other-2027', $other->school_account, '审批通过', 'SECRET-2027', '他人项目', '2027-05-02', 10000);
        $this->legacyLine('other-2027', '笔', '', 4, '支', 10, 40);
        $this->legacyLine('other-2027', '纸 张', 'A4', 2, '包', 30, 60);

        $this->actingAs($owner)->get(route('procurement.applications.index', [
            'tab' => 'submitted', 'year' => 2024, 'regular' => 'regular', 'number' => 'NEW',
        ]))->assertSuccessful()->assertInertia(fn ($page) => $page->has('records', 1)
            ->where('records.0.number', 'NEW-2024')->where('records.0.year', 2024)
            ->where('records.0.is_regular', true)->where('records.0.matching_years', [2025, 2026])
            ->where('total', 1)->where('years', [2026, 2025, 2024]));

        $this->actingAs($owner)->get(route('procurement.applications.index', [
            'tab' => 'submitted', 'year' => 2026, 'regular' => 'regular',
        ]))->assertSuccessful()->assertInertia(fn ($page) => $page->has('records', 1)
            ->where('records.0.number', 'OLD-2026')->where('records.0.is_regular', true));

        $response = $this->actingAs($owner)->get(route('procurement.applications.export', [
            'tab' => 'submitted', 'regular' => 'regular', 'year' => 2025,
        ]));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertSame(2, substr_count($sheet, '<row r="') - 1);
        $this->assertStringContainsString('所属年度', $sheet);
        $this->assertStringContainsString('常规项目', $sheet);
        $this->assertStringNotContainsString('SECRET', $sheet);
    }

    public function test_regular_matching_requires_approved_complete_identical_multiset_and_draft_uses_creation_year(): void
    {
        [$owner, , $departmentId] = $this->people();
        $base = $this->newRequest($owner, $departmentId, 'approved', 'BASE', '基准', '2024-01-02');
        $sameYear = $this->newRequest($owner, $departmentId, 'approved', 'SAME', '同年', '2024-08-02');
        $different = $this->newRequest($owner, $departmentId, 'approved', 'DIFFERENT', '重复行数不同', '2025-01-02');
        $rejected = $this->newRequest($owner, $departmentId, 'rejected', 'REJECTED', '未通过', '2025-01-02');
        $missing = $this->newRequest($owner, $departmentId, 'approved', 'MISSING', '无结构化清单', '2026-01-02');
        $changedAmount = $this->newRequest($owner, $departmentId, 'approved', 'AMOUNT', '金额不同', '2025-01-02');
        $changedUnit = $this->newRequest($owner, $departmentId, 'approved', 'UNIT', '单位不同', '2025-01-02');
        $engineering = $this->newRequest($owner, $departmentId, 'approved', 'ENGINEERING', '工程附件', '2025-01-02');
        $draft = $this->newRequest($owner, $departmentId, 'draft', 'DRAFT', '草稿', '2023-01-02');
        foreach ([$base, $sameYear] as $id) {
            $this->newLine($id, '笔', null, 1, '支', 5000, 5000);
            $this->newLine($id, '笔', null, 1, '支', 5000, 5000);
        }
        foreach ([$different, $rejected] as $id) {
            $this->newLine($id, '笔', null, 1, '支', 5000, 5000);
            $this->newLine($id, '本', null, 1, '本', 5000, 5000);
        }
        foreach ([$changedAmount, $changedUnit, $engineering] as $id) {
            $this->newLine($id, '笔', null, 1, '支', 5000, 5000);
            $this->newLine($id, '笔', null, 1, '支', 5000, 5000);
        }
        DB::table('procurement_applications')->where('id', $changedAmount)->update(['budget_cents' => 10001]);
        DB::table('procurement_line_items')->where('application_id', $changedUnit)->limit(1)->update(['unit' => '盒']);
        DB::table('procurement_applications')->where('id', $engineering)->update(['category' => '工程类']);
        $this->legacyRequest('old-draft', $owner->school_account, '未提交', 'OLD-DRAFT', '旧草稿', '2022-01-02');
        DB::table('legacy_procurement_records')->where('source_table', 'zcy_cgsq')
            ->where('source_id', 'old-draft')->update(['details' => json_encode(['sbrq' => '2025-01-02'])]);
        $this->actingAs($owner)->get(route('procurement.applications.index', [
            'tab' => 'submitted', 'regular' => 'regular',
        ]))->assertSuccessful()->assertInertia(fn ($page) => $page->has('records', 0));
        $this->actingAs($owner)->get(route('procurement.applications.index', [
            'tab' => 'submitted', 'regular' => 'other',
        ]))->assertSuccessful()->assertInertia(fn ($page) => $page->has('records', 8));
        $this->actingAs($owner)->get(route('procurement.applications.index', [
            'tab' => 'draft', 'year' => 2023,
        ]))->assertSuccessful()->assertInertia(fn ($page) => $page->has('records', 1)
            ->where('records.0.number', 'DRAFT')->where('records.0.year', 2023));
        $this->actingAs($owner)->get(route('procurement.applications.index', [
            'tab' => 'draft', 'year' => 2022,
        ]))->assertSuccessful()->assertInertia(fn ($page) => $page->has('records', 1)
            ->where('records.0.number', 'OLD-DRAFT')->where('records.0.year', 2022));
    }

    private function people(): array
    {
        $departmentId = DB::table('departments')->insertGetId([
            'name' => '测试部门', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [
            User::factory()->create(['school_account' => 'OWNER']),
            User::factory()->create(['school_account' => 'OTHER']),
            $departmentId,
        ];
    }

    private function newRequest(User $user, int $departmentId, string $status, string $number, string $title, ?string $date = null): int
    {
        return DB::table('procurement_applications')->insertGetId([
            'applicant_id' => $user->id, 'department_id' => $departmentId,
            'project_number' => $number, 'title' => $title, 'status' => $status,
            'budget_cents' => 10000, 'submitted_at' => $status === 'draft' ? null : ($date ?? now()),
            'created_at' => $date ?? now(), 'updated_at' => $date ?? now(),
        ]);
    }

    private function legacyRequest(string $id, string $account, string $status, string $number, string $title, ?string $date = null, ?int $amount = null): void
    {
        DB::table('legacy_procurement_records')->insert([
            'source_table' => 'zcy_cgsq', 'source_id' => $id, 'application_source_id' => $id,
            'creator_account' => $account, 'creator_name' => '历史申请人',
            'number' => $number, 'title' => $title, 'status' => $status,
            'amount_cents' => $amount, 'details' => json_encode(['sbbm' => '测试部门', 'sbrq' => $date ?? now()->toDateString()]),
            'occurred_at' => $date ?? now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function newLine(int $applicationId, string $name, ?string $specification, float $quantity, string $unit, int $price, int $total): void
    {
        DB::table('procurement_line_items')->insert([
            'application_id' => $applicationId, 'position' => 1, 'name' => $name,
            'specification' => $specification, 'quantity' => $quantity, 'unit' => $unit,
            'unit_price_cents' => $price, 'total_cents' => $total,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function legacyLine(string $applicationId, string $name, string $specification, float $quantity, string $unit, int $price, int $total): void
    {
        DB::table('legacy_procurement_records')->insert([
            'source_table' => 'zcy_cgsq_qd', 'source_id' => uniqid('line-', true),
            'application_source_id' => $applicationId,
            'details' => json_encode(['mc' => $name, 'ggxh' => $specification, 'sl' => $quantity,
                'dw' => $unit, 'dj' => $price, 'je' => $total]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
