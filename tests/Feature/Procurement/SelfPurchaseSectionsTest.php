<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SelfPurchaseSectionsTest extends TestCase
{
    use RefreshDatabase;

    private function department(string $name): int
    {
        return DB::table('departments')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function assign(User $user, string $role): void
    {
        DB::table('role_user')->insert(['user_id' => $user->id, 'role_id' => DB::table('roles')->where('code', $role)->value('id')]);
    }

    private function setupPeople(): array
    {
        $department = $this->department('会计学院');
        $responsible = $this->department('信息技术部、实验室建设管理办公室');
        $applicant = User::factory()->create(['department_id' => $department]);
        $head = User::factory()->create(['department_id' => $department]);
        $responsibleHead = User::factory()->create(['department_id' => $responsible]);
        $manager = User::factory()->create(['department_id' => $department]);
        $finance = User::factory()->create(['department_id' => $department]);
        $assetManager = User::factory()->create(['department_id' => $department]);
        $assetHead = User::factory()->create(['department_id' => $department]);
        $leader = User::factory()->create(['department_id' => $department]);
        foreach (compact('head', 'responsibleHead', 'manager', 'finance', 'assetManager', 'assetHead') as $name => $person) {
            $this->assign($person, match ($name) {
                'head', 'responsibleHead' => 'department_head',
                'manager' => 'procurement_manager',
                'finance' => 'finance',
                'assetManager' => 'asset_manager',
                'assetHead' => 'asset_head',
            });
        }
        DB::table('procurement_department_approvers')->insert([
            'department_id' => $department, 'supervising_leader_id' => $leader->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $indicator = DB::table('budget_indicators')->insertGetId([
            'source_id' => 'self-test', 'source' => 'school_source', 'code' => 'S001', 'name' => '测试经费',
            'status' => '1', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact('department', 'responsible', 'applicant', 'head', 'responsibleHead', 'manager', 'finance', 'assetManager', 'assetHead', 'leader', 'indicator');
    }

    private function draft(array $people, int $price, bool $furniture = false, bool $equipment = false, bool $hasResponsible = false): int
    {
        $this->actingAs($people['applicant'])->post(route('procurement.applications.sections.create'), [
            'section' => 'basic', 'title' => '设备采购', 'category' => '货物类', 'contact_phone' => '12345678901',
            'purchase_method' => '询价', 'is_special_purchase' => false, 'is_non_expenditure' => false,
            'has_usage_department' => false, 'procurement_requirements' => '用于教学',
            'responsible_department_id' => $hasResponsible ? $people['responsible'] : $people['department'],
            'reason_category' => '原设备损坏或已无法满足现有需求，需重新采购',
            'has_furniture' => $furniture, 'has_equipment_asset' => $equipment,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $id = (int) DB::table('procurement_applications')->max('id');
        $this->actingAs($people['applicant'])->post(route('procurement.applications.sections.update', $id), [
            'section' => 'items', 'items' => [['name' => '采购品', 'quantity' => 1, 'unit_price' => $price]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($people['applicant'])->post(route('procurement.applications.sections.update', $id), [
            'section' => 'budget', 'allocations' => [['budget_indicator_id' => $people['indicator'], 'amount' => $price]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        return $id;
    }

    public function test_policy_and_form_use_same_sections_and_mapped_department(): void
    {
        $people = $this->setupPeople();
        $this->actingAs($people['applicant'])->get(route('procurement.applications.create'))
            ->assertInertia(fn ($page) => $page->component('procurement/SelfPurchasePolicy'));
        $this->actingAs($people['applicant'])->get(route('procurement.applications.new-form'))
            ->assertInertia(fn ($page) => $page->component('procurement/TenderForm')->where('selfPurchase', true)
                ->where('responsibleDepartments.2.id', $people['responsible']));
    }

    public function test_asset_purchase_adds_asset_and_responsible_approvers(): void
    {
        $people = $this->setupPeople();
        $id = $this->draft($people, 1001, false, true, true);
        $this->actingAs($people['applicant'])->post(route('procurement.applications.sections.submit', $id))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(
            [$people['head']->id, $people['responsibleHead']->id, $people['assetManager']->id, $people['assetHead']->id, $people['manager']->id, $people['finance']->id, $people['leader']->id],
            DB::table('approval_steps')->where('application_id', $id)->orderBy('position')->pluck('assignee_id')->all(),
        );
        $this->assertDatabaseHas('procurement_applications', ['id' => $id, 'purchase_type' => 'self_purchase', 'classification_threshold_cents' => 10_000_000]);
    }

    public function test_small_amount_and_method_boundaries_are_enforced(): void
    {
        $people = $this->setupPeople();
        $ordinary = $this->draft($people, 9999);
        $this->actingAs($people['applicant'])->post(route('procurement.applications.sections.submit', $ordinary))
            ->assertSessionHasErrors('items');
        $furniture = $this->draft($people, 200, true);
        $this->actingAs($people['applicant'])->post(route('procurement.applications.sections.submit', $furniture))
            ->assertSessionHasErrors('items');
        $this->actingAs($people['applicant'])->post(route('procurement.applications.sections.update', $ordinary), [
            'section' => 'basic', 'title' => '无效方式', 'category' => '货物类', 'contact_phone' => '123',
            'purchase_method' => '公开招标', 'is_special_purchase' => false, 'is_non_expenditure' => false,
            'has_usage_department' => false, 'responsible_department_id' => $people['department'],
            'reason_category' => '上述都不是，请自行列明', 'reason' => '项目需要', 'has_furniture' => false, 'has_equipment_asset' => false,
        ])->assertSessionHasErrors('purchase_method');
    }

    public function test_ten_thousand_is_allowed_without_assets_and_hundred_thousand_is_redirected(): void
    {
        $people = $this->setupPeople();
        $allowed = $this->draft($people, 10000);
        $this->actingAs($people['applicant'])->post(route('procurement.applications.sections.submit', $allowed))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(
            [$people['head']->id, $people['manager']->id, $people['finance']->id, $people['leader']->id],
            DB::table('approval_steps')->where('application_id', $allowed)->orderBy('position')->pluck('assignee_id')->all(),
        );
        $tooLarge = $this->draft($people, 100000);
        $this->actingAs($people['applicant'])->post(route('procurement.applications.sections.submit', $tooLarge))
            ->assertSessionHasErrors('items');
        $this->assertDatabaseHas('procurement_applications', ['id' => $tooLarge, 'status' => 'draft']);
    }

    public function test_furniture_threshold_and_missing_approver_are_checked(): void
    {
        $people = $this->setupPeople();
        $furniture = $this->draft($people, 201, true);
        DB::table('role_user')->where('user_id', $people['assetManager']->id)->delete();
        $this->actingAs($people['applicant'])->post(route('procurement.applications.sections.submit', $furniture))
            ->assertSessionHasErrors('approvers');
        $this->assign($people['assetManager'], 'asset_manager');
        $this->actingAs($people['applicant'])->post(route('procurement.applications.sections.submit', $furniture))
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_other_users_and_tender_routes_cannot_change_self_purchase_draft(): void
    {
        $people = $this->setupPeople();
        $id = $this->draft($people, 10000);
        $other = User::factory()->create(['department_id' => $people['department']]);
        $this->actingAs($other)->post(route('procurement.applications.sections.update', $id), [
            'section' => 'items', 'items' => [],
        ])->assertForbidden();
        $this->actingAs($people['applicant'])->post(route('procurement.tenders.sections.update', $id), [
            'section' => 'items', 'items' => [],
        ])->assertForbidden();
        $this->actingAs($people['applicant'])->post(route('procurement.applications.sections.submit', $id))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($people['applicant'])->post(route('procurement.applications.sections.update', $id), [
            'section' => 'items', 'items' => [],
        ])->assertForbidden();
    }
}
