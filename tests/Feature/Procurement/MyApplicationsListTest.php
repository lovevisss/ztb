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

    private function newRequest(User $user, int $departmentId, string $status, string $number, string $title): void
    {
        DB::table('procurement_applications')->insert([
            'applicant_id' => $user->id, 'department_id' => $departmentId,
            'project_number' => $number, 'title' => $title, 'status' => $status,
            'budget_cents' => 10000, 'submitted_at' => $status === 'draft' ? null : now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function legacyRequest(string $id, string $account, string $status, string $number, string $title): void
    {
        DB::table('legacy_procurement_records')->insert([
            'source_table' => 'zcy_cgsq', 'source_id' => $id, 'application_source_id' => $id,
            'creator_account' => $account, 'creator_name' => '历史申请人',
            'number' => $number, 'title' => $title, 'status' => $status,
            'details' => json_encode(['sbbm' => '测试部门']), 'occurred_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
