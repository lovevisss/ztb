<?php

namespace Tests\Feature\Integration;

use App\Auth\CasIdentityResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lovevisss\LaravelZufedfcCas\Data\CasIdentity;
use Tests\TestCase;

class OrganizationSyncTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        $department = DB::table('departments')->insertGetId(['name' => '采购处', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['department_id' => $department]);
        DB::table('role_user')->insert([
            'user_id' => $user->id,
            'role_id' => DB::table('roles')->where('code', $role)->value('id'),
        ]);

        return $user;
    }

    private function feed(array $people, bool $complete = false): array
    {
        return [
            'complete_snapshot' => $complete,
            'departments' => [
                ['code' => 'SCHOOL', 'name' => '学校', 'parent_code' => null],
                ['code' => 'IT', 'name' => '信息中心', 'parent_code' => 'SCHOOL'],
                ['code' => 'LOG', 'name' => '后勤处', 'parent_code' => 'SCHOOL'],
            ],
            'positions' => [
                ['code' => 'HEAD', 'name' => '部门负责人'],
                ['code' => 'STAFF', 'name' => '职员'],
            ],
            'people' => $people,
        ];
    }

    public function test_admin_syncs_hierarchy_positions_staff_moves_and_business_role_mapping(): void
    {
        $admin = $this->actor('system_admin');
        $oldDepartment = DB::table('departments')->insertGetId(['name' => '旧部门', 'created_at' => now(), 'updated_at' => now()]);
        $existing = User::factory()->create(['school_account' => '001', 'department_id' => $oldDepartment]);
        $originalPassword = $existing->password;
        $people = [
            ['account' => '001', 'name' => '张老师', 'email' => $existing->email, 'mobile_phone' => '13800138000', 'department_code' => 'IT', 'position_code' => 'HEAD', 'active' => true],
            ['account' => '002', 'name' => '李老师', 'email' => 'li@example.edu.cn', 'department_code' => 'LOG', 'position_code' => 'STAFF', 'active' => true],
        ];

        $this->actingAs($admin)->postJson(route('organization.sync'), $this->feed($people))
            ->assertSuccessful()->assertJsonPath('users_created', 1)->assertJsonPath('users_updated', 1);
        $this->actingAs($admin)->get(route('organization.index'))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->component('admin/SchoolOrganization')->has('departments', 3));
        $it = DB::table('departments')->where('external_code', 'IT')->first();
        $school = DB::table('departments')->where('external_code', 'SCHOOL')->first();
        $this->assertSame($school->id, $it->parent_id);
        $this->assertDatabaseHas('users', ['id' => $existing->id, 'name' => '张老师', 'mobile_phone' => '13800138000', 'department_id' => $it->id, 'school_account' => '001']);
        $this->assertSame($originalPassword, $existing->fresh()->password);

        $this->actingAs($admin)->putJson(route('organization.position-roles.update', 'HEAD'), [
            'role_codes' => ['department_head'],
        ])->assertSuccessful();
        $originalDepartmentCreatedAt = DB::table('departments')->where('external_code', 'IT')->value('created_at');
        $originalPositionCreatedAt = DB::table('school_positions')->where('code', 'HEAD')->value('created_at');
        $this->travel(1)->days();
        $this->actingAs($admin)->postJson(route('organization.sync'), $this->feed($people))->assertSuccessful();
        $this->assertSame($originalDepartmentCreatedAt, DB::table('departments')->where('external_code', 'IT')->value('created_at'));
        $this->assertSame($originalPositionCreatedAt, DB::table('school_positions')->where('code', 'HEAD')->value('created_at'));
        $this->assertTrue($existing->fresh()->hasAnyRole(['department_head']));
        $this->assertDatabaseHas('role_user', [
            'user_id' => $existing->id,
            'role_id' => DB::table('roles')->where('code', 'department_head')->value('id'),
            'source' => 'school',
        ]);
    }

    public function test_complete_snapshot_deactivates_missing_synced_people_but_preserves_manual_accounts(): void
    {
        $admin = $this->actor('system_admin');
        $manual = User::factory()->create(['department_id' => $admin->department_id, 'school_account' => 'MANUAL']);
        $one = ['account' => '001', 'name' => '张老师', 'email' => 'zhang@example.edu.cn', 'department_code' => 'IT', 'position_code' => 'STAFF', 'active' => true];
        $two = ['account' => '002', 'name' => '李老师', 'email' => 'li@example.edu.cn', 'department_code' => 'LOG', 'position_code' => 'STAFF', 'active' => true];
        $this->actingAs($admin)->postJson(route('organization.sync'), $this->feed([$one, $two], true))->assertSuccessful();
        $this->actingAs($admin)->postJson(route('organization.sync'), $this->feed([$one], true))->assertSuccessful();

        $this->assertDatabaseHas('users', ['school_account' => '002', 'is_active' => false]);
        $this->assertDatabaseHas('users', ['id' => $manual->id, 'is_active' => true]);
        $this->assertNull(app(CasIdentityResolver::class)->resolve(new CasIdentity('002', '002')));
    }

    public function test_invalid_feed_is_atomic_and_non_admin_cannot_sync_or_map_roles(): void
    {
        $admin = $this->actor('system_admin');
        $staff = $this->actor('staff');
        $person = ['account' => '001', 'name' => '张老师', 'email' => 'zhang@example.edu.cn', 'department_code' => 'IT', 'position_code' => 'STAFF', 'active' => true];
        $this->actingAs($staff)->postJson(route('organization.sync'), $this->feed([$person]))->assertForbidden();
        $this->actingAs($staff)->putJson(route('organization.position-roles.update', 'HEAD'), ['role_codes' => ['department_head']])->assertForbidden();

        $cycle = $this->feed([$person]);
        $cycle['departments'][0]['parent_code'] = 'IT';
        $this->actingAs($admin)->postJson(route('organization.sync'), $cycle)->assertUnprocessable();
        $this->assertDatabaseMissing('departments', ['external_code' => 'IT']);

        $duplicates = $this->feed([$person, $person]);
        $this->actingAs($admin)->postJson(route('organization.sync'), $duplicates)->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['school_account' => '001']);
    }
}
