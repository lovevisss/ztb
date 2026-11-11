<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationRecordTest extends TestCase
{
    use RefreshDatabase;

    private function withRole(string $code): User
    {
        $user = User::factory()->create();
        DB::table('role_user')->insert([
            'user_id' => $user->id,
            'role_id' => DB::table('roles')->where('code', $code)->value('id'),
        ]);

        return $user;
    }

    public function test_only_system_admin_can_view_and_filter_operation_records(): void
    {
        $admin = $this->withRole('system_admin');
        $manager = $this->withRole('procurement_manager');
        DB::table('operation_records')->insert([
            'actor_id' => $manager->id, 'actor_name' => $manager->name,
            'category' => 'approval', 'action' => 'reassigned', 'summary' => '调整审批人',
            'subject_type' => 'application', 'subject_id' => '19', 'subject_label' => 'CG2026-019',
            'outcome' => 'success', 'created_at' => now(),
        ]);

        $this->get(route('operation-records.index'))->assertRedirect(route('login'));
        $this->actingAs($manager)->get(route('operation-records.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('operation-records.index', ['search' => 'CG2026-019', 'category' => 'approval']))
            ->assertOk()->assertInertia(fn ($page) => $page->component('admin/OperationRecords')
                ->where('records.total', 1)->where('records.data.0.summary', '调整审批人'));
        $this->actingAs($admin)->get(route('operation-records.index', ['category' => 'login']))
            ->assertInertia(fn ($page) => $page->where('records.total', 0));
    }

    public function test_local_login_success_and_failure_are_recorded_without_credentials(): void
    {
        config()->set('cas.service_url', null);
        $user = User::factory()->create();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $records = DB::table('operation_records')->where('category', 'login')->orderBy('id')->get();
        $this->assertCount(2, $records);
        $this->assertSame('failure', $records[0]->outcome);
        $this->assertSame(hash('sha256', mb_strtolower($user->email)), $records[0]->subject_id);
        $this->assertSame('success', $records[1]->outcome);
        foreach ($records as $record) {
            $this->assertStringNotContainsString('wrong-password', json_encode($record));
            $this->assertStringNotContainsString($user->email, json_encode($record));
        }
    }

    public function test_successful_profile_change_is_recorded_but_validation_failure_is_not(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch(route('profile.update'), ['name' => '新姓名', 'email' => 'invalid'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('operation_records', 0);

        $this->actingAs($user)->patch(route('profile.update'), ['name' => '新姓名', 'email' => 'new@example.com'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('operation_records', [
            'actor_id' => $user->id, 'action' => 'profile_updated', 'outcome' => 'success',
        ]);
    }

    public function test_legacy_import_is_idempotent(): void
    {
        $user = User::factory()->create();
        $sourceId = DB::table('cas_auth_events')->insertGetId([
            'user_id' => $user->id, 'account_hash' => hash('sha256', '007'),
            'outcome' => 'authenticated', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_11_11_000000_create_operation_records.php');
        $migration->backfill();
        $migration->backfill();

        $this->assertDatabaseHas('operation_records', [
            'source_table' => 'cas_auth_events', 'source_id' => $sourceId,
            'actor_id' => $user->id, 'summary' => '统一认证登录成功',
        ]);
        $this->assertSame(1, DB::table('operation_records')->where('source_table', 'cas_auth_events')->count());
    }
}
