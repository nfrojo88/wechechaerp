<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendancePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('hr.view')
            || $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin', 'gm', 'general_manager', 'coordinator', 'site_engineer']);
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->can('hr.view')
            || $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin', 'gm', 'general_manager', 'coordinator', 'site_engineer'])
            || ($attendance->employee?->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin', 'gm', 'site_engineer', 'coordinator']);
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin', 'gm']);
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin', 'gm']);
    }
}
