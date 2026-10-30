@extends('layouts.app')

@section('title', 'Correspondence Dashboard — Letters Management')

@push('styles')
<style>
    @media print {
        .wc-no-print { display: none !important; }
        .card { border: 1px solid #e2e8f0 !important; box-shadow: none !important; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-3 px-md-4 py-3">

    {{-- Header --}}
    <x-page-header 
        title="Correspondence & Letter Management" 
        subtitle="Manage incoming & outgoing official letters, routing, attachments, and departmental forwarding workflows">
        <x-slot:actions>
            @if(auth()->user()->hasAnyRole(['admin', 'global_admin', 'secretary']))
            <a href="{{ route('letters.create') }}" class="btn btn-primary btn-sm rounded-pill px-3 shadow-xs fw-bold">
                <i class="fa-solid fa-plus me-1"></i>New Letter
            </a>
            @endif
            <a href="{{ route('letters.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-xs">
                <i class="fa-solid fa-inbox me-1"></i>My Inbox
                @if(($metrics['my_inbox'] ?? 0) > 0)
                    <span class="badge bg-danger ms-1 font-tabular">{{ $metrics['my_inbox'] }}</span>
                @endif
            </a>
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-xs wc-no-print" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i>Print
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- Metric Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Total Correspondence" 
                value="{{ number_format($metrics['total']) }}" 
                icon="fa-solid fa-envelopes-bulk" 
                variant="primary" 
                helpText="Archived & active official letters" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Incoming Letters" 
                value="{{ number_format($metrics['incoming']) }}" 
                icon="fa-solid fa-inbox" 
                variant="info" 
                helpText="External letters received" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Outgoing Letters" 
                value="{{ number_format($metrics['outgoing']) }}" 
                icon="fa-solid fa-paper-plane" 
                variant="warning" 
                helpText="Dispatched company letters" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-kpi-card 
                label="Pending Review" 
                value="{{ number_format($metrics['pending']) }}" 
                icon="fa-solid fa-clock" 
                variant="{{ $metrics['pending'] > 0 ? 'danger' : 'success' }}" 
                helpText="{{ $metrics['pending'] > 0 ? 'Awaiting action or routing' : 'All correspondence cleared' }}" />
        </div>
    </div>

    {{-- Recent Correspondence & Side Panels --}}
    <div class="row g-4 mb-4">
        {{-- Recent Correspondence Table --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold" style="color: var(--brand-900);">
                        <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Recent Correspondence
                    </h6>
                    <a href="{{ route('letters.index', ['tab' => 'all']) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-xs">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                            <thead class="table-light text-muted small text-uppercase" style="letter-spacing: 0.5px;">
                                <tr>
                                    <th class="ps-4 py-3">Letter #</th>
                                    <th class="py-3 text-center">Type</th>
                                    <th class="py-3">Subject</th>
                                    <th class="py-3">Date</th>
                                    <th class="py-3">Sender / Recipient</th>
                                    <th class="py-3 text-center">Status</th>
                                    <th class="pe-4 py-3 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentLetters as $letter)
                                <tr>
                                    <td class="ps-4 py-3" data-label="Letter #">
                                        <a href="{{ route('letters.show', $letter->id) }}" class="fw-bold text-decoration-none font-monospace" style="color: var(--brand-900);">
                                            {{ $letter->letter_number }}
                                        </a>
                                        @if($letter->priority === 'urgent')
                                            <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.65rem;">URGENT</span>
                                        @endif
                                    </td>
                                    <td class="py-3 text-center" data-label="Type">
                                        @if($letter->type === 'incoming')
                                            <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2.5 py-1">
                                                <i class="fa-solid fa-arrow-down-left me-1"></i>Incoming
                                            </span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2.5 py-1">
                                                <i class="fa-solid fa-arrow-up-right me-1"></i>Outgoing
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3" data-label="Subject">
                                        <div class="fw-semibold text-truncate text-dark" style="max-width: 200px;" title="{{ $letter->subject }}">
                                            {{ $letter->subject }}
                                        </div>
                                    </td>
                                    <td class="py-3 font-tabular small text-muted" data-label="Date">
                                        {{ $letter->date ? $letter->date->format('M d, Y') : '-' }}
                                    </td>
                                    <td class="py-3 small text-dark" data-label="Party">
                                        {{ $letter->type === 'incoming' ? ($letter->sender ?? 'External') : ($letter->recipient_organization ?? 'External') }}
                                    </td>
                                    <td class="py-3 text-center" data-label="Status">
                                        @php
                                            $badgeClass = match($letter->status) {
                                                'pending'    => 'bg-warning-subtle text-warning border border-warning-subtle',
                                                'viewed'     => 'bg-info-subtle text-info border border-info-subtle',
                                                'redirected' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                                'closed'     => 'bg-success-subtle text-success border border-success-subtle',
                                                default      => 'bg-secondary-subtle text-secondary border border-secondary-subtle'
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }} rounded-pill px-2.5 py-1">{{ ucfirst($letter->status) }}</span>
                                    </td>
                                    <td class="pe-4 py-3 text-end" data-label="Action">
                                        <a href="{{ route('letters.show', $letter->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1 shadow-xs">
                                            <i class="fa-solid fa-eye me-1"></i>View
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <x-empty-state 
                                            icon="fa-solid fa-folder-open" 
                                            title="No Correspondence Records Found" 
                                            message="Inbound and outbound corporate letters will appear here once registered." />
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Actions & Status Summary Card --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <h6 class="fw-bold mb-0" style="color: var(--brand-900);">
                        <i class="fa-solid fa-bolt text-warning me-2"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body p-4">
                    @if(auth()->user()->hasAnyRole(['admin', 'global_admin', 'secretary']))
                    <div class="d-grid gap-2 mb-3">
                        <a href="{{ route('letters.create', ['type' => 'incoming']) }}" class="btn btn-info text-white fw-bold py-2 rounded-pill shadow-xs">
                            <i class="fa-solid fa-file-import me-1"></i>Register Incoming Letter
                        </a>
                        <a href="{{ route('letters.create', ['type' => 'outgoing']) }}" class="btn btn-warning text-dark fw-bold py-2 rounded-pill shadow-xs">
                            <i class="fa-solid fa-file-export me-1"></i>Register Outgoing Letter
                        </a>
                    </div>
                    @endif
                    <div class="d-grid gap-2">
                        <a href="{{ route('letters.index', ['tab' => 'inbox']) }}" class="btn btn-outline-primary py-2 rounded-pill shadow-xs">
                            <i class="fa-solid fa-inbox me-1"></i>Open My Letters Inbox
                        </a>
                        <a href="{{ route('letters.index', ['tab' => 'sent']) }}" class="btn btn-outline-secondary py-2 rounded-pill shadow-xs">
                            <i class="fa-solid fa-paper-plane me-1"></i>View Letters Created by Me
                        </a>
                    </div>
                </div>
            </div>

            {{-- Breakdown Card --}}
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <h6 class="fw-bold mb-0" style="color: var(--brand-900);">
                        <i class="fa-solid fa-chart-pie text-primary me-2"></i>Status Distribution
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-muted"><i class="fa-solid fa-clock text-warning me-1"></i> Pending Review</span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill font-tabular fw-bold">{{ $metrics['pending'] }}</span>
                    </div>
                    <div class="progress rounded-pill mb-3" style="height: 6px;">
                        <div class="progress-bar bg-warning" style="width: {{ $metrics['total'] > 0 ? round(($metrics['pending'] / $metrics['total']) * 100) : 0 }}%"></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-muted"><i class="fa-solid fa-check-double text-success me-1"></i> Closed / Reviewed</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-tabular fw-bold">{{ $metrics['closed'] }}</span>
                    </div>
                    <div class="progress rounded-pill mb-3" style="height: 6px;">
                        <div class="progress-bar bg-success" style="width: {{ $metrics['total'] > 0 ? round(($metrics['closed'] / $metrics['total']) * 100) : 0 }}%"></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="small text-muted"><i class="fa-solid fa-arrow-down-left text-info me-1"></i> Incoming vs Outgoing</span>
                        <span class="small fw-bold text-dark font-tabular">{{ $metrics['incoming'] }} / {{ $metrics['outgoing'] }}</span>
                    </div>
                    <div class="progress rounded-pill" style="height: 6px;">
                        <div class="progress-bar bg-info" style="width: {{ $metrics['total'] > 0 ? round(($metrics['incoming'] / $metrics['total']) * 100) : 50 }}%"></div>
                        <div class="progress-bar bg-warning" style="width: {{ $metrics['total'] > 0 ? round(($metrics['outgoing'] / $metrics['total']) * 100) : 50 }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
