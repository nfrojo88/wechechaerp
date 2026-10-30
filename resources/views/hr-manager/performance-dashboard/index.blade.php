@extends('layouts.app')
@section('title', 'Staff Performance Dashboard — Wechecha ERP')

@section('content')
<div class="container-fluid px-0">
    {{-- Page Header --}}
    <x-page-header
        title="Staff Performance & Appraisal Dashboard"
        subtitle="Manage employee evaluations, quarterly review scores, competency matrix tracking, and promotion readiness ratings."
        :breadcrumbs="[
            ['label' => 'Dashboard', 'url' => route('dashboard')],
            ['label' => 'HR Management', 'url' => route('dashboard.hr')],
            ['label' => 'Performance Reviews']
        ]">
        <x-slot name="actions">
            <a href="{{ route('performance-dashboard.create-review') }}" class="btn btn-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-plus me-1.5"></i>New Review
            </a>
            <a href="{{ route('performance-dashboard.analytics') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-chart-line me-1.5"></i>Analytics
            </a>
            <a href="{{ route('performance-dashboard.export') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold">
                <i class="fa-solid fa-download me-1.5"></i>Export CSV
            </a>
        </x-slot>
    </x-page-header>

    {{-- Executive Summary KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Total Reviewed"
                :value="number_format($stats['total_reviewed'])"
                changeLabel="Appraisals finalized"
                icon="fa-solid fa-clipboard-check"
                color="success" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Pending Review"
                :value="number_format($stats['pending_review'])"
                changeLabel="Awaiting manager rating"
                icon="fa-solid fa-clock"
                color="warning" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Submitted for Approval"
                :value="number_format($stats['submitted_for_approval'])"
                changeLabel="HR/GM signoff required"
                icon="fa-solid fa-paper-plane"
                color="info" />
        </div>
        <div class="col-6 col-lg-3">
            <x-kpi-card
                title="Average Score"
                :value="number_format($stats['avg_score'] ?? 0, 2) . ' / 5.0'"
                changeLabel="Enterprise competency mean"
                icon="fa-solid fa-star"
                color="primary" />
        </div>
    </div>

    {{-- Filters Card --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('performance-dashboard.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-sm-6 col-md-5">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">Employee</label>
                    <select name="employee_id" class="form-select form-select-sm">
                        <option value="">All Employees</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>
                                {{ $emp->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-4">
                    <label class="form-label text-muted small fw-bold text-uppercase mb-1" style="font-size: 0.72rem;">Review Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Submitted</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 py-2 w-100 fw-semibold shadow-xs">
                        <i class="fa-solid fa-filter me-1.5"></i>Filter
                    </button>
                    <a href="{{ route('performance-dashboard.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs" title="Reset filter">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Reviews Table Card --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-star me-1"></i>Appraisals
                </span>
                <h6 class="fw-bold mb-0 text-dark">Staff Performance Evaluation Ledger</h6>
            </div>
            <span class="text-muted small">{{ count($reviews) }} Reviews</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 wc-table-mobile-cards">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-secondary text-uppercase small fw-bold">Employee</th>
                        <th class="text-secondary text-uppercase small fw-bold">Period Scope</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Overall Score</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Technical</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Soft Skills</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Attendance</th>
                        <th class="text-center text-secondary text-uppercase small fw-bold">Productivity</th>
                        <th class="text-secondary text-uppercase small fw-bold">Status</th>
                        <th class="pe-4 text-end text-secondary text-uppercase small fw-bold">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reviews as $review)
                        <tr>
                            <td class="ps-4" data-label="Employee">
                                <div class="fw-bold text-dark">{{ $review->employee->name }}</div>
                                <span class="badge bg-light text-muted border font-monospace" style="font-size: 0.7rem;">
                                    {{ $review->employee->code }}
                                </span>
                            </td>
                            <td data-label="Period Scope">
                                <span class="fw-semibold text-dark">{{ $review->review_period->format('M Y') }}</span>
                            </td>
                            <td class="text-center" data-label="Overall Score">
                                <span class="badge bg-{{ $review->rating_badge ?? 'primary' }} rounded-pill px-3 py-1 fs-6 font-tabular">
                                    {{ number_format($review->overall_score, 1) }}
                                </span>
                            </td>
                            <td class="text-center font-tabular text-dark" data-label="Technical">
                                {{ number_format($review->technical_skills_score, 1) }}
                            </td>
                            <td class="text-center font-tabular text-dark" data-label="Soft Skills">
                                {{ number_format($review->soft_skills_score, 1) }}
                            </td>
                            <td class="text-center font-tabular text-dark" data-label="Attendance">
                                {{ number_format($review->attendance_score, 1) }}
                            </td>
                            <td class="text-center font-tabular text-dark" data-label="Productivity">
                                {{ number_format($review->productivity_score, 1) }}
                            </td>
                            <td data-label="Status">
                                @if ($review->status === 'draft')
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-2.5 py-1">Draft</span>
                                @elseif ($review->status === 'submitted')
                                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-2.5 py-1 fw-bold">Submitted</span>
                                @else
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-2.5 py-1 fw-bold">Approved</span>
                                @endif
                            </td>
                            <td class="pe-4 text-end" data-label="Actions">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('performance-dashboard.show-review', $review->id) }}" class="btn btn-sm btn-outline-info rounded-pill px-2.5 py-1 shadow-xs" title="View Review">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    @if ($review->status === 'draft')
                                        <form method="POST" action="{{ route('performance-dashboard.submit-review', $review->id) }}" class="d-inline" onsubmit="return confirm('Submit this appraisal for approval?')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1 shadow-xs" title="Submit Review">
                                                <i class="fa-solid fa-paper-plane"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <x-empty-state
                                    title="No Performance Appraisals Found"
                                    description="No performance reviews match your filter criteria. Create a new review to evaluate staff competency."
                                    icon="fa-solid fa-star" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(method_exists($reviews, 'hasPages') && $reviews->hasPages())
            <div class="card-footer bg-white border-0 py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">
                    Showing {{ $reviews->firstItem() ?? 0 }} to {{ $reviews->lastItem() ?? 0 }} of {{ $reviews->total() }} reviews
                </span>
                <div>
                    {{ $reviews->links() }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
