<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_applications_lists_only_my_legacy_requests_alongside_new_requests(): void
    {
        $user = User::factory()->create(['school_account' => '20150301']);
        $other = User::factory()->create(['school_account' => 'OTHER']);
        $now = now();
        $departmentId = DB::table('departments')->insertGetId(['name' => '测试部门', 'created_at' => $now, 'updated_at' => $now]);
        foreach ([['old-mine', '20150301'], ['old-other', 'OTHER']] as [$sourceId, $account]) {
            DB::table('legacy_procurement_records')->insert([
                'source_table' => 'zcy_cgsq', 'source_id' => $sourceId,
                'application_source_id' => $sourceId, 'creator_account' => $account,
                'user_id' => $account === '20150301' ? $user->id : $other->id,
                'title' => $sourceId, 'details' => '{}', 'occurred_at' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::table('procurement_applications')->insert([
            'applicant_id' => $user->id, 'department_id' => $departmentId,
            'title' => '新申请', 'status' => 'draft',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->actingAs($user)->get(route('procurement.applications.index'))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->component('procurement/Index')
            ->has('records', 1)->where('records.0.source_id', 'old-mine')
            ->where('counts.draft', 1)->where('counts.submitted', 1));
        $this->actingAs($user)->get(route('procurement.legacy-history.show', 'old-mine'))->assertSuccessful();
        $this->actingAs($user)->get(route('procurement.applications.index', ['legacy' => 'old-mine']))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->component('procurement/LegacyDetail')
            ->where('project.source_id', 'old-mine')
            ->where('backHref', '/procurement/applications'));
        $this->actingAs($user)->get(route('procurement.applications.index', ['legacy' => 'old-other']))->assertNotFound();
        $this->actingAs($user)->get(route('procurement.legacy-history.show', 'old-other'))->assertForbidden();
        $this->actingAs($other)->get(route('procurement.applications.index'))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->has('records', 1)
            ->where('records.0.source_id', 'old-other'));
    }

    public function test_my_applications_can_be_empty_for_a_user_without_requests(): void
    {
        $this->actingAs(User::factory()->create(['school_account' => 'NO-REQUESTS']))
            ->get(route('procurement.applications.index'))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->has('records', 0)
            ->where('counts.draft', 0)->where('counts.submitted', 0));
    }

    public function test_my_legacy_detail_exposes_item_history_and_file_index_without_download_path(): void
    {
        $user = User::factory()->create(['school_account' => 'LEGACY-OWNER']);
        $now = now();
        foreach ([
            ['zcy_cgsq', 'project', '项目', ['sbrq' => '2026-11-08']],
            ['zcy_cgsq_qd', 'item', '设备', ['mc' => '设备', 'sl' => 2]],
            ['zcy_progress_history', 'event', '负责人审批', ['lcsm' => '负责人审批', 'zxr' => '张老师']],
        ] as [$table, $sourceId, $title, $details]) {
            DB::table('legacy_procurement_records')->insert([
                'source_table' => $table, 'source_id' => $sourceId,
                'application_source_id' => 'project', 'creator_account' => $user->school_account,
                'title' => $title, 'details' => json_encode($details), 'occurred_at' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::table('legacy_procurement_attachments')->insert([
            'source_table' => 't_s_attachment', 'source_id' => 'file',
            'owner_table' => 'zcy_cgsq', 'owner_source_id' => 'project',
            'application_source_id' => 'project', 'name' => '材料.pdf',
            'legacy_path' => '/private/old.pdf', 'migration_status' => 'pending_file',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->actingAs($user)->get(route('procurement.applications.index', ['legacy' => 'project']))
            ->assertInertia(fn ($page) => $page->component('procurement/LegacyDetail')
                ->has('records', 2)->has('attachments', 1)
                ->where('attachments.0.name', '材料.pdf')
                ->missing('attachments.0.legacy_path'));
    }

    public function test_history_is_read_only_and_scoped_to_the_user_department(): void
    {
        $now = now();
        $firstDepartment = DB::table('departments')->insertGetId(['name' => '第一部门', 'created_at' => $now, 'updated_at' => $now]);
        $secondDepartment = DB::table('departments')->insertGetId(['name' => '第二部门', 'created_at' => $now, 'updated_at' => $now]);
        $viewer = User::factory()->create(['department_id' => $firstDepartment]);
        foreach ([['one', $firstDepartment], ['two', $secondDepartment]] as [$sourceId, $departmentId]) {
            DB::table('legacy_procurement_records')->insert([
                'source_table' => 'zcy_cgsq', 'source_id' => $sourceId,
                'application_source_id' => $sourceId, 'department_id' => $departmentId,
                'title' => $sourceId, 'amount_cents' => 10000, 'occurred_at' => $now,
                'details' => '{}', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $this->actingAs($viewer)->get(route('procurement.legacy-history.index'))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->component('procurement/LegacyHistory')
            ->where('projects.total', 1)->where('summary.projects_count', 1));
        $this->actingAs($viewer)->get(route('procurement.legacy-history.show', 'one'))->assertSuccessful();
        $this->actingAs($viewer)->get(route('procurement.legacy-history.show', 'two'))->assertForbidden();
        $this->actingAs($viewer)->post(route('procurement.legacy-history.show', 'one'))->assertMethodNotAllowed();
    }
}
