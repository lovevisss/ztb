<?php

namespace Tests\Feature\Integration;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SyncSchoolPhonesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school_source', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        DB::purge('school_source');
        Schema::connection('school_source')->create('t_cx_zzqxryxx', function (Blueprint $table) {
            $table->string('xgh');
            $table->string('rylx');
            $table->string('yddh')->nullable();
        });
    }

    public function test_preview_then_update_only_existing_users_phone_and_repeat_safely(): void
    {
        $owner = User::factory()->create([
            'school_account' => '001', 'mobile_phone' => null, 'name' => '本地姓名', 'is_active' => false,
        ]);
        $second = User::factory()->create(['school_account' => '002', 'mobile_phone' => '旧电话']);
        DB::connection('school_source')->table('t_cx_zzqxryxx')->insert([
            ['xgh' => '001', 'rylx' => '1', 'yddh' => ' 13800138000 '],
            ['xgh' => '002', 'rylx' => '2', 'yddh' => '13900139000'],
            ['xgh' => '003', 'rylx' => '1', 'yddh' => '13700137000'],
            ['xgh' => '004', 'rylx' => '1', 'yddh' => null],
            ['xgh' => '005', 'rylx' => '3', 'yddh' => '13600136000'],
        ]);

        $this->artisan('school:sync-phones --dry-run')->assertExitCode(0);
        $this->assertNull($owner->fresh()->mobile_phone);
        $this->assertSame('旧电话', $second->fresh()->mobile_phone);

        $this->artisan('school:sync-phones')->assertExitCode(0);
        $this->assertDatabaseHas('users', [
            'id' => $owner->id, 'mobile_phone' => '13800138000', 'name' => '本地姓名', 'is_active' => false,
        ]);
        $this->assertSame('13900139000', $second->fresh()->mobile_phone);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('organization_sync_runs', 0);
        $this->artisan('school:sync-phones')->assertExitCode(0);
        $this->assertSame('13800138000', $owner->fresh()->mobile_phone);

        DB::connection('school_source')->table('t_cx_zzqxryxx')->where('xgh', '001')->update(['yddh' => null]);
        $this->artisan('school:sync-phones')->assertExitCode(0);
        $this->assertSame('13800138000', $owner->fresh()->mobile_phone);
    }

    public function test_duplicate_or_empty_source_does_not_change_local_phones(): void
    {
        $owner = User::factory()->create(['school_account' => '001', 'mobile_phone' => null]);
        DB::connection('school_source')->table('t_cx_zzqxryxx')->insert([
            ['xgh' => '001', 'rylx' => '1', 'yddh' => '13800138000'],
            ['xgh' => '001', 'rylx' => '2', 'yddh' => '13900139000'],
        ]);
        $this->artisan('school:sync-phones')->assertExitCode(1);
        $this->assertNull($owner->fresh()->mobile_phone);

        DB::connection('school_source')->table('t_cx_zzqxryxx')->delete();
        $this->artisan('school:sync-phones')->assertExitCode(1);
        $this->assertNull($owner->fresh()->mobile_phone);
    }
}
