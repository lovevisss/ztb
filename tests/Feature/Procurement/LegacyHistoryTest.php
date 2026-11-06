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
            ->has('applications', 1)->has('legacyApplications', 1)
            ->where('legacyApplications.0.source_id', 'old-mine'));
        $this->actingAs($user)->get(route('procurement.legacy-history.show', 'old-mine'))->assertSuccessful();
        $this->actingAs($user)->get(route('procurement.legacy-history.show', 'old-other'))->assertForbidden();
        $this->actingAs($other)->get(route('procurement.applications.index'))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->has('applications', 0)
            ->has('legacyApplications', 1)->where('legacyApplications.0.source_id', 'old-other'));
    }

    public function test_my_applications_can_be_empty_for_a_user_without_requests(): void
    {
        $this->actingAs(User::factory()->create(['school_account' => 'NO-REQUESTS']))
            ->get(route('procurement.applications.index'))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->has('applications', 0)
            ->has('legacyApplications', 0));
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
