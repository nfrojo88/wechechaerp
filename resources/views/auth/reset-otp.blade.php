@extends('layouts.guest')

@section('title', 'Verify OTP - Construct-Pro ERP')

@section('content')
<style>
/* ── Design Tokens ────────────────────────────────── */
:root {
  --brand-900: #0f1623;
  --brand-800: #1a2436;
  --brand-700: #1e2d45;
  --brand-600: #243554;
  --brand-500: #2d4168;
  --brand-400: #3a5580;
  --brand-200: #7a99c2;
  --brand-100: #c3d5ee;
  --brand-50:  #edf3fb;
  --accent:       #f59e0b;
  --accent-hover: #d97706;
  --gray-200: #e5e7eb;
  --gray-400: #9ca3af;
  --gray-600: #4b5563;
  --gray-800: #1f2937;
  --gray-900: #111827;
  --success-bg: #d1fae5;
  --success-text: #065f46;
  --radius-sm:  6px;
  --radius-md:  10px;
  --radius-lg:  16px;
  --radius-xl:  24px;
  --transition: 0.22s cubic-bezier(.4,0,.2,1);
}

/* ── Auth Wrapper ─────────────────────────────────── */
.auth-wrapper {
  min-height: 100vh;
  min-height: 100dvh;
  background: linear-gradient(135deg, var(--brand-900) 0%, var(--brand-700) 50%, var(--brand-800) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow-x: hidden;
  overflow-y: auto;
  padding: 24px 16px;
}
.auth-wrapper::before {
  content: '';
  position: absolute;
  top: -200px; right: -200px;
  width: 600px; height: 600px;
  background: radial-gradient(circle, rgba(245,158,11,.18) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}
.auth-wrapper::after {
  content: '';
  position: absolute;
  bottom: -150px; left: -150px;
  width: 500px; height: 500px;
  background: radial-gradient(circle, rgba(50,81,128,.35) 0%, transparent 70%);
  border-radius: 50%;
  pointer-events: none;
}

/* ── Auth Card ────────────────────────────────────── */
.auth-card {
  background: white;
  border-radius: var(--radius-xl);
  padding: 48px 52px;
  width: 100%;
  max-width: 440px;
  box-shadow: 0 32px 80px rgba(0,0,0,.35), 0 8px 24px rgba(0,0,0,.15);
  position: relative;
  z-index: 1;
  animation: slideUp .4s cubic-bezier(.4,0,.2,1) both;
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(24px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Logo ─────────────────────────────────────────── */
.auth-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
}
.auth-logo-icon {
  width: 80px; height: 80px;
  border-radius: var(--radius-lg);
  display: flex; align-items: center; justify-content: center;
  overflow: hidden;
  background: transparent;
  box-shadow: none;
}
.auth-logo-icon img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.auth-logo-text {
  text-align: center;
  margin-bottom: 6px;
}
.auth-logo-text .title {
  font-size: 24px;
  font-weight: 800;
  color: var(--gray-900);
  letter-spacing: -.5px;
  line-height: 1;
}
.auth-logo-text .sub {
  font-size: 12px;
  color: var(--gray-600);
  margin-top: 6px;
  line-height: 1.5;
}
.auth-divider {
  height: 1px;
  background: var(--gray-200);
  margin: 24px 0;
}

/* ── Form elements ────────────────────────────────── */
.form-label {
  font-size: 12px;
  font-weight: 700;
  color: var(--gray-600);
  margin-bottom: 7px;
  text-transform: uppercase;
  letter-spacing: .5px;
  text-align: center;
  display: block;
}
.form-control {
  border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-md);
  padding: 11px 14px;
  font-size: 14px;
  color: var(--gray-800);
  background: white;
  transition: border-color var(--transition), box-shadow var(--transition);
  font-family: 'Inter', sans-serif;
  width: 100%;
}

.otp-input {
  font-size: 28px;
  font-weight: 700;
  letter-spacing: 12px;
  text-align: center;
  padding: 16px;
  font-family: monospace;
}

.otp-input:focus {
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
  outline: none;
}
.otp-input::placeholder { color: var(--gray-400); letter-spacing: 12px; }

/* ── Sign In button ───────────────────────────────── */
.btn-verify {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: 13px 24px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  font-size: 15px;
  font-weight: 700;
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  letter-spacing: -.1px;
  box-shadow: 0 4px 18px rgba(16, 185, 129, 0.35);
  transition: all var(--transition);
  margin-top: 8px;
  font-family: 'Inter', sans-serif;
  position: relative;
  overflow: hidden;
}
.btn-verify::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,.08) 0%, transparent 60%);
}
.btn-verify:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(16, 185, 129, 0.45);
  color: white;
}
.btn-verify:active { transform: translateY(0); }
.btn-verify i { font-size: 16px; }

/* ── Error alert ──────────────────────────────────── */
.alert-danger {
  background: #fee2e2;
  color: #991b1b;
  border: none;
  border-radius: var(--radius-md);
  padding: 12px 16px;
  font-size: 13px;
  font-weight: 500;
}
.alert-success {
  background: #d1fae5;
  color: #065f46;
  border: none;
  border-radius: var(--radius-md);
  padding: 12px 16px;
  font-size: 13px;
  font-weight: 500;
}

/* ── Timer ────────────────────────────────────────── */
.timer-section {
  text-align: center;
  margin-bottom: 20px;
}
.timer-box {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 16px;
  background: var(--brand-50);
  border-radius: 50px;
  color: var(--brand-500);
  font-weight: 600;
  font-size: 13px;
  border: 1px solid var(--brand-100);
}
.btn-resend {
  background: none;
  border: none;
  color: var(--brand-500);
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  padding: 0;
}
.btn-resend:hover { text-decoration: underline; color: var(--brand-700); }
.btn-resend:disabled { color: var(--gray-400); cursor: not-allowed; text-decoration: none; }

/* ── Footer note ──────────────────────────────────── */
.auth-footer {
  text-align: center;
  margin-top: 28px;
  font-size: 11.5px;
  color: var(--gray-400);
}

/* ── Mobile ───────────────────────────────────────── */
@media (max-width: 480px) {
  .auth-card { padding: 36px 28px; }
  .otp-input { font-size: 24px; letter-spacing: 8px; }
}
</style>

<div class="auth-wrapper">
    <div class="auth-card">

        <div class="text-center mb-2">
            <div class="auth-logo">
                <div class="auth-logo-icon">
                    <img src="https://res.cloudinary.com/dg1ijsqx6/image/upload/v1785238806/Gemini_Generated_Image_4aap624aap624aap_1_djaxwl.png" alt="Company Logo">
                </div>
            </div>
            <div class="auth-logo-text">
                <div class="title">Verify Phone</div>
                <div class="sub">
                    Enter the 6-digit code sent to<br>
                    <strong style="color: var(--brand-600); font-size: 13px;">{{ session('reset_phone') }}</strong>
                </div>
            </div>
        </div>

        <div class="auth-divider"></div>

        @if(session('success'))
            <div class="alert alert-success mb-3">
                <i class="fa-solid fa-check-circle me-1"></i>
                {{ session('success') }}
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-danger mb-3" style="background: #fef3c7; color: #92400e;">
                <i class="fa-solid fa-exclamation-triangle me-1"></i>
                {{ session('warning') }}
            </div>
        @endif

        @if(session('debug_otp'))
            <div class="alert alert-danger mb-3" style="background: #ffedd5; color: #9a3412; border: 1px solid #fdba74;">
                <div class="fw-bold"><i class="fa-solid fa-bug"></i> DEBUG MODE</div>
                <div class="mt-1" style="font-size: 20px; font-family: monospace; letter-spacing: 4px; text-align: center;">
                    {{ session('debug_otp') }}
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('password.verify') }}" id="otpForm">
            @csrf
            
            <div class="mb-4">
                <label for="otp" class="form-label">Enter 6-Digit Code</label>
                <input
                    type="text"
                    class="form-control otp-input @error('otp') is-invalid @enderror"
                    id="otp"
                    name="otp"
                    value="{{ old('otp') }}"
                    placeholder="••••••"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    required
                    autofocus
                >
                @error('otp')
                    <div class="alert alert-danger mt-2 mb-0 py-2 text-center">
                        <i class="fa-solid fa-exclamation-circle"></i> {{ $message }}
                    </div>
                @enderror
            </div>

            <div class="timer-section">
                <div class="timer-box">
                    <i class="fa-regular fa-clock"></i>
                    <span id="timer">10:00</span>
                </div>
                <div class="mt-2 text-muted" style="font-size: 13px;">
                    Didn't receive code? 
                    <button type="button" class="btn-resend" id="resendBtn" disabled>
                        Resend <span id="resendTimer">(60s)</span>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-verify mt-2">
                Verify Code
                <i class="fa-solid fa-check-circle"></i>
            </button>

            <div class="text-center mt-3">
                <a href="{{ route('password.request') }}" class="text-decoration-none" style="color: var(--gray-500); font-size: 13px; font-weight: 500;">
                    <i class="fa-solid fa-arrow-left me-1"></i> Use different phone number
                </a>
            </div>
        </form>

        <div class="auth-footer">
            © {{ date('Y') }} Construct-Pro ERP &nbsp;·&nbsp; All rights reserved
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const otpInput = document.getElementById('otp');
    const timerDisplay = document.getElementById('timer');
    const resendBtn = document.getElementById('resendBtn');
    const resendTimer = document.getElementById('resendTimer');
    
    otpInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
    
    let timeLeft = 600;
    const expiryInterval = setInterval(function() {
        timeLeft--;
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        if (timeLeft <= 0) {
            clearInterval(expiryInterval);
            alert('OTP code has expired. Please request a new code.');
            window.location.href = '{{ route('password.request') }}';
        }
    }, 1000);
    
    let resendTimeLeft = 60;
    const resendInterval = setInterval(function() {
        resendTimeLeft--;
        resendTimer.textContent = `(${resendTimeLeft}s)`;
        
        if (resendTimeLeft <= 0) {
            clearInterval(resendInterval);
            resendBtn.disabled = false;
            resendTimer.textContent = '';
        }
    }, 1000);
    
    resendBtn.addEventListener('click', function() {
        if (confirm('Request a new verification code?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('password.resend') }}';
            
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);
            
            document.body.appendChild(form);
            form.submit();
        }
    });
});
</script>
@endsection

