@extends('layouts.app')

@section('title', 'My Correspondence Letters - Wechacha ERP')

@section('content')
<div class="container-fluid py-3" style="max-width: 1200px;">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h3 class="fw-bold mb-1">
                <i class="fa-solid fa-file-pen text-primary me-2"></i>My Letters
            </h3>
            <p class="text-muted small mb-0">Write official correspondence, save drafts, and submit directly to the Secretary Inbox.</p>
        </div>
        <div>
            <a href="{{ route('letters.my-letters.create') }}" class="btn btn-primary shadow-sm px-3 fw-semibold">
                <i class="fa-solid fa-plus-circle me-1"></i> Write Letter
            </a>
        </div>
    </div>

    {{-- Feedback Messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-start border-4 border-success shadow-xs mb-4" role="alert">
            <i class="fa-solid fa-circle-check text-success me-2 fs-5 align-middle"></i>
            <span>{{ session('success') }}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-start border-4 border-danger shadow-xs mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation text-danger me-2 fs-5 align-middle"></i>
            <span>{{ session('error') }}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Metrics Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Total Letters</div>
                        <h3 class="fw-bold mb-0 text-primary mt-1">{{ $stats['total'] }}</h3>
                    </div>
                    <div class="bg-primary-subtle text-primary rounded-circle p-3 fs-4">
                        <i class="fa-solid fa-folder-open"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100 border-start border-4 border-secondary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Drafts</div>
                        <h3 class="fw-bold mb-0 text-secondary mt-1">{{ $stats['draft'] }}</h3>
                    </div>
                    <div class="bg-secondary-subtle text-secondary rounded-circle p-3 fs-4">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Sent to Secretary</div>
                        <h3 class="fw-bold mb-0 text-warning mt-1">{{ $stats['sent'] }}</h3>
                    </div>
                    <div class="bg-warning-subtle text-warning rounded-circle p-3 fs-4">
                        <i class="fa-solid fa-paper-plane"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 h-100 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Registered</div>
                        <h3 class="fw-bold mb-0 text-success mt-1">{{ $stats['registered'] }}</h3>
                    </div>
                    <div class="bg-success-subtle text-success rounded-circle p-3 fs-4">
                        <i class="fa-solid fa-stamp"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('letters.my-letters.index') }}" class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search subject, body, ref #..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>Sent to Secretary</option>
                        <option value="registered" {{ request('status') === 'registered' ? 'selected' : '' }}>Registered</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        @foreach(\App\Models\Letter::CATEGORIES as $cat)
                            <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Filter</button>
                    @if(request()->hasAny(['status', 'category', 'search', 'date_from', 'date_to']))
                        <a href="{{ route('letters.my-letters.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Letters Table Card --}}
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-muted small text-uppercase fw-bold" style="letter-spacing: 0.5px;">
                    <tr>
                        <th class="ps-3 py-3" style="width: 25%;">Subject</th>
                        <th class="py-3" style="width: 15%;">Reference #</th>
                        <th class="py-3" style="width: 15%;">Category</th>
                        <th class="py-3" style="width: 15%;">Addressed / Handled By</th>
                        <th class="py-3 text-center" style="width: 12%;">Status</th>
                        <th class="py-3" style="width: 10%;">Date</th>
                        <th class="pe-3 py-3 text-end" style="width: 8%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($letters as $letter)
                        <tr>
                            {{-- Subject --}}
                            <td class="ps-3">
                                <div class="fw-bold text-dark mb-0 text-truncate" style="max-width: 280px;" title="{{ $letter->subject }}">
                                    <a href="{{ route('letters.show', $letter->id) }}" class="text-decoration-none text-dark hover-primary">
                                        {{ $letter->subject }}
                                    </a>
                                </div>
                                <div class="small text-muted text-truncate" style="max-width: 280px;">
                                    {{ \Illuminate\Support\Str::limit(strip_tags($letter->specification), 45) }}
                                </div>
                                @if($letter->attachments->isNotEmpty())
                                    <span class="badge bg-light text-secondary border mt-1" style="font-size: 0.7rem;">
                                        <i class="fa-solid fa-paperclip me-1"></i>{{ $letter->attachments->count() }} attachment(s)
                                    </span>
                                @endif
                            </td>

                            {{-- Reference Number --}}
                            <td>
                                @if($letter->letter_number)
                                    <span class="font-monospace fw-bold text-primary">{{ $letter->letter_number }}</span>
                                    @if($letter->is_reference_locked)
                                        <i class="fa-solid fa-lock text-muted ms-1" style="font-size: 0.75rem;" title="Locked sequential number"></i>
                                    @endif
                                @else
                                    <span class="badge bg-light text-muted border font-monospace">Pending Assignment</span>
                                @endif
                            </td>

                            {{-- Category --}}
                            <td>
                                @if($letter->category)
                                    @php
                                        $catBadgeClass = match($letter->category) {
                                            'Leave Letter' => 'bg-info-subtle text-info border border-info',
                                            'Advance Loan Letter' => 'bg-warning-subtle text-warning-emphasis border border-warning',
                                            'Payment' => 'bg-success-subtle text-success border border-success',
                                            'Government' => 'bg-danger-subtle text-danger border border-danger',
                                            'Bank & Insurance' => 'bg-primary-subtle text-primary border border-primary',
                                            default => 'bg-secondary-subtle text-secondary border'
                                        };
                                    @endphp
                                    <span class="badge {{ $catBadgeClass }} rounded-pill px-2.5 py-1">
                                        {{ $letter->category }}
                                    </span>
                                @else
                                    <span class="text-muted small fst-italic">Pending Category</span>
                                @endif
                            </td>

                            {{-- Handled / Addressed Person --}}
                            <td>
                                @if($letter->addressedTo)
                                    <div class="fw-semibold text-dark small">
                                        <i class="fa-solid fa-user-check text-success me-1"></i>{{ $letter->addressedTo->name }}
                                    </div>
                                    <div class="text-muted" style="font-size: 0.72rem;">{{ $letter->addressedTo->email }}</div>
                                @else
                                    <span class="text-muted small fst-italic">Pending Assignment</span>
                                @endif
                            </td>

                            {{-- Status Badge --}}
                            <td class="text-center">
                                @if($letter->status === \App\Models\Letter::STATUS_DRAFT)
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary rounded-pill px-2.5 py-1">
                                        <i class="fa-solid fa-file-pen me-1"></i>Draft
                                    </span>
                                @elseif($letter->status === \App\Models\Letter::STATUS_SENT || $letter->status === \App\Models\Letter::STATUS_PENDING)
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning rounded-pill px-2.5 py-1" title="Sent directly to Secretary Inbox">
                                        <i class="fa-solid fa-paper-plane me-1"></i>Sent
                                    </span>
                                @elseif($letter->status === \App\Models\Letter::STATUS_REGISTERED)
                                    <span class="badge bg-success-subtle text-success border border-success rounded-pill px-2.5 py-1">
                                        <i class="fa-solid fa-stamp me-1"></i>Registered
                                    </span>
                                @elseif($letter->status === \App\Models\Letter::STATUS_CLOSED)
                                    <span class="badge bg-dark text-white rounded-pill px-2.5 py-1">
                                        <i class="fa-solid fa-check-double me-1"></i>Closed
                                    </span>
                                @else
                                    <span class="badge bg-info-subtle text-info border border-info rounded-pill px-2.5 py-1">
                                        {{ ucfirst($letter->status) }}
                                    </span>
                                @endif
                            </td>

                            {{-- Date --}}
                            <td>
                                <div class="text-dark small fw-medium">{{ optional($letter->created_at)->format('d M Y') }}</div>
                                <small class="text-muted" style="font-size: 0.72rem;">{{ optional($letter->created_at)->format('h:i A') }}</small>
                            </td>

                            {{-- Actions --}}
                            <td class="pe-3 text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('letters.show', $letter->id) }}" class="btn btn-outline-secondary" title="View Letter">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>

                                    @if($letter->status === \App\Models\Letter::STATUS_DRAFT)
                                        <a href="{{ route('letters.my-letters.edit', $letter->id) }}" class="btn btn-outline-primary" title="Edit Draft">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>

                                        <form action="{{ route('letters.my-letters.send', $letter->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Send this letter directly to the Secretary Inbox now?');">
                                            @csrf
                                            <button type="submit" class="btn btn-success" title="Send to Secretary">
                                                <i class="fa-solid fa-paper-plane"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted mb-3">
                                    <i class="fa-solid fa-envelope-open-text fa-3x text-secondary opacity-50"></i>
                                </div>
                                <h6 class="fw-bold text-dark">No correspondence letters found</h6>
                                <p class="text-muted small mb-3">You have not composed any letters matching the current filter.</p>
                                <a href="{{ route('letters.my-letters.create') }}" class="btn btn-sm btn-primary">
                                    <i class="fa-solid fa-plus-circle me-1"></i> Write a Letter Now
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($letters->hasPages())
            <div class="card-footer bg-white border-top p-3 d-flex justify-content-end">
                {{ $letters->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
