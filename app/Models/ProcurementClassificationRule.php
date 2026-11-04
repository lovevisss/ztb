<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $threshold_cents
 * @property string $scope
 * @property int|null $department_id
 * @property Carbon $effective_at
 */
class ProcurementClassificationRule extends Model
{
    protected $fillable = ['threshold_cents', 'scope', 'department_id', 'effective_at', 'created_by'];

    protected function casts(): array
    {
        return ['effective_at' => 'datetime'];
    }

    public static function effectiveFor(?int $departmentId): self
    {
        $now = now();

        return self::query()
            ->where('effective_at', '<=', $now)
            ->where(function ($query) use ($departmentId) {
                $query->where('scope', 'global');
                if ($departmentId !== null) {
                    $query->orWhere(function ($departmentQuery) use ($departmentId) {
                        $departmentQuery->where('scope', 'department')->where('department_id', $departmentId);
                    });
                }
            })
            ->orderByRaw("CASE WHEN scope = 'department' THEN 0 ELSE 1 END")
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->firstOrFail();
    }
}
