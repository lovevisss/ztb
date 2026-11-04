<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SelfPurchaseApplicationTest extends TestCase
{
    use RefreshDatabase;

    private function applicant(): User
    {
        $departmentId = DB::table('departments')->insertGetId(['name' => '信息中心', 'created_at' => now(), 'updated_at' => now()]);

        $user = User::factory()->create(['department_id' => $departmentId]);

        foreach (['department_head', 'procurement_manager'] as $role) {
            $approver = User::factory()->create(['department_id' => $departmentId]);
            DB::table('role_user')->insert([
                'user_id' => $approver->id,
                'role_id' => DB::table('roles')->where('code', $role)->value('id'),
            ]);
        }

        return $user;
    }

    private function applicationFields(int $budget = 99999): array
    {
        return [
            'title' => '教学设备采购',
            'budget' => $budget,
            'funding_source' => '教学经费',
            'organization_form' => '部门自行组织',
            'purchase_method' => '询价',
            'reason' => '现有设备需要更新',
            'responsible_department' => '信息中心',
            'is_fixed_asset' => true,
        ];
    }

    public function test_applicant_can_save_and_edit_an_incomplete_draft(): void
    {
        $applicant = $this->applicant();

        $this->actingAs($applicant)->post(route('procurement.applications.store'), [
            'intent' => 'draft',
            'title' => '待完善项目',
        ])->assertRedirect();

        $application = DB::table('procurement_applications')->first();
        $this->assertSame('draft', $application->status);
        $this->assertNull($application->project_number);
        $this->assertSame($applicant->department_id, $application->department_id);

        $this->actingAs($applicant)->put(route('procurement.applications.update', $application->id), [
            'intent' => 'draft',
            'title' => '更新后的项目',
        ])->assertRedirect();

        $this->assertDatabaseHas('procurement_applications', ['id' => $application->id, 'title' => '更新后的项目']);
    }

    public function test_submission_requires_all_self_purchase_fields(): void
    {
        $this->actingAs($this->applicant())->post(route('procurement.applications.store'), [
            'intent' => 'submit',
            'title' => '缺少预算',
        ])->assertSessionHasErrors(['budget', 'funding_source', 'organization_form', 'purchase_method', 'reason', 'responsible_department', 'is_fixed_asset']);

        $this->assertDatabaseCount('procurement_applications', 0);
    }

    public function test_budget_boundary_classifies_submission_and_generates_unique_numbers(): void
    {
        $applicant = $this->applicant();

        foreach ([99998, 99999] as $budget) {
            $this->actingAs($applicant)->post(route('procurement.applications.store'), [
                ...$this->applicationFields($budget),
                'intent' => 'submit',
            ])->assertRedirect();

            $this->assertDatabaseHas('procurement_applications', [
                'budget_cents' => $budget * 100,
                'purchase_type' => 'self_purchase',
                'status' => 'submitted',
            ]);
        }

        $numbers = DB::table('procurement_applications')->pluck('project_number');
        $this->assertCount(2, $numbers->unique());
        $this->assertFalse($numbers->contains(null));
    }

    public function test_another_user_cannot_edit_a_draft(): void
    {
        $applicant = $this->applicant();
        $other = User::factory()->create(['department_id' => $applicant->department_id]);

        $this->actingAs($applicant)->post(route('procurement.applications.store'), ['intent' => 'draft', 'title' => '原申请']);
        $id = DB::table('procurement_applications')->value('id');

        $this->actingAs($other)->put(route('procurement.applications.update', $id), ['intent' => 'draft', 'title' => '越权修改'])->assertForbidden();
        $this->assertDatabaseHas('procurement_applications', ['id' => $id, 'title' => '原申请']);
    }

    public function test_submitted_application_cannot_be_edited_and_list_is_scoped_to_applicant(): void
    {
        $applicant = $this->applicant();
        $other = User::factory()->create(['department_id' => $applicant->department_id]);

        $this->actingAs($applicant)->post(route('procurement.applications.store'), [
            ...$this->applicationFields(), 'intent' => 'submit',
        ])->assertRedirect();
        $id = DB::table('procurement_applications')->value('id');

        $this->actingAs($applicant)->put(route('procurement.applications.update', $id), [
            ...$this->applicationFields(), 'title' => '非法修改', 'intent' => 'draft',
        ])->assertForbidden();

        $this->actingAs($other)->get(route('procurement.applications.index'))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->component('procurement/Index')->has('applications', 0));
    }

    public function test_fixed_asset_can_be_explicitly_false(): void
    {
        $this->actingAs($this->applicant())->post(route('procurement.applications.store'), [
            ...$this->applicationFields(), 'is_fixed_asset' => false, 'intent' => 'submit',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('procurement_applications', ['is_fixed_asset' => false, 'status' => 'submitted']);
    }
}
