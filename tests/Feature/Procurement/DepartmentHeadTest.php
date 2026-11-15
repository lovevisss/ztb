<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DepartmentHeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_assign_replace_and_clear_a_cross_department_head(): void
    {
        $department = DB::table('departments')->insertGetId(['name' => '使用部门', 'created_at' => now(), 'updated_at' => now()]);
        $otherDepartment = DB::table('departments')->insertGetId(['name' => '其他部门', 'created_at' => now(), 'updated_at' => now()]);
        $admin = User::factory()->create(['department_id' => $otherDepartment]);
        $outsider = User::factory()->create(['department_id' => $department]);
        $first = User::factory()->create(['department_id' => $otherDepartment, 'school_account' => 'H001']);
        $second = User::factory()->create(['department_id' => $department, 'school_account' => 'H002']);
        $inactive = User::factory()->create(['department_id' => $department, 'is_active' => false]);
        DB::table('role_user')->insert([
            'user_id' => $admin->id, 'role_id' => DB::table('roles')->where('code', 'system_admin')->value('id'),
        ]);

        $this->actingAs($outsider)->get(route('department-heads.index'))->assertForbidden();
        $this->actingAs($outsider)->put(route('department-heads.update', $department), ['user_id' => $first->id])->assertForbidden();
        $this->actingAs($admin)->get(route('department-heads.index', ['search' => '使用']))
            ->assertInertia(fn ($page) => $page->component('admin/DepartmentHeads')->has('departments', 1));
        $this->actingAs($admin)->put(route('department-heads.update', $department), ['user_id' => $first->id])
            ->assertRedirect()->assertSessionHas('status', '部门负责人已保存。');
        $this->assertDatabaseHas('department_head_assignments', ['department_id' => $department, 'user_id' => $first->id]);
        $this->actingAs($admin)->put(route('department-heads.update', $department), ['user_id' => $inactive->id])
            ->assertSessionHasErrors('user_id');
        $this->actingAs($admin)->put(route('department-heads.update', $department), ['user_id' => $second->id])
            ->assertRedirect();
        $this->assertDatabaseHas('department_head_assignments', ['department_id' => $department, 'user_id' => $second->id]);
        $this->actingAs($admin)->put(route('department-heads.update', $department), ['user_id' => null])
            ->assertRedirect()->assertSessionHas('status', '已清除手动指定，恢复按角色匹配。');
        $this->assertDatabaseMissing('department_head_assignments', ['department_id' => $department]);
        $this->assertDatabaseCount('department_head_assignment_events', 3);
    }
}
