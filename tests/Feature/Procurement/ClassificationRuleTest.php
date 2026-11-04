<?php

namespace Tests\Feature\Procurement;

use App\Models\ProcurementClassificationRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClassificationRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function userWithRole(?string $role = null, ?int $departmentId = null): User
    {
        $departmentId ??= DB::table('departments')->insertGetId([
            'name' => '申请部门', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create(['department_id' => $departmentId]);

        if ($role !== null) {
            $roleId = DB::table('roles')->where('code', $role)->value('id');
            DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $user->id]);
        }

        return $user;
    }

    private function submit(User $user, int $budget): int
    {
        $headRoleId = DB::table('roles')->where('code', 'department_head')->value('id');
        $hasHead = DB::table('users')->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->where('users.department_id', $user->department_id)->where('role_user.role_id', $headRoleId)->exists();
        if (! $hasHead) {
            $head = User::factory()->create(['department_id' => $user->department_id]);
            DB::table('role_user')->insert(['user_id' => $head->id, 'role_id' => $headRoleId]);
        }

        $managerRoleId = DB::table('roles')->where('code', 'procurement_manager')->value('id');
        if (! DB::table('role_user')->where('role_id', $managerRoleId)->exists()) {
            $manager = User::factory()->create(['department_id' => $user->department_id]);
            DB::table('role_user')->insert(['user_id' => $manager->id, 'role_id' => $managerRoleId]);
        }

        $fields = [
            'intent' => 'submit',
            'title' => '分类验证',
            'budget' => $budget,
            'funding_source' => '教学经费',
            'organization_form' => '部门自行组织',
            'purchase_method' => '询价',
            'reason' => '教学需要',
            'responsible_department' => '信息中心',
            'is_fixed_asset' => false,
        ];

        $isTender = $budget * 100 >= ProcurementClassificationRule::effectiveFor($user->department_id)->threshold_cents;
        if ($isTender) {
            $fields = [
                ...$fields,
                'project_leader' => '张老师',
                'procurement_requirements' => '教学需要',
                'purchase_list' => '设备 1 台',
                'technical_parameters' => '基础参数',
                'service_requirements' => '三年质保',
                'acceptance_criteria' => '按清单验收',
                'budget_basis_note' => '市场询价',
                'demand_document' => UploadedFile::fake()->create('需求书.pdf', 8, 'application/pdf'),
                'budget_basis_document' => UploadedFile::fake()->create('预算依据.pdf', 8, 'application/pdf'),
            ];
        }

        $route = $isTender ? 'procurement.tenders.store' : 'procurement.applications.store';
        $this->actingAs($user)->post(route($route), $fields)->assertRedirect()->assertSessionHasNoErrors();

        return (int) DB::table('procurement_applications')->max('id');
    }

    public function test_procurement_manager_can_schedule_a_global_rule_without_reclassifying_submitted_projects(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 4, 10, 0));
        $manager = $this->userWithRole('procurement_manager');
        $applicant = $this->userWithRole();
        $oldId = $this->submit($applicant, 110000);

        $this->actingAs($manager)->post(route('procurement.classification-rules.store'), [
            'threshold_yuan' => 120000,
            'scope' => 'global',
            'effective_at' => '2026-11-05 00:00:00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $beforeEffectiveId = $this->submit($applicant, 110000);
        $this->travelTo(now()->addDay());
        $newId = $this->submit($applicant, 110000);

        $old = DB::table('procurement_applications')->find($oldId);
        $before = DB::table('procurement_applications')->find($beforeEffectiveId);
        $new = DB::table('procurement_applications')->find($newId);

        $this->assertSame('central_tender', $old->purchase_type);
        $this->assertSame('central_tender', $before->purchase_type);
        $this->assertSame('self_purchase', $new->purchase_type);
        $this->assertSame(10_000_000, $old->classification_threshold_cents);
        $this->assertSame(12_000_000, $new->classification_threshold_cents);
        $this->assertNotSame($old->classification_rule_id, $new->classification_rule_id);
    }

    public function test_department_rule_applies_only_to_its_department(): void
    {
        $manager = $this->userWithRole('procurement_manager');
        $targetDepartment = DB::table('departments')->insertGetId(['name' => '外国语学院', 'created_at' => now(), 'updated_at' => now()]);
        $targetUser = $this->userWithRole(null, $targetDepartment);
        $otherUser = $this->userWithRole();

        $this->actingAs($manager)->post(route('procurement.classification-rules.store'), [
            'threshold_yuan' => 80000,
            'scope' => 'department',
            'department_id' => $targetDepartment,
            'effective_at' => now()->subMinute()->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $targetId = $this->submit($targetUser, 90000);
        $otherId = $this->submit($otherUser, 90000);

        $this->assertSame('central_tender', DB::table('procurement_applications')->find($targetId)->purchase_type);
        $this->assertSame('self_purchase', DB::table('procurement_applications')->find($otherId)->purchase_type);
    }

    public function test_unprivileged_user_cannot_view_or_change_rules(): void
    {
        $user = $this->userWithRole();

        $this->actingAs($user)->get(route('procurement.classification-rules.index'))->assertForbidden();
        $this->actingAs($user)->post(route('procurement.classification-rules.store'), [
            'threshold_yuan' => 50000,
            'scope' => 'global',
            'effective_at' => now()->format('Y-m-d H:i:s'),
        ])->assertForbidden();

        $this->assertDatabaseCount('procurement_classification_rules', 1);
    }

    public function test_rule_input_requires_positive_amount_and_valid_scope(): void
    {
        $manager = $this->userWithRole('procurement_manager');

        $this->actingAs($manager)->post(route('procurement.classification-rules.store'), [
            'threshold_yuan' => 0,
            'scope' => 'department',
            'effective_at' => 'bad-date',
        ])->assertSessionHasErrors(['threshold_yuan', 'department_id', 'effective_at']);

        $this->assertDatabaseCount('procurement_classification_rules', 1);
    }

    public function test_manager_can_view_rule_history_and_departments(): void
    {
        $manager = $this->userWithRole('procurement_manager');

        $this->actingAs($manager)->get(route('procurement.classification-rules.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('procurement/ClassificationRules')
                ->has('rules', 1)
                ->where('rules.0.is_active', true)
                ->has('departments', 1));
    }

    public function test_future_rule_is_shown_as_scheduled_while_existing_rule_remains_active(): void
    {
        $manager = $this->userWithRole('procurement_manager');

        $this->actingAs($manager)->post(route('procurement.classification-rules.store'), [
            'threshold_yuan' => 150000,
            'scope' => 'global',
            'effective_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->actingAs($manager)->get(route('procurement.classification-rules.index'))
            ->assertInertia(fn ($page) => $page
                ->where('rules.0.is_active', false)
                ->where('rules.0.is_scheduled', true)
                ->where('rules.1.is_active', true));
    }
}
