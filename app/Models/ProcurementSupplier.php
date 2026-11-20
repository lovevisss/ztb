<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementSupplier extends Model
{
    protected $fillable = ['name', 'name_key', 'contact_name', 'contact_phone', 'department_id', 'created_by', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
