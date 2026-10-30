@props([
    'status' => 'draft',
    'label' => null,
    'size' => 'sm', // 'sm' or 'md'
])

@php
    $normalized = strtolower(trim((string)$status));
    
    // Status color mapping dictionary
    $statusMap = [
        // Green: Approved / Completed / Active
        'approved'   => ['bg' => 'var(--wc-success-bg)', 'text' => 'var(--wc-success-text)', 'icon' => 'fa-circle-check', 'default' => 'Approved'],
        'active'     => ['bg' => 'var(--wc-success-bg)', 'text' => 'var(--wc-success-text)', 'icon' => 'fa-circle-check', 'default' => 'Active'],
        'completed'  => ['bg' => 'var(--wc-success-bg)', 'text' => 'var(--wc-success-text)', 'icon' => 'fa-circle-check', 'default' => 'Completed'],
        'paid'       => ['bg' => 'var(--wc-success-bg)', 'text' => 'var(--wc-success-text)', 'icon' => 'fa-check',        'default' => 'Paid'],
        'cleared'    => ['bg' => 'var(--wc-success-bg)', 'text' => 'var(--wc-success-text)', 'icon' => 'fa-circle-check', 'default' => 'Cleared'],

        // Amber: Pending / In Progress / Under Review
        'pending'    => ['bg' => 'var(--wc-warning-bg)', 'text' => 'var(--wc-warning-text)', 'icon' => 'fa-clock',        'default' => 'Pending'],
        'in_progress'=> ['bg' => 'var(--wc-warning-bg)', 'text' => 'var(--wc-warning-text)', 'icon' => 'fa-spinner',      'default' => 'In Progress'],
        'submitted'  => ['bg' => 'var(--wc-warning-bg)', 'text' => 'var(--wc-warning-text)', 'icon' => 'fa-paper-plane',   'default' => 'Submitted'],
        'review'     => ['bg' => 'var(--wc-warning-bg)', 'text' => 'var(--wc-warning-text)', 'icon' => 'fa-magnifying-glass','default' => 'In Review'],
        'warning'    => ['bg' => 'var(--wc-warning-bg)', 'text' => 'var(--wc-warning-text)', 'icon' => 'fa-triangle-exclamation','default' => 'Warning'],

        // Red: Rejected / Cancelled / Terminated
        'rejected'   => ['bg' => 'var(--wc-danger-bg)',  'text' => 'var(--wc-danger-text)',  'icon' => 'fa-circle-xmark', 'default' => 'Rejected'],
        'cancelled'  => ['bg' => 'var(--wc-danger-bg)',  'text' => 'var(--wc-danger-text)',  'icon' => 'fa-ban',          'default' => 'Cancelled'],
        'terminated' => ['bg' => 'var(--wc-danger-bg)',  'text' => 'var(--wc-danger-text)',  'icon' => 'fa-user-slash',   'default' => 'Terminated'],
        'critical'   => ['bg' => 'var(--wc-danger-bg)',  'text' => 'var(--wc-danger-text)',  'icon' => 'fa-triangle-exclamation','default' => 'Critical'],
        'locked'     => ['bg' => 'var(--wc-danger-bg)',  'text' => 'var(--wc-danger-text)',  'icon' => 'fa-lock',         'default' => 'Locked'],

        // Neutral / Draft / Info
        'draft'      => ['bg' => 'var(--wc-surface-subtle)', 'text' => 'var(--wc-text-muted)', 'icon' => 'fa-file-lines', 'default' => 'Draft'],
        'settled'    => ['bg' => 'var(--wc-info-bg)',        'text' => 'var(--wc-info-text)',   'icon' => 'fa-receipt',    'default' => 'Settled'],
        'info'       => ['bg' => 'var(--wc-info-bg)',        'text' => 'var(--wc-info-text)',   'icon' => 'fa-circle-info','default' => 'Info'],
    ];

    $cfg = $statusMap[$normalized] ?? [
        'bg' => 'var(--wc-surface-subtle)', 
        'text' => 'var(--wc-text-muted)', 
        'icon' => 'fa-circle', 
        'default' => ucfirst(str_replace(['_', '-'], ' ', $status))
    ];

    $displayLabel = $label ?? $cfg['default'];
    $padding = $size === 'md' ? 'px-3 py-1 text-sm' : 'px-2 py-0.5 text-xs';
@endphp

<span {{ $attributes->merge(['class' => "badge rounded-pill d-inline-flex align-items-center gap-1.5 font-medium {$padding}"]) }}
      style="background-color: {{ $cfg['bg'] }}; color: {{ $cfg['text'] }}; border: 1px solid rgba(0,0,0,0.05); font-weight: 600; font-size: 0.75rem;">
    <i class="fa-solid {{ $cfg['icon'] }}" style="font-size: 0.65rem;"></i>
    <span>{{ $displayLabel }}</span>
</span>
