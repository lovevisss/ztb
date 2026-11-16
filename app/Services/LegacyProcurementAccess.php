<?php

namespace App\Services;

use App\Models\User;

class LegacyProcurementAccess
{
    public function canView(User $user, object $project): bool
    {
        return $user->hasAnyRole(['procurement_manager', 'system_admin'])
            || (int) ($project->user_id ?? 0) === $user->id
            || ($user->department_id !== null && (int) ($project->department_id ?? 0) === $user->department_id)
            || ($user->school_account !== null && $user->school_account !== ''
                && data_get($project, 'creator_account') === $user->school_account);
    }
}
