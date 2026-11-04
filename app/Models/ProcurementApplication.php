<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementApplication extends Model
{
    protected $fillable = [
        'applicant_id', 'department_id', 'title', 'budget_cents', 'funding_source',
        'organization_form', 'purchase_method', 'reason', 'responsible_department',
        'is_fixed_asset', 'purchase_type', 'status', 'project_number', 'submitted_at',
        'classification_rule_id', 'classification_threshold_cents',
        'application_form', 'project_leader', 'procurement_requirements', 'purchase_list',
        'technical_parameters', 'service_requirements', 'acceptance_criteria', 'budget_basis_note',
        'approval_template_id', 'approval_attempt',
        'source_application_id',
        'executor_id',
    ];

    protected function casts(): array
    {
        return ['is_fixed_asset' => 'boolean', 'submitted_at' => 'datetime'];
    }
}
