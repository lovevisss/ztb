<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_managers_see_submitted_projects_across_both_systems_without_other_peoples_drafts(): void
    {
        $first = DB::table('departments')->insertGetId(['name' => '第一部门', 'created_at' => now(), 'updated_at' => now()]);
        $second = DB::table('departments')->insertGetId(['name' => '第二部门', 'created_at' => now(), 'updated_at' => now()]);
        $manager = User::factory()->create(['department_id' => $second]);
        $admin = User::factory()->create(['department_id' => $second]);
        $outsider = User::factory()->create(['department_id' => $second]);
        $applicant = User::factory()->create(['department_id' => $first]);
        foreach ([[$manager, 'procurement_manager'], [$admin, 'system_admin']] as [$user, $code]) {
            DB::table('role_user')->insert([
                'user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', $code)->value('id'),
            ]);
        }

        DB::table('procurement_applications')->insert([
            ['applicant_id' => $applicant->id, 'department_id' => $first, 'project_number' => 'NEW-2026',
                'title' => '新系统项目', 'status' => 'submitted', 'budget_cents' => 10000,
                'submitted_at' => '2026-11-15 10:00:00', 'created_at' => now(), 'updated_at' => now()],
            ['applicant_id' => $applicant->id, 'department_id' => $first, 'project_number' => null,
                'title' => '未提交的新草稿', 'status' => 'draft', 'budget_cents' => null,
                'submitted_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('legacy_procurement_records')->insert([
            ['source_table' => 'zcy_cgsq', 'source_id' => 'old-submitted', 'application_source_id' => 'old-submitted',
                'department_id' => $second, 'number' => 'OLD-2025', 'title' => '旧系统项目',
                'creator_name' => '旧申请人', 'status' => '审批通过', 'amount_cents' => 20000,
                'occurred_at' => '2025-06-01 10:00:00', 'details' => '{}', 'created_at' => now(), 'updated_at' => now()],
            ['source_table' => 'zcy_cgsq', 'source_id' => 'old-draft', 'application_source_id' => 'old-draft',
                'department_id' => $second, 'number' => null, 'title' => '未提交的旧草稿',
                'creator_name' => null, 'status' => '未提交', 'amount_cents' => null,
                'occurred_at' => '2026-11-16 10:00:00', 'details' => '{}',
                'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($outsider)->get(route('procurement.projects.index'))->assertForbidden();
        foreach ([$manager, $admin] as $viewer) {
            $this->actingAs($viewer)->get(route('procurement.projects.index'))
                ->assertSuccessful()->assertInertia(fn ($page) => $page->component('procurement/ProjectOverview')
                ->where('projects.total', 2)->where('totals.new', 1)->where('totals.legacy', 1)
                ->where('projects.data.0.source', 'new')->where('projects.data.0.number', 'NEW-2026')
                ->where('projects.data.1.source', 'legacy')->where('projects.data.1.source_id', 'old-submitted'));
            $this->actingAs($viewer)->get(route('procurement.projects.index', ['source' => 'legacy', 'year' => 2025, 'department_id' => $second, 'search' => '旧申请人']))
                ->assertInertia(fn ($page) => $page->where('projects.total', 1)->where('projects.data.0.source_id', 'old-submitted'));
            $this->actingAs($viewer)->get(route('procurement.projects.index', ['source' => 'new', 'department_id' => $second]))
                ->assertInertia(fn ($page) => $page->where('projects.total', 0));
        }
        $this->actingAs($manager)->get(route('procurement.legacy-history.show', 'old-submitted'))->assertSuccessful();
    }

    public function test_overview_paginates_the_combined_results(): void
    {
        $department = DB::table('departments')->insertGetId(['name' => '采购部', 'created_at' => now(), 'updated_at' => now()]);
        $manager = User::factory()->create(['department_id' => $department]);
        DB::table('role_user')->insert([
            'user_id' => $manager->id, 'role_id' => DB::table('roles')->where('code', 'procurement_manager')->value('id'),
        ]);
        for ($i = 1; $i <= 21; $i++) {
            DB::table('legacy_procurement_records')->insert([
                'source_table' => 'zcy_cgsq', 'source_id' => "old-{$i}", 'application_source_id' => "old-{$i}",
                'department_id' => $department, 'title' => "项目 {$i}", 'status' => '审批通过',
                'occurred_at' => now()->subDays($i), 'details' => '{}', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($manager)->get(route('procurement.projects.index'))
            ->assertInertia(fn ($page) => $page->where('projects.total', 21)->has('projects.data', 20));
        $this->actingAs($manager)->get(route('procurement.projects.index', ['page' => 2]))
            ->assertInertia(fn ($page) => $page->where('projects.total', 21)->has('projects.data', 1));
    }
}
