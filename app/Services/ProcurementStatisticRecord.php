<?php

namespace App\Services;

use App\Models\ProcurementApplication;
use DateTimeInterface;

class ProcurementStatisticRecord
{
    public readonly string $execution_state;

    public readonly string $archive_state;

    public function __construct(
        public readonly int $id,
        public readonly string $project_number,
        public readonly string $title,
        public readonly int $department_id,
        public readonly string $department_name,
        public readonly string $responsible_department,
        public readonly string $purchase_method,
        public readonly string $organization_form,
        public readonly string $funding_source,
        public readonly string $purchase_type,
        public readonly string $status,
        public readonly int $budget_cents,
        public readonly ?string $submitted_at,
        public readonly ?int $actual_amount_cents,
        ?string $execution_status,
    ) {
        $this->execution_state = $execution_status ?? 'not_started';
        $this->archive_state = $status === 'archived' ? 'archived' : 'not_archived';
    }

    public static function fromModel(ProcurementApplication $record): self
    {
        $actualAmount = $record->getAttribute('actual_amount_cents');
        $executionStatus = $record->getAttribute('execution_status');
        $submittedAt = $record->getAttribute('submitted_at');

        return new self(
            (int) $record->getKey(),
            (string) $record->project_number,
            (string) $record->title,
            (int) $record->department_id,
            (string) $record->getAttribute('department_name'),
            (string) $record->responsible_department,
            (string) $record->purchase_method,
            (string) $record->organization_form,
            (string) $record->funding_source,
            (string) $record->purchase_type,
            (string) $record->status,
            (int) $record->budget_cents,
            $submittedAt instanceof DateTimeInterface ? $submittedAt->format('Y-m-d H:i:s') : ($submittedAt === null ? null : (string) $submittedAt),
            $executionStatus === 'completed' && $actualAmount !== null ? (int) $actualAmount : null,
            $executionStatus === null ? null : (string) $executionStatus,
        );
    }
}
