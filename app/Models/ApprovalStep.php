<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalStep extends Model
{
    protected $fillable = ['assignee_id', 'status', 'opinion', 'acted_at'];

    protected function casts(): array
    {
        return ['acted_at' => 'datetime'];
    }
}
