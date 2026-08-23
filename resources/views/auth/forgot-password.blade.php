<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password — {{ config('app.name', 'Wechecha Construction ERP') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
/* ── Design Tokens (matches login.blade.php) ────────── */
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
*, *::before, *::after { box-sizing: border-box; }
body {
  margin: 0;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  font-size: 14px;
  -webkit-font-smoothing: antialiased;
}

/* ── Auth Background ──────────────────────────────── */
.auth-wrapper {
  min-height: 100vh;
  background: linear-gradient(135deg, var(--brand-900) 0%, var(--brand-700) 50%, var(--brand-800) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  padding: 20px;
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

/* ── Floating particles ───────────────────────────── */
.particles { position: absolute; inset: 0; overflow: hidden; pointer-events: none; }
.particle {
  position: absolute; width: 3px; height: 3px; border-radius: 50%;
  background: rgba(245,158,11,.5); animation: rise linear infinite;
}
@keyframes rise {
  0%   { transform: translateY(100vh) scale(0); opacity: 0; }
  10%  { opacity: 1; }
  90%  { opacity: .8; }
  100% { transform: translateY(-20px) scale(1.5); opacity: 0; }
}

/* ── Auth Card ────────────────────────────────────── */
.auth-card {
  background: white;
  border-radius: var(--radius-xl);
  padding: 48px 52px;
  width: 100%;
  max-width: 460px;
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
  margin-bottom: 16px;
}
.auth-logo-icon {
  width: 72px; height: 72px;
  background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
  border-radius: var(--radius-lg);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 10px 30px rgba(30,45,69,.45);
  position: relative;
  overflow: hidden;
}
.auth-logo-icon::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,.12) 0%, transparent 60%);
}
.auth-logo-icon img {
  width: 56px; height: 56px;
  object-fit: contain;
  position: relative; z-index: 1;
}
.auth-logo-text {
  text-align: center;
  margin-bottom: 4px;
}
.auth-logo-text .title {
  font-size: 22px;
  font-weight: 800;
  color: var(--gray-900);
  letter-spacing: -.5px;
  line-height: 1;
}
.auth-logo-text .sub {
  font-size: 11px;
  color: var(--gray-400);
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-top: 5px;
}

/* ── Step indicator ───────────────────────────────── */
.step-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0;
  margin: 20px 0;
}
.step-dot {
  width: 32px; height: 32px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700;
  position: relative; z-index: 1;
}
.step-dot.active {
  background: linear-gradient(135deg, var(--brand-700), var(--brand-500));
  color: white;
  box-shadow: 0 4px 12px rgba(30,45,69,.3);
}
.step-dot.inactive {
  background: var(--gray-200);
  color: var(--gray-400);
}
.step-line {
  height: 2px; width: 40px;
  background: var(--gray-200);
}
.step-line.done { background: var(--brand-400); }

/* ── Info banner ──────────────────────────────────── */
.info-banner {
  background: var(--brand-50);
  border: 1px solid var(--brand-100);
  border-radius: var(--radius-md);
  padding: 12px 16px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 24px;
}
.info-banner i { color: var(--brand-400); font-size: 15px; margin-top: 1px; flex-shrink: 0; }
.info-banner p { margin: 0; font-size: 12.5px; color: var(--brand-500); line-height: 1.5; }

/* ── Divider ──────────────────────────────────────── */
.auth-divider {
  height: 1px;
  background: var(--gray-200);
  margin: 20px 0;
}

/* ── Form elements ────────────────────────────────── */
.form-label {
  font-size: 11.5px;
  font-weight: 700;
  color: var(--gray-600);
  margin-bottom: 7px;
  text-transform: uppercase;
  letter-spacing: .5px;
}
.input-wrapper {
  position: relative;
}
.input-wrapper .input-icon {
  position: absolute;
  left: 14px; top: 50%;
  transform: translateY(-50%);
  color: var(--gray-400);
  font-size: 15px;
  pointer-events: none;
  transition: color var(--transition);
}
.form-control {
  border: 1.5px solid var(--gray-200);
  border-radius: var(--radius-md);
  padding: 12px 14px 12px 42px;
  font-size: 14px;
  color: var(--gray-800);
  background: white;
  transition: border-color var(--transition), box-shadow var(--transition);
  font-family: 'Inter', sans-serif;
  width: 100%;
}
.form-control:focus {
  border-color: var(--brand-400);
  box-shadow: 0 0 0 3px rgba(50,81,128,.12);
  outline: none;
}
.form-control:focus ~ .input-icon,
.input-wrapper:focus-within .input-icon {
  color: var(--brand-400);
}
.form-control::placeholder { color: var(--gray-400); }
.form-control.is-invalid {
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239,68,68,.1);
}

/* ── Submit button ────────────────────────────────── */
.btn-submit {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: 14px 24px;
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
.btn-submit::before {
  content: '';
  position: absolute; inset: 0;
  background: linear-gradient(135deg, rgba(255,255,255,.1) 0%, transparent 60%);
}
.btn-submit:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 28px rgba(30,45,69,.45);
  color: white;
}
.btn-submit:active { transform: translateY(0); }
.btn-submit i { font-size: 16px; }
.btn-submit .arrow { transition: transform var(--transition); }
.btn-submit:hover .arrow { transform: translateX(4px); }

/* ── Alerts ───────────────────────────────────────── */
.alert-danger {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
  border-radius: var(--radius-md);
  padding: 12px 16px;
  font-size: 13px;
  font-weight: 500;
  display: flex; align-items: center; gap: 8px;
}
.alert-success {
  background: #d1fae5;
  color: #065f46;
  border: 1px solid #a7f3d0;
  border-radius: var(--radius-md);
  padding: 12px 16px;
  font-size: 13px;
  font-weight: 500;
  display: flex; align-items: center; gap: 8px;
}

/* ── Back link ────────────────────────────────────── */
.back-link {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 20px;
  font-size: 13px;
  color: var(--gray-400);
  text-decoration: none;
  transition: color var(--transition);
}
.back-link:hover { color: var(--brand-500); }
.back-link i { font-size: 12px; }

/* ── Footer ───────────────────────────────────────── */
.auth-footer {
  text-align: center;
  margin-top: 24px;
  padding-top: 20px;
  border-top: 1px solid var(--gray-200);
  font-size: 11px;
  color: var(--gray-400);
}

@media (max-width: 480px) {
  .auth-card { padding: 36px 24px; }
  .step-line { width: 24px; }
}
    </style>
</head>
<body>
<div class="auth-wrapper">
    <!-- Particles -->
    <div class="particles" id="particles"></div>

    <div class="auth-card">

        <!-- Logo -->
        <div class="auth-logo">
            <div class="auth-logo-icon">
                <img src="https://res.cloudinary.com/dg1ijsqx6/image/upload/v1785238806/Gemini_Generated_Image_4aap624aap624aap_1_djaxwl.png" alt="Wechecha Logo">
            </div>
        </div>
        <div class="auth-logo-text">
            <div class="title">Forgot Password?</div>
            <div class="sub">Wechecha Construction ERP</div>
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step-dot active">1</div>
            <div class="step-line"></div>
            <div class="step-dot inactive">2</div>
            <div class="step-line"></div>
            <div class="step-dot inactive">3</div>
        </div>

        <!-- Alerts -->
        @if(session('success'))
            <div class="alert-success mb-3">
                <i class="fa-solid fa-circle-check"></i>
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert-danger mb-3">
                <i class="fa-solid fa-circle-exclamation"></i>
                {{ $errors->first() }}
            </div>
        @endif

        <!-- Info Banner -->
        <div class="info-banner">
            <i class="fa-solid fa-circle-info"></i>
            <p>Enter your registered <strong>phone number</strong> or <strong>email address</strong>. We'll send a 6-digit OTP verification code instantly.</p>
        </div>

        <div class="auth-divider"></div>

        <!-- Form -->
        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="mb-4">
                <label for="phone" class="form-label">Phone Number or Email</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-mobile-screen input-icon"></i>
                    <input
                        type="text"
                        class="form-control @error('phone') is-invalid @enderror"
                        id="phone"
                        name="phone"
                        value="{{ old('phone') }}"
                        placeholder="+251 911 234 567 or email@example.com"
                        required
                        autofocus
                    >
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-paper-plane"></i>
                Send OTP Reset Code
                <i class="fa-solid fa-arrow-right arrow"></i>
            </button>
        </form>

        <!-- Back to Login -->
        <a href="{{ route('login') }}" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Sign In
        </a>

        <!-- Footer -->
        <div class="auth-footer">
            © {{ date('Y') }} Wechecha Construction &nbsp;·&nbsp; All rights reserved
        </div>
    </div>
</div>

<script>
// Particles
(function(){
    var c = document.getElementById('particles');
    for(var i=0;i<18;i++){
        var p = document.createElement('div');
        p.className = 'particle';
        p.style.cssText = 'left:'+Math.random()*100+'%;width:'+(2+Math.random()*3)+'px;height:'+(2+Math.random()*3)+'px;animation-duration:'+(7+Math.random()*10)+'s;animation-delay:'+(Math.random()*8)+'s;opacity:'+(0.3+Math.random()*0.4)+';';
        c.appendChild(p);
    }
})();
</script>
</body>
</html>
