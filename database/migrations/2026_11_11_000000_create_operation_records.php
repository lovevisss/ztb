<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('category', 40);
            $table->string('action', 80);
            $table->string('subject_type', 40)->nullable();
            $table->string('subject_id', 128)->nullable();
            $table->string('subject_label')->nullable();
            $table->string('summary');
            $table->string('outcome', 20);
            $table->string('source_table', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamp('created_at');
            $table->unique(['source_table', 'source_id']);
            $table->index(['created_at', 'id']);
            $table->index(['category', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });

        $this->backfill();
    }

    public function backfill(): void
    {
        $sources = [
            'cas_auth_events' => ['login', 'user_id', null],
            'approval_events' => ['approval', 'actor_id', 'application_id'],
            'procurement_execution_events' => ['execution', 'actor_id', 'application_id'],
            'school_identity_events' => ['account', 'actor_id', 'user_id'],
            'manual_role_events' => ['account', 'actor_id', 'user_id'],
        ];
        foreach ($sources as $table => [$category, $actorColumn, $subjectColumn]) {
            DB::table($table)->orderBy('id')->chunkById(500, function ($events) use ($table, $category, $actorColumn, $subjectColumn): void {
                $actorIds = $events->pluck($actorColumn)->filter()->unique()->all();
                $actors = DB::table('users')->whereIn('id', $actorIds)->pluck('name', 'id');
                $subjects = $subjectColumn === 'application_id'
                    ? DB::table('procurement_applications')->whereIn('id', $events->pluck($subjectColumn)->unique())->get(['id', 'title', 'project_number'])->keyBy('id')
                    : collect();
                $rows = [];
                foreach ($events as $event) {
                    $actorId = $event->{$actorColumn};
                    $subjectId = $table === 'cas_auth_events'
                        ? ($event->user_id ?? $event->account_hash)
                        : ($subjectColumn === null ? null : $event->{$subjectColumn});
                    $subject = $subjectColumn === 'application_id' ? $subjects->get($subjectId) : null;
                    $action = $table === 'cas_auth_events' ? $event->outcome : ($event->event ?? $event->action ?? 'updated');
                    $rows[] = [
                        'actor_id' => $actorId,
                        'actor_name' => $actorId ? ($actors->get($actorId) ?? null) : null,
                        'category' => $category,
                        'action' => (string) $action,
                        'subject_type' => $table === 'cas_auth_events' ? 'account' : ($subjectColumn === 'application_id' ? 'application' : 'user'),
                        'subject_id' => $subjectId === null ? null : (string) $subjectId,
                        'subject_label' => $subject?->project_number ?? $subject?->title
                            ?? ($subjectId && $table !== 'cas_auth_events' ? '用户 #'.$subjectId : null),
                        'summary' => match ($table) {
                            'cas_auth_events' => $event->outcome === 'authenticated' ? '统一认证登录成功' : '统一认证登录未通过',
                            'approval_events' => '采购审批：'.$action,
                            'procurement_execution_events' => '采购执行：'.$action,
                            'school_identity_events' => '校内账号资料修改',
                            default => '手动角色 '.$action,
                        },
                        'outcome' => $table === 'cas_auth_events' && $event->outcome !== 'authenticated' ? 'failure' : 'success',
                        'source_table' => $table,
                        'source_id' => $event->id,
                        'created_at' => $event->created_at,
                    ];
                }
                DB::table('operation_records')->insertOrIgnore($rows);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_records');
    }
};
