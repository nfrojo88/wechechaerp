{{--
  ═══════════════════════════════════════════════════════════════
  SHARED MOBILE STYLES — Store Manager & Store Keeper
  Include with: @push('styles') @include('layouts._store_mobile') @endpush
  ═══════════════════════════════════════════════════════════════
--}}
<style>
/* ══════════════════════════════════════════════════════════════
   STORE MODULE — Mobile Responsive System
   ══════════════════════════════════════════════════════════════ */

/* ── MD / Tablet ≤768px ────────────────────────────────────── */
@media (max-width: 768px) {

  /* Container padding tightened */
  .container-fluid.px-4 { padding-left: 12px !important; padding-right: 12px !important; }
  .container-fluid.py-3 { padding-top: 10px !important; padding-bottom: 10px !important; }

  /* ── Page Headers: stack vertically ── */
  .page-header-row {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 10px !important;
  }
  .page-header-row .header-actions {
    width: 100%;
    justify-content: flex-start !important;
  }
  .page-header-row h1,
  .page-header-row h4 { font-size: 1rem !important; }
  .page-header-row p.text-muted { font-size: 11.5px !important; }

  /* ── Dashboard Header (dash-header) ── */
  .dash-header { padding: 16px 16px 14px !important; border-radius: 12px !important; margin-bottom: 16px !important; }
  .dash-header h4 { font-size: 1rem !important; }
  .dash-header p  { font-size: 0.78rem !important; }
  .dash-header .d-flex.gap-2 { gap: 6px !important; flex-wrap: wrap; }
  .dash-header .btn-sm { font-size: 11.5px !important; padding: 5px 10px !important; }

  /* ── Financial strip: 2 column grid on mobile ── */
  .fin-strip { padding: 14px 14px !important; border-radius: 12px !important; margin-bottom: 16px !important; }
  .fin-strip .row.g-0 > [class*="col-6"] { padding: 8px 10px !important; }
  .fin-strip .fin-item { border-right: none !important; border-bottom: 1px solid rgba(255,255,255,.14) !important; padding: 6px 0 !important; }
  .fin-strip .fin-item:nth-child(odd) { border-right: 1px solid rgba(255,255,255,.14) !important; }
  .fin-strip .fin-item:last-child { border-bottom: none !important; }
  .fin-value { font-size: 1.1rem !important; }
  .fin-label { font-size: 0.62rem !important; }
  .fin-sub   { font-size: 0.68rem !important; }

  /* ── KPI Stat Cards: smaller on mobile ── */
  .kpi-card { padding: 12px 14px !important; }
  .kpi-card .kpi-icon { width: 40px !important; height: 40px !important; font-size: 1.1rem !important; }
  .kpi-value { font-size: 1.2rem !important; }
  .kpi-label { font-size: 0.62rem !important; }
  .kpi-sub   { font-size: 0.68rem !important; }

  /* ── Context Banner: stack ── */
  .context-banner .card-body.d-flex { flex-direction: column !important; align-items: flex-start !important; gap: 10px !important; }
  .context-banner .btn { width: 100%; }

  /* ── Section card header: wrap ── */
  .section-card .card-header { flex-wrap: wrap; gap: 6px; padding: 10px 14px !important; font-size: 0.8rem !important; }

  /* ── Card headers with d-flex: allow wrapping ── */
  .card-header.d-flex { flex-wrap: wrap; gap: 6px; }
  .card-header .btn { font-size: 11px; padding: 4px 10px; }

  /* ── Filter forms: stack columns ── */
  .filter-form .row > [class*="col-md"] { width: 100% !important; }
  .filter-form .col-md-3,
  .filter-form .col-md-4,
  .filter-form .col-md-5 { flex: 0 0 100% !important; max-width: 100% !important; }
  .filter-form .text-end { text-align: left !important; }
  .filter-form .d-flex.gap-2 { flex-wrap: wrap; }

  /* ── Tables: always horizontal scroll ── */
  .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .table thead th { font-size: 10px !important; padding: 8px 10px !important; white-space: nowrap; }
  .table td { padding: 9px 10px !important; font-size: 12px !important; }

  /* ── Action button columns in tables: compact ── */
  .table td:last-child .btn { padding: 4px 8px !important; font-size: 11px !important; }
  .table td .d-flex.gap-1 { gap: 4px !important; flex-wrap: wrap; }
  .table td .btn-sm { padding: 4px 8px !important; font-size: 11px !important; }

  /* ── Badges ── */
  .badge { font-size: 10px !important; padding: 3px 7px !important; }

  /* ── Store-keeper weekly demand table header ── */
  .card-header .d-flex.justify-content-between { flex-direction: column; gap: 8px; align-items: flex-start !important; }
  .card-header .btn-outline-secondary { width: 100%; text-align: center; }

  /* ── Petty cash form cards ── */
  .petty-cash-form .row > [class*="col-md"] { flex: 0 0 100% !important; max-width: 100% !important; }

  /* ── Transfer show page: info grid stacks ── */
  .transfer-info-grid .row > .col-md-6 { flex: 0 0 100% !important; max-width: 100% !important; }
  .transfer-info-grid .row > .col-md-4 { flex: 0 0 50% !important; max-width: 50% !important; }

  /* ── Fixed asset cards ── */
  .asset-info-row { flex-direction: column !important; }

  /* ── Quick action buttons: 2 col grid ── */
  .qa-grid { display: grid !important; grid-template-columns: 1fr 1fr; gap: 8px; }
  .qa-grid .qa-btn { width: 100%; justify-content: center; font-size: 12px !important; padding: 8px 10px !important; }

  /* ── Pagination: compact ── */
  .pagination { flex-wrap: wrap; gap: 2px; }
  .page-link { padding: 4px 9px !important; font-size: 11.5px !important; }

  /* ── Modal fix for store pages ── */
  .modal-dialog { margin: 8px !important; max-width: calc(100vw - 16px) !important; }
  .modal-dialog.modal-lg,
  .modal-dialog.modal-xl { max-width: calc(100vw - 16px) !important; }
  .modal-body { padding: 14px !important; }
  .modal-header { padding: 12px 16px !important; }
  .modal-footer { padding: 10px 16px !important; flex-wrap: wrap; gap: 6px; }

  /* ── Slips & print buttons: hide on mobile ── */
  .btn-print-hide { display: none !important; }
}

/* ── SM / Small Phone ≤576px ───────────────────────────────── */
@media (max-width: 576px) {
  .container-fluid.px-4 { padding-left: 8px !important; padding-right: 8px !important; }

  .dash-header { padding: 12px 12px !important; }
  .dash-header h4 { font-size: 0.92rem !important; }

  /* KPI: 2 per row layout */
  .kpi-value { font-size: 1rem !important; }
  .kpi-card .kpi-icon { width: 34px !important; height: 34px !important; font-size: 0.95rem !important; }

  /* Tables: extra compact */
  .table thead th { font-size: 9.5px !important; padding: 7px 8px !important; }
  .table td { padding: 7px 8px !important; font-size: 11.5px !important; }

  /* Fin strip: 1 col on tiny */
  .fin-value { font-size: 1rem !important; }

  /* Filter form buttons: full width */
  .filter-form button[type="submit"],
  .filter-form a.btn { width: 100%; justify-content: center; }

  /* Transfer info: all single col */
  .transfer-info-grid .row > .col-md-4 { flex: 0 0 100% !important; max-width: 100% !important; }

  /* Card header actions: full width */
  .card-header .ms-auto { margin-left: 0 !important; width: 100%; }
  .card-header .ms-auto .btn { width: 100%; text-align: center; }
}

/* ── XS / Tiny Phone ≤400px ────────────────────────────────── */
@media (max-width: 400px) {
  .dash-header h4 { font-size: 0.85rem !important; }
  .qa-grid { grid-template-columns: 1fr !important; }
  .table thead th { font-size: 9px !important; }
  .table td { font-size: 11px !important; }
  .kpi-value { font-size: 0.9rem !important; }
}
</style>
