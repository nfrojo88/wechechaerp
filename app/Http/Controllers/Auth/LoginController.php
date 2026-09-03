<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // Validate input
        $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required'],
        ]);

        $identifier = $request->email;
        $password = $request->password;
        $user = null;

        // 1. Try to find user by exact email or exact username (name column)
        $user = \App\Models\User::where('email', $identifier)
            ->orWhere('name', $identifier)
            ->first();

        // 2. If not found, try to find user by phone number
        if (!$user) {
            $phoneDigits = preg_replace('/[^\d+]/', '', $identifier);
            
            if (!empty($phoneDigits)) {
                $intlPhone = $phoneDigits;
                $localPhone = $phoneDigits;

                if (preg_match('/^0(9|7)\d{8}$/', $phoneDigits)) {
                    $intlPhone = '+251' . substr($phoneDigits, 1);
                } elseif (preg_match('/^\+251(9|7)\d{8}$/', $phoneDigits)) {
                    $localPhone = '0' . substr($phoneDigits, 4);
                } elseif (preg_match('/^251(9|7)\d{8}$/', $phoneDigits)) {
                    $intlPhone = '+' . $phoneDigits;
                    $localPhone = '0' . substr($phoneDigits, 3);
                }

                // First check users table if phone column exists
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'phone')) {
                    $user = \App\Models\User::where('phone', $intlPhone)
                        ->orWhere('phone', $localPhone)
                        ->first();
                }

                // If not in users, check employees
                if (!$user) {
                    $employee = \App\Models\Employee::where('phone', $intlPhone)
                        ->orWhere('phone', $localPhone)
                        ->first();
                    
                    if ($employee && $employee->user_id) {
                        $user = \App\Models\User::find($employee->user_id);
                    }
                }
            }
        }

        // 3. Attempt authentication using the resolved user's true email/password
        if ($user && Auth::attempt(['email' => $user->email, 'password' => $password])) {
            $request->session()->regenerate();
            
            // Check guarantee letter restriction
            $authUser = Auth::user();
            if ($authUser->employee && $authUser->employee->isGuaranteeLetterExpired()) {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Your account has been suspended. Please submit your guarantee letter to HR to regain access.',
                ])->onlyInput('email');
            }

            // Check GM approval restriction (only for unapproved employees without assigned system roles)
            if ($authUser->employee && !$authUser->employee->is_approved_by_gm && $authUser->roles()->count() === 0 && $authUser->employee->created_at) {
                // Check if 1 week has passed since registration
                if ($authUser->employee->created_at->diffInDays(now()) >= 7) {
                    Auth::logout();
                    return back()->withErrors([
                        'email' => 'Your account has been locked. Please contact your GM for approval.',
                    ])->onlyInput('email');
                }
            }
            
            return redirect()->intended($this->redirectTo());
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    protected function redirectTo()
    {
        $user = auth()->user();
        if (!$user || !$user->roles || $user->roles->isEmpty()) {
            return route('pending-role');
        }

        $rawRole = $user->roles->first()->name;
        $role = strtolower(str_replace([' ', '-'], '_', trim($rawRole)));
        
        return match($role) {
            'global_admin', 'admin' => route('dashboard.admin'),
            'gm', 'general_manager' => route('dashboard.gm'),
            'planning'            => route('dashboard.planning'),
            'planning_manager'    => route('dashboard.planning'),
            'technical_manager'   => route('dashboard.planning'),
            'coordinator'         => route('dashboard.coordinator'),
            'site_engineer'       => route('dashboard.site-engineer'),
            'foreman'             => route('dashboard.foreman'),
            'store_manager', 'storemanager', 'store' => \Illuminate\Support\Facades\Route::has('dashboard.store-manager') ? route('dashboard.store-manager') : url('/dashboard/store-manager'),
            'store_keeper', 'storekeeper'           => \Illuminate\Support\Facades\Route::has('dashboard.store-keeper') ? route('dashboard.store-keeper') : url('/dashboard/store-keeper'),
            'hr', 'hr_officer'    => route('dashboard.hr'),
            'finance', 'finance_head' => route('dashboard.finance'),
            'purchase', 'purchase_manager', 'market_research' => route('dashboard.purchase'),
            'contract_admin'      => route('dashboard.contract-admin'),
            'bid_team'            => route('bidding.index'),
            'secretary'           => route('dashboard.secretary'),
            'general_service', 'general_services' => route('dashboard.general_service'),
            'law'                 => route('subcon.index'),
            'marketing'           => route('marketing.dashboard'),
            'auditor', 'audit', 'internal_auditor', 'audit_team' => \Illuminate\Support\Facades\Route::has('dashboard.audit') ? route('dashboard.audit') : url('/dashboard/audit'),
            default               => route('pending-role'),
        };

    }
}
