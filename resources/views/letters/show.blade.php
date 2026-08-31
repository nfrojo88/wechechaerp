@extends('layouts.app')
@php use Illuminate\Support\Str; @endphp

@section('title', 'Letter: ' . $letter->letter_number)

@section('content')
<div class="container-fluid py-3">

    {{-- Top Header --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h3 class="fw-bold mb-0">
                    <i class="fa-solid fa-file-lines text-primary me-2"></i>{{ $letter->letter_number }}
                </h3>
                @if($letter->type === 'incoming')
                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2 py-1">
                        <i class="fa-solid fa-arrow-down-left me-1"></i> Incoming
                    </span>
                @else
                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">
                        <i class="fa-solid fa-arrow-up-right me-1"></i> Outgoing
                    </span>
                @endif

                @if($letter->priority === 'urgent')
                    <span class="badge bg-danger px-2 py-1"><i class="fa-solid fa-fire me-1"></i>URGENT</span>
                @endif

                @php
                    $badgeClass = match($letter->status) {
                        'pending'    => 'bg-warning text-dark',
                        'viewed'     => 'bg-info text-dark',
                        'redirected' => 'bg-primary text-white',
                        'closed'     => 'bg-success text-white',
                        default      => 'bg-secondary text-white'
                    };
                @endphp
                <span class="badge {{ $badgeClass }} px-2 py-1">{{ ucfirst($letter->status) }}</span>
            </div>
            <p class="text-muted small mb-0">Registered on <strong>{{ $letter->created_at->format('M d, Y H:i') }}</strong> by <strong>{{ $letter->creator->name ?? 'Secretary' }}</strong></p>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('letters.index') }}" class="btn btn-outline-secondary shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inbox
            </a>
            @if($letter->status !== 'closed')
                <button type="button" class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#redirectModal">
                    <i class="fa-solid fa-share me-1"></i> Redirect / Forward
                </button>
                <button type="button" class="btn btn-success shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#closeModal">
                    <i class="fa-solid fa-check-double me-1"></i> Mark Reviewed / Closed
                </button>
            @endif
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-start border-4 border-success" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-start border-4 border-danger" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Left Column: Letter Content, Metadata & Attachments --}}
        <div class="col-lg-8">
            {{-- Subject & Specification Card --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-heading text-primary me-2"></i>{{ $letter->subject }}
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <h6 class="fw-bold text-secondary small text-uppercase mb-2">Specification & Content</h6>
                        <div class="text-dark" style="white-space: pre-wrap; line-height: 1.6;">{{ $letter->specification }}</div>
                    </div>

                    {{-- Metadata Grid --}}
                    <div class="row g-2 small">
                        <div class="col-md-6">
                            <div class="p-2 border rounded bg-white">
                                <span class="text-muted d-block">Official Letter Date:</span>
                                <strong class="text-dark">{{ $letter->date ? $letter->date->format('F d, Y') : '-' }}</strong>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-2 border rounded bg-white">
                                <span class="text-muted d-block">{{ $letter->type === 'incoming' ? 'Sender / Origin Organization:' : 'Recipient Organization:' }}</span>
                                <strong class="text-dark">{{ $letter->type === 'incoming' ? ($letter->sender ?? 'N/A') : ($letter->recipient_organization ?? 'N/A') }}</strong>
                            </div>
                        </div>
                        @if($letter->sender_department)
                        <div class="col-md-6">
                            <div class="p-2 border rounded bg-white">
                                <span class="text-muted d-block">Originating Department:</span>
                                <strong class="text-dark">{{ $letter->sender_department }}</strong>
                            </div>
                        </div>
                        @endif
                        <div class="col-md-6">
                            <div class="p-2 border rounded bg-white">
                                <span class="text-muted d-block">Created By:</span>
                                <strong class="text-dark">{{ $letter->creator->name ?? 'Secretary' }} ({{ $letter->creator->email ?? '' }})</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Attachments Section & Inline Previews --}}
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-paperclip text-primary me-2"></i>Attached Documents ({{ $letter->attachments->count() }})
                    </h5>
                </div>
                <div class="card-body">
                    @forelse($letter->attachments as $att)
                        <div class="border rounded-3 p-3 mb-3 bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    @if($att->is_pdf)
                                        <i class="fa-solid fa-file-pdf fa-2x text-danger"></i>
                                    @else
                                        <i class="fa-solid fa-file-image fa-2x text-primary"></i>
                                    @endif
                                    <div>
                                        <div class="fw-bold text-dark">{{ $att->file_name }}</div>
                                        <small class="text-muted">{{ $att->formatted_size }} • Uploaded by {{ $att->uploader->name ?? 'Staff' }} on {{ $att->created_at->format('M d, Y') }}</small>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('letters.attachments.preview', $att->id) }}" class="btn btn-sm btn-outline-secondary" target="_blank">
                                        <i class="fa-solid fa-external-link me-1"></i> Open Full View
                                    </a>
                                    <a href="{{ route('letters.attachments.download', $att->id) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="fa-solid fa-download me-1"></i> Download
                                    </a>
                                </div>
                            </div>

                            {{-- Inline Preview --}}
                            @php
                                $previewUrl = route('letters.attachments.preview', $att->id);
                            @endphp
                            @if($att->is_pdf)
                                <div class="mt-2 border rounded bg-light overflow-hidden" style="height: 520px;">
                                    <iframe src="{{ $previewUrl }}" width="100%" height="100%" style="border: none;">
                                        <p class="p-3 text-center text-muted">Your browser does not support inline PDF viewing. <a href="{{ route('letters.attachments.download', $att->id) }}">Click here to download.</a></p>
                                    </iframe>
                                </div>
                            @elseif($att->is_image)
                                <div class="mt-2 text-center p-2 bg-light rounded border">
                                    <a href="{{ $previewUrl }}" target="_blank">
                                        <img src="{{ $previewUrl }}"
                                             alt="{{ $att->file_name }}"
                                             class="img-fluid rounded shadow-sm"
                                             style="max-height: 450px; object-fit: contain; cursor: zoom-in;">
                                    </a>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-paperclip fa-2x mb-2 d-block opacity-50"></i>
                            No document attachments uploaded for this letter.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Right Column: Actions & Routing History Timeline --}}
        <div class="col-lg-4">
            @php
                $currentUser = auth()->user();
                $isSecretaryOnly = $currentUser && $currentUser->hasRole('secretary') && !$currentUser->hasAnyRole(['admin', 'global_admin', 'gm', 'manager', 'director', 'hr_manager', 'finance_head']);
            @endphp

            {{-- Quick Action Card --}}
            @if($letter->status !== 'closed')
            <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-4 border-primary">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0"><i class="fa-solid fa-bolt text-warning me-2"></i>Action Controls</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        @if($isSecretaryOnly)
                            As Secretary, you can forward this letter to the assigned manager or department for decision-making.
                        @else
                            You can forward this letter to another colleague/department or record a decision and close it.
                        @endif
                    </p>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#redirectModal">
                            <i class="fa-solid fa-share me-1"></i> Redirect / Forward Letter
                        </button>

                        @if(!$isSecretaryOnly)
                            <button type="button" class="btn btn-success py-2 fw-bold" data-bs-toggle="modal" data-bs-target="#closeModal">
                                <i class="fa-solid fa-check-double me-1"></i> Give Decision & Close Letter
                            </button>
                        @else
                            <div class="alert alert-info py-2 px-3 mb-0 small border-0 bg-info bg-opacity-10 text-dark rounded-3">
                                <i class="fa-solid fa-shield-halved text-info me-1"></i>
                                <strong>Secretary Access:</strong> You can create and forward letters. Official decisions & closure are reserved for assigned managers.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            @else
            <div class="card border-0 shadow-sm rounded-3 mb-4 bg-success bg-opacity-10 border border-success border-opacity-25">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center gap-2 text-success fw-bold mb-1">
                        <i class="fa-solid fa-circle-check fa-lg"></i> Letter Closed
                    </div>
                    <p class="small text-muted mb-1">Closed by <strong>{{ $letter->closer->name ?? 'Staff' }}</strong> on {{ $letter->closed_at ? $letter->closed_at->format('M d, Y H:i') : '' }}.</p>
                    @if($letter->closing_notes)
                        <div class="small p-2 bg-white rounded border text-dark mt-2">
                            <strong>Decision / Closing Notes:</strong> {{ $letter->closing_notes }}
                        </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Routing History Timeline --}}
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid fa-timeline text-primary me-2"></i>Routing History & Audit Trail
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div class="timeline position-relative ps-3" style="border-left: 2px solid #dee2e6;">
                        @forelse($letter->recipients as $recipient)
                            <div class="timeline-item mb-4 position-relative ps-3">
                                {{-- Circle dot --}}
                                <div class="position-absolute rounded-circle bg-primary" 
                                     style="width: 12px; height: 12px; left: -20px; top: 4px; border: 2px solid white;"></div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge bg-secondary small">
                                        {{ ucfirst(str_replace('_', ' ', $recipient->action)) }}
                                    </span>
                                    <small class="text-muted">{{ $recipient->created_at->format('M d, H:i') }}</small>
                                </div>

                                <div class="small fw-semibold text-dark">
                                    From: <span class="text-primary">{{ $recipient->fromUser->name ?? 'Secretary' }}</span>
                                </div>
                                <div class="small text-muted">
                                    To: <strong class="text-dark">{{ $recipient->recipient_label }}</strong>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size: 0.68rem;">
                                        <i class="fa-solid fa-mobile-screen me-1"></i>SMS Dispatched
                                    </span>
                                </div>

                                @if($recipient->notes)
                                    <div class="small bg-light p-2 rounded mt-2 text-secondary border">
                                        <i class="fa-solid fa-comment-dots me-1 text-muted"></i> "{{ $recipient->notes }}"
                                    </div>
                                @endif

                                @if($recipient->viewed_at)
                                    <div class="small text-success mt-1" style="font-size: 0.75rem;">
                                        <i class="fa-solid fa-eye me-1"></i> Viewed on {{ $recipient->viewed_at->format('M d, Y H:i') }}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-muted small text-center py-3">No routing logs recorded yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal 1: Redirect / Forward Letter --}}
<div class="modal fade" id="redirectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-share me-2"></i>Redirect / Forward Letter</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('letters.redirect', $letter->id) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">Forward this letter to another colleague or role with forwarding instructions. This will be added to the official audit timeline.</p>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Target Mode <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="send_target_type" id="modalTargetUser" value="user" checked onchange="toggleModalTarget()">
                                <label class="form-check-label fw-semibold" for="modalTargetUser">Specific Person</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="send_target_type" id="modalTargetRole" value="role" onchange="toggleModalTarget()">
                                <label class="form-check-label fw-semibold" for="modalTargetRole">Role / Department</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="modalUserBox">
                        <label class="form-label fw-bold">Select Person <span class="text-danger">*</span></label>
                        <select name="to_user_id" id="modalToUser" class="form-select" required>
                            <option value="">-- Choose User --</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="modalRoleBox">
                        <label class="form-label fw-bold">Select Role / Department <span class="text-danger">*</span></label>
                        <select name="to_role_name" id="modalToRole" class="form-select">
                            <option value="">-- Choose Role --</option>
                            @foreach($roles as $r)
                                <option value="{{ $r }}">{{ ucfirst(str_replace(['_', '-'], ' ', $r)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Forwarding Notes / Remarks <span class="text-danger">*</span></label>
                        <textarea name="redirection_notes" class="form-control" rows="3" 
                                  placeholder="e.g., Forwarding to Finance team for payment approval and processing..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">
                        <i class="fa-solid fa-paper-plane me-1"></i> Forward Letter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal 2: Mark Reviewed / Closed --}}
<div class="modal fade" id="closeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-check-double me-2"></i>Mark as Reviewed & Closed</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('letters.close', $letter->id) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">Provide a summary of the action taken or resolution for this letter to mark it officially closed.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Resolution Notes / Action Taken <span class="text-danger">*</span></label>
                        <textarea name="closing_notes" class="form-control" rows="4" 
                                  placeholder="e.g., Letter reviewed, site inspection conducted, and approval letter sent to client." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">
                        <i class="fa-solid fa-check-double me-1"></i> Confirm & Close Letter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleModalTarget() {
    const isRole = document.getElementById('modalTargetRole').checked;
    const userBox = document.getElementById('modalUserBox');
    const roleBox = document.getElementById('modalRoleBox');
    const userSelect = document.getElementById('modalToUser');
    const roleSelect = document.getElementById('modalToRole');

    if (isRole) {
        userBox.classList.add('d-none');
        roleBox.classList.remove('d-none');
        userSelect.removeAttribute('required');
        roleSelect.setAttribute('required', 'required');
    } else {
        userBox.classList.remove('d-none');
        roleBox.classList.add('d-none');
        userSelect.setAttribute('required', 'required');
        roleSelect.removeAttribute('required');
    }
}
</script>
@endsection
