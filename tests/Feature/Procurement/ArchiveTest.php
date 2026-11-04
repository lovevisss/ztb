<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchiveTest extends TestCase
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

    private function application(User $applicant, string $form = 'self_purchase', string $status = 'execution_completed'): int
    {
        $id = DB::table('procurement_applications')->insertGetId([
            'applicant_id' => $applicant->id,
            'department_id' => $applicant->department_id,
            'application_form' => $form,
            'title' => '归档采购项目',
            'budget_cents' => 90_000_00,
            'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('procurement_applications')->where('id', $id)->update(['project_number' => 'CG2026-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT)]);
        if ($status === 'execution_completed') {
            DB::table('procurement_executions')->insert([
                'application_id' => $id, 'actual_amount_cents' => 80_000_00,
                'supplier' => '示例供应商', 'completed_on' => '2026-11-04',
                'result_note' => '已验收', 'status' => 'completed',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $id;
    }

    private function attachment(int $applicationId, User $user, string $kind): int
    {
        $path = "procurement/{$applicationId}/{$kind}.pdf";
        Storage::disk('local')->put($path, 'sample archive content');

        return DB::table('procurement_attachments')->insertGetId([
            'application_id' => $applicationId, 'uploaded_by' => $user->id,
            'kind' => $kind, 'original_name' => "{$kind}.pdf", 'disk' => 'local',
            'path' => $path, 'mime_type' => 'application/pdf', 'size' => 22,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_missing_required_material_and_unfinished_execution_block_archive(): void
    {
        Storage::fake('local');
        $applicant = $this->user('信息中心');
        $unfinished = $this->application($applicant, status: 'executing');
        $completed = $this->application($applicant);

        $this->actingAs($applicant)->post(route('procurement.archives.store', $unfinished))->assertForbidden();
        $this->actingAs($applicant)->post(route('procurement.archives.store', $completed))
            ->assertSessionHasErrors('materials');
        $this->assertDatabaseCount('procurement_archives', 0);
        $this->assertDatabaseHas('procurement_applications', ['id' => $completed, 'status' => 'execution_completed']);
    }

    public function test_complete_archive_is_read_only_and_preserves_history_and_private_attachments(): void
    {
        Storage::fake('local');
        $applicant = $this->user('信息中心');
        $outsider = $this->user('后勤处');
        $id = $this->application($applicant);
        $fileIds = [];
        foreach (['contract', 'result_announcement', 'acceptance', 'invoice'] as $kind) {
            $fileIds[] = $this->attachment($id, $applicant, $kind);
        }
        $this->actingAs($outsider)->post(route('procurement.archives.store', $id))->assertForbidden();
        DB::table('approval_events')->insert([
            'application_id' => $id, 'step_id' => null, 'actor_id' => $applicant->id,
            'event' => 'submitted', 'details' => '原始审批记录', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($applicant)->post(route('procurement.archives.store', $id))->assertRedirect();
        $this->assertDatabaseHas('procurement_applications', ['id' => $id, 'status' => 'archived']);
        $this->assertDatabaseHas('procurement_archives', ['application_id' => $id, 'archived_by' => $applicant->id]);
        $this->actingAs($applicant)->get(route('procurement.archives.show', $id))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->component('procurement/ArchiveDetail')
            ->where('application.status', 'archived')->where('approvalEvents.0.details', '原始审批记录')
            ->has('attachments', 4));
        $this->actingAs($applicant)->get(route('procurement.attachments.download', $fileIds[0]))->assertSuccessful();
        $this->actingAs($outsider)->get(route('procurement.attachments.download', $fileIds[0]))->assertForbidden();
        $this->actingAs($outsider)->get(route('procurement.archives.show', $id))->assertForbidden();
        $this->actingAs($applicant)->post(route('procurement.archives.store', $id))->assertForbidden();
        $this->actingAs($applicant)->put(route('procurement.executions.save', $id), ['intent' => 'draft'])->assertForbidden();
        $this->assertDatabaseHas('approval_events', ['application_id' => $id, 'details' => '原始审批记录']);
    }

    public function test_admin_can_configure_distinct_required_materials_and_archive_keeps_rule_snapshot(): void
    {
        Storage::fake('local');
        $admin = $this->user('采购处', 'system_admin');
        $applicant = $this->user('信息中心');
        $selfId = $this->application($applicant);
        $tenderId = $this->application($applicant, 'central_tender');

        $this->actingAs($applicant)->put(route('procurement.archive-rules.update'), [
            'application_form' => 'self_purchase', 'required_kinds' => ['contract'],
        ])->assertForbidden();
        $manager = $this->user('资产处', 'procurement_manager');
        $this->actingAs($manager)->put(route('procurement.archive-rules.update'), [
            'application_form' => 'self_purchase', 'required_kinds' => [],
        ])->assertForbidden();
        $this->actingAs($admin)->put(route('procurement.archive-rules.update'), [
            'application_form' => 'self_purchase', 'required_kinds' => ['contract'],
        ])->assertRedirect();
        $this->actingAs($admin)->put(route('procurement.archive-rules.update'), [
            'application_form' => 'central_tender', 'required_kinds' => ['contract', 'result_announcement'],
        ])->assertRedirect();
        $this->actingAs($admin)->get(route('procurement.archive-rules.index'))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->component('procurement/ArchiveRules')->has('rules'));

        $this->attachment($selfId, $applicant, 'contract');
        $this->attachment($tenderId, $applicant, 'contract');
        $this->actingAs($applicant)->post(route('procurement.archives.store', $selfId))->assertRedirect();
        $this->actingAs($applicant)->post(route('procurement.archives.store', $tenderId))->assertSessionHasErrors('materials');
        $this->attachment($tenderId, $applicant, 'result_announcement');
        $this->actingAs($applicant)->post(route('procurement.archives.store', $tenderId))->assertRedirect();

        $this->actingAs($admin)->put(route('procurement.archive-rules.update'), [
            'application_form' => 'self_purchase', 'required_kinds' => ['contract', 'invoice'],
        ])->assertRedirect();
        $archive = DB::table('procurement_archives')->where('application_id', $selfId)->first();
        $this->assertSame(['contract'], json_decode($archive->required_kinds_snapshot, true));
    }

    public function test_archived_projects_are_searchable_by_status(): void
    {
        Storage::fake('local');
        $applicant = $this->user('信息中心');
        $id = $this->application($applicant);
        foreach (['contract', 'result_announcement', 'acceptance', 'invoice'] as $kind) {
            $this->attachment($id, $applicant, $kind);
        }
        $this->actingAs($applicant)->post(route('procurement.archives.store', $id))->assertRedirect();
        $this->actingAs($applicant)->get(route('procurement.history.index', ['status' => 'archived']))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->where('projects.total', 1)
            ->where('projects.data.0.id', $id));
    }

    public function test_missing_private_file_blocks_archive_even_when_attachment_record_exists(): void
    {
        Storage::fake('local');
        $applicant = $this->user('信息中心');
        $id = $this->application($applicant);
        foreach (['contract', 'result_announcement', 'acceptance', 'invoice'] as $kind) {
            $this->attachment($id, $applicant, $kind);
        }
        Storage::disk('local')->delete("procurement/{$id}/contract.pdf");

        $this->actingAs($applicant)->post(route('procurement.archives.store', $id))
            ->assertSessionHasErrors('materials');
        $this->assertDatabaseCount('procurement_archives', 0);
    }
}
