<?php

namespace Tests\Feature\Procurement;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegacyAttachmentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_legacy_file_is_downloadable_only_by_project_viewers(): void
    {
        Storage::fake('local');
        $department = DB::table('departments')->insertGetId(['name' => '信息中心', 'created_at' => now(), 'updated_at' => now()]);
        $otherDepartment = DB::table('departments')->insertGetId(['name' => '其他部门', 'created_at' => now(), 'updated_at' => now()]);
        $owner = User::factory()->create(['school_account' => 'OWNER', 'department_id' => $otherDepartment]);
        $colleague = User::factory()->create(['department_id' => $department]);
        $outsider = User::factory()->create(['department_id' => $otherDepartment]);
        DB::table('legacy_procurement_records')->insert([
            'source_table' => 'zcy_cgsq', 'source_id' => 'project', 'application_source_id' => 'project',
            'creator_account' => 'OWNER', 'department_id' => $department, 'title' => '旧项目',
            'details' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $attachment = DB::table('legacy_procurement_attachments')->insertGetId([
            'source_table' => 't_s_attachment', 'source_id' => 'file', 'owner_table' => 'zcy_cgsq',
            'owner_source_id' => 'project', 'application_source_id' => 'project', 'name' => '申请报告',
            'legacy_path' => 'upload/2026/06/15/test.pdf', 'migration_status' => 'pending_file',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $url = route('procurement.legacy-attachments.download', ['sourceId' => 'project', 'attachment' => $attachment]);

        $this->actingAs($owner)->get(route('procurement.applications.index', ['legacy' => 'project']))
            ->assertSuccessful()->assertInertia(fn ($page) => $page->where('attachments.0.id', $attachment)
            ->where('attachments.0.migration_status', 'pending_file')->missing('attachments.0.legacy_path'));
        $this->actingAs($owner)->get($url)->assertNotFound();
        Storage::disk('local')->put('legacy/2026/06/15/test.pdf', 'original document');
        $manifest = json_encode([
            'id' => $attachment, 'legacy_path' => 'upload/2026/06/15/test.pdf',
            'size' => strlen('original document'), 'sha256' => hash('sha256', 'original document'),
        ], JSON_THROW_ON_ERROR)."\n";
        Storage::disk('local')->put('manifest.jsonl', $manifest);
        $manifestPath = Storage::disk('local')->path('manifest.jsonl');

        $this->artisan('legacy:attachments', ['--manifest' => $manifestPath, '--dry-run' => true])->assertExitCode(0);
        $this->assertDatabaseHas('legacy_procurement_attachments', ['id' => $attachment, 'migration_status' => 'pending_file']);
        $this->artisan('legacy:attachments', ['--manifest' => $manifestPath])->assertExitCode(0);
        $this->artisan('legacy:attachments', ['--manifest' => $manifestPath])->assertExitCode(0);
        $this->assertDatabaseHas('legacy_procurement_attachments', [
            'id' => $attachment, 'stored_path' => 'legacy/2026/06/15/test.pdf', 'migration_status' => 'available',
        ]);
        foreach ([$owner, $colleague] as $viewer) {
            $this->actingAs($viewer)->get(route('procurement.legacy-history.show', 'project'))->assertSuccessful()
                ->assertInertia(fn ($page) => $page->where('attachments.0.migration_status', 'available'));
            $response = $this->actingAs($viewer)->get($url)->assertOk();
            $this->assertStringContainsString(rawurlencode('申请报告.pdf'), $response->headers->get('Content-Disposition'));
        }
        $this->actingAs($outsider)->get($url)->assertForbidden();
        $this->actingAs($owner)->get(route('procurement.legacy-attachments.download', [
            'sourceId' => 'another', 'attachment' => $attachment,
        ]))->assertNotFound();
        Storage::disk('local')->delete('legacy/2026/06/15/test.pdf');
        $this->actingAs($owner)->get($url)->assertNotFound();
    }

    public function test_bad_checksum_never_marks_attachment_available(): void
    {
        Storage::fake('local');
        $attachment = DB::table('legacy_procurement_attachments')->insertGetId([
            'source_table' => 't_s_attachment', 'source_id' => 'file', 'owner_table' => 'zcy_cgsq',
            'owner_source_id' => 'project', 'application_source_id' => 'project',
            'legacy_path' => 'upload/2026/06/15/test.pdf', 'migration_status' => 'pending_file',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Storage::disk('local')->put('legacy/2026/06/15/test.pdf', 'wrong content');
        Storage::disk('local')->put('manifest.jsonl', json_encode([
            'id' => $attachment, 'legacy_path' => 'upload/2026/06/15/test.pdf',
            'size' => strlen('wrong content'), 'sha256' => str_repeat('0', 64),
        ], JSON_THROW_ON_ERROR)."\n");

        $this->artisan('legacy:attachments', ['--manifest' => Storage::disk('local')->path('manifest.jsonl')])
            ->assertExitCode(1);
        $this->assertDatabaseHas('legacy_procurement_attachments', ['id' => $attachment, 'migration_status' => 'pending_file', 'stored_path' => null]);
    }
}
