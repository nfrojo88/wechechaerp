<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - {{ config('app.name', 'Construct-Pro ERP') }}</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
*, *::before, *::after { box-sizing: border-box; }
body {
  margin: 0;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  font-size: 14px;
  -webkit-font-smoothing: antialiased;
}

/* ── Auth Wrapper ─────────────────────────────────── */
.auth-wrapper {
  min-height: 100vh;
  min-height: 100dvh; /* Dynamic viewport for mobile keyboards */
  background: linear-gradient(135deg, var(--brand-900) 0%, var(--brand-700) 50%, var(--brand-800) 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow-x: hidden;
  overflow-y: auto;  /* Allow scroll when keyboard is open */
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
  font-size: 22px;
  font-weight: 800;
  color: var(--gray-900);
  letter-spacing: -.5px;
  line-height: 1.2;
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

/* ── Footer note ──────────────────────────────────── */
.auth-footer {
  text-align: center;
  margin-top: 28px;
  font-size: 11.5px;
  color: var(--gray-400);
}

/* ── Mobile Responsive ────────────────────────────── */
/* MD — ≤768px (phones & small tablets) */
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
  /* Prevent iOS input zoom (must be ≥16px) */
  .form-control { font-size: 16px; padding: 12px 14px; padding-right: 44px; }
  .btn-signin { font-size: 15px; padding: 14px 20px; min-height: 50px; }
  .auth-footer { font-size: 10.5px; margin-top: 20px; }
}

/* SM — ≤480px (small phones) */
@media (max-width: 480px) {
  .auth-wrapper { padding: 12px 8px 24px; }
  .auth-card { padding: 28px 18px; border-radius: 16px; }
  .auth-logo-icon { width: 60px; height: 60px; }
  .auth-logo-text .title { font-size: 17px; }
  .form-label { font-size: 11px; }
  .form-control { font-size: 16px; padding: 11px 12px; }
  .btn-signin { font-size: 14px; padding: 13px 16px; }
  .auth-footer { font-size: 10px; line-height: 1.6; }
  /* Truncate very long footer on tiny screens */
  .auth-footer br { display: block; }
}

/* XS — ≤360px (very tiny phones) */
@media (max-width: 360px) {
  .auth-card { padding: 22px 14px; }
  .auth-logo-icon { width: 52px; height: 52px; }
  .auth-logo-text .title { font-size: 15px; letter-spacing: -.3px; }
  .btn-signin { font-size: 13.5px; }
}
    </style>
</head>
<body class="auth-wrapper">
    <div class="auth-card">

        {{-- Logo & Title --}}
        <div class="text-center mb-2">
            <div class="auth-logo">
                <div class="auth-logo-icon">
                    <img src="https://res.cloudinary.com/dg1ijsqx6/image/upload/v1785238806/Gemini_Generated_Image_4aap624aap624aap_1_djaxwl.png" alt="Company Logo">
                </div>
            </div>
            <div class="auth-logo-text">
                <div class="title">Wechecha Construction</div>
                <div class="sub">ERP System</div>
            </div>
        </div>

        <div class="auth-divider"></div>

        {{-- Errors --}}
        @if($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Username, Phone or Email</label>
                <input
                    type="text"
                    class="form-control"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="johndoe, name@company.com or 0911234567"
                    required
                    autofocus
                >
                <small class="text-muted" style="font-size: 11px;">You can login with your username, email or registered phone number</small>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label mb-0">Password</label>
                    <a href="{{ route('password.request') }}" class="text-decoration-none" style="font-size: 12px; color: var(--brand-500); font-weight: 500;">Forgot Password?</a>
                </div>
                <div class="position-relative">
                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        required
                        style="padding-right: 40px;"
                    >
                    <span id="togglePassword" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--gray-400);">
                        <i class="fa-regular fa-eye"></i>
                    </span>
                </div>
            </div>

            <div class="mb-4 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label text-muted" for="remember" style="font-size: 13px;">Remember me</label>
            </div>

            <button type="submit" class="btn-signin">
                Sign In to Dashboard
                <i class="fa-solid fa-arrow-right-to-bracket"></i>
            </button>

            {{-- Register Link --}}
            <div class="text-center mt-3">
                <p class="text-muted mb-0" style="font-size: 13px;">
                    New employee? 
                    <a href="{{ route('register') }}" class="text-decoration-none fw-semibold" style="color: var(--brand-500);">
                        Register here
                    </a>
                </p>
            </div>
        </form>

        <div class="auth-footer">
            © {{ date('Y') }} Wechecha Construction &nbsp;·&nbsp; All rights reserved &nbsp;·&nbsp; Developed by Nataye Technology
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>
