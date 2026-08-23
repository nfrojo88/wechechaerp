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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PhonePasswordResetController extends Controller
{
    protected SmsEthiopiaService $smsService;

    public function __construct(SmsEthiopiaService $smsService)
    {
        $this->middleware('guest');
        $this->smsService = $smsService;
    }

    /**
     * Normalize phone number to local (09...) and international (+2519...) formats
     */
    private function normalizePhone(string $phone): array
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
     * Show the forgot password form
     */
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Process phone number or email and send OTP
     */
    public function sendResetOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:3|max:100',
        ], [
            'phone.required' => 'Please enter your registered phone number or email address.'
        ]);

        $input = trim($request->phone);
        $user = null;
        $targetPhone = null;

        // Check if input is an email address
        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $input)->first();
            if (!$user) {
                return back()->withErrors([
                    'phone' => 'No active user account found with this email address.'
                ])->withInput();
            }

            // Get phone from linked employee or user record
            if ($user->employee && $user->employee->phone) {
                $targetPhone = $user->employee->phone;
            } elseif (\Illuminate\Support\Facades\Schema::hasColumn('users', 'phone') && $user->phone) {
                $targetPhone = $user->phone;
            } else {
                return back()->withErrors([
                    'phone' => 'Your account does not have a registered phone number. Please contact system admin or HR to reset your credentials.'
                ])->withInput();
            }
        } else {
            // Treat as Phone Number or Username
            $phoneFormats = $this->normalizePhone($input);
            $intlPhone = $phoneFormats['intl'];
            $localPhone = $phoneFormats['local'];

            // 1. Check in employees table
            $employee = Employee::where('phone', $intlPhone)
                ->orWhere('phone', $localPhone)
                ->first();

            if ($employee && $employee->user_id) {
                $user = User::find($employee->user_id);
                $targetPhone = $employee->phone;
            }

            // 2. Check in users table
            if (!$user) {
                $user = User::where('email', $intlPhone)
                    ->orWhere('email', $localPhone)
                    ->orWhere('name', $input)
                    ->first();

                if ($user && $user->employee && $user->employee->phone) {
                    $targetPhone = $user->employee->phone;
                } else {
                    $targetPhone = $intlPhone;
                }
            }

            if (!$user) {
                return back()->withErrors([
                    'phone' => 'No active account found with this phone number or credential. Please contact HR.'
                ])->withInput();
            }
        }

        // Standardize the target phone
        $phoneFormats = $this->normalizePhone($targetPhone);
        $standardPhone = $phoneFormats['intl'];

        // Generate OTP
        $otp = SmsEthiopiaService::generateOTP();
        
        // Delete old OTPs for this phone
        OtpVerification::where('phone', $standardPhone)
            ->where('created_at', '<', now()->subMinutes(10))
            ->delete();

        // Create new OTP record
        OtpVerification::create([
            'phone' => $standardPhone,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'verified' => false,
        ]);

        // Send OTP via SMS
        $result = $this->smsService->sendOTP($standardPhone, $otp);

        session()->put('reset_phone', $standardPhone);
        session()->put('reset_user_id', $user->id);

        if ($result['success']) {
            return redirect()->route('password.verify')
                ->with('success', 'Password reset code sent to your phone. Please check your messages.');
        } else {
            Log::warning("SMS Failed - Showing OTP for testing", [
                'phone' => $standardPhone,
                'otp' => $otp,
                'error' => $result['message']
            ]);
            
            return redirect()->route('password.verify')
                ->with('warning', 'SMS service temporarily unavailable. Please use the code below.')
                ->with('debug_otp', $otp); // Testing fallback
        }
    }

    /**
     * Show OTP verification form
     */
    public function showVerifyOtpForm()
    {
        if (!session('reset_phone')) {
            return redirect()->route('password.request')->withErrors(['phone' => 'Session expired. Please enter your phone number first.']);
        }

        return view('auth.reset-otp');
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $phone = session('reset_phone');
        if (!$phone) {
            return redirect()->route('password.request')->withErrors(['phone' => 'Session expired. Please start again.']);
        }

        $otpRecord = OtpVerification::where('phone', $phone)
            ->where('verified', false)
            ->latest()
            ->first();

        if (!$otpRecord) {
            return back()->withErrors(['otp' => 'OTP not found. Please request a new one.']);
        }

        if ($otpRecord->attempts >= 3) {
            return back()->withErrors(['otp' => 'Too many failed attempts. Please request a new OTP.']);
        }

        if ($otpRecord->isExpired()) {
            return back()->withErrors(['otp' => 'OTP expired. Please request a new one.']);
        }

        if ($otpRecord->isValid($request->otp)) {
            $otpRecord->markAsVerified();
            session()->put('reset_verified', true);

            return redirect()->route('password.reset')
                ->with('success', 'Code verified successfully! Please create your new password.');
        } else {
            $otpRecord->incrementAttempts();
            return back()->withErrors([
                'otp' => 'Invalid verification code. Attempts remaining: ' . (3 - $otpRecord->attempts)
            ])->withInput();
        }
    }

    /**
     * Show Reset Password form
     */
    public function showResetForm()
    {
        if (!session('reset_phone') || !session('reset_verified')) {
            return redirect()->route('password.request')->withErrors(['phone' => 'Unauthorized access. Please verify your phone number.']);
        }

        return view('auth.reset-password');
    }

    /**
     * Update the password
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $phone = session('reset_phone');
        if (!$phone || !session('reset_verified')) {
            return redirect()->route('password.request')->withErrors(['phone' => 'Session expired or unauthorized. Please start again.']);
        }

        $user = null;
        if (session('reset_user_id')) {
            $user = User::find(session('reset_user_id'));
        }

        if (!$user) {
            $phoneFormats = $this->normalizePhone($phone);
            
            $employee = Employee::where('phone', $phoneFormats['intl'])
                ->orWhere('phone', $phoneFormats['local'])
                ->first();

            if ($employee && $employee->user_id) {
                $user = User::find($employee->user_id);
            } else {
                $user = User::where('email', $phoneFormats['intl'])->orWhere('email', $phoneFormats['local'])->first();
            }
        }

        if (!$user) {
            return redirect()->route('password.request')->withErrors(['phone' => 'User account not found.']);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Automatically unlock and approve employee account upon successful OTP password reset
        if ($user->employee) {
            $user->employee->update([
                'is_approved_by_gm' => true,
                'gm_approved_at'    => now(),
            ]);
        }

        session()->forget(['reset_phone', 'reset_verified', 'reset_user_id']);

        return redirect()->route('login')
            ->with('success', 'Your password has been reset successfully! Please log in with your new password.');
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request)
    {
        $phone = session('reset_phone');
        if (!$phone) {
            return redirect()->route('password.request')->withErrors(['phone' => 'Session expired. Please start again.']);
        }

        $otp = SmsEthiopiaService::generateOTP();
        OtpVerification::where('phone', $phone)->delete();

        OtpVerification::create([
            'phone' => $phone,
            'otp' => $otp,
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
        ]);

        $result = $this->smsService->sendOTP($phone, $otp);

        if ($result['success']) {
            return back()->with('success', 'A new code has been sent to your phone number.');
        } else {
            Log::warning("SMS Failed - Showing OTP for testing", [
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
            'audit_team'          => route('audit.index'),
            default               => route('dashboard.admin'),
        };
    }
}
