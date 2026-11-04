<?php

namespace Tests\Feature\Integration;

use App\Models\User;
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
}
