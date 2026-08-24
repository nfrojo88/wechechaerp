<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckGuaranteeLetter
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        // Check if user has an employee record
        if ($user && $user->employee) {
            $employee = $user->employee;

            // If account is already terminated or suspended
            if ($employee->status === 'terminated' || $employee->status === 'suspended') {
                Auth::logout();
                return redirect()->route('login')->withErrors([
                    'email' => 'Your employee account is locked (' . ($employee->lock_reason ?: ucfirst($employee->status)) . '). Please contact HR.'
                ]);
            }
            
            // Check if 45-day test period has expired without renewal or guarantee letter & TIN
            if ($employee->is_test_period_expired || $employee->is_guarantee_overdue) {
                // Auto-update status to terminated/locked so it moves to employee history
                try {
                    $employee->update([
                        'status' => 'terminated',
                        'lock_reason' => '45-Day Test Period Expired: Missing Guarantee Letter or TIN Number',
                    ]);
                } catch (\Throwable $e) {}

                Auth::logout();
                
                return redirect()->route('login')->withErrors([
                    'email' => 'Your account has been locked. Your 45-day test period has ended without renewal or submission of your Guarantee Letter and TIN information. Please contact HR.'
                ]);
            }
        }
        
        return $next($request);
    }
}
