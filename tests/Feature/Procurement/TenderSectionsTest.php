<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use App\Services\TenderNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            'source_id' => 'test', 'name' => '教学经费', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $id), [
            'section' => 'budget', 'allocations' => [['budget_indicator_id' => $indicator, 'amount' => 100000]],
        ])->assertRedirect();
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.submit', $id))->assertRedirect()->assertSessionHasNoErrors();
        $steps = DB::table('approval_steps')->where('application_id', $id)->orderBy('position')->get();
        $this->assertCount(3, $steps);
        $this->assertSame($usageHead->id, $steps[0]->assignee_id);
        $this->assertSame($applicantHead->id, $steps[1]->assignee_id);
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
        $indicator = DB::table('budget_indicators')->insertGetId(['source_id' => 'test', 'name' => '经费', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.update', $id), [
            'section' => 'budget', 'allocations' => [['budget_indicator_id' => $indicator, 'amount' => 100000]],
        ])->assertRedirect();
        $this->actingAs($applicant)->post(route('procurement.tenders.sections.submit', $id))
            ->assertSessionHasErrors('usage_department_id');
        $this->assertDatabaseCount('approval_steps', 0);
    }
}
