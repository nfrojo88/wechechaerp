<?php

namespace App\Policies;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PayrollPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    { 
        return $user->can('hr.view') 
            || $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'Finance head', 'finance_head', 'finance_manager', 'admin', 'global_admin', 'gm', 'general_manager']); 
    }

    public function view(User $user, Payroll $p): bool
    { 
        return $user->can('hr.view') 
            || $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'Finance head', 'finance_head', 'finance_manager', 'admin', 'global_admin', 'gm', 'general_manager']); 
    }

    public function create(User $user): bool
    { 
        return $user->can('hr.manage') 
            || $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'finance_head', 'admin', 'global_admin']); 
    }

    public function update(User $user, Payroll $p): bool
    { 
        return ($user->can('hr.manage') || $user->hasAnyRole(['hr', 'hr_manager', 'hr_officer', 'finance_head', 'admin', 'global_admin'])) && $p->status === 'draft'; 
    }
}
