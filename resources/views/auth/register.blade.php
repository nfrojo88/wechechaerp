@extends('layouts.guest')

@section('title', 'Employee Registration - Construct-Pro ERP')

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
  width: 90px; height: 90px;
  border-radius: var(--radius-lg);
  display: flex; align-items: center; justify-content: center;
  overflow: hidden;
  background: transparent;
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
  font-size: 11.5px;
  color: var(--gray-400);
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-top: 4px;
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
.form-control:focus {
  border-color: var(--brand-400);
  box-shadow: 0 0 0 3px rgba(50,81,128,.14);
  outline: none;
}
.form-control::placeholder { color: var(--gray-400); }

/* ── Sign In button ───────────────────────────────── */
.btn-signin {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: 13px 24px;
  background: linear-gradient(135deg, var(--brand-700) 0%, var(--brand-500) 60%, var(--brand-400) 100%);
  color: white;
  font-size: 15px;
  font-weight: 700;
  border: none;
  border-radius: var(--radius-md);
  cursor: pointer;
  letter-spacing: -.1px;
  box-shadow: 0 4px 18px rgba(30,45,69,.35);
  transition: all var(--transition);
  margin-top: 8px;
  font-family: 'Inter', sans-serif;
  position: relative;
  overflow: hidden;
}
.btn-signin::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,.08) 0%, transparent 60%);
}
.btn-signin:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(30,45,69,.45);
  color: white;
}
.btn-signin:active { transform: translateY(0); }
.btn-signin i { font-size: 16px; }

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

/* ── Footer note ──────────────────────────────────── */
.auth-footer {
  text-align: center;
  margin-top: 28px;
  font-size: 11.5px;
  color: var(--gray-400);
}

/* ── Mobile Responsive ────────────────────────────── */
@media (max-width: 768px) {
  .auth-wrapper { align-items: flex-start; padding: 20px 12px 32px; }
  .auth-card {
    padding: 32px 24px;
    border-radius: 20px;
    box-shadow: 0 16px 48px rgba(0,0,0,.30);
    margin-top: 12px;
    margin-bottom: 12px;
  }
  .auth-logo-icon { width: 70px; height: 70px; }
  .auth-logo-text .title { font-size: 18px; }
  .auth-logo-text .sub { font-size: 10.5px; }
  .auth-divider { margin: 18px 0; }
  .form-control { font-size: 16px; padding: 12px 14px; }
  .btn-signin { font-size: 15px; padding: 14px 20px; min-height: 50px; }
  .auth-footer { font-size: 10.5px; margin-top: 20px; }
}
@media (max-width: 480px) {
  .auth-wrapper { padding: 12px 8px 24px; }
  .auth-card { padding: 28px 18px; border-radius: 16px; }
  .auth-logo-icon { width: 60px; height: 60px; }
  .auth-logo-text .title { font-size: 17px; }
  .form-label { font-size: 11px; }
  .form-control { font-size: 16px; padding: 11px 12px; }
  .btn-signin { font-size: 14px; padding: 13px 16px; }
  .auth-footer { font-size: 10px; line-height: 1.6; }
}
@media (max-width: 360px) {
  .auth-card { padding: 22px 14px; }
  .auth-logo-icon { width: 52px; height: 52px; }
  .auth-logo-text .title { font-size: 15px; }
  .btn-signin { font-size: 13.5px; }
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
                <div class="title">Employee Registration</div>
                <div class="sub">Construct-Pro ERP System</div>
            </div>
        </div>

        <div class="auth-divider"></div>

        @if(session('success'))
            <div class="alert alert-success mb-3">
                <i class="fa-solid fa-check-circle me-1"></i>
                {{ session('success') }}
            </div>
        @endif

        @error('phone')
            <div class="alert alert-danger mb-3">
                <i class="fa-solid fa-exclamation-circle me-1"></i>
                {{ $message }}
            </div>
        @enderror

        <form method="POST" action="{{ route('register.send-otp') }}">
            @csrf
            <div class="mb-3">
                <label for="phone" class="form-label">Registered Phone Number</label>
                <input
                    type="text"
                    class="form-control"
                    id="phone"
                    name="phone"
                    value="{{ old('phone') }}"
                    placeholder="+251 911 234 567"
                    required
                    autofocus
                >
                <small class="text-muted" style="font-size: 11px; display:block; margin-top: 6px;">Your phone number must be registered by HR before signing up.</small>
            </div>

            <button type="submit" class="btn-signin mt-4">
                Send Verification Code
                <i class="fa-solid fa-paper-plane"></i>
            </button>

            <div class="text-center mt-3">
                <p class="text-muted mb-0" style="font-size: 13px;">
                    Already have an account? 
                    <a href="{{ route('login') }}" class="text-decoration-none fw-semibold" style="color: var(--brand-500);">
                        Sign in here
                    </a>
                </p>
            </div>
        </form>

        <div class="auth-footer">
            © {{ date('Y') }} Wechecha Construction &nbsp;·&nbsp; All rights reserved &nbsp;·&nbsp; Developed by Nataye Technology
        </div>
    </div>
</div>
@endsection
