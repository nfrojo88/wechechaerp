<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PerformanceReview;

class PerformanceReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin', 'gm', 'general_manager']);
    }

    public function view(User $user, PerformanceReview $review): bool
    {
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin', 'gm', 'general_manager']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin']);
    }

    public function update(User $user, PerformanceReview $review): bool
    {
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin']) && $review->status === 'draft';
    }

    public function approve(User $user): bool
    {
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin', 'gm', 'general_manager']);
    }
}
