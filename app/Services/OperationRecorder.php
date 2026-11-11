<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OperationRecorder
{
    public function record(
        ?int $actorId,
        ?string $actorName,
        string $category,
        string $action,
        string $summary,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $subjectLabel = null,
        string $outcome = 'success',
    ): void {
        DB::table('operation_records')->insert([
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'category' => $category,
            'action' => $action,
            'summary' => $summary,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => $subjectLabel,
            'outcome' => $outcome,
            'created_at' => now(),
        ]);
    }
}
