@extends('layouts.app')

@section('title', 'Damaged Assets — Store Manager')

@section('content')
<div class="container-fluid py-4">

    {{-- ── Header ── --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h4 fw-bold mb-0">
                <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>
                Damaged / Unrepairable Assets
            </h1>
            <p class="text-muted small mb-0 mt-1">
                Assets sent by General Service that could not be repaired. Review and take final action.
            </p>
        </div>
        <a href="{{ route('store-manager.fixed-assets.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Fixed Assets
        </a>
    </div>

    {{-- ── Flash Messages ── --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ── Search ── --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('store-manager.damaged-assets.index') }}" class="d-flex gap-2 flex-wrap align-items-center">
                <input type="text" name="search" class="form-control form-control-sm" style="max-width:280px;"
                       placeholder="Search by request no, asset name, code..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Search
                </button>
                @if(request('search'))
                    <a href="{{ route('store-manager.damaged-assets.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-rotate-left me-1"></i> Clear
                    </a>
                @endif
            </form>
        </div>
    </div>

    {{-- ── Requests Table ── --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <div class="d-flex align-items-center justify-content-between">
                <h6 class="fw-bold mb-0">
                    <i class="fa-solid fa-list me-2 text-danger"></i>
                    Pending Review ({{ $requests->total() }})
                </h6>
            </div>
        </div>
        <div class="card-body p-0">
            @if($requests->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-circle-check fa-3x mb-3 text-success opacity-50"></i>
                    <p class="fw-semibold mb-1">No damaged assets pending review.</p>
                    <p class="small">All assets from General Service have been processed.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-4">Request No</th>
                                <th>Asset</th>
                                <th>Reported By (Foreman)</th>
                                <th>GS Condition</th>
                                <th>Sent to SM</th>
                                <th>GS Notes</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($requests as $mr)
                            @php
                                $unit = $mr->fixedAssetUnit;
                                $asset = $unit?->parentAsset;
                                $conditionLabel = match($mr->replacement_condition) {
                                    'in_maintenance'       => '🔧 In Maintenance',
                                    'unrepairable_damage'  => '💥 Unrepairable Damage',
                                    default                => ucfirst(str_replace('_', ' ', $mr->replacement_condition ?? 'Unknown')),
                                };
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <span class="font-monospace fw-bold text-danger">{{ $mr->request_no }}</span>
                                    <div class="small text-muted">{{ $mr->issue_type_label }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $mr->asset_name }}</div>
                                    @if($mr->asset_code)
                                        <div class="small text-muted font-monospace">{{ $mr->asset_code }}</div>
                                    @endif
                                    @if($asset?->store)
                                        <div class="small text-muted">
                                            <i class="fa-solid fa-location-dot me-1"></i>{{ $asset->store->name }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($mr->reportedBy)
                                        <div class="fw-semibold small">{{ $mr->reportedBy->name }}</div>
                                    @elseif($mr->employee)
                                        <div class="fw-semibold small">{{ $mr->employee->full_name }}</div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-danger">{{ $conditionLabel }}</span>
                                </td>
                                <td class="small text-muted">
                                    @if($mr->sent_to_store_manager_at)
                                        {{ \Carbon\Carbon::parse($mr->sent_to_store_manager_at)->format('d M Y') }}
                                        <div class="text-muted" style="font-size:11px;">
                                            {{ \Carbon\Carbon::parse($mr->sent_to_store_manager_at)->diffForHumans() }}
                                        </div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td style="max-width:200px;">
                                    @if($mr->admin_notes)
                                        <p class="small text-secondary mb-0" style="line-height:1.4;">
                                            {{ Str::limit($mr->admin_notes, 100) }}
                                        </p>
                                    @else
                                        <span class="text-muted small">No notes</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#actionModal"
                                            data-mr-id="{{ $mr->id }}"
                                            data-mr-no="{{ $mr->request_no }}"
                                            data-asset="{{ $mr->asset_name }}"
                                            data-code="{{ $mr->asset_code }}"
                                            onclick="fillActionModal(this)">
                                        <i class="fa-solid fa-gavel me-1"></i> Take Action
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($requests->hasPages())
                    <div class="px-4 py-3 border-top">
                        {{ $requests->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>

{{-- ── Action Modal ── --}}
<div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold" id="actionModalLabel">
                    <i class="fa-solid fa-gavel me-2"></i> Take Action on Damaged Asset
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="actionForm" action="">
                @csrf
                @method('POST')
                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-4">
                        <div class="fw-bold" id="modalAssetName">—</div>
                        <div class="text-muted small font-monospace" id="modalAssetCode">—</div>
                        <div class="text-muted small" id="modalMrNo">—</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Action <span class="text-danger">*</span></label>
                        <select name="action" class="form-select" required id="modalAction">
                            <option value="">— Choose Action —</option>
                            <option value="received">✅ Received — Acknowledge receipt (asset stays as damaged)</option>
                            <option value="disposed">🗑️ Dispose — Physically remove from inventory</option>
                            <option value="write_off">📝 Write-off — Record as written off (accounting)</option>
                        </select>
                        <div class="form-text mt-2">
                            <strong>Dispose</strong> and <strong>Write-off</strong> will permanently retire this unit from the system.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes (optional)</label>
                        <textarea name="notes" class="form-control" rows="3"
                                  placeholder="Add any notes about the condition, disposal method, write-off reason, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4">
                        <i class="fa-solid fa-gavel me-2"></i> Confirm Action
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function fillActionModal(btn) {
    const mrId   = btn.dataset.mrId;
    const mrNo   = btn.dataset.mrNo;
    const asset  = btn.dataset.asset;
    const code   = btn.dataset.code;

    document.getElementById('modalAssetName').textContent = asset;
    document.getElementById('modalAssetCode').textContent = code || '';
    document.getElementById('modalMrNo').textContent      = 'Request: ' + mrNo;
    document.getElementById('modalAction').value          = '';

    // Set form action dynamically
    document.getElementById('actionForm').action = '/store-manager/damaged-assets/' + mrId + '/dispose';
}
</script>
@endpush
