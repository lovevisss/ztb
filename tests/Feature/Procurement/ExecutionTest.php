<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

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

    private function application(User $applicant, string $status = 'approved'): int
    {
        $id = DB::table('procurement_applications')->insertGetId([
            'applicant_id' => $applicant->id,
            'department_id' => $applicant->department_id,
            'application_form' => 'self_purchase',
            'title' => '设备采购',
            'budget_cents' => 9_000_000,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('procurement_applications')->where('id', $id)->update(['project_number' => 'CG2026-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT)]);

        return $id;
    }

    private function completeFields(): array
    {
        return [
            'intent' => 'complete',
            'actual_amount' => 120000,
            'supplier' => '示例设备有限公司',
            'completed_on' => '2026-11-04',
            'result_note' => '已完成采购并验收',
        ];
    }

    public function test_applicant_completes_execution_with_actual_amount_supplier_date_and_four_document_types(): void
    {
        $applicant = $this->user($this->department('信息中心'));
        $id = $this->application($applicant);

        $this->actingAs($applicant)->post(route('procurement.executions.save', $id), [
            '_method' => 'put',
            ...$this->completeFields(),
            'contract_document' => UploadedFile::fake()->create('合同.pdf', 10, 'application/pdf'),
            'result_announcement_document' => UploadedFile::fake()->create('结果公告.pdf', 10, 'application/pdf'),
            'acceptance_document' => UploadedFile::fake()->create('验收单.pdf', 10, 'application/pdf'),
            'invoice_document' => UploadedFile::fake()->create('发票.pdf', 10, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('procurement_executions', [
            'application_id' => $id, 'actual_amount_cents' => 12_000_000,
            'supplier' => '示例设备有限公司', 'completed_on' => '2026-11-04',
            'result_note' => '已完成采购并验收', 'status' => 'completed',
        ]);
        $this->assertDatabaseHas('procurement_applications', ['id' => $id, 'status' => 'execution_completed']);

        foreach (['contract', 'result_announcement', 'acceptance', 'invoice'] as $kind) {
            $attachment = DB::table('procurement_attachments')->where('application_id', $id)->where('kind', $kind)->first();
            $this->assertNotNull($attachment);
            Storage::disk('local')->assertExists($attachment->path);
        }
    }

    public function test_partial_execution_can_be_saved_then_completed_without_duplicate_record(): void
    {
        $applicant = $this->user($this->department('信息中心'));
        $id = $this->application($applicant);

        $this->actingAs($applicant)->put(route('procurement.executions.save', $id), [
            'intent' => 'draft', 'supplier' => '初选供应商',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('procurement_applications', ['id' => $id, 'status' => 'executing']);
        $this->assertDatabaseHas('procurement_executions', ['application_id' => $id, 'supplier' => '初选供应商', 'status' => 'draft']);

        $this->actingAs($applicant)->get(route('procurement.executions.show', $id))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->component('procurement/ExecutionForm')->where('execution.supplier', '初选供应商'));

        $this->actingAs($applicant)->put(route('procurement.executions.save', $id), $this->completeFields())
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('procurement_executions', 1);
        $this->assertDatabaseHas('procurement_executions', ['application_id' => $id, 'status' => 'completed']);
    }

    public function test_completion_requires_result_fields_and_rejects_unsafe_files(): void
    {
        $applicant = $this->user($this->department('信息中心'));
        $id = $this->application($applicant);

        $this->actingAs($applicant)->put(route('procurement.executions.save', $id), [
            'intent' => 'complete', 'supplier' => '仅有供应商',
        ])->assertSessionHasErrors(['actual_amount', 'completed_on', 'result_note']);
        $this->actingAs($applicant)->put(route('procurement.executions.save', $id), [
            'intent' => 'draft',
            'contract_document' => UploadedFile::fake()->create('payload.php', 5, 'text/x-php'),
        ])->assertSessionHasErrors('contract_document');

        $this->assertDatabaseCount('procurement_executions', 0);
        $this->assertDatabaseCount('procurement_attachments', 0);
    }

    public function test_manager_can_assign_executor_who_can_edit_and_view_from_other_department(): void
    {
        $applicant = $this->user($this->department('信息中心'));
        $manager = $this->user($this->department('采购处'), 'procurement_manager');
        $executor = $this->user($this->department('后勤处'));
        $id = $this->application($applicant);

        $this->actingAs($applicant)->put(route('procurement.executions.assign', $id), ['executor_id' => $executor->id])->assertForbidden();
        $this->actingAs($manager)->put(route('procurement.executions.assign', $id), ['executor_id' => $executor->id])->assertRedirect();
        $this->assertDatabaseHas('procurement_applications', ['id' => $id, 'executor_id' => $executor->id]);

        $this->actingAs($executor)->get(route('procurement.executions.show', $id))->assertSuccessful();
        $this->actingAs($executor)->put(route('procurement.executions.save', $id), [
            'intent' => 'draft', 'supplier' => '执行人登记',
        ])->assertRedirect();
        $this->actingAs($executor)->get(route('procurement.applications.show', $id))->assertSuccessful();
    }

    public function test_inactive_person_cannot_be_assigned_as_executor(): void
    {
        $applicant = $this->user($this->department('信息中心'));
        $manager = $this->user($this->department('采购处'), 'procurement_manager');
        $executor = $this->user($this->department('后勤处'));
        $executor->update(['is_active' => false]);
        $id = $this->application($applicant);

        $this->actingAs($manager)->put(route('procurement.executions.assign', $id), [
            'executor_id' => $executor->id,
        ])->assertSessionHasErrors('executor_id');
        $this->assertDatabaseHas('procurement_applications', ['id' => $id, 'executor_id' => null]);
    }

    public function test_unapproved_or_archived_project_and_unassigned_user_cannot_be_modified(): void
    {
        $department = $this->department('信息中心');
        $applicant = $this->user($department);
        $colleague = $this->user($department);
        $submitted = $this->application($applicant, 'submitted');
        $approved = $this->application($applicant);
        $archived = $this->application($applicant, 'archived');

        $this->actingAs($applicant)->put(route('procurement.executions.save', $submitted), ['intent' => 'draft'])->assertForbidden();
        $this->actingAs($colleague)->put(route('procurement.executions.save', $approved), ['intent' => 'draft'])->assertForbidden();
        $this->actingAs($applicant)->put(route('procurement.executions.save', $archived), ['intent' => 'draft'])->assertForbidden();
    }
}
