<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session Expired — Wechecha Construction ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
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
            overflow: hidden;
        }
        /* Background */
        .page-wrapper {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--brand-900) 0%, var(--brand-700) 50%, var(--brand-800) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 20px;
        }
        .blob {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            filter: blur(60px);
            opacity: 0.35;
        }
        .blob-1 {
            width: 500px; height: 500px;
            top: -120px; right: -120px;
            background: radial-gradient(circle, rgba(245,158,11,.5) 0%, transparent 70%);
            animation: float1 8s ease-in-out infinite;
        }
        .blob-2 {
            width: 400px; height: 400px;
            bottom: -100px; left: -100px;
            background: radial-gradient(circle, rgba(58,85,128,.8) 0%, transparent 70%);
            animation: float2 10s ease-in-out infinite;
        }
        .blob-3 {
            width: 300px; height: 300px;
            top: 50%; left: 50%;
            transform: translate(-50%,-50%);
            background: radial-gradient(circle, rgba(239,68,68,.2) 0%, transparent 70%);
            animation: pulse-blob 4s ease-in-out infinite;
        }
        @keyframes float1 {
            0%,100%{transform:translateY(0) scale(1);}50%{transform:translateY(30px) scale(1.05);}
        }
        @keyframes float2 {
            0%,100%{transform:translateY(0) scale(1);}50%{transform:translateY(-25px) scale(1.03);}
        }
        @keyframes pulse-blob {
            0%,100%{opacity:.2;transform:translate(-50%,-50%) scale(1);}
            50%{opacity:.4;transform:translate(-50%,-50%) scale(1.2);}
        }
        /* Particles */
        .particles { position:absolute;inset:0;overflow:hidden;pointer-events:none; }
        .particle {
            position:absolute;width:4px;height:4px;border-radius:50%;
            background:rgba(245,158,11,.6);animation:rise linear infinite;
        }
        @keyframes rise {
            0%{transform:translateY(100vh) scale(0);opacity:0;}
            10%{opacity:1;}90%{opacity:1;}
            100%{transform:translateY(-20px) scale(1.5);opacity:0;}
        }
        /* Logo */
        .brand-logo {
            position:absolute;top:28px;left:50%;transform:translateX(-50%);
            display:flex;align-items:center;gap:10px;z-index:20;
        }
        .brand-logo .logo-mark {
            width:36px;height:36px;
            background:linear-gradient(135deg,var(--accent) 0%,var(--accent-hover) 100%);
            border-radius:10px;display:flex;align-items:center;justify-content:center;
            box-shadow:0 4px 12px rgba(245,158,11,.4);
        }
        .brand-logo .logo-mark i{font-size:18px;color:#fff;}
        .brand-logo .logo-text{font-size:15px;font-weight:700;color:rgba(255,255,255,.85);}
        /* Card */
        .expired-card {
            background:rgba(255,255,255,.04);
            backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
            border:1px solid rgba(255,255,255,.1);
            border-radius:var(--radius-xl);
            padding:56px 52px 48px;
            width:100%;max-width:480px;
            text-align:center;position:relative;z-index:10;
            box-shadow:0 32px 80px rgba(0,0,0,.5),0 8px 24px rgba(0,0,0,.3),inset 0 1px 0 rgba(255,255,255,.08);
            animation:slideUp .5s cubic-bezier(.4,0,.2,1) both;
        }
        @keyframes slideUp {
            from{opacity:0;transform:translateY(32px) scale(.97);}
            to{opacity:1;transform:translateY(0) scale(1);}
        }
        /* Ring Icon */
        .icon-ring { width:100px;height:100px;margin:0 auto 28px;position:relative; }
        .icon-ring svg { width:100px;height:100px;transform:rotate(-90deg); }
        .icon-ring-bg { fill:none;stroke:rgba(255,255,255,.08);stroke-width:3; }
        .icon-ring-progress {
            fill:none;stroke:var(--accent);stroke-width:3;stroke-linecap:round;
            stroke-dasharray:283;stroke-dashoffset:283;
            animation:ring-drain 10s linear forwards;
            filter:drop-shadow(0 0 6px rgba(245,158,11,.8));
        }
        @keyframes ring-drain { to{stroke-dashoffset:0;} }
        .icon-center {
            position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
        }
        .icon-center i {
            font-size:36px;color:var(--accent);
            filter:drop-shadow(0 0 12px rgba(245,158,11,.6));
            animation:icon-pulse 2s ease-in-out infinite;
        }
        @keyframes icon-pulse {
            0%,100%{transform:scale(1);filter:drop-shadow(0 0 12px rgba(245,158,11,.6));}
            50%{transform:scale(1.12);filter:drop-shadow(0 0 20px rgba(245,158,11,.9));}
        }
        /* Badge */
        .error-badge {
            display:inline-flex;align-items:center;gap:6px;
            background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);
            color:#f87171;font-size:11px;font-weight:600;
            letter-spacing:1.5px;text-transform:uppercase;
            padding:5px 14px;border-radius:100px;margin-bottom:20px;
        }
        .error-badge .dot {
            width:6px;height:6px;background:#f87171;border-radius:50%;
            animation:blink 1.2s ease-in-out infinite;
        }
        @keyframes blink {
            0%,100%{opacity:1;}50%{opacity:.2;}
        }
        /* Typography */
        .expired-title {
            font-size:28px;font-weight:800;color:#fff;
            margin:0 0 12px;letter-spacing:-.5px;line-height:1.2;
        }
        .expired-subtitle {
            font-size:15px;color:var(--brand-100);
            line-height:1.65;margin:0 0 32px;opacity:.85;
        }
        /* Divider */
        .divider { display:flex;align-items:center;gap:12px;margin:0 0 28px; }
        .divider::before,.divider::after {
            content:'';flex:1;height:1px;background:rgba(255,255,255,.1);
        }
        .divider span {
            font-size:11px;color:rgba(255,255,255,.35);font-weight:500;
            letter-spacing:1px;text-transform:uppercase;white-space:nowrap;
        }
        /* Info Pills */
        .info-pills { display:flex;gap:10px;justify-content:center;margin-bottom:32px;flex-wrap:wrap; }
        .info-pill {
            display:flex;align-items:center;gap:7px;
            background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);
            border-radius:100px;padding:8px 16px;font-size:12px;color:var(--brand-100);
        }
        .info-pill i { font-size:13px;color:var(--accent); }
        /* Button */
        .btn-relogin {
            display:flex;align-items:center;justify-content:center;gap:10px;
            width:100%;padding:16px 24px;
            background:linear-gradient(135deg,var(--accent) 0%,var(--accent-hover) 100%);
            color:#fff;font-family:'Inter',sans-serif;font-size:15px;font-weight:700;
            text-decoration:none;border-radius:var(--radius-lg);border:none;cursor:pointer;
            transition:all var(--transition);
            box-shadow:0 8px 24px rgba(245,158,11,.35),0 2px 8px rgba(0,0,0,.2);
            letter-spacing:.3px;position:relative;overflow:hidden;
        }
        .btn-relogin::before {
            content:'';position:absolute;inset:0;
            background:linear-gradient(135deg,rgba(255,255,255,.15) 0%,transparent 60%);
            opacity:0;transition:opacity var(--transition);
        }
        .btn-relogin:hover {
            transform:translateY(-2px);color:#fff;
            box-shadow:0 14px 36px rgba(245,158,11,.45),0 4px 12px rgba(0,0,0,.25);
        }
        .btn-relogin:hover::before { opacity:1; }
        .btn-relogin:active { transform:translateY(0); }
        .btn-icon-arrow { transition:transform var(--transition); }
        .btn-relogin:hover .btn-icon-arrow { transform:translateX(4px); }
        /* Footer */
        .footer-note { margin-top:24px;font-size:12px;color:rgba(255,255,255,.3);line-height:1.6; }
        .footer-note a { color:var(--brand-200);text-decoration:none;transition:color var(--transition); }
        .footer-note a:hover { color:var(--accent); }
        #countdown-text { font-variant-numeric:tabular-nums;color:var(--accent);font-weight:700; }
        @media(max-width:520px) {
            .expired-card{padding:40px 24px 36px;}
            .expired-title{font-size:22px;}
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
    <div class="particles" id="particles"></div>

    <div class="brand-logo">
        <div class="logo-mark" style="overflow:hidden;padding:2px;">
            <img src="https://res.cloudinary.com/dg1ijsqx6/image/upload/v1785238806/Gemini_Generated_Image_4aap624aap624aap_1_djaxwl.png" alt="Logo" style="width:28px;height:28px;object-fit:contain;">
        </div>
        <span class="logo-text">Wechecha Construction ERP</span>
    </div>

    <div class="expired-card">
        <div class="icon-ring">
            <svg viewBox="0 0 100 100">
                <circle class="icon-ring-bg"      cx="50" cy="50" r="45"/>
                <circle class="icon-ring-progress" cx="50" cy="50" r="45"/>
            </svg>
            <div class="icon-center">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
        </div>

        <div class="error-badge"><span class="dot"></span>Session Expired</div>

        <h1 class="expired-title">Your page has expired</h1>
        <p class="expired-subtitle">
            Your session timed out for security reasons.<br>
            Please log in again to continue where you left off.
        </p>

        <div class="info-pills">
            <div class="info-pill"><i class="fa-solid fa-shield-halved"></i><span>Secure session</span></div>
            <div class="info-pill"><i class="fa-solid fa-lock"></i><span>Data protected</span></div>
            <div class="info-pill"><i class="fa-solid fa-rotate-right"></i><span>Redirecting in <span id="countdown-text">10</span>s</span></div>
        </div>

        <div class="divider"><span>ready to continue?</span></div>

        <a href="{{ url('/login') }}" class="btn-relogin" id="relogin-btn">
            <i class="fa-solid fa-arrow-right-to-bracket"></i>
            Re-Login to Your Account
            <i class="fa-solid fa-arrow-right btn-icon-arrow"></i>
        </a>

        <p class="footer-note">
            Your work is saved. Unsaved form inputs may have been lost.<br>
            Having trouble? <a href="mailto:support@wechechaconstruction.com">Contact support</a>
        </p>
    </div>
</div>

<script>
(function(){
    var container = document.getElementById('particles');
    for(var i=0;i<22;i++){
        var p=document.createElement('div');
        p.className='particle';
        p.style.cssText='left:'+Math.random()*100+'%;width:'+(2+Math.random()*4)+'px;height:'+(2+Math.random()*4)+'px;animation-duration:'+(6+Math.random()*10)+'s;animation-delay:'+(Math.random()*8)+'s;opacity:'+(0.3+Math.random()*0.5);
        container.appendChild(p);
    }
})();

(function(){
    var secs=10;
    var el=document.getElementById('countdown-text');
    var iv=setInterval(function(){
        secs--;
        if(el) el.textContent=secs;
        if(secs<=0){ clearInterval(iv); window.location.href='{{ url("/login") }}'; }
    },1000);
    document.getElementById('relogin-btn').addEventListener('click',function(){ clearInterval(iv); });
})();
</script>
</body>
</html>
