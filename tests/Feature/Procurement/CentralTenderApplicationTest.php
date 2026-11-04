<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CentralTenderApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function applicant(): User
    {
        $departmentId = DB::table('departments')->insertGetId(['name' => '信息中心', 'created_at' => now(), 'updated_at' => now()]);

        $applicant = User::factory()->create(['department_id' => $departmentId]);
        foreach (['department_head', 'procurement_manager'] as $role) {
            $approver = User::factory()->create(['department_id' => $departmentId]);
            DB::table('role_user')->insert([
                'user_id' => $approver->id,
                'role_id' => DB::table('roles')->where('code', $role)->value('id'),
            ]);
        }

        return $applicant;
    }

    private function tenderFields(int $budget = 100000): array
    {
        return [
            'title' => '智慧教室设备采购',
            'project_leader' => '张老师',
            'budget' => $budget,
            'funding_source' => '教学经费',
            'organization_form' => '学校统一组织',
            'purchase_method' => '公开招标',
            'responsible_department' => '教务处',
            'procurement_requirements' => '建设两间智慧教室',
            'purchase_list' => '交互屏 2 台、录播设备 2 套',
            'technical_parameters' => '交互屏不低于 86 英寸',
            'service_requirements' => '三年质保',
            'acceptance_criteria' => '按采购清单逐项验收',
            'budget_basis_note' => '依据市场询价测算',
        ];
    }

    public function test_tender_submission_requires_detailed_fields_and_required_documents(): void
    {
        $this->actingAs($this->applicant())->post(route('procurement.tenders.store'), [
            'intent' => 'submit', 'title' => '材料不足的项目', 'budget' => 100000,
        ])->assertSessionHasErrors([
            'project_leader', 'funding_source', 'organization_form', 'purchase_method',
            'responsible_department', 'procurement_requirements', 'purchase_list',
            'technical_parameters', 'service_requirements', 'acceptance_criteria',
            'budget_basis_note',
        ]);

        $this->actingAs($this->applicant())->post(route('procurement.tenders.store'), [
            ...$this->tenderFields(), 'intent' => 'submit',
        ])->assertSessionHasErrors(['demand_document', 'budget_basis_document']);

        $this->assertDatabaseCount('procurement_applications', 0);
    }

    public function test_tender_submission_saves_details_and_private_attachments(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant)->post(route('procurement.tenders.store'), [
            ...$this->tenderFields(),
            'intent' => 'submit',
            'demand_document' => UploadedFile::fake()->create('需求书.pdf', 12, 'application/pdf'),
            'budget_basis_document' => UploadedFile::fake()->create('预算依据.pdf', 10, 'application/pdf'),
            'other_documents' => [UploadedFile::fake()->create('补充材料.pdf', 8, 'application/pdf')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $application = DB::table('procurement_applications')->first();
        $this->assertSame('central_tender', $application->application_form);
        $this->assertSame('central_tender', $application->purchase_type);
        $this->assertSame('submitted', $application->status);
        $this->assertSame('张老师', $application->project_leader);
        $this->assertSame('交互屏不低于 86 英寸', $application->technical_parameters);
        $this->assertNotNull($application->project_number);
        $this->assertDatabaseCount('procurement_attachments', 3);

        foreach (DB::table('procurement_attachments')->get() as $attachment) {
            $this->assertSame($application->id, $attachment->application_id);
            Storage::disk('local')->assertExists($attachment->path);
        }
    }

    public function test_budget_100001_is_also_classified_as_central_tender(): void
    {
        $this->actingAs($this->applicant())->post(route('procurement.tenders.store'), [
            ...$this->tenderFields(100001),
            'intent' => 'submit',
            'demand_document' => UploadedFile::fake()->create('需求书.pdf', 12, 'application/pdf'),
            'budget_basis_document' => UploadedFile::fake()->create('预算依据.pdf', 10, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('procurement_applications', [
            'budget_cents' => 10_000_100,
            'purchase_type' => 'central_tender',
        ]);
    }

    public function test_tender_draft_can_keep_first_document_and_submit_after_completion(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant)->post(route('procurement.tenders.store'), [
            'intent' => 'draft',
            'title' => '待完善的统一招标',
            'demand_document' => UploadedFile::fake()->create('需求书.pdf', 12, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $application = DB::table('procurement_applications')->first();
        $this->assertSame('draft', $application->status);
        $this->assertDatabaseCount('procurement_attachments', 1);

        $this->actingAs($applicant)->get(route('procurement.tenders.edit', $application->id))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->component('procurement/TenderForm')->has('attachments', 1));

        $this->actingAs($applicant)->put(route('procurement.tenders.update', $application->id), [
            ...$this->tenderFields(),
            'intent' => 'submit',
            'budget_basis_document' => UploadedFile::fake()->create('预算依据.pdf', 10, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('procurement_applications', ['id' => $application->id, 'status' => 'submitted']);
        $this->assertDatabaseCount('procurement_attachments', 2);
    }

    public function test_forms_reject_a_budget_belonging_to_the_other_purchase_type(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant)->post(route('procurement.applications.store'), [
            'intent' => 'submit', 'title' => '错误入口', 'budget' => 100000,
            'funding_source' => '教学经费', 'organization_form' => '部门自行组织',
            'purchase_method' => '询价', 'reason' => '教学需要',
            'responsible_department' => '教务处', 'is_fixed_asset' => false,
        ])->assertSessionHasErrors('budget');

        $this->actingAs($applicant)->post(route('procurement.tenders.store'), [
            ...$this->tenderFields(99999), 'intent' => 'submit',
            'demand_document' => UploadedFile::fake()->create('需求书.pdf', 12, 'application/pdf'),
            'budget_basis_document' => UploadedFile::fake()->create('预算依据.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('budget');

        $this->assertDatabaseCount('procurement_applications', 0);
    }

    public function test_only_applicant_can_download_attachments_and_other_users_cannot_edit_draft(): void
    {
        $applicant = $this->applicant();
        $other = User::factory()->create(['department_id' => $applicant->department_id]);

        $this->actingAs($applicant)->post(route('procurement.tenders.store'), [
            'intent' => 'draft',
            'demand_document' => UploadedFile::fake()->create('需求书.pdf', 12, 'application/pdf'),
        ])->assertRedirect();
        $applicationId = DB::table('procurement_applications')->value('id');
        $attachmentId = DB::table('procurement_attachments')->value('id');

        $this->actingAs($applicant)->get(route('procurement.attachments.download', $attachmentId))->assertOk();
        $this->actingAs($other)->get(route('procurement.attachments.download', $attachmentId))->assertForbidden();
        $this->actingAs($other)->put(route('procurement.tenders.update', $applicationId), ['intent' => 'draft', 'title' => '越权修改'])->assertForbidden();
    }

    public function test_executable_attachment_is_rejected_without_creating_a_project(): void
    {
        $this->actingAs($this->applicant())->post(route('procurement.tenders.store'), [
            'intent' => 'draft',
            'title' => '恶意附件',
            'demand_document' => UploadedFile::fake()->create('payload.php', 5, 'text/x-php'),
        ])->assertSessionHasErrors('demand_document');

        $this->assertDatabaseCount('procurement_applications', 0);
    }
}
