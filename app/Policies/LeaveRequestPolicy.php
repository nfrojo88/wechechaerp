<?php

namespace App\Policies;

use App\Models\User;
use App\Models\LeaveRequest;

class LeaveRequestPolicy
{
    /**
     * Determine whether the user can view any leave requests.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin', 'gm', 'general_manager']);
    }

    /**
     * Determine whether the user can view the leave request.
     */
    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        // HR staff can view all, employees can view their own
        if ($user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin', 'gm', 'general_manager'])) {
            return true;
        }

        // Check if user is the employee who requested the leave
        return $leaveRequest->employee?->user_id === $user->id;
    }

    /**
     * Determine whether the user can create a leave request.
     */
    public function create(User $user): bool
    {
        return true; // All employees can request leave
    }

    /**
     * Determine whether the user can approve the leave request.
     */
    public function approve(User $user): bool
    {
        $roleStr = strtolower(implode(' ', $user->getRoleNames()->toArray()));
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin', 'gm', 'general_manager']) || str_contains($roleStr, 'gm') || str_contains($roleStr, 'admin') || str_contains($roleStr, 'hr');
    }

    /**
     * Determine whether the user can reject the leave request.
     */
    public function reject(User $user): bool
    {
        $roleStr = strtolower(implode(' ', $user->getRoleNames()->toArray()));
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin', 'gm', 'general_manager']) || str_contains($roleStr, 'gm') || str_contains($roleStr, 'admin') || str_contains($roleStr, 'hr');
    }
}

