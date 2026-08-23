<?php

namespace App\Providers;

use App\Models\Boq;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\MaterialRequest;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\EngWorkOrder;
use App\Models\Schedule;
use App\Models\Store;
use App\Models\User;
use App\Policies\BoqPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\EngWorkOrderPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\InventoryMovementPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\MaterialRequestPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PayrollPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\SchedulePolicy;
use App\Policies\StorePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class            => UserPolicy::class,
        Store::class           => StorePolicy::class,
        Project::class         => ProjectPolicy::class,
        Product::class         => ProductPolicy::class,
        Inventory::class       => InventoryPolicy::class,
        InventoryMovement::class => InventoryMovementPolicy::class,
        // Phase 3
        Boq::class             => BoqPolicy::class,
        Schedule::class        => SchedulePolicy::class,
        // Phase 4
        MaterialRequest::class => MaterialRequestPolicy::class,
        PurchaseOrder::class   => PurchaseOrderPolicy::class,
        // Phase 5
        Employee::class        => EmployeePolicy::class,
        \App\Models\Attendance::class => \App\Policies\AttendancePolicy::class,
        \App\Models\LeaveRequest::class => \App\Policies\LeaveRequestPolicy::class,
        \App\Models\EmployeeContract::class => \App\Policies\EmployeeContractPolicy::class,
        \App\Models\PerformanceReview::class => \App\Policies\PerformanceReviewPolicy::class,
        \App\Models\ManpowerForecast::class => \App\Policies\ManpowerForecastPolicy::class,
        Payroll::class         => PayrollPolicy::class,
        Expense::class         => ExpensePolicy::class,
        Payment::class         => PaymentPolicy::class,
        // Engineer Work Scheduling
        EngWorkOrder::class    => EngWorkOrderPolicy::class,
        // Ask Money / Employee Expense Requests
        \App\Models\ExpenseRequest::class => \App\Policies\ExpenseRequestPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Grant all permissions to global_admin, admin, gm
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('global_admin') || $user->hasRole('admin') || $user->hasRole('gm')) {
                return true;
            }
        });
    }
}
