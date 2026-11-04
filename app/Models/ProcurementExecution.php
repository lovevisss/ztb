<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementExecution extends Model
{
    protected $fillable = [
        'application_id', 'actual_amount_cents', 'supplier', 'completed_on',
        'result_note', 'status', 'registered_at',
    ];

    protected function casts(): array
    {
        return ['registered_at' => 'datetime'];
    }
}
