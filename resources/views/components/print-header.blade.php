@props([
    'title'     => 'Official Report',
    'subtitle'  => null,
    'dateRange' => null,
])

<div class="wc-print-only wc-print-header">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <div class="company-title">WECHECHA CONSTRUCTION P.L.C</div>
            <div class="meta-info text-uppercase fw-semibold" style="letter-spacing: 0.5px; font-size: 8pt;">
                General Contractor &bull; Addis Ababa, Ethiopia &bull; ERP Operational Report
            </div>
            <div class="report-title mt-2">{{ $title }}</div>
            @if($subtitle)
                <div class="meta-info mt-0.5">{{ $subtitle }}</div>
            @endif
            @if($dateRange)
                <div class="meta-info mt-0.5 fw-semibold">Reporting Period: {{ $dateRange }}</div>
            @endif
        </div>
        <div class="text-end meta-info" style="font-size: 8.5pt;">
            <div><strong>Generated:</strong> {{ now()->format('d M Y, H:i') }} EAT</div>
            <div><strong>Printed By:</strong> {{ auth()->user()->name ?? 'System User' }} ({{ auth()->user()->getRoleNames()->first() ?? 'Staff' }})</div>
            <div><strong>Status:</strong> Official Audit Copy</div>
        </div>
    </div>
</div>
