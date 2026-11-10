<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BudgetIndicatorSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school_source', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        DB::purge('school_source');
        Schema::connection('school_source')->create('t_ejxyybt_jfdmb', function (Blueprint $table) {
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->string('charge_name')->nullable();
            $table->string('departcode')->nullable();
            $table->string('departname')->nullable();
            $table->string('validity')->nullable();
        });
    }

    public function test_sync_maps_school_codes_and_preserves_legacy_rows(): void
    {
        DB::table('budget_indicators')->insert([
            'source_id' => '7', 'code' => '13100', 'name' => '旧库经费',
            'status' => '1', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::connection('school_source')->table('t_ejxyybt_jfdmb')->insert([
            ['code' => ' 1SKT90131001 ', 'name' => ' 项目一   ', 'charge_name' => ' 张老师 ',
                'departcode' => '0031', 'departname' => ' 金融学院 ', 'validity' => 'Y'],
            ['code' => '2SKT90131002', 'name' => '项目二', 'charge_name' => null,
                'departcode' => null, 'departname' => null, 'validity' => 'N'],
        ]);

        $this->artisan('procurement:sync-budgets')->assertExitCode(0);
        $this->assertDatabaseHas('budget_indicators', [
            'source_id' => 'school_source:1SKT90131001', 'source' => 'school_source',
            'code' => '1SKT90131001', 'name' => '项目一', 'director' => '张老师',
            'department_code' => '0031', 'department_name' => '金融学院',
            'status' => '1', 'available_cents' => null,
        ]);
        $this->assertDatabaseHas('budget_indicators', ['source_id' => 'school_source:2SKT90131002', 'status' => '0']);
        $this->assertDatabaseHas('budget_indicators', ['source_id' => '7', 'source' => 'legacy_purchase']);
        $this->artisan('procurement:sync-budgets')->assertExitCode(0);
        $this->assertDatabaseCount('budget_indicators', 3);

        $user = User::factory()->create();
        $this->actingAs($user)->getJson(route('procurement.budgets.index', ['q' => '13100']))
            ->assertOk()->assertJsonPath('source_total', 2)->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.code', '1SKT90131001');
        $this->actingAs($user)->getJson(route('procurement.budgets.index', ['department' => '0031', 'status' => '1']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.department_name', '金融学院');
        $this->actingAs($user)->getJson(route('procurement.budgets.index', ['status' => '0']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.code', '2SKT90131002');
    }

    public function test_removed_school_code_becomes_inactive_without_deleting_its_row(): void
    {
        $source = DB::connection('school_source')->table('t_ejxyybt_jfdmb');
        $source->insert([
            ['code' => 'A', 'name' => '经费A', 'validity' => 'Y'],
            ['code' => 'B', 'name' => '经费B', 'validity' => 'Y'],
        ]);
        $this->artisan('procurement:sync-budgets')->assertExitCode(0);
        $source->where('code', 'A')->delete();
        $this->artisan('procurement:sync-budgets')->assertExitCode(0);

        $this->assertDatabaseHas('budget_indicators', ['source_id' => 'school_source:A', 'status' => '0']);
        $this->assertDatabaseHas('budget_indicators', ['source_id' => 'school_source:B', 'status' => '1']);
    }

    public function test_empty_or_unavailable_source_does_not_modify_local_data(): void
    {
        DB::table('budget_indicators')->insert([
            'source_id' => 'school_source:existing', 'source' => 'school_source',
            'code' => 'existing', 'name' => '已有经费', 'status' => '1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->artisan('procurement:sync-budgets')->assertExitCode(1);
        $this->assertDatabaseHas('budget_indicators', ['source_id' => 'school_source:existing', 'status' => '1']);

        Schema::connection('school_source')->drop('t_ejxyybt_jfdmb');
        $this->artisan('procurement:sync-budgets')->assertExitCode(1);
        $this->assertDatabaseHas('budget_indicators', ['source_id' => 'school_source:existing', 'status' => '1']);
    }

    public function test_manual_sync_requires_manager_and_reports_failure(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('procurement.budgets.sync'))->assertForbidden();

        DB::table('role_user')->insert([
            'user_id' => $user->id,
            'role_id' => DB::table('roles')->where('code', 'procurement_manager')->value('id'),
        ]);
        $this->actingAs($user)->post(route('procurement.budgets.sync'))
            ->assertRedirect()->assertSessionHasErrors('budget_sync');

        DB::connection('school_source')->table('t_ejxyybt_jfdmb')->insert([
            'code' => 'A', 'name' => '新经费', 'validity' => 'Y',
        ]);
        $this->actingAs($user)->post(route('procurement.budgets.sync'))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('budget_indicators', ['source_id' => 'school_source:A']);
    }
}
