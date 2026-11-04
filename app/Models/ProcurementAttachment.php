<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementAttachment extends Model
{
    protected $fillable = [
        'application_id', 'uploaded_by', 'kind', 'original_name', 'disk', 'path', 'mime_type', 'size',
    ];

    /** @return BelongsTo<ProcurementApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(ProcurementApplication::class);
    }
}
