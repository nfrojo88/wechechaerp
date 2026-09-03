<?php

namespace App\Policies;

use App\Models\ExpenseRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExpenseRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     * Global admins and system admins have full bypass.
     */
    public function before(User $user, $ability)
    {
        if ($user->hasAnyRole(['global_admin', 'admin'])) {
            return true;
        }
    }

    /**
     * Determine whether the user can view any expense requests.
     */
    public function viewAny(User $user): bool
    {
        return true; // Filtered at query level per role
    }

    /**
     * Determine whether the user can view the specific expense request.
     * Any authenticated staff with module access can view.
     */
    public function view(User $user, ExpenseRequest $expenseRequest): bool
    {
        return true; // Any authenticated user with module access can view
    }

    /**
     * Determine whether the user can create an expense request.
     */
    public function create(User $user): bool
    {
        return true; // Any authenticated employee can submit
    }

    /**
     * Determine whether the user can perform Employee Confirmation.
     */
    public function employeeApprove(User $user, ExpenseRequest $expenseRequest): bool
    {
        $userEmployeeId = $user->employee?->id;
        $isAssignedEmployee = (
            ($userEmployeeId && $expenseRequest->employee_id == $userEmployeeId) ||
            ($expenseRequest->employee && $expenseRequest->employee->user_id == $user->id) ||
            $user->hasAnyRole(['admin', 'global_admin'])
        );

        return $isAssignedEmployee && $expenseRequest->status === ExpenseRequest::STATUS_PENDING_EMPLOYEE;
    }

    /**
     * Determine whether the user can perform HR / Coordinator Review (Approve/Reject).
     */
    public function hrReview(User $user, ExpenseRequest $expenseRequest): bool
    {
        $roleNames = strtolower(implode(' ', $user->getRoleNames()->toArray()));
        $isHrOrCoordinator = $user->can('hr.view') 
            || str_contains($roleNames, 'hr') 
            || str_contains($roleNames, 'coordinator') 
            || $user->hasAnyRole(['coordinator', 'Coordinator', 'admin', 'global_admin']);

        return $isHrOrCoordinator && in_array($expenseRequest->status, [ExpenseRequest::STATUS_PENDING_HR, 'Pending (HR Review)']);
    }

    /**
     * Determine whether the user can perform GM Review (> 5000 ETB).
     */
    public function gmReview(User $user, ExpenseRequest $expenseRequest): bool
    {
        $roleNames = strtolower(implode(' ', $user->getRoleNames()->toArray()));
        $isGm = str_contains($roleNames, 'gm') || $user->hasRole('gm');

        return $isGm && $expenseRequest->status === 'Pending (GM Review)';
    }

    /**
     * Determine whether the user can perform Finance Assignment.
     */
    public function financeAssign(User $user, ExpenseRequest $expenseRequest): bool
    {
        $roleNames = strtolower(implode(' ', $user->getRoleNames()->toArray()));
        $isFinanceHead = $user->hasAnyRole(['finance_head', 'finance_manager', 'Finance head', 'admin', 'global_admin']) 
            || str_contains($roleNames, 'finance_head') 
            || str_contains($roleNames, 'finance_manager')
            || str_contains($roleNames, 'admin');

        return $isFinanceHead && in_array($expenseRequest->status, ['Approved - Assigned to Finance', 'Assigned to Finance', ExpenseRequest::STATUS_APPROVED_ASSIGNED, ExpenseRequest::STATUS_ASSIGNED]);
    }

    public function markPaid(User $user, ExpenseRequest $expenseRequest): bool
    {
        $isAssignedStaff = (
            (int)$expenseRequest->assigned_finance_staff_id === (int)$user->id || 
            (int)$expenseRequest->finance_staff_id === (int)$user->id
        );

        $isFinanceHead = $user->hasAnyRole(['Finance head', 'finance_head', 'finance_manager', 'admin', 'global_admin']);

        return ($isAssignedStaff || $isFinanceHead) && in_array($expenseRequest->status, ['Approved - Assigned to Finance', 'Assigned to Finance', ExpenseRequest::STATUS_APPROVED_ASSIGNED, ExpenseRequest::STATUS_ASSIGNED]);
    }

}
