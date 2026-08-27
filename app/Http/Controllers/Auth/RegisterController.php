<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Employee;
use App\Models\OtpVerification;
use App\Services\SmsEthiopiaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    protected $smsService;

    public function __construct(SmsEthiopiaService $smsService)
    {
        $this->middleware('guest');
        $this->smsService = $smsService;
    }

    /**
     * Normalize phone number to local (09...) and international (+2519...) formats
     */
    private function normalizePhone($phone)
    {
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        if (preg_match('/^0(9|7)\d{8}$/', $phone)) {
            return [
                'local' => $phone, 
                'intl' => '+251' . substr($phone, 1)
            ];
        }
        
        if (preg_match('/^\+251(9|7)\d{8}$/', $phone)) {
            return [
                'local' => '0' . substr($phone, 4), 
                'intl' => $phone
            ];
        }

        if (preg_match('/^251(9|7)\d{8}$/', $phone)) {
            return [
                'local' => '0' . substr($phone, 3), 
                'intl' => '+' . $phone
            ];
        }

        return ['local' => $phone, 'intl' => $phone];
    }

    /**
     * Show registration form
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Send OTP to phone number
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:10|max:15',
        ]);

        $phoneFormats = $this->normalizePhone($request->phone);
        $intlPhone = $phoneFormats['intl'];
        $localPhone = $phoneFormats['local'];

        // Check if phone exists in employees table (both formats)
        $employee = Employee::where('phone', $intlPhone)
            ->orWhere('phone', $localPhone)
            ->first();

        if (!$employee) {
            return back()->withErrors([
                'phone' => 'Phone number not registered. Please contact HR to register your employee profile first.'
            ])->withInput();
        }

        // Check if employee already has an active user account
        if ($employee->user_id && User::find($employee->user_id)) {
            return back()->withErrors([
                'phone' => 'An active account is already registered for this employee. Please login or use "Forgot Password".'
            ])->withInput();
        }

        // Generate OTP
        $otp = SmsEthiopiaService::generateOTP();
        
        // Delete old OTPs for this phone (use the intl phone as standard for the session)
        OtpVerification::where('phone', $intlPhone)
            ->where('created_at', '<', now()->subMinutes(10))
            ->delete();

        // Create new OTP
        OtpVerification::create([
            'phone' => $intlPhone,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        // Try to send OTP via SMS (we send to the intl format)
        $result = $this->smsService->sendOTP($intlPhone, $otp);

        session()->put('phone', $intlPhone);

        if ($result['success']) {
            return redirect()->route('register.verify-otp')
                ->with('success', 'OTP sent to your phone number. Please check your messages.');
        } else {
            // SMS FAILED
            \Log::warning("SMS Failed - Showing OTP for testing", [
                'phone' => $intlPhone,
                'otp' => $otp,
                'error' => $result['message']
            ]);
            
            return redirect()->route('register.verify-otp')
                ->with('warning', 'SMS service temporarily unavailable. Please contact support for your verification code.')
                ->with('debug_otp', $otp); // TEMPORARY: For testing only
        }
    }

    /**
     * Show OTP verification form
     */
    public function showVerifyOtpForm()
    {
        if (!session('phone')) {
            return redirect()->route('register')->withErrors(['phone' => 'Session expired. Please enter your phone number first.']);
        }

        return view('auth.verify-otp');
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $phone = session('phone');
        if (!$phone) {
            return redirect()->route('register')->withErrors(['phone' => 'Session expired. Please start again.']);
        }

        // Get latest OTP for this phone
        $otpRecord = OtpVerification::where('phone', $phone)
            ->where('verified', false)
            ->latest()
            ->first();

        if (!$otpRecord) {
            return back()->withErrors(['otp' => 'OTP not found. Please request a new one.']);
        }

        // Check attempts
        if ($otpRecord->attempts >= 3) {
            return back()->withErrors(['otp' => 'Too many failed attempts. Please request a new OTP.']);
        }

        // Check if expired
        if ($otpRecord->isExpired()) {
            return back()->withErrors(['otp' => 'OTP expired. Please request a new one.']);
        }

        // Verify OTP
        if ($otpRecord->isValid($request->otp)) {
            // Mark as verified
            $otpRecord->markAsVerified();

            // Redirect to create password
            return redirect()->route('register.create-password')
                ->with('success', 'Phone number verified successfully! Please create your credentials.');
        } else {
            // Increment attempts
            $otpRecord->incrementAttempts();

            return back()->withErrors([
                'otp' => 'Invalid OTP. Attempts remaining: ' . (3 - $otpRecord->attempts)
            ])->withInput();
        }
    }

    /**
     * Show create password form
     */
    public function showCreatePasswordForm()
    {
        if (!session('phone')) {
            return redirect()->route('register')->withErrors(['phone' => 'Session expired. Please start again.']);
        }

        return view('auth.create-password');
    }

    /**
     * Create password and complete registration
     */
    public function createPassword(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $phone = session('phone');
        if (!$phone) {
            return redirect()->route('register')->withErrors(['phone' => 'Session expired. Please start again.']);
        }

        $phoneFormats = $this->normalizePhone($phone);

        // Get employee
        $employee = Employee::where('phone', $phoneFormats['intl'])
            ->orWhere('phone', $phoneFormats['local'])
            ->first();

        if (!$employee) {
            return redirect()->route('register')->withErrors(['phone' => 'Employee record not found. Please contact HR.']);
        }

        try {
            DB::beginTransaction();

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                // Normally we'd set phone_verified_at, but the user table might not have it.
                // We'll rely on standard fields.
            ]);

            // Link employee to user
            $employee->update(['user_id' => $user->id]);

            // Assign default role (you can customize this)
            if (!$user->hasAnyRole()) {
                // Assign role based on department or default to 'employee'
                $role = 'employee'; // Default role
                
                if ($employee->department === 'HR') {
                    $role = 'hr-manager';
                } elseif ($employee->role_title && stripos($employee->role_title, 'engineer') !== false) {
                    $role = 'site-engineer';
                }
                
                try {
                    $user->assignRole($role);
                } catch (\Exception $e) {
                    // If role doesn't exist, just continue
                }
            }

            DB::commit();

            // Clear session
            session()->forget('phone');

            // Login user
            auth()->login($user);

            return redirect()->intended($this->redirectTo())
                ->with('success', 'Registration completed successfully! Welcome to Construct-Pro ERP.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withErrors([
                'password' => 'Failed to complete registration: ' . $e->getMessage()
            ])->withInput();
        }
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request)
    {
        $phone = session('phone');
        if (!$phone) {
            return redirect()->route('register')->withErrors(['phone' => 'Session expired. Please start again.']);
        }

        // Generate new OTP
        $otp = SmsEthiopiaService::generateOTP();
        
        // Delete old OTPs for this phone
        OtpVerification::where('phone', $phone)->delete();

        // Create new OTP
        OtpVerification::create([
            'phone' => $phone,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        // Try to send OTP via SMS
        $result = $this->smsService->sendOTP($phone, $otp);

        if ($result['success']) {
            return back()->with('success', 'A new OTP has been sent to your phone number.');
        } else {
            \Log::warning("SMS Failed - Showing OTP for testing", [
                'phone' => $phone,
                'otp' => $otp,
                'error' => $result['message']
            ]);
            
            return back()
                ->with('warning', 'SMS service temporarily unavailable.')
                ->with('debug_otp', $otp);
        }
    }

    /**
     * Redirect to the correct dashboard based on role
     */
    protected function redirectTo()
    {
        $user = auth()->user();
        if (!$user || !$user->roles || $user->roles->isEmpty()) {
            return '/';
        }

        $rawRole = $user->roles->first()->name;
        $role = strtolower(str_replace([' ', '-'], '_', trim($rawRole)));
        
        return match($role) {
            'global_admin', 'admin' => route('dashboard.admin'),
            'gm'                  => route('dashboard.gm'),
            'planning'            => route('dashboard.planning'),
            'planning_manager'    => route('dashboard.planning'),
            'technical_manager'   => route('dashboard.planning'),
            'coordinator'         => route('dashboard.coordinator'),
            'site_engineer'       => route('dashboard.site-engineer'),
            'foreman'             => route('dashboard.foreman'),
            'store_manager', 'storemanager', 'store' => \Illuminate\Support\Facades\Route::has('store-manager.dashboard') ? route('store-manager.dashboard') : url('/store-manager'),
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
            default               => route('dashboard.admin'),
        };

    }
}
