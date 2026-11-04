<?php

namespace App\Services;

use App\Models\ProcurementApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProcurementAccess
{
    public function canView(User $user, ProcurementApplication $application): bool
    {
        return $application->applicant_id === $user->id
            || $application->executor_id === $user->id
            || $user->hasAnyRole(['procurement_manager', 'system_admin'])
            || $this->visibleHistoryQuery($user)->whereKey($application->id)->exists();
    }

    public function canExecute(User $user, ProcurementApplication $application): bool
    {
        return $application->applicant_id === $user->id
            || $application->executor_id === $user->id
            || $user->hasAnyRole(['procurement_manager', 'system_admin']);
    }

    /** @return Builder<ProcurementApplication> */
    public function visibleHistoryQuery(User $user): Builder
    {
        $query = ProcurementApplication::query()->where('status', '!=', 'draft');
        if ($user->hasAnyRole(['procurement_manager', 'system_admin'])) {
            return $query;
        }

        $responsibleDepartment = $user->hasAnyRole(['responsible_department']) && $user->department_id !== null
            ? DB::table('departments')->where('id', $user->department_id)->value('name') : null;

        return $query->where(function (Builder $scope) use ($user, $responsibleDepartment) {
            $scope->where('applicant_id', $user->id)
                ->orWhere('executor_id', $user->id)
                ->orWhereIn('id', function ($subquery) use ($user) {
                    $subquery->select('application_id')->from('approval_steps')->where('assignee_id', $user->id);
                });
            if ($user->department_id !== null) {
                $scope->orWhere('department_id', $user->department_id);
            }
            if ($responsibleDepartment !== null) {
                $scope->orWhere('responsible_department', $responsibleDepartment);
            }
        });
    }
}
