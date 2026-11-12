<?php

namespace Tests\Feature\Integration;

use App\Models\User;
use App\Services\SchoolOrganizationSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchoolIdentityManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $departmentId = DB::table('departments')->insertGetId([
            'name' => '测试部门'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create(['department_id' => $departmentId]);
        DB::table('role_user')->insert([
            'user_id' => $user->id,
            'role_id' => DB::table('roles')->where('code', $role)->value('id'),
        ]);

        return $user;
    }

    public function test_only_admin_can_map_unique_exact_school_accounts_and_deactivate_people(): void
    {
        $admin = $this->user('system_admin');
        $manager = $this->user('procurement_manager');
        $staff = $this->user('staff');
        $other = $this->user('staff');

        $this->actingAs($manager)->get(route('school-identities.index'))->assertForbidden();
        $this->actingAs($manager)->put(route('school-identities.update', $staff), [
            'school_account' => '0007', 'is_active' => true,
        ])->assertForbidden();

        $this->actingAs($admin)->get(route('school-identities.index'))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->component('admin/SchoolIdentities')->has('users.data', 4));
        $this->actingAs($admin)->put(route('school-identities.update', $staff), [
            'school_account' => '0007', 'is_active' => true,
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'school_account' => '0007', 'is_active' => true]);

        $this->actingAs($admin)->put(route('school-identities.update', $other), [
            'school_account' => '0007', 'is_active' => true,
        ])->assertSessionHasErrors('school_account');
        $this->actingAs($admin)->put(route('school-identities.update', $staff), [
            'school_account' => '0007', 'is_active' => false,
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'school_account' => '0007', 'is_active' => false]);
        $this->assertDatabaseCount('school_identity_events', 2);
    }

    public function test_admin_assigns_one_person_manual_roles_with_audit_and_preserves_school_roles(): void
    {
        $admin = $this->user('system_admin');
        $manager = $this->user('procurement_manager');
        $staff = $this->user('staff');
        $other = $this->user('staff');
        DB::table('role_user')->where('user_id', $staff->id)
            ->update(['source' => 'school']);

        $this->actingAs($manager)->put(route('school-identities.roles.update', $staff), [
            'manual_role_codes' => ['department_head'],
        ])->assertForbidden();
        $this->actingAs($admin)->get(route('school-identities.index'))
            ->assertInertia(fn ($page) => $page->has('roles', 9)->has('users.data', 4));

        $payload = ['manual_role_codes' => ['department_head', 'finance']];
        $this->actingAs($admin)->put(route('school-identities.roles.update', $staff), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('role_user', [
            'user_id' => $staff->id,
            'role_id' => DB::table('roles')->where('code', 'department_head')->value('id'),
            'source' => 'manual',
        ]);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $staff->id,
            'role_id' => DB::table('roles')->where('code', 'staff')->value('id'),
            'source' => 'school',
        ]);
        $this->assertDatabaseMissing('role_user', [
            'user_id' => $other->id,
            'role_id' => DB::table('roles')->where('code', 'department_head')->value('id'),
        ]);
        $this->assertDatabaseHas('manual_role_events', [
            'user_id' => $staff->id, 'actor_id' => $admin->id,
            'role_code' => 'department_head', 'action' => 'granted', 'channel' => 'web',
        ]);
        $this->actingAs($admin)->put(route('school-identities.roles.update', $staff), [
            'manual_role_codes' => ['staff'],
        ])->assertSessionHasErrors('manual_role_codes');
        $positionId = DB::table('school_positions')->insertGetId([
            'code' => 'TEST_STAFF', 'name' => '普通岗位', 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(SchoolOrganizationSync::class)->syncRoles($staff, $positionId);
        $this->assertDatabaseHas('role_user', [
            'user_id' => $staff->id,
            'role_id' => DB::table('roles')->where('code', 'department_head')->value('id'),
            'source' => 'manual',
        ]);
        $this->actingAs($admin)->put(route('school-identities.roles.update', $staff), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('manual_role_events', 2);
        $this->actingAs($admin)->put(route('school-identities.roles.update', $staff), [
            'manual_role_codes' => [],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('role_user', ['user_id' => $staff->id, 'source' => 'manual']);
        $this->assertDatabaseMissing('role_user', [
            'user_id' => $staff->id,
            'role_id' => DB::table('roles')->where('code', 'department_head')->value('id'),
        ]);
        $this->assertDatabaseCount('manual_role_events', 4);
    }

    public function test_last_admin_and_manager_cannot_be_removed_in_ui(): void
    {
        $admin = $this->user('system_admin');
        DB::table('role_user')->insert([
            'user_id' => $admin->id,
            'role_id' => DB::table('roles')->where('code', 'procurement_manager')->value('id'),
            'source' => 'manual',
        ]);
        $this->actingAs($admin)->put(route('school-identities.roles.update', $admin), [
            'manual_role_codes' => [],
        ])->assertSessionHasErrors('manual_role_codes');
        $this->assertDatabaseHas('role_user', [
            'user_id' => $admin->id,
            'role_id' => DB::table('roles')->where('code', 'system_admin')->value('id'),
        ]);
        $this->actingAs($admin)->put(route('school-identities.roles.update', $admin), [
            'manual_role_codes' => ['system_admin'],
        ])->assertSessionHasErrors('manual_role_codes');
        $this->assertDatabaseHas('role_user', [
            'user_id' => $admin->id,
            'role_id' => DB::table('roles')->where('code', 'procurement_manager')->value('id'),
        ]);
        $this->assertDatabaseCount('manual_role_events', 0);
    }

    public function test_console_command_bootstraps_and_revokes_manual_roles_by_exact_account(): void
    {
        $target = User::factory()->create(['school_account' => '20150301']);
        $this->artisan('school:manage-user-roles', [
            'account' => '20150301', '--grant' => ['system_admin', 'procurement_manager'],
        ])->assertExitCode(0);
        $this->assertTrue($target->hasAnyRole(['system_admin']));
        $this->assertTrue($target->hasAnyRole(['procurement_manager']));
        $this->assertDatabaseHas('manual_role_events', [
            'user_id' => $target->id, 'actor_id' => null,
            'role_code' => 'system_admin', 'action' => 'granted', 'channel' => 'console:'.substr(getenv('USERNAME') ?: getenv('USER') ?: 'unknown', 0, 48),
        ]);
        $this->artisan('school:manage-user-roles', [
            'account' => '20150301', '--grant' => ['system_admin', 'procurement_manager'],
        ])->assertExitCode(0);
        $this->assertDatabaseCount('manual_role_events', 2);
        $this->artisan('school:manage-user-roles', [
            'account' => '20150301', '--revoke' => ['system_admin'],
        ])->assertExitCode(1);
        $this->artisan('school:manage-user-roles', [
            'account' => '20150301', '--revoke' => ['system_admin', 'procurement_manager'],
            '--allow-last-admin' => true, '--allow-last-manager' => true,
        ])->assertExitCode(0);
        $this->assertFalse($target->hasAnyRole(['system_admin', 'procurement_manager']));
        $this->assertDatabaseCount('manual_role_events', 4);
    }
}
