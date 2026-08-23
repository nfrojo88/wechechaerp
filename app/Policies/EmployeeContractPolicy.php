<?php

namespace App\Policies;

use App\Models\User;
use App\Models\EmployeeContract;

class EmployeeContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'finance_manager', 'admin', 'global_admin', 'gm', 'general_manager']);
    }

    public function view(User $user, EmployeeContract $contract): bool
    {
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'finance_manager', 'admin', 'global_admin', 'gm', 'general_manager']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin']);
    }

    public function update(User $user, EmployeeContract $contract): bool
    {
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'admin', 'global_admin']) && in_array($contract->status, ['draft', 'approved']);
    }

    public function approve(User $user): bool
    {
        return $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'finance_manager', 'admin', 'global_admin', 'gm', 'general_manager']);
    }
}
