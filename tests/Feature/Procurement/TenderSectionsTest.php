<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Services\TenderNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenderSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $department = '信息中心', bool $head = false): User
    {
        $id = DB::table('departments')->insertGetId(['name' => $department, 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['department_id' => $id]);
        if ($head) {
            DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', 'department_head')->value('id')]);
        }

        return $user;
    }

    public function test_attachment_save_adds_files_and_deletes_only_own_ordinary_attachments(): void
    {
        Storage::fake('local');
        $applicant = $this->user();
        $outsider = $this->user('教务处');
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.create'), [
            'section' => 'attachments', 'files' => [UploadedFile::fake()->create('旧附件.pdf', 8, 'application/pdf')],
        ])->assertRedirect();
        $applicationId = DB::table('procurement_applications')->value('id');
        $oldFile = DB::table('procurement_attachments')->first();

        $this->actingAs($outsider)->post(route('procurement.tenders.sections.update', $applicationId), [
            'section' => 'attachments', 'delete_ids' => [$oldFile->id],
        ])->assertForbidden();
        Storage::disk('local')->assertExists($oldFile->path);

        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $applicationId), [
            'section' => 'attachments', 'delete_ids' => [$oldFile->id],
            'files' => [UploadedFile::fake()->create('新附件.pdf', 8, 'application/pdf')],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('procurement_attachments', ['id' => $oldFile->id]);
        Storage::disk('local')->assertMissing($oldFile->path);
        $newFile = DB::table('procurement_attachments')->where('application_id', $applicationId)->first();
        $this->assertSame('新附件.pdf', $newFile->original_name);
        Storage::disk('local')->assertExists($newFile->path);

        $this->actingAs($applicant)->post(route('procurement.tenders.sections.create'), [
            'section' => 'attachments', 'files' => [UploadedFile::fake()->create('其他项目.pdf', 8, 'application/pdf')],
        ])->assertRedirect();
        $otherFile = DB::table('procurement_attachments')->where('application_id', '!=', $applicationId)->first();
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $applicationId), [
            'section' => 'attachments', 'delete_ids' => [$otherFile->id],
        ])->assertSessionHasErrors('delete_ids.0');
        Storage::disk('local')->assertExists($otherFile->path);

        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $applicationId), [
            'section' => 'engineering', 'category' => '工程类',
            'bill_file' => UploadedFile::fake()->create('工程清单.pdf', 8, 'application/pdf'),
        ])->assertRedirect();
        $bill = DB::table('procurement_attachments')->where('application_id', $applicationId)->where('kind', 'engineering_bill')->first();
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $applicationId), [
            'section' => 'attachments', 'delete_ids' => [$bill->id],
        ])->assertSessionHasErrors('delete_ids.0');
        Storage::disk('local')->assertExists($bill->path);

        DB::table('procurement_applications')->where('id', $applicationId)->update(['status' => 'submitted']);
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $applicationId), [
            'section' => 'attachments', 'delete_ids' => [$newFile->id],
        ])->assertForbidden();
        Storage::disk('local')->assertExists($newFile->path);
    }

    public function test_first_section_save_allocates_daily_number_and_other_sections_remain_independent(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('procurement.tenders.sections.create'), [
            'section' => 'basic', 'title' => '设备采购', 'category' => '货物类',
            'contact_phone' => '12345678901', 'is_special_purchase' => false,
            'is_non_expenditure' => false, 'has_usage_department' => false,
            'procurement_requirements' => '购置设备',
        ])->assertRedirect();
        $first = DB::table('procurement_applications')->first();
        $this->assertSame('CGSQ-'.now()->format('Ymd').'-0001', $first->project_number);
        $this->assertSame('设备采购', $first->title);
        $this->actingAs($user)->post(route('procurement.tenders.sections.update', $first->id), [
            'section' => 'items', 'items' => [['name' => '屏幕', 'quantity' => 2, 'unit_price' => 50000, 'unit' => '台']],
        ])->assertRedirect();
        $this->assertDatabaseHas('procurement_applications', ['id' => $first->id, 'title' => '设备采购', 'budget_cents' => 10_000_000]);
        $this->assertDatabaseHas('procurement_line_items', ['application_id' => $first->id, 'total_cents' => 10_000_000]);
        $this->actingAs($user)->post(route('procurement.tenders.sections.create'), [
            'section' => 'items', 'items' => [],
        ])->assertRedirect();
        $this->assertDatabaseHas('procurement_applications', ['project_number' => 'CGSQ-'.now()->format('Ymd').'-0002']);
    }

    public function test_personal_catalog_isolated_and_application_items_are_snapshots(): void
    {
        $alice = $this->user();
        $bob = $this->user('教务处');
        $this->actingAs($alice)->post(route('procurement.catalog.store'), ['name' => '电脑', 'unit_price' => 3000])->assertRedirect();
        $catalogId = DB::table('personal_catalog_items')->value('id');
        $this->actingAs($bob)->delete(route('procurement.catalog.delete', $catalogId))->assertNotFound();
        $this->actingAs($bob)->put(route('procurement.catalog.update', $catalogId), ['name' => '篡改', 'unit_price' => 1])->assertNotFound();
        $this->actingAs($bob)->get(route('procurement.tenders.new-form'))
            ->assertInertia(fn ($page) => $page->component('procurement/TenderForm')->has('catalog', 0));
        $this->actingAs($alice)->get(route('procurement.tenders.new-form'))
            ->assertInertia(fn ($page) => $page->component('procurement/TenderForm')->has('catalog', 1));
    }

    public function test_budget_indicator_search_filters_all_synced_records_and_paginates(): void
    {
        $user = $this->user();
        DB::table('departments')->where('id', $user->department_id)->update(['external_code' => 'IT']);
        DB::table('budget_indicators')->insert([
            'source_id' => 'old-1', 'code' => 'YS001', 'name' => '旧库项目',
            'status' => '1', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (range(1, 27) as $index) {
            DB::table('budget_indicators')->insert([
                'source_id' => 'school_source:YS'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'source' => 'school_source',
                'code' => 'YS'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'name' => $index === 27 ? '实验室建设' : '教学经费'.$index,
                'director' => '张老师', 'department_code' => $index === 27 ? 'OTHER' : 'IT',
                'department_name' => $index === 27 ? '其他部门' : '源表信息部',
                'status' => $index === 27 ? '0' : '1',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->actingAs($user)->getJson(route('procurement.budgets.index'))
            ->assertOk()->assertJsonPath('total', 27)->assertJsonPath('source_total', 27)->assertJsonPath('last_page', 2)->assertJsonCount(25, 'data');
        $this->actingAs($user)->getJson(route('procurement.budgets.index', ['q' => '旧库项目']))
            ->assertOk()->assertJsonPath('total', 0);
        $this->actingAs($user)->getJson(route('procurement.budgets.index', ['q' => '实验室']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.status', '0');
        $this->actingAs($user)->getJson(route('procurement.budgets.index', ['q' => 'YS027']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.name', '实验室建设');
        $this->actingAs($user)->getJson(route('procurement.budgets.index', ['department' => 'IT', 'status' => '1']))
            ->assertOk()->assertJsonPath('total', 26)->assertJsonPath('data.0.department_name', '源表信息部');
        $this->actingAs($user)->getJson(route('procurement.budgets.index', ['department' => 'OTHER', 'status' => '0']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.department_code', 'OTHER');
    }

    public function test_inactive_budget_cannot_be_newly_selected_but_saved_snapshot_survives(): void
    {
        $user = $this->user();
        $indicator = DB::table('budget_indicators')->insertGetId([
            'source_id' => 'school_source:YS001', 'source' => 'school_source',
            'code' => 'YS001', 'name' => '旧经费', 'status' => '0',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $payload = ['section' => 'budget', 'allocations' => [['budget_indicator_id' => $indicator, 'amount' => 100000]]];
        $this->actingAs($user)->post(route('procurement.tenders.sections.create'), $payload)
            ->assertSessionHasErrors('allocations.0.budget_indicator_id');
        $this->assertDatabaseCount('procurement_applications', 0);

        DB::table('budget_indicators')->where('id', $indicator)->update(['status' => '1']);
        $this->actingAs($user)->post(route('procurement.tenders.sections.create'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $applicationId = DB::table('procurement_applications')->value('id');
        DB::table('budget_indicators')->where('id', $indicator)->update(['status' => '0', 'name' => '已改名经费']);
        $this->actingAs($user)->post(route('procurement.tenders.sections.update', $applicationId), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('procurement_budget_allocations', [
            'application_id' => $applicationId, 'budget_indicator_id' => $indicator,
            'code' => 'YS001', 'name' => '旧经费',
        ]);
        $this->actingAs($user)->get(route('procurement.tenders.edit', $applicationId))
            ->assertInertia(fn ($page) => $page->where('allocations.0.name', '旧经费'));

        $legacyId = DB::table('budget_indicators')->insertGetId([
            'source_id' => 'legacy-2', 'code' => 'YS002', 'name' => '旧库经费', 'status' => '1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($user)->post(route('procurement.tenders.sections.update', $applicationId), [
            'section' => 'budget', 'allocations' => [['budget_indicator_id' => $legacyId, 'amount' => 100000]],
        ])->assertSessionHasErrors('allocations.0.budget_indicator_id');
        DB::table('procurement_budget_allocations')->where('application_id', $applicationId)->update([
            'budget_indicator_id' => $legacyId, 'code' => 'YS002', 'name' => '旧库经费',
        ]);
        $this->actingAs($user)->post(route('procurement.tenders.sections.update', $applicationId), [
            'section' => 'budget', 'allocations' => [['budget_indicator_id' => $legacyId, 'amount' => 100000]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('procurement.tenders.edit', $applicationId))
            ->assertInertia(fn ($page) => $page->where('allocations.0.name', '旧库经费'));
    }

    public function test_number_sequence_resets_on_next_day(): void
    {
        $this->travelTo(now()->startOfDay());
        $first = DB::transaction(fn () => app(TenderNumber::class)->next());
        $second = DB::transaction(fn () => app(TenderNumber::class)->next());
        $this->assertSame('CGSQ-'.now()->format('Ymd').'-0001', $first);
        $this->assertSame('CGSQ-'.now()->format('Ymd').'-0002', $second);
        $this->travel(1)->days();
        $this->assertSame('CGSQ-'.now()->format('Ymd').'-0001', DB::transaction(fn () => app(TenderNumber::class)->next()));
    }

    public function test_purchase_method_is_fixed_for_normal_purchase_and_selected_for_special_purchase(): void
    {
        $user = $this->user();
        $base = [
            'section' => 'basic', 'title' => '设备采购', 'category' => '货物类',
            'is_non_expenditure' => false, 'has_usage_department' => false,
        ];
        $this->actingAs($user)->post(route('procurement.tenders.sections.create'), [
            ...$base, 'is_special_purchase' => false, 'purchase_method' => '单一来源',
            'special_purchase_reason' => '旧理由',
        ])->assertRedirect();
        $application = DB::table('procurement_applications')->first();
        $this->assertSame('公开招标', $application->purchase_method);
        $this->assertNull($application->special_purchase_reason);

        $this->actingAs($user)->post(route('procurement.tenders.sections.update', $application->id), [
            ...$base, 'is_special_purchase' => true, 'purchase_method' => '',
        ])->assertSessionHasErrors('purchase_method');
        $this->actingAs($user)->post(route('procurement.tenders.sections.update', $application->id), [
            ...$base, 'is_special_purchase' => true, 'purchase_method' => '公开招标',
        ])->assertSessionHasErrors('purchase_method');
        $this->actingAs($user)->post(route('procurement.tenders.sections.update', $application->id), [
            ...$base, 'is_special_purchase' => true, 'purchase_method' => '竞争性磋商',
            'special_purchase_reason' => '适合项目需求',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('procurement_applications', [
            'id' => $application->id, 'purchase_method' => '竞争性磋商',
            'special_purchase_reason' => '适合项目需求',
        ]);
    }

    public function test_new_form_prefills_synced_phone_but_draft_keeps_its_own_phone(): void
    {
        $user = $this->user();
        $user->update(['mobile_phone' => '13800138000']);
        $this->actingAs($user)->get(route('procurement.tenders.new-form'))
            ->assertInertia(fn ($page) => $page->component('procurement/TenderForm')->where('applicantPhone', '13800138000')->where('application', null));

        $this->actingAs($user)->post(route('procurement.tenders.sections.create'), [
            'section' => 'basic', 'title' => '采购项目', 'category' => '货物类', 'contact_phone' => '13700137000',
            'is_special_purchase' => false, 'is_non_expenditure' => false, 'has_usage_department' => false,
            'procurement_requirements' => '采购设备',
        ])->assertRedirect();
        $id = DB::table('procurement_applications')->value('id');
        $user->update(['mobile_phone' => '13900139000']);
        $this->actingAs($user)->get(route('procurement.tenders.edit', $id))
            ->assertInertia(fn ($page) => $page->component('procurement/TenderForm')
                ->where('applicantPhone', '13900139000')->where('application.contact_phone', '13700137000'));

        $user->update(['mobile_phone' => null]);
        $this->actingAs($user)->get(route('procurement.tenders.new-form'))
            ->assertInertia(fn ($page) => $page->where('applicantPhone', null));
    }

    public function test_submit_checks_totals_and_prepends_usage_department_head(): void
    {
        $applicant = $this->user();
        $applicantHead = User::factory()->create(['department_id' => $applicant->department_id]);
        $usageHead = $this->user('教务处', true);
        $manager = User::factory()->create(['department_id' => $applicant->department_id]);
        foreach ([[$applicantHead, 'department_head'], [$manager, 'procurement_manager']] as [$user, $role]) {
            DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', $role)->value('id')]);
        }
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.create'), [
            'section' => 'basic', 'title' => '设备采购', 'category' => '货物类', 'contact_phone' => '12345678901',
            'is_special_purchase' => false, 'is_non_expenditure' => false, 'has_usage_department' => true,
            'usage_department_id' => $usageHead->department_id, 'procurement_requirements' => '购置设备',
        ])->assertRedirect();
        $id = DB::table('procurement_applications')->value('id');
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $id), [
            'section' => 'items', 'items' => [['name' => '设备', 'quantity' => 1, 'unit_price' => 100000]],
        ])->assertRedirect();
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.submit', $id))->assertSessionHasErrors('budget');
        $indicator = DB::table('budget_indicators')->insertGetId([
            'source_id' => 'school_source:test', 'source' => 'school_source', 'name' => '教学经费', 'status' => '1', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $id), [
            'section' => 'budget', 'allocations' => [['budget_indicator_id' => $indicator, 'amount' => 100000]],
        ])->assertRedirect();
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.submit', $id))->assertRedirect()->assertSessionHasNoErrors();
        $steps = DB::table('approval_steps')->where('application_id', $id)->orderBy('position')->get();
        $this->assertCount(3, $steps);
        $this->assertSame($usageHead->id, $steps[0]->assignee_id);
        $this->assertSame($applicantHead->id, $steps[1]->assignee_id);

        $this->actingAs($usageHead)->post(route('procurement.approvals.decide', $steps[0]->id), [
            'action' => 'return', 'opinion' => '请修改清单',
        ])->assertRedirect();
        $this->actingAs($applicant)->get(route('procurement.tenders.edit', $id))->assertSuccessful();
        $this->actingAs($manager)->get(route('procurement.tenders.edit', $id))->assertForbidden();
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $id), [
            'section' => 'items', 'items' => [['name' => '修改后的设备', 'quantity' => 1, 'unit_price' => 100000]],
        ])->assertRedirect();
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.submit', $id))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('approval_steps', ['id' => $steps[0]->id, 'attempt' => 1, 'status' => 'returned', 'opinion' => '请修改清单']);
        $this->assertDatabaseHas('approval_steps', ['application_id' => $id, 'attempt' => 2, 'position' => 1, 'assignee_id' => $usageHead->id]);
        $this->assertDatabaseHas('procurement_line_items', ['application_id' => $id, 'name' => '修改后的设备']);
    }

    public function test_usage_department_without_head_blocks_submission(): void
    {
        $applicant = $this->user();
        foreach (['department_head', 'procurement_manager'] as $role) {
            $approver = User::factory()->create(['department_id' => $applicant->department_id]);
            DB::table('role_user')->insert(['user_id' => $approver->id, 'role_id' => DB::table('roles')->where('code', $role)->value('id')]);
        }
        $usageDepartment = $this->user('图书馆');
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.create'), [
            'section' => 'basic', 'title' => '设备采购', 'category' => '货物类', 'contact_phone' => '12345678901',
            'is_special_purchase' => false, 'is_non_expenditure' => false, 'has_usage_department' => true,
            'usage_department_id' => $usageDepartment->department_id, 'procurement_requirements' => '购置设备',
        ])->assertRedirect();
        $id = DB::table('procurement_applications')->value('id');
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $id), [
            'section' => 'items', 'items' => [['name' => '设备', 'quantity' => 1, 'unit_price' => 100000]],
        ])->assertRedirect();
        $indicator = DB::table('budget_indicators')->insertGetId(['source_id' => 'school_source:test', 'source' => 'school_source', 'name' => '经费', 'status' => '1', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $id), [
            'section' => 'budget', 'allocations' => [['budget_indicator_id' => $indicator, 'amount' => 100000]],
        ])->assertRedirect();
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.submit', $id))
            ->assertSessionHasErrors('usage_department_id');
        $this->assertDatabaseCount('approval_steps', 0);
    }

    public function test_engineering_draft_keeps_ordinary_items_and_uses_engineering_cost(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('procurement.tenders.sections.create'), [
            'section' => 'items', 'items' => [['name' => '电脑', 'quantity' => 1, 'unit_price' => 50000]],
        ])->assertRedirect();
        $id = DB::table('procurement_applications')->value('id');
        $this->actingAs($user)->post(route('procurement.tenders.sections.update', $id), [
            'section' => 'engineering', 'category' => '工程类', 'project_name' => '装修工程',
            'estimated_cost' => 100000,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('procurement_applications', ['id' => $id, 'category' => '工程类', 'budget_cents' => 10_000_000]);
        $this->assertDatabaseHas('procurement_line_items', ['application_id' => $id, 'name' => '电脑']);
        $this->actingAs($user)->get(route('procurement.tenders.edit', $id))
            ->assertInertia(fn ($page) => $page->component('procurement/TenderForm')->where('engineering.project_name', '装修工程')->has('items', 1));

        $this->actingAs($user)->post(route('procurement.tenders.sections.update', $id), [
            'section' => 'basic', 'category' => '货物类', 'is_special_purchase' => false,
            'is_non_expenditure' => false, 'has_usage_department' => false,
        ])->assertRedirect();
        $this->assertDatabaseHas('procurement_applications', ['id' => $id, 'category' => '货物类', 'budget_cents' => 5_000_000]);
        $this->assertDatabaseHas('procurement_engineering_details', ['application_id' => $id, 'project_name' => '装修工程']);
    }

    public function test_engineering_submission_requires_fields_and_bill_then_uses_its_cost(): void
    {
        Storage::fake('local');
        $applicant = $this->user();
        foreach (['department_head', 'procurement_manager'] as $role) {
            $approver = User::factory()->create(['department_id' => $applicant->department_id]);
            DB::table('role_user')->insert(['user_id' => $approver->id, 'role_id' => DB::table('roles')->where('code', $role)->value('id')]);
        }
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.create'), [
            'section' => 'basic', 'title' => '教学楼装修', 'category' => '工程类',
            'contact_phone' => '13800138000', 'procurement_requirements' => '装修教学楼',
            'is_special_purchase' => false, 'is_non_expenditure' => false, 'has_usage_department' => false,
        ])->assertRedirect();
        $id = DB::table('procurement_applications')->value('id');
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $id), [
            'section' => 'engineering', 'category' => '工程类', 'project_name' => '教学楼装修', 'estimated_cost' => 100000,
        ])->assertRedirect();
        $indicator = DB::table('budget_indicators')->insertGetId(['source_id' => 'school_source:engineering', 'source' => 'school_source', 'name' => '工程经费', 'status' => '1', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $id), [
            'section' => 'budget', 'allocations' => [['budget_indicator_id' => $indicator, 'amount' => 100000]],
        ])->assertRedirect();
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.submit', $id))
            ->assertSessionHasErrors(['construction_content', 'project_overview', 'schedule_requirements', 'bill_file']);
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $id), [
            'section' => 'engineering', 'category' => '工程类', 'project_name' => '教学楼装修',
            'construction_content' => '教室改造', 'project_overview' => '教学楼项目',
            'schedule_requirements' => '60天', 'estimated_cost' => 100000,
            'bill_file' => UploadedFile::fake()->create('工程清单.pdf', 10, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $oldPath = DB::table('procurement_attachments')->where('application_id', $id)->where('kind', 'engineering_bill')->value('path');
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $id), [
            'section' => 'engineering', 'category' => '工程类', 'project_name' => '教学楼装修',
            'construction_content' => '教室改造', 'project_overview' => '教学楼项目',
            'schedule_requirements' => '60天', 'estimated_cost' => 100000,
            'bill_file' => UploadedFile::fake()->create('更新清单.pdf', 12, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($oldPath);
        $this->assertDatabaseHas('procurement_attachments', ['application_id' => $id, 'kind' => 'engineering_bill', 'original_name' => '更新清单.pdf']);
        $this->assertSame(1, DB::table('procurement_attachments')->where('application_id', $id)->where('kind', 'engineering_bill')->count());
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.submit', $id))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('procurement_applications', ['id' => $id, 'status' => 'submitted', 'budget_cents' => 10_000_000]);
        $this->assertDatabaseHas('procurement_attachments', ['application_id' => $id, 'kind' => 'engineering_bill']);
        $this->actingAs($applicant)->get(route('procurement.applications.show', $id))
            ->assertInertia(fn ($page) => $page->component('procurement/ApprovalDetail')->where('engineering.project_name', '教学楼装修'));
    }
}
