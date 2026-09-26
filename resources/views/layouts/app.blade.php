<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Wechecha Construction ERP') }}</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS (embedded for reliable deployment) -->
    <style>
/* ==========================================================================
   Construct-Pro ERP — Global Design System
   ========================================================================== */
:root {
  --brand-900: #0f1623;
  --brand-800: #1a2436;
  --brand-700: #1e2d45;
  --brand-600: #243554;
  --brand-500: #2d4168;
  --brand-400: #3a5580;
  --brand-300: #5272a0;
  --brand-200: #7a99c2;
  --brand-100: #c3d5ee;
  --brand-50:  #edf3fb;
  --accent:        #f59e0b;
  --accent-hover:  #d97706;
  --accent-light:  #fef3c7;
  --success:  #10b981;
  --danger:   #ef4444;
  --warning:  #f59e0b;
  --info:     #3b82f6;
  --gray-50:  #f9fafb;
  --gray-100: #f3f4f6;
  --gray-200: #e5e7eb;
  --gray-300: #d1d5db;
  --gray-400: #9ca3af;
  --gray-500: #6b7280;
  --gray-600: #4b5563;
  --gray-700: #374151;
  --gray-800: #1f2937;
  --gray-900: #111827;
  --sidebar-width:        260px;
  --sidebar-collapsed-w:  72px;
  --topbar-height:        64px;
  --radius-sm:   6px;
  --radius-md:   10px;
  --radius-lg:   16px;
  --radius-xl:   24px;
  --shadow-sm:   0 1px 3px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.06);
  --shadow-md:   0 4px 16px rgba(0,0,0,.10), 0 2px 6px rgba(0,0,0,.06);
  --shadow-lg:   0 10px 30px rgba(0,0,0,.12);
  --transition:  0.22s cubic-bezier(.4,0,.2,1);
}
*, *::before, *::after { box-sizing: border-box; }
html, body {
  height: 100%;
  margin: 0;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  font-size: 14px;
  background: var(--gray-100);
  color: var(--gray-800);
  -webkit-font-smoothing: antialiased;
}
/* App Shell */
.app-shell { display: flex; min-height: 100vh; }
/* Sidebar */
.sidebar {
  width: var(--sidebar-width);
  min-height: 100vh;
  background: linear-gradient(180deg, var(--brand-900) 0%, var(--brand-800) 100%);
  display: flex;
  flex-direction: column;
  position: fixed;
  left: 0; top: 0; bottom: 0;
  z-index: 1000;
  transition: width var(--transition);
  overflow: hidden;
  box-shadow: 4px 0 20px rgba(0,0,0,.25);
}
.sidebar.collapsed { width: var(--sidebar-collapsed-w); }
.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 20px 20px 16px;
  border-bottom: 1px solid rgba(255,255,255,.08);
  text-decoration: none;
  flex-shrink: 0;
}
.sidebar-brand-icon {
  width: 42px; height: 42px;
  border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
  background: transparent;
}
.sidebar-brand-icon img { width: 100%; height: 100%; object-fit: contain; }
.sidebar-brand-text {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transition: opacity var(--transition), width var(--transition);
}
.sidebar-brand-text .brand-name {
  font-size: 15px; font-weight: 700; color: white;
  white-space: nowrap; letter-spacing: -.3px;
}
.sidebar-brand-text .brand-sub {
  font-size: 10px; color: var(--brand-200);
  text-transform: uppercase; letter-spacing: .8px; white-space: nowrap;
}
.sidebar.collapsed .sidebar-brand-text { opacity: 0; width: 0; }
.sidebar-toggle-btn {
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 32px;
  margin: 12px auto;
  border-radius: var(--radius-sm);
  border: none;
  background: rgba(255,255,255,.08);
  color: var(--brand-200);
  cursor: pointer;
  transition: background var(--transition), color var(--transition);
  flex-shrink: 0;
}
.sidebar-toggle-btn:hover { background: rgba(255,255,255,.15); color: white; }
.sidebar-scroll {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 8px 0 16px;
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,.1) transparent;
}
.sidebar-scroll::-webkit-scrollbar { width: 4px; }
.sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
.sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 2px; }
.sidebar-section-label {
  font-size: 9.5px; font-weight: 700; letter-spacing: 1px;
  text-transform: uppercase; color: var(--brand-300);
  padding: 16px 20px 6px;
  white-space: nowrap; overflow: hidden;
  transition: opacity var(--transition);
}
.sidebar.collapsed .sidebar-section-label { opacity: 0; }
.sidebar-nav { list-style: none; margin: 0; padding: 0; }
.sidebar-nav-item { display: flex; flex-direction: column; }
.sidebar-nav-link {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 18px; margin: 1px 10px;
  border-radius: var(--radius-md);
  color: var(--brand-100);
  text-decoration: none;
  font-size: 13.5px; font-weight: 500;
  white-space: nowrap; overflow: hidden;
  transition: background var(--transition), color var(--transition), transform var(--transition);
  position: relative;
}
.sidebar-nav-link i {
  font-size: 15px; width: 20px; text-align: center;
  flex-shrink: 0; opacity: .8;
  transition: opacity var(--transition), transform var(--transition);
}
.sidebar-nav-link span { transition: opacity var(--transition); }
.sidebar.collapsed .sidebar-nav-link span { opacity: 0; }
.sidebar.collapsed .sidebar-nav-link { justify-content: center; padding: 10px; margin: 1px 8px; }
.sidebar.collapsed .sidebar-nav-link i { width: auto; }
.sidebar-nav-link:hover { background: rgba(255,255,255,.09); color: white; }
.sidebar-nav-link:hover i { opacity: 1; transform: scale(1.1); }
.sidebar-nav-link.active {
  background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
  color: white; font-weight: 600;
  box-shadow: 0 4px 14px rgba(245,158,11,.35);
}
.sidebar-nav-link.active i { opacity: 1; }
.sidebar-footer {
  border-top: 1px solid rgba(255,255,255,.08);
  padding: 14px 16px;
  display: flex; align-items: center; gap: 10px;
  overflow: hidden; flex-shrink: 0;
}
.sidebar-footer-avatar {
  width: 36px; height: 36px;
  background: var(--brand-500);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; font-weight: 700; font-size: 13px;
  color: white; text-transform: uppercase;
}
.sidebar-footer-info { overflow: hidden; transition: opacity var(--transition); }
.sidebar-footer-info .user-name {
  font-size: 13px; font-weight: 600; color: white;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sidebar-footer-info .user-role { font-size: 10.5px; color: var(--brand-200); white-space: nowrap; }
.sidebar.collapsed .sidebar-footer-info { opacity: 0; width: 0; }
/* Main Content */
.main-content {
  margin-left: var(--sidebar-width);
  flex: 1; min-width: 0;
  display: flex; flex-direction: column;
  transition: margin-left var(--transition);
  min-height: 100vh;
}
.app-footer { margin-top: auto; }
.sidebar.collapsed ~ .main-content { margin-left: var(--sidebar-collapsed-w); }
/* Top Header */
.top-header {
  height: var(--topbar-height);
  background: white;
  border-bottom: 1px solid var(--gray-200);
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 24px;
  position: sticky; top: 0; z-index: 100;
  box-shadow: var(--shadow-sm);
}
.top-header .breadcrumb { font-size: 13px; margin: 0; }
.top-header .breadcrumb-item a { color: var(--gray-400); text-decoration: none; }
.top-header .breadcrumb-item.active { color: var(--gray-700); font-weight: 600; }
.header-actions { display: flex; align-items: center; gap: 8px; }
.header-icon-btn {
  width: 38px; height: 38px;
  border: none; background: var(--gray-100);
  border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  color: var(--gray-500); cursor: pointer;
  transition: background var(--transition), color var(--transition);
  text-decoration: none; position: relative;
}
.header-icon-btn:hover { background: var(--gray-200); color: var(--gray-700); }
.header-badge {
  position: absolute; top: 5px; right: 5px;
  width: 8px; height: 8px;
  background: var(--danger); border-radius: 50%; border: 2px solid white;
}
.header-user-btn {
  display: flex; align-items: center; gap: 10px;
  padding: 6px 10px 6px 6px;
  border-radius: var(--radius-md);
  background: var(--gray-100); border: none; cursor: pointer;
  transition: background var(--transition);
  text-decoration: none; color: var(--gray-700);
}
.header-user-btn:hover { background: var(--gray-200); }
.header-user-avatar {
  width: 30px; height: 30px;
  background: linear-gradient(135deg, var(--brand-600), var(--brand-400));
  border-radius: var(--radius-sm);
  display: flex; align-items: center; justify-content: center;
  color: white; font-weight: 700; font-size: 12px;
}
.header-user-name { font-size: 13px; font-weight: 600; }
/* Content Wrapper */
.content-wrapper { padding: 28px 28px 40px; flex: 1; min-height: 0; }
/* Alerts */
.flash-container { margin-bottom: 20px; }
.alert {
  border: none; border-radius: var(--radius-md);
  font-size: 13.5px; font-weight: 500;
  display: flex; align-items: center; gap: 10px; padding: 14px 18px;
}
.alert-success { background: #d1fae5; color: #065f46; }
.alert-danger  { background: #fee2e2; color: #991b1b; }
.alert-warning { background: #fef3c7; color: #92400e; }
/* Cards */
.card { border: 1px solid var(--gray-200) !important; border-radius: var(--radius-lg) !important; box-shadow: var(--shadow-sm) !important; transition: box-shadow var(--transition); }
.card:hover { box-shadow: var(--shadow-md) !important; }
.card-header { background: white !important; border-bottom: 1px solid var(--gray-200) !important; border-radius: var(--radius-lg) var(--radius-lg) 0 0 !important; padding: 16px 20px !important; }
.card-body { padding: 20px !important; }
.card-footer { background: var(--gray-50) !important; border-top: 1px solid var(--gray-200) !important; border-radius: 0 0 var(--radius-lg) var(--radius-lg) !important; padding: 12px 20px !important; }
/* Stat Cards */
.stat-card { border-radius: var(--radius-lg) !important; border: none !important; padding: 22px 24px; position: relative; overflow: hidden; transition: transform var(--transition), box-shadow var(--transition); }
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg) !important; }
.stat-card .stat-icon { width: 48px; height: 48px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 16px; }
.stat-card .stat-value { font-size: 26px; font-weight: 800; line-height: 1; margin-bottom: 4px; }
.stat-card .stat-label { font-size: 12.5px; opacity: .75; font-weight: 500; }
/* Tables */
.table { font-size: 13.5px; }
.table thead th { background: var(--gray-50) !important; color: var(--gray-500); font-size: 11.5px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; border-bottom: 1px solid var(--gray-200); padding: 12px 16px; white-space: nowrap; }
.table td { padding: 13px 16px; vertical-align: middle; border-bottom: 1px solid var(--gray-100); color: var(--gray-700); }
.table tbody tr:last-child td { border-bottom: none; }
.table-hover tbody tr:hover td { background: var(--brand-50); }
.table tfoot th { padding: 12px 16px; font-size: 13px; background: var(--gray-50) !important; border-top: 2px solid var(--gray-200); }
/* Forms */
.form-label { font-size: 12.5px; font-weight: 600; color: var(--gray-600); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; }
.form-control, .form-select { border: 1.5px solid var(--gray-200); border-radius: var(--radius-md); padding: 9px 13px; font-size: 13.5px; color: var(--gray-800); transition: border-color var(--transition), box-shadow var(--transition); background: white; }
.form-control:focus, .form-select:focus { border-color: var(--brand-400); box-shadow: 0 0 0 3px rgba(50,81,128,.12); outline: none; }
.form-control::placeholder { color: var(--gray-400); }
textarea.form-control { resize: vertical; min-height: 80px; }
.form-text { font-size: 11.5px; color: var(--gray-400); margin-top: 4px; }
/* Buttons */
.btn { border-radius: var(--radius-md); font-weight: 600; font-size: 13px; padding: 9px 18px; transition: all var(--transition); display: inline-flex; align-items: center; gap: 6px; border: none; line-height: 1.4; }
.btn-sm { padding: 6px 13px; font-size: 12px; }
.btn-lg { padding: 12px 24px; font-size: 15px; }
.btn-primary { background: linear-gradient(135deg, var(--brand-600) 0%, var(--brand-500) 100%); color: white; box-shadow: 0 2px 8px rgba(30,45,69,.3); }
.btn-primary:hover { background: linear-gradient(135deg, var(--brand-700) 0%, var(--brand-600) 100%); color: white; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(30,45,69,.35); }
.btn-success { background: linear-gradient(135deg, #059669 0%, #10b981 100%); color: white; box-shadow: 0 2px 8px rgba(16,185,129,.25); }
.btn-success:hover { background: linear-gradient(135deg, #047857 0%, #059669 100%); color: white; }
.btn-danger { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); color: white; box-shadow: 0 2px 8px rgba(239,68,68,.25); }
.btn-danger:hover { background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%); color: white; }
.btn-outline-primary { border: 1.5px solid var(--brand-400) !important; color: var(--brand-500); background: transparent; }
.btn-outline-primary:hover { background: var(--brand-50); color: var(--brand-700); border-color: var(--brand-600) !important; }
.btn-outline-secondary { border: 1.5px solid var(--gray-300) !important; color: var(--gray-600); background: transparent; }
.btn-outline-secondary:hover { background: var(--gray-100); color: var(--gray-800); }
.btn-outline-danger { border: 1.5px solid #fca5a5 !important; color: #dc2626; background: transparent; }
.btn-outline-danger:hover { background: #fee2e2; color: #b91c1c; }
.btn-outline-info { border: 1.5px solid #93c5fd !important; color: #1d4ed8; background: transparent; }
.btn-outline-info:hover { background: #dbeafe; color: #1e40af; }
.btn-secondary { background: var(--gray-200); color: var(--gray-700); }
.btn-secondary:hover { background: var(--gray-300); color: var(--gray-900); }
.btn-warning { background: linear-gradient(135deg, var(--accent-hover), var(--accent)); color: white; }
.btn-warning:hover { color: white; opacity: .9; }
/* Badges */
.badge { font-weight: 600; font-size: 11px; padding: 4px 9px; border-radius: 20px; letter-spacing: .2px; }
.badge.bg-success    { background: #d1fae5 !important; color: #065f46 !important; }
.badge.bg-danger     { background: #fee2e2 !important; color: #991b1b !important; }
.badge.bg-warning    { background: #fef3c7 !important; color: #92400e !important; }
.badge.bg-primary    { background: var(--brand-100) !important; color: var(--brand-700) !important; }
.badge.bg-secondary  { background: var(--gray-200) !important; color: var(--gray-700) !important; }
.badge.bg-info       { background: #dbeafe !important; color: #1e40af !important; }
/* Pagination */
.pagination { margin: 0; gap: 4px; }
.page-link { border-radius: var(--radius-md) !important; border: 1.5px solid var(--gray-200) !important; color: var(--gray-600); font-size: 13px; font-weight: 500; padding: 6px 13px; transition: all var(--transition); }
.page-link:hover { background: var(--brand-50); color: var(--brand-600); border-color: var(--brand-200) !important; }
.page-item.active .page-link { background: var(--brand-600) !important; border-color: var(--brand-600) !important; color: white; box-shadow: 0 2px 8px rgba(30,45,69,.25); }
/* Modals */
.modal-content { border: none !important; border-radius: var(--radius-xl) !important; box-shadow: 0 25px 60px rgba(0,0,0,.2) !important; overflow: hidden; }
.modal-header { background: var(--gray-50); border-bottom: 1px solid var(--gray-200) !important; padding: 18px 24px !important; }
.modal-title { font-weight: 700; font-size: 16px; color: var(--gray-800); }
.modal-header.bg-dark .modal-title,
.modal-header.bg-primary .modal-title,
.modal-header.bg-secondary .modal-title,
.modal-header.text-white .modal-title,
.modal-header.bg-danger .modal-title,
.modal-header.bg-success .modal-title,
.modal-header[style*="linear-gradient"] .modal-title { color: #ffffff !important; }
.modal-body { padding: 24px !important; }
.modal-footer { background: var(--gray-50); border-top: 1px solid var(--gray-200) !important; padding: 16px 24px !important; gap: 8px; }

/* Opacity fallbacks for older Bootstrap or custom utility override */
.bg-primary.bg-opacity-10 { background-color: rgba(36, 53, 84, 0.08) !important; }
.bg-success.bg-opacity-10 { background-color: rgba(16, 185, 129, 0.08) !important; }
.bg-warning.bg-opacity-10 { background-color: rgba(245, 158, 11, 0.08) !important; }
.bg-info.bg-opacity-10 { background-color: rgba(14, 165, 233, 0.08) !important; }
.bg-danger.bg-opacity-10 { background-color: rgba(239, 68, 68, 0.08) !important; }
.bg-primary.bg-opacity-25 { background-color: rgba(36, 53, 84, 0.18) !important; }
/* Progress */
.progress { border-radius: 20px; background: var(--gray-200); height: 8px; overflow: hidden; }
.progress-bar { border-radius: 20px; transition: width .6s ease; }
/* Dropdowns */
.dropdown-menu { border: 1.5px solid var(--gray-200); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); padding: 8px; font-size: 13px; }
.dropdown-item { border-radius: var(--radius-md); padding: 9px 14px; color: var(--gray-700); font-weight: 500; display: flex; align-items: center; gap: 10px; transition: background var(--transition); }
.dropdown-item:hover { background: var(--brand-50); color: var(--brand-700); }
.dropdown-item i { width: 16px; text-align: center; color: var(--gray-400); }
.dropdown-divider { border-color: var(--gray-200); margin: 6px 0; }
.dropdown-header { color: var(--gray-400); font-size: 11px; text-transform: uppercase; letter-spacing: .6px; font-weight: 700; padding: 6px 14px; }
/* Page Title */
.page-title { font-size: 22px; font-weight: 800; color: var(--gray-900); letter-spacing: -.4px; margin: 0; }
/* Footer */
.app-footer { 
  background: white; 
  border-top: 1px solid rgba(0,0,0,0.05); 
  padding: 18px 28px; 
  display: flex; 
  align-items: center; 
  justify-content: space-between; 
  font-size: 13px; 
  color: var(--gray-500);
  box-shadow: 0 -4px 20px rgba(0,0,0,0.02);
  margin-top: auto;
}
.app-footer .footer-brand { font-weight: 700; color: var(--brand-600); letter-spacing: -0.2px; }
.app-footer .footer-version { background: var(--gray-100); padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; color: var(--gray-600); }
/* Auth */
.auth-wrapper { min-height: 100vh; background: linear-gradient(135deg, var(--brand-900) 0%, var(--brand-700) 50%, var(--brand-800) 100%); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
.auth-wrapper::before { content: ''; position: absolute; top: -200px; right: -200px; width: 600px; height: 600px; background: radial-gradient(circle, rgba(245,158,11,.15) 0%, transparent 70%); border-radius: 50%; }
.auth-wrapper::after { content: ''; position: absolute; bottom: -150px; left: -150px; width: 500px; height: 500px; background: radial-gradient(circle, rgba(50,81,128,.3) 0%, transparent 70%); border-radius: 50%; }
.auth-card { background: white; border-radius: var(--radius-xl); padding: 44px 48px; width: 100%; max-width: 420px; box-shadow: 0 32px 80px rgba(0,0,0,.3); position: relative; z-index: 1; }
.auth-card .auth-logo { display: flex; align-items: center; justify-content: center; gap: 14px; margin-bottom: 8px; }
.auth-card .auth-logo-icon { width: 72px; height: 72px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; overflow: hidden; background: transparent; }
.auth-card .auth-logo-icon img { width: 100%; height: 100%; object-fit: contain; }
.auth-card .auth-logo-text .title { font-size: 22px; font-weight: 800; color: var(--gray-900); line-height: 1; letter-spacing: -.4px; }
.auth-card .auth-logo-text .sub { font-size: 11px; color: var(--gray-400); text-transform: uppercase; letter-spacing: .8px; margin-top: 2px; }
/* Utilities */
.text-primary { color: var(--brand-600) !important; }
.bg-primary   { background: var(--brand-600) !important; }
.fw-bold { font-weight: 700 !important; }
.fw-semibold { font-weight: 600 !important; }
.font-monospace { font-family: 'JetBrains Mono', 'Fira Code', monospace; font-size: 12.5px; }
/* Empty state */
.empty-state { text-align: center; padding: 64px 32px; color: var(--gray-400); }
.empty-state i { font-size: 42px; margin-bottom: 16px; opacity: .4; display: block; }
.empty-state h6 { font-size: 15px; font-weight: 600; color: var(--gray-500); margin-bottom: 6px; }
.empty-state p { font-size: 13px; margin: 0; }
/* ======================================================
   MOBILE RESPONSIVE SYSTEM — Full Breakpoint Cascade
   ====================================================== */

/* Sidebar Backdrop (mobile drawer overlay) */
.sidebar-backdrop {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.55);
  z-index: 999;
  backdrop-filter: blur(2px);
  -webkit-backdrop-filter: blur(2px);
  transition: opacity .25s ease;
}
.sidebar-backdrop.active { display: block; }

/* Scrollable table wrapper auto-applied on mobile */
.table-responsive-mobile { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* Touch-friendly tap target minimum */
.btn-touch {
  min-height: 44px;
  min-width: 44px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

/* ── XL (≤1200px) ── */
@media (max-width: 1200px) {
  .stat-card .stat-value { font-size: 22px; }
  .content-wrapper { padding: 24px 20px 36px; }
}

/* ── LG (≤992px) ── */
@media (max-width: 992px) {
  :root { --sidebar-width: 240px; }
  .content-wrapper { padding: 20px 18px 32px; }
  .stat-card { padding: 18px 20px; }
  .stat-card .stat-value { font-size: 20px; }
  .page-title { font-size: 19px; }
  .app-footer { padding: 14px 20px; }
}

/* ── MD / Tablet (≤768px) ── */
@media (max-width: 768px) {
  /* Sidebar becomes off-canvas drawer */
  .sidebar {
    transform: translateX(-100%);
    width: var(--sidebar-width) !important;
    z-index: 1050;
    transition: transform .28s cubic-bezier(.4,0,.2,1);
    box-shadow: none;
  }
  .sidebar.mobile-open {
    transform: translateX(0);
    box-shadow: 8px 0 32px rgba(0,0,0,.35);
  }
  .sidebar.collapsed { width: var(--sidebar-width) !important; }
  /* Desktop collapse toggle hidden on mobile */
  .sidebar-toggle-btn { display: none !important; }

  /* Main content fills full width */
  .main-content { margin-left: 0 !important; }

  /* Top header */
  .top-header {
    padding: 0 12px;
    height: 56px;
    gap: 6px;
  }
  .header-actions { gap: 4px; }
  .header-icon-btn { width: 34px; height: 34px; }
  .header-user-btn { padding: 4px 6px; }

  /* Content wrapper */
  .content-wrapper { padding: 16px 12px 80px; }

  /* Page titles */
  .page-title { font-size: 17px; }
  h1.h3 { font-size: 1.1rem !important; }
  h5 { font-size: 0.95rem; }

  /* Cards */
  .card-body { padding: 14px !important; }
  .card-header { padding: 12px 14px !important; }
  .card-footer { padding: 10px 14px !important; }
  .stat-card { padding: 16px 14px; }
  .stat-card .stat-value { font-size: 18px; }
  .stat-card .stat-label { font-size: 11.5px; }
  .stat-card .stat-icon { width: 40px; height: 40px; font-size: 16px; }

  /* Tables: make all scrollable horizontally */
  .table-responsive,
  div:not(.table-responsive) > .table { display: block; overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .table thead th { font-size: 10.5px; padding: 10px 12px; white-space: nowrap; }
  .table td { padding: 10px 12px; font-size: 12.5px; }

  /* Buttons: comfortable touch targets */
  .btn { padding: 8px 14px; font-size: 12.5px; min-height: 38px; }
  .btn-sm { padding: 6px 10px; font-size: 12px; min-height: 34px; }

  /* Modals */
  .modal-dialog { margin: 8px; max-width: calc(100vw - 16px); }
  .modal-dialog.modal-lg { max-width: calc(100vw - 16px); }
  .modal-dialog.modal-xl { max-width: calc(100vw - 16px); }
  .modal-body { padding: 16px !important; }
  .modal-header { padding: 14px 16px !important; }
  .modal-footer { padding: 12px 16px !important; flex-wrap: wrap; }
  .modal-content { border-radius: 16px !important; }

  /* Badges: readable on small screens */
  .badge { font-size: 10.5px; }

  /* Tabs & pills */
  .nav-pills { gap: 4px; flex-wrap: wrap; }
  .nav-pills .nav-link { padding: 7px 14px; font-size: 12.5px; }
  .nav-tabs .nav-link { padding: 8px 12px; font-size: 12.5px; }

  /* Forms */
  .form-control, .form-select { font-size: 14px; padding: 10px 12px; }
  .form-label { font-size: 12px; }

  /* Flex card action rows: wrap on mobile */
  .d-flex.gap-2 { flex-wrap: wrap; }

  /* Probation alert & announcements */
  .alert.d-flex { flex-direction: column; gap: 10px !important; align-items: flex-start !important; }
  .alert.d-flex .flex-shrink-0 { align-self: flex-start; }
  .alert .d-flex.align-items-center.gap-2.flex-shrink-0 { width: 100%; justify-content: flex-start; flex-wrap: wrap; }

  /* Footer */
  .app-footer { padding: 12px 16px; font-size: 12px; flex-direction: column; gap: 4px; align-items: flex-start; }

  /* Dropdown menus: full width on small */
  .dropdown-menu { min-width: 200px !important; max-width: calc(100vw - 32px); }
  .dropdown-menu-end { right: 0 !important; left: auto !important; }

  /* Auth card */
  .auth-card { padding: 28px 20px; margin: 12px; }

  /* Empty state compact */
  .empty-state { padding: 40px 16px; }
  .empty-state i { font-size: 32px; }

  /* Pagination: compact */
  .pagination { flex-wrap: wrap; }
  .page-link { padding: 5px 10px; font-size: 12px; }
}

/* ── SM / Small Phone (≤576px) ── */
@media (max-width: 576px) {
  /* Header: slim on tiny screens */
  .top-header { height: 52px; padding: 0 10px; }
  .header-user-name { display: none !important; }
  /* Role badge in header: shorten */
  .btn[id="headerRoleButton"] .badge { max-width: 80px !important; }

  /* Content wrapper: tighter */
  .content-wrapper { padding: 12px 10px 80px; }

  /* Row gutters: tighten on tiny screens */
  .row { --bs-gutter-x: 0.5rem; }

  /* Stat cards: side by side 2-col */
  .stat-card { padding: 12px 12px; }
  .stat-card .stat-value { font-size: 16px; }
  .stat-card .stat-icon { width: 34px; height: 34px; font-size: 14px; margin-bottom: 10px; }

  /* Cards */
  .card-body { padding: 12px !important; }

  /* Modals full screen feel */
  .modal-dialog { margin: 4px; border-radius: 20px; }
  .modal-body { padding: 14px !important; }

  /* h1/h2/h3 scales */
  h1, .h1 { font-size: 1.3rem; }
  h2, .h2 { font-size: 1.15rem; }
  h3, .h3, h1.h3 { font-size: 1rem !important; }
  h4, .h4 { font-size: 0.95rem; }

  /* Buttons: full width option */
  .btn-block-xs { width: 100% !important; justify-content: center; }

  /* Tables: extra compact */
  .table thead th { font-size: 10px; padding: 8px 10px; }
  .table td { padding: 8px 10px; font-size: 12px; }

  /* Tabs: scroll horizontally if needed */
  .nav { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 4px; }
  .nav-item { flex-shrink: 0; }

  /* Footer: single line */
  .app-footer { flex-direction: row; flex-wrap: wrap; justify-content: space-between; }

  /* Alert stacks */
  .flash-container .alert { padding: 10px 12px; font-size: 12.5px; }

  /* Dropdown menus */
  .dropdown-menu { min-width: 180px !important; }
}

/* ── XS / Tiny Phone (≤400px) ── */
@media (max-width: 400px) {
  :root { --sidebar-width: 100vw; }
  .top-header { height: 50px; padding: 0 8px; }
  .content-wrapper { padding: 10px 8px 80px; }
  .card-body { padding: 10px !important; }
  .btn { font-size: 12px; padding: 7px 10px; }
  .btn-sm { font-size: 11px; padding: 5px 8px; }
  .modal-dialog { margin: 0 !important; border-radius: 0 !important; }
  .modal-content { border-radius: 0 !important; min-height: 100dvh; }
}

/* ── Print ── */
@media print {
  .sidebar, .top-header, .app-footer, .sidebar-backdrop, .btn-print-hide { display: none !important; }
  .main-content { margin-left: 0 !important; }
  .content-wrapper { padding: 10px 0 !important; }
  .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}

/* Animations */
.content-wrapper > * { animation: fadeInUp .3s ease both; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.sidebar.collapsed .sidebar-nav-link { position: relative; }
    </style>
    @stack('styles')
</head>
<body>
    {{-- Mobile sidebar backdrop / overlay --}}
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="app-shell">
        <div class="sidebar" id="sidebar">
            {{-- Mobile close button inside sidebar --}}
            <button type="button" class="d-md-none position-absolute btn text-white opacity-75" id="mobileSidebarClose"
                style="top: 10px; right: 10px; z-index: 1060; background: rgba(255,255,255,.1); border-radius: 8px; width: 32px; height: 32px; padding: 0; display: flex !important; align-items: center; justify-content: center;">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <a href="{{ url('/') }}" class="sidebar-brand">
                <div class="sidebar-brand-icon">
                    <img src="https://res.cloudinary.com/dg1ijsqx6/image/upload/v1785238806/Gemini_Generated_Image_4aap624aap624aap_1_djaxwl.png" alt="Company Logo">
                </div>
                <div class="sidebar-brand-text">
                    <span class="brand-name">Wechecha Construction</span>
                    <span class="brand-sub">ERP System</span>
                </div>
            </a>

            <button type="button" class="sidebar-toggle-btn d-none d-md-flex" id="sidebarToggle">
                <i class="fa-solid fa-bars"></i>
            </button>

            @include('layouts.sidebar')
        </div>

        <div class="main-content">
            <header class="top-header">
                <div class="d-flex align-items-center gap-3">
                    <button class="header-icon-btn d-md-none" id="mobileSidebarToggle">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1 shadow-sm px-2.5 py-1 rounded-3" id="dynamicGlobalBackBtn" onclick="dynamicGoBack()" title="Go Back">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span class="d-none d-sm-inline fs-7 fw-medium">Back</span>
                    </button>
                    <nav aria-label="breadcrumb" class="d-none d-sm-block">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ url('/') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">@yield('title', 'Overview')</li>
                        </ol>
                    </nav>
                </div>
                
                <div class="header-actions">
                    @php
                        $probationAlertEmployees = collect();
                        $probationAlertCount = 0;
                        $isHrOrGm = auth()->check() && (auth()->user()->hasAnyRole(['hr_officer', 'hr_manager', 'hr', 'gm', 'general_manager', 'admin', 'global_admin']));
                        if ($isHrOrGm) {
                            try {
                                $probationAlertEmployees = \App\Models\Employee::where('status', 'active')
                                    ->where('probation_completed', false)
                                    ->whereNotNull('date_of_joining')
                                    ->where(function($q) {
                                        $q->whereNull('guarantee_letter')
                                          ->orWhere('guarantee_letter', '')
                                          ->orWhereNull('tin_number')
                                          ->orWhere('tin_number', '');
                                    })
                                    ->get()
                                    ->filter(fn($e) => $e->days_since_joining >= 20);
                                $probationAlertCount = $probationAlertEmployees->count();
                            } catch (\Throwable $e) {}
                        }

                        $unreadLetterNotifications = collect();
                        if (auth()->check()) {
                            try {
                                $unreadLetterNotifications = \App\Models\LetterNotification::with('letter')
                                    ->where('user_id', auth()->id())
                                    ->where('is_read', false)
                                    ->latest()
                                    ->take(5)
                                    ->get();
                            } catch (\Throwable $e) {}
                        }

                        $totalAlertCount = ($isHrOrGm ? $probationAlertCount : 0) + $unreadLetterNotifications->count();
                    @endphp

                    @if($isHrOrGm && $probationAlertCount > 0)
                        <a href="{{ route('employees.index') }}?probation_alert=1" class="btn btn-sm btn-outline-warning d-flex align-items-center gap-2 fw-bold text-dark px-2.5 py-1 rounded-3 shadow-xs me-2 border-warning" title="{{ $probationAlertCount }} Employee(s) on Day 20–45 of Test Period requiring Guarantee Letter / TIN">
                            <i class="fa-solid fa-clock-rotate-left text-danger animate-pulse"></i>
                            <span class="d-none d-xl-inline">Test Period Alert</span>
                            <span class="badge bg-danger rounded-pill">{{ $probationAlertCount }}</span>
                        </a>
                    @endif

                    @if(auth()->user() && auth()->user()->hasRole(['store_keeper', 'site_engineer']))
                        <span class="badge bg-secondary me-2">Store: {{ auth()->user()->store_id ?? 'None' }}</span>
                    @endif
                    
                    <div class="dropdown">
                        <a href="#" class="header-icon-btn position-relative" data-bs-toggle="dropdown">
                            <i class="fa-regular fa-bell"></i>
                            @if($totalAlertCount > 0)
                                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle"></span>
                            @endif
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 300px; max-width: 360px;">
                            <li><h6 class="dropdown-header d-flex justify-content-between align-items-center">
                                Notifications
                                @if($totalAlertCount > 0)
                                    <span class="badge bg-warning text-dark">{{ $totalAlertCount }} Urgent</span>
                                @endif
                            </h6></li>
                            @if($isHrOrGm && $probationAlertCount > 0)
                                <li>
                                    <a class="dropdown-item py-2 bg-warning-subtle" href="{{ route('employees.index') }}?probation_alert=1">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="fa-solid fa-triangle-exclamation text-danger mt-1"></i>
                                            <div>
                                                <strong class="d-block text-dark font-size-13">{{ $probationAlertCount }} Employee(s) in Test Period Alert</strong>
                                                <small class="text-muted">Day 20–45 window: Guarantee Letter & TIN / Renewal needed before account lockout.</small>
                                            </div>
                                        </div>
                                    </a>
                                </li>
                            @endif

                            @if($unreadLetterNotifications->isNotEmpty())
                                @if($isHrOrGm && $probationAlertCount > 0)
                                    <li><hr class="dropdown-divider my-1"></li>
                                @endif
                                @foreach($unreadLetterNotifications as $notif)
                                    <li>
                                        <a class="dropdown-item py-2" href="{{ $notif->letter_id ? route('letters.show', $notif->letter_id) : '#' }}">
                                            <div class="d-flex align-items-start gap-2">
                                                <i class="fa-solid fa-envelope-open-text text-primary mt-1"></i>
                                                <div>
                                                    <strong class="d-block text-dark font-size-13">{{ $notif->letter?->subject ?? 'New Letter Notification' }}</strong>
                                                    <small class="text-muted text-wrap d-block">{{ \Illuminate\Support\Str::limit($notif->message, 70) }}</small>
                                                    <small class="text-muted" style="font-size: 0.7rem;">{{ $notif->created_at?->diffForHumans() }}</small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                @endforeach
                            @endif

                            @if($totalAlertCount === 0)
                                <li>
                                    <div class="text-center py-3 text-muted small">
                                        <i class="fa-regular fa-bell-slash d-block mb-1 fs-5 text-muted opacity-50"></i>
                                        No new notifications
                                    </div>
                                </li>
                            @endif
                        </ul>
                    </div>
                    
                    @auth
                        @php
                            $authTargetIds = array_filter([
                                auth()->id(),
                                auth()->user()->employee?->id,
                            ]);
                            $allAuthCoas = \App\Models\ChartOfAccount::whereIn('assigned_to', $authTargetIds)->get();
                            $authPettyCash = $allAuthCoas->filter(function ($coa) {
                                $code = (string) ($coa->code ?? '');
                                $name = strtolower($coa->name ?? '');
                                $subtype = strtolower($coa->subtype ?? '');
                                $type = strtolower($coa->type ?? '');

                                return str_starts_with($code, '111')
                                    || str_starts_with($code, '110')
                                    || str_contains($name, 'petty')
                                    || str_contains($name, 'cash')
                                    || str_contains($name, 'fund')
                                    || str_contains($name, 'ፔቲ')
                                    || in_array($subtype, ['cash', 'petty_cash', 'cash_equivalent'])
                                    || ($type === 'asset' && in_array($subtype, ['cash', 'current_asset', 'asset']));
                            });
                            if ($authPettyCash->isEmpty() && $allAuthCoas->isNotEmpty()) {
                                $authPettyCash = $allAuthCoas->filter(fn($c) => in_array(strtolower($c->type ?? ''), ['asset', 'expense']));
                            }
                            $authPettyCashBal = (float) $authPettyCash->sum('current_balance');
                        @endphp
                        @if($authPettyCash->isNotEmpty())
                            <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-outline-success rounded-pill px-3 d-none d-sm-inline-flex align-items-center gap-1 shadow-xs fw-bold me-2" title="Your Assigned Petty Cash Available Balance">
                                <i class="fa-solid fa-wallet text-success"></i>
                                <span class="font-monospace">Petty Cash: ETB {{ number_format($authPettyCashBal, 2) }}</span>
                            </a>
                        @endif
                    @endauth

                    @auth
                        @php
                            $headerUser = auth()->user();
                            $activeRole = $headerUser->getActiveRole();
                            $activeRoleLabel = $headerUser->getActiveRoleLabel();
                            $userRoles = $headerUser->roles;
                        @endphp
                        {{-- Header Role Switcher Button --}}
                        <div class="dropdown me-2">
                            <button class="btn btn-sm btn-outline-primary rounded-pill px-3 d-inline-flex align-items-center gap-1 shadow-xs fw-semibold" 
                                    data-bs-toggle="dropdown" 
                                    type="button" 
                                    id="headerRoleButton" 
                                    title="Active Role: {{ $activeRoleLabel }} (Click to switch)">
                                <i class="fa-solid fa-user-tag text-primary"></i>
                                <span class="d-none d-sm-inline text-muted small fw-normal">Role:</span>
                                <span class="badge bg-primary text-white text-truncate" style="max-width: 140px;">
                                    {{ $activeRoleLabel }}
                                </span>
                                @if($userRoles->count() > 1)
                                    <span class="badge bg-light text-primary border rounded-pill ms-1" title="{{ $userRoles->count() }} assigned roles">
                                        {{ $userRoles->count() }}
                                    </span>
                                @endif
                                <i class="fa-solid fa-chevron-down ms-1 text-muted" style="font-size: 0.65rem;"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 p-2" style="min-width: 280px;" aria-labelledby="headerRoleButton">
                                <li class="px-2 py-1 text-muted small fw-bold text-uppercase d-flex justify-content-between align-items-center" style="font-size: 0.68rem;">
                                    <span><i class="fa-solid fa-repeat me-1 text-primary"></i> Switch Active Role</span>
                                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill">{{ $userRoles->count() }} Assigned</span>
                                </li>
                                <li><hr class="dropdown-divider my-1"></li>
                                @if($userRoles->isNotEmpty())
                                    @foreach($userRoles as $r)
                                        @php $isCurrent = ($r->name === $activeRole); @endphp
                                        <li>
                                            <form method="POST" action="{{ route('user.switch-role') }}" class="m-0 p-0">
                                                @csrf
                                                <input type="hidden" name="role" value="{{ $r->name }}">
                                                <button type="submit" class="dropdown-item rounded-2 d-flex align-items-center justify-content-between py-2 px-2 {{ $isCurrent ? 'bg-primary text-white fw-bold active' : '' }}">
                                                    <span class="d-flex align-items-center gap-2">
                                                        <i class="fa-solid {{ $isCurrent ? 'fa-circle-check text-white' : 'fa-circle-dot text-muted' }}" style="font-size: 0.85rem;"></i>
                                                        <span class="text-truncate">{{ ucfirst(str_replace(['_', '-'], ' ', $r->name)) }}</span>
                                                    </span>
                                                    @if($isCurrent)
                                                        <span class="badge bg-white text-primary rounded-pill small" style="font-size: 0.65rem;">Active</span>
                                                    @endif
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                @else
                                    <li class="px-3 py-2 text-muted small">No roles assigned yet.</li>
                                @endif
                                <li><hr class="dropdown-divider my-1"></li>
                                <li>
                                    <a class="dropdown-item small text-primary d-flex align-items-center gap-2 py-1" href="{{ route('profile.edit') }}#role-manager-section">
                                        <i class="fa-solid fa-sliders"></i>
                                        <span>Manage My Roles in Profile</span>
                                    </a>
                                </li>
                                @if(auth()->user()->hasAnyRole(['admin', 'global_admin']))
                                    <li>
                                        <a class="dropdown-item small text-muted d-flex align-items-center gap-2 py-1" href="{{ route('admin.role-assignment.index') }}">
                                            <i class="fa-solid fa-users-gear"></i>
                                            <span>Admin: Assign All System Roles</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    @endauth

                    <div class="dropdown">
                        <a href="#" class="header-user-btn" data-bs-toggle="dropdown">
                            <div class="header-user-avatar">
                                {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                            </div>
                            <span class="header-user-name d-none d-md-block">{{ auth()->user()->name ?? 'User' }}</span>
                            <i class="fa-solid fa-chevron-down ms-1 small text-muted d-none d-md-block"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                            @if(isset($authPettyCash) && $authPettyCash->isNotEmpty())
                                <li class="px-3 py-2 bg-success bg-opacity-10 border-bottom mb-1">
                                    <div class="small text-muted fw-bold text-uppercase" style="font-size: 0.68rem;">
                                        <i class="fa-solid fa-wallet text-success me-1"></i> Assigned Petty Cash
                                    </div>
                                    <div class="fw-bold text-success font-monospace fs-6">ETB {{ number_format($authPettyCashBal, 2) }}</div>
                                    <div class="small text-muted text-truncate" style="font-size: 0.72rem; max-width: 200px;">{{ $authPettyCash->pluck('name')->implode(', ') }}</div>
                                </li>
                            @endif
                            <li class="px-3 py-2 bg-light border-bottom mb-1">
                                <div class="small text-muted fw-bold text-uppercase" style="font-size: 0.68rem;">
                                    <i class="fa-solid fa-user-tag text-primary me-1"></i> Current Role
                                </div>
                                <div class="fw-bold text-primary small d-flex align-items-center justify-content-between">
                                    <span>{{ auth()->user()->getActiveRoleLabel() }}</span>
                                    @if(auth()->user()->roles->count() > 1)
                                        <span class="badge bg-primary text-white rounded-pill" style="font-size: 0.65rem;">{{ auth()->user()->roles->count() }} Roles</span>
                                    @endif
                                </div>
                            </li>
                            <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="fa-solid fa-user me-2"></i> My Profile</a></li>
                            <li>
                                <a class="dropdown-item text-primary" href="{{ route('profile.edit') }}#role-manager-section">
                                    <i class="fa-solid fa-repeat me-2"></i> Change / Manage Role
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger"><i class="fa-solid fa-sign-out-alt me-2"></i> Logout</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="content-wrapper">
                @if($isHrOrGm && $probationAlertCount > 0)
                    <div class="alert alert-warning border-start border-4 border-warning shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3" role="alert">
                        <div class="d-flex align-items-start gap-3">
                            <div class="rounded-circle bg-warning bg-opacity-25 p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width:42px;height:42px;">
                                <i class="fa-solid fa-triangle-exclamation text-dark fa-lg"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark fs-6">
                                    ⏳ 45-Day Test Period Compliance Alert ({{ $probationAlertCount }} Employee{{ $probationAlertCount > 1 ? 's' : '' }} on Day 20–45)
                                </strong>
                                <span class="text-dark small">
                                    These employees are currently in their 20 to 45 day test/probation period. Guarantee Letter & TIN information or Renewal must be completed before the 45-day deadline to prevent automatic account lockout.
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <a href="{{ route('employees.index') }}?probation_alert=1" class="btn btn-sm btn-warning text-dark fw-bold shadow-xs">
                                <i class="fa-solid fa-users me-1"></i> Review {{ $probationAlertCount }} Employee(s)
                            </a>
                            <a href="{{ route('employees.history') }}" class="btn btn-sm btn-outline-dark">
                                <i class="fa-solid fa-clock-rotate-left me-1"></i> Employee History
                            </a>
                        </div>
                    </div>
                @endif

                {{-- Global Admin In-App Announcement Banner --}}
                @php
                    $activeAnnouncement = null;
                    try {
                        if (\Illuminate\Support\Facades\Schema::hasTable('announcements')) {
                            $activeAnnouncement = \App\Models\Announcement::activeBanner()->latest()->first();
                        }
                    } catch (\Throwable $e) {}
                @endphp

                @if($activeAnnouncement)
                    <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-3 mx-4 d-flex align-items-center justify-content-between gap-3 bg-gradient" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 5px solid #f59e0b !important;" role="alert">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-warning text-dark p-2.5 rounded-circle shadow-xs flex-shrink-0">
                                <i class="fa-solid fa-bullhorn fs-5"></i>
                            </div>
                            <div>
                                <strong class="d-block text-dark fw-bold" style="font-size: 0.95rem;">
                                    {{ $activeAnnouncement->title }}
                                </strong>
                                <div class="text-dark small" style="line-height: 1.45; white-space: pre-wrap;">{{ $activeAnnouncement->message }}</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" title="Dismiss announcement"></button>
                    </div>
                @endif

                @if(session('success'))
                    <div class="flash-container">
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>{{ session('success') }}</div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="flash-container">
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <div>{{ session('error') }}</div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                @endif

                @if(isset($errors) && $errors->any())
                    <div class="flash-container">
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <div>
                                <strong class="d-block mb-1">Please correct the following before saving:</strong>
                                <ul class="mb-0 ps-3">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                @endif

                @if(session('info'))
                    <div class="flash-container">
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-circle-info"></i>
                            <div>{{ session('info') }}</div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>
            
            <footer class="app-footer">
                <div>
                    <span class="me-1">© {{ date('Y') }}</span>
                    <span class="footer-brand"><i class="fa-solid fa-helmet-safety me-1 text-warning"></i>Wechecha Construction</span>
                    <span class="ms-1 d-none d-sm-inline">· All rights reserved · Developed by Nataye Technology</span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="footer-version">v1.0.0</span>
                </div>
            </footer>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    $(document).ready(function () {

        /* ── Desktop sidebar collapse ── */
        $('#sidebarToggle').on('click', function () {
            $('#sidebar').toggleClass('collapsed');
        });

        /* ── Mobile sidebar open ── */
        function openMobileSidebar() {
            $('#sidebar').addClass('mobile-open');
            $('#sidebarBackdrop').addClass('active');
            $('body').css('overflow', 'hidden'); // prevent scroll-behind
        }

        /* ── Mobile sidebar close ── */
        function closeMobileSidebar() {
            $('#sidebar').removeClass('mobile-open');
            $('#sidebarBackdrop').removeClass('active');
            $('body').css('overflow', '');
        }

        $('#mobileSidebarToggle').on('click', openMobileSidebar);
        $('#mobileSidebarClose').on('click', closeMobileSidebar);

        /* Close on backdrop click */
        $('#sidebarBackdrop').on('click', closeMobileSidebar);

        /* Close sidebar on navigation (mobile link click) */
        $('#sidebar .sidebar-nav-link').on('click', function () {
            if (window.innerWidth <= 768) {
                closeMobileSidebar();
            }
        });

        /* Close sidebar on Escape key */
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeMobileSidebar();
        });

        /* ── Responsive table wrappers ──
           Wrap any bare <table> inside a card on mobile for horizontal scroll */
        if (window.innerWidth <= 768) {
            $('.card-body table:not(.table-no-wrap)').each(function () {
                if (!$(this).parent().hasClass('table-responsive')) {
                    $(this).wrap('<div class="table-responsive"></div>');
                }
            });
        }

        /* ── Fix dropdown overflow on mobile ── */
        $(document).on('shown.bs.dropdown', function (e) {
            if (window.innerWidth <= 768) {
                var menu = $(e.target).find('.dropdown-menu');
                var rect = menu[0] ? menu[0].getBoundingClientRect() : null;
                if (rect && rect.right > window.innerWidth) {
                    menu.css('right', '0').css('left', 'auto');
                }
            }
        });

        /* ── Auto-dismiss flash alerts after 6 seconds ── */
        setTimeout(function () {
            $('.flash-container .alert').fadeOut(600, function () { $(this).remove(); });
        }, 6000);

    });

    function dynamicGoBack() {
        if (document.referrer && document.referrer.indexOf(window.location.host) !== -1 && document.referrer !== window.location.href) {
            window.history.back();
        } else {
            window.location.href = "{{ url('/dashboard') }}";
        }
    }
    </script>
    @stack('scripts')
</body>
</html>
