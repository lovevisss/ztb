<?php

namespace Tests\Feature\Integration;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MiddataOrganizationSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school_source', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        DB::purge('school_source');
        Schema::connection('school_source')->create('t_cx_xyxx', function (Blueprint $table) {
            $table->string('dwbm');
            $table->string('dwmc');
            $table->string('lsdwh')->nullable();
        });
        Schema::connection('school_source')->create('t_cx_zzqxryxx', function (Blueprint $table) {
            $table->string('xgh');
            $table->string('xm');
            $table->string('rylx');
            $table->string('dwbm');
            $table->string('dzyx')->nullable();
        });
    }

    public function test_sync_is_repeatable_and_deactivates_people_leaving_the_feed(): void
    {
        $source = DB::connection('school_source');
        $source->table('t_cx_xyxx')->insert(['dwbm' => '100', 'dwmc' => '信息部']);
        $source->table('t_cx_zzqxryxx')->insert([
            ['xgh' => '001', 'xm' => '张老师', 'rylx' => '1', 'dwbm' => '100', 'dzyx' => 'same@example.edu.cn'],
            ['xgh' => '002', 'xm' => '李老师', 'rylx' => '2', 'dwbm' => '900', 'dzyx' => 'same@example.edu.cn'],
        ]);

        $this->artisan('school:sync-organization')->assertExitCode(0);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('departments', ['external_code' => '900', 'name' => '未分配院系']);
        $this->assertDatabaseHas('users', ['school_account' => '002', 'email' => hash('sha256', '002').'@school.invalid']);
        $this->artisan('school:sync-organization')->assertExitCode(0);
        $this->assertDatabaseCount('users', 2);

        $source->table('t_cx_zzqxryxx')->where('xgh', '001')->update(['xm' => '张老师新', 'dwbm' => '900']);
        $source->table('t_cx_zzqxryxx')->where('xgh', '002')->update(['rylx' => '3']);
        $this->artisan('school:sync-organization')->assertExitCode(0);
        $this->assertDatabaseHas('users', ['school_account' => '001', 'name' => '张老师新', 'is_active' => true]);
        $this->assertDatabaseHas('users', ['school_account' => '002', 'is_active' => false]);
        $this->assertDatabaseHas('organization_sync_runs', ['source' => 'middata', 'status' => 'success', 'people_count' => 1, 'deactivated_count' => 1]);
    }

    public function test_invalid_source_does_not_change_local_data_and_records_failure(): void
    {
        DB::connection('school_source')->table('t_cx_xyxx')->insert(['dwbm' => '100', 'dwmc' => '信息部']);
        DB::connection('school_source')->table('t_cx_zzqxryxx')->insert(['xgh' => '001', 'xm' => '张老师', 'rylx' => '1', 'dwbm' => 'missing']);

        $this->artisan('school:sync-organization')->assertExitCode(1);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('departments', 0);
        $this->assertDatabaseHas('organization_sync_runs', ['source' => 'middata', 'status' => 'failed']);
    }

    public function test_unavailable_source_does_not_change_local_data(): void
    {
        config()->set('database.connections.school_source.database', 'Z:/missing/source.sqlite');
        DB::purge('school_source');

        $this->artisan('school:sync-organization')->assertExitCode(1);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('departments', 0);
        $this->assertDatabaseHas('organization_sync_runs', ['source' => 'middata', 'status' => 'failed']);
    }
}
