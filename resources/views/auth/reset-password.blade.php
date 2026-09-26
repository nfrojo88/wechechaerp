@extends('layouts.guest')

@section('title', 'Create Account - Construct-Pro ERP')

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
  max-width: 480px;
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
  width: 64px; height: 64px;
  background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
  border-radius: var(--radius-lg);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 10px 30px rgba(30,45,69,.45);
}
.auth-logo-icon i {
  color: var(--accent);
  font-size: 28px;
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

/* ── Mobile ───────────────────────────────────────── */
@media (max-width: 480px) {
  .auth-card { padding: 36px 28px; }
}
</style>

<div class="auth-wrapper">
    <div class="auth-card">

        <div class="text-center mb-2">
            <div class="auth-logo">
                <div class="auth-logo-icon" style="background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 10px 30px rgba(16, 185, 129, 0.45);">
                    <i class="fa-solid fa-key" style="color: white;"></i>
                </div>
            </div>
            <div class="auth-logo-text">
                <div class="title">Create New Password</div>
                <div class="sub">
                    <span style="color: #10b981; font-weight: 600;"><i class="fa-solid fa-check-circle me-1"></i> Phone Verified</span>
                    <br>Set up your new password
                </div>
            </div>
        </div>

        <div class="auth-divider"></div>

        @if($errors->any())
            <div class="alert alert-danger mb-3">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input
                    type="password"
                    class="form-control"
                    id="password"
                    name="password"
                    placeholder="••••••••"
                    required
                >
                <small class="text-muted" style="font-size: 11px; display:block; margin-top: 6px;">Must be at least 8 characters long.</small>
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label">Confirm Password</label>
                <input
                    type="password"
                    class="form-control"
                    id="password_confirmation"
                    name="password_confirmation"
                    placeholder="••••••••"
                    required
                >
            </div>

            <button type="submit" class="btn-signin mt-2">
                Save New Password
                <i class="fa-solid fa-save"></i>
            </button>
        </form>

        <div class="auth-footer">
            © {{ date('Y') }} Construct-Pro ERP &nbsp;·&nbsp; All rights reserved
        </div>
    </div>
</div>
@endsection

