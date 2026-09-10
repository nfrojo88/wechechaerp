@extends('layouts.app')

@section('title', 'Global Admin — Announcements & Bulk SMS')

@section('content')
<div class="container-fluid px-4 py-4">
    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h3 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-bullhorn text-warning me-2"></i>Announcements &amp; Bulk SMS
            </h3>
            <p class="text-muted small mb-0">
                Exclusive to <strong>Global Admin</strong> — Dispatch bulk SMS greetings (e.g. Ethiopian New Year wishes) and publish company-wide in-app announcements.
            </p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-md-0">
            <button type="button" class="btn btn-warning text-dark fw-bold shadow-sm px-3 rounded-pill" data-bs-toggle="collapse" data-bs-target="#composeAnnouncementCard" aria-expanded="true">
                <i class="fa-solid fa-paper-plane me-1"></i> Compose New Broadcast
            </button>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="fa-solid fa-circle-check fs-5 text-success"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 d-flex align-items-center gap-2 mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation fs-5 text-danger"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Stat Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Total Broadcasts</span>
                        <h4 class="fw-bold text-dark mt-1 mb-0">{{ number_format($totalBroadcasts) }}</h4>
                    </div>
                    <div class="bg-primary-subtle text-primary p-3 rounded-circle">
                        <i class="fa-solid fa-tower-broadcast fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Total SMS Delivered</span>
                        <h4 class="fw-bold text-success mt-1 mb-0">{{ number_format($totalSmsSent) }}</h4>
                    </div>
                    <div class="bg-success-subtle text-success p-3 rounded-circle">
                        <i class="fa-solid fa-comment-sms fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Active In-App Banners</span>
                        <h4 class="fw-bold text-warning mt-1 mb-0">{{ number_format($activeBannersCount) }}</h4>
                    </div>
                    <div class="bg-warning-subtle text-warning p-3 rounded-circle">
                        <i class="fa-solid fa-flag fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold">Active Staff Reachable</span>
                        <h4 class="fw-bold text-info mt-1 mb-0">{{ number_format($totalEmployeesWithPhone) }}</h4>
                    </div>
                    <div class="bg-info-subtle text-info p-3 rounded-circle">
                        <i class="fa-solid fa-users fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- COMPOSE BROADCAST CARD (Collapsible) --}}
    <div class="collapse show mb-4" id="composeAnnouncementCard">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-gradient bg-dark text-white py-3 px-4 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Compose Announcement &amp; Bulk SMS
                </h5>
                <span class="badge bg-warning text-dark px-3 py-1 fw-bold rounded-pill">Global Admin Only</span>
            </div>

            <div class="card-body p-4 bg-light bg-opacity-50">
                {{-- Quick Templates Palette --}}
                <div class="mb-4 p-3 bg-white rounded-3 border shadow-xs">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-uppercase text-secondary">
                            <i class="fa-solid fa-wand-magic-sparkles text-primary me-1"></i>One-Click Holiday &amp; Wish Templates (የበዓል እና የምኞት ፈጣን መልእክቶች)
                        </span>
                        <small class="text-muted">Click any preset to auto-fill title &amp; message</small>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-warning text-dark rounded-pill template-btn"
                                onclick="applyTemplate('new_year')">
                            🌼 Ethiopian New Year / እንቁጣጣሽ (2017)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success text-dark rounded-pill template-btn"
                                onclick="applyTemplate('genna')">
                            🎄 Genna / Christmas (ገና)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger text-dark rounded-pill template-btn"
                                onclick="applyTemplate('meskel')">
                            🕊️ Meskel / Demera (መስቀል)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary text-dark rounded-pill template-btn"
                                onclick="applyTemplate('eid')">
                            🌙 Eid Mubarak (ዒድ ሙባረክ)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-dark rounded-pill template-btn"
                                onclick="applyTemplate('notice')">
                            📢 General Company Notice (ማስታወቂያ)
                        </button>
                    </div>
                </div>

                <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('admin.announcements.store') ? route('admin.announcements.store') : url('/admin/announcements') }}" id="announcementForm">
                    @csrf
                    <div class="row g-3">
                        {{-- Title / Subject --}}
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Title / Headline (ርዕስ) <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="announcementTitle" class="form-control form-control-lg rounded-3 fw-semibold" 
                                   placeholder="e.g. መልካም አዲስ ዓመት! / Happy Ethiopian New Year 2017" required value="{{ old('title') }}">
                        </div>

                        {{-- In-App Banner Expiry --}}
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Banner Expiry Date (የማስታወቂያው ማብቂያ)</label>
                            <input type="date" name="expires_at" id="expiresAt" class="form-control form-control-lg rounded-3" 
                                   value="{{ old('expires_at') }}" min="{{ date('Y-m-d') }}">
                            <small class="text-muted" style="font-size:0.75rem;">Leave empty if in-app announcement banner does not expire.</small>
                        </div>

                        {{-- Message Body --}}
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold mb-0">Message Content (የመልእክቱ ዝርዝር) <span class="text-danger">*</span></label>
                                <span class="small text-muted" id="charCounter">
                                    <span id="charCount" class="fw-bold text-primary">0</span> chars | 
                                    <span id="smsPartCount" class="badge bg-secondary">1 SMS</span>
                                </span>
                            </div>
                            <textarea name="message" id="announcementMessage" class="form-control rounded-3" rows="4" 
                                      placeholder="Write announcement or holiday greeting here (supports Amharic and English)..." 
                                      oninput="updateCharCount()" required>{{ old('message') }}</textarea>
                        </div>

                        {{-- Channel Toggles --}}
                        <div class="col-12">
                            <div class="p-3 bg-white rounded-3 border d-flex flex-wrap gap-4 align-items-center shadow-xs">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="send_sms" id="sendSmsToggle" value="1" checked onchange="toggleSmsSection()">
                                    <label class="form-check-label fw-bold text-success" for="sendSmsToggle">
                                        <i class="fa-solid fa-mobile-screen me-1"></i> Send Bulk SMS to Mobile Phones (በስልክ አጭር የጽሁፍ መልእክት ላክ)
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_published" id="inAppToggle" value="1" checked>
                                    <label class="form-check-label fw-bold text-primary" for="inAppToggle">
                                        <i class="fa-solid fa-desktop me-1"></i> Publish In-App Announcement Banner on Dashboard (በስርዓቱ ዳሽቦርድ ላይ ለጥፍ)
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Target Audience Selection --}}
                        <div class="col-12">
                            <div class="card border border-info-subtle rounded-3 p-3 bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                    <strong class="text-info text-uppercase small">
                                        <i class="fa-solid fa-users-gear me-1"></i>Target Audience (ተቀባዮችን ይምረጡ)
                                    </strong>
                                    <span class="badge bg-info-subtle text-info border px-2 py-1" id="audienceSummaryBadge">
                                        All Active Staff ({{ $totalEmployeesWithPhone }} phone numbers)
                                    </span>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-md-3 col-6">
                                        <div class="form-check p-2 border rounded-3 bg-light">
                                            <input class="form-check-input ms-1 me-2" type="radio" name="target_type" id="targetAll" value="all" checked onchange="onTargetTypeChange()">
                                            <label class="form-check-label fw-semibold" for="targetAll">
                                                👥 All Active Staff
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="form-check p-2 border rounded-3 bg-light">
                                            <input class="form-check-input ms-1 me-2" type="radio" name="target_type" id="targetDept" value="department" onchange="onTargetTypeChange()">
                                            <label class="form-check-label fw-semibold" for="targetDept">
                                                🏢 By Department
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="form-check p-2 border rounded-3 bg-light">
                                            <input class="form-check-input ms-1 me-2" type="radio" name="target_type" id="targetProject" value="project" onchange="onTargetTypeChange()">
                                            <label class="form-check-label fw-semibold" for="targetProject">
                                                🏗️ By Project
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-6">
                                        <div class="form-check p-2 border rounded-3 bg-light">
                                            <input class="form-check-input ms-1 me-2" type="radio" name="target_type" id="targetSelected" value="selected" onchange="onTargetTypeChange()">
                                            <label class="form-check-label fw-semibold" for="targetSelected">
                                                🎯 Custom Selection
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                {{-- Department Checkboxes (hidden by default) --}}
                                <div id="deptSelectionSection" style="display:none;" class="p-3 bg-light rounded border mb-2">
                                    <label class="form-label small fw-bold text-secondary mb-2">Select Departments:</label>
                                    <div class="row g-2">
                                        @foreach($departments as $dept)
                                            <div class="col-md-3 col-sm-6">
                                                <div class="form-check">
                                                    <input class="form-check-input dept-checkbox" type="checkbox" name="departments[]" value="{{ $dept }}" id="dept_{{ Str::slug($dept) }}">
                                                    <label class="form-check-label small" for="dept_{{ Str::slug($dept) }}">{{ $dept }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Project Checkboxes (hidden by default) --}}
                                <div id="projectSelectionSection" style="display:none;" class="p-3 bg-light rounded border mb-2">
                                    <label class="form-label small fw-bold text-secondary mb-2">Select Projects:</label>
                                    <div class="row g-2">
                                        @foreach($projects as $proj)
                                            <div class="col-md-4 col-sm-6">
                                                <div class="form-check">
                                                    <input class="form-check-input proj-checkbox" type="checkbox" name="project_ids[]" value="{{ $proj->id }}" id="proj_{{ $proj->id }}">
                                                    <label class="form-check-label small text-truncate d-block" for="proj_{{ $proj->id }}" title="{{ $proj->name }}">
                                                        {{ $proj->name }}
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Employee Multi-Select (hidden by default) --}}
                                <div id="employeeSelectionSection" style="display:none;" class="p-3 bg-light rounded border mb-2">
                                    <label class="form-label small fw-bold text-secondary mb-2">Choose Specific Employees:</label>
                                    <div style="max-height: 200px; overflow-y: auto;" class="border rounded p-2 bg-white">
                                        <div class="row g-1">
                                            @foreach($employees as $emp)
                                                <div class="col-md-4 col-sm-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input emp-checkbox" type="checkbox" name="employee_ids[]" value="{{ $emp->id }}" id="emp_{{ $emp->id }}">
                                                        <label class="form-check-label small text-truncate d-block" for="emp_{{ $emp->id }}">
                                                            {{ $emp->full_name }} ({{ $emp->phone ?? 'No Phone' }})
                                                        </label>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Safety Test SMS Section --}}
                        <div class="col-12" id="testSmsSection">
                            <div class="p-3 bg-white rounded-3 border border-warning-subtle shadow-xs d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-shield-halved text-warning fs-4"></i>
                                    <div>
                                        <strong class="text-dark d-block small">Safety First: Send a Test SMS to yourself</strong>
                                        <span class="text-muted" style="font-size:0.75rem;">Review the text and delivery format on your phone before broadcasting to all staff.</span>
                                    </div>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <input type="text" id="testPhoneNumber" class="form-control form-control-sm" placeholder="e.g. 0911234567" style="max-width: 170px;" value="{{ auth()->user()->employee?->phone ?? auth()->user()->phone ?? '' }}">
                                    <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold text-nowrap" id="btnSendTestSms" onclick="dispatchTestSms()">
                                        <i class="fa-solid fa-paper-plane me-1"></i>Send Test SMS
                                    </button>
                                </div>
                            </div>
                            <div id="testSmsAlert" class="mt-2" style="display:none;"></div>
                        </div>

                        {{-- Submit Button --}}
                        <div class="col-12 text-end pt-2">
                            <button type="submit" class="btn btn-warning btn-lg text-dark fw-bold px-5 rounded-pill shadow-sm" id="btnSubmitBroadcast" onclick="return confirmBroadcast()">
                                <i class="fa-solid fa-bullhorn me-2"></i>Publish &amp; Broadcast Now
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- BROADCAST HISTORY TABLE --}}
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
        <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center border-bottom">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="fa-solid fa-clock-rotate-left text-secondary me-2"></i>Announcement &amp; Broadcast History
            </h5>
            <span class="badge bg-light text-dark border">{{ $announcements->total() }} Total</span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light table-light small text-uppercase fw-semibold">
                        <tr>
                            <th class="ps-4">Date &amp; Time</th>
                            <th>Headline / Title</th>
                            <th>Target Audience</th>
                            <th>In-App Banner</th>
                            <th>SMS Delivery</th>
                            <th>Author</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($announcements as $ann)
                            <tr>
                                <td class="ps-4 small text-muted text-nowrap">
                                    {{ $ann->created_at->format('M d, Y H:i') }}
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ $ann->title }}</div>
                                    <div class="text-muted small text-truncate" style="max-width: 320px;" title="{{ $ann->message }}">
                                        {{ $ann->message }}
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $typeBadge = match($ann->target_type) {
                                            'department' => 'bg-info-subtle text-info border-info-subtle',
                                            'project'    => 'bg-primary-subtle text-primary border-primary-subtle',
                                            'selected'   => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                                            default      => 'bg-success-subtle text-success border-success-subtle',
                                        };
                                        $typeLabel = match($ann->target_type) {
                                            'department' => 'By Department',
                                            'project'    => 'By Project',
                                            'selected'   => 'Custom Selected',
                                            default      => 'All Employees',
                                        };
                                    @endphp
                                    <span class="badge {{ $typeBadge }} border px-2 py-1">
                                        {{ $typeLabel }} ({{ $ann->total_recipients }})
                                    </span>
                                </td>
                                <td>
                                    @if($ann->is_published)
                                        @if($ann->expires_at && $ann->expires_at->isPast())
                                            <span class="badge bg-secondary-subtle text-secondary border">Expired</span>
                                        @else
                                            <span class="badge bg-success text-white">Active Live</span>
                                        @endif
                                    @else
                                        <span class="badge bg-light text-muted border">Off</span>
                                    @endif
                                </td>
                                <td>
                                    @if($ann->send_sms)
                                        <div class="d-flex align-items-center gap-1">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle" title="Delivered SMS">
                                                <i class="fa-solid fa-check me-1"></i>{{ $ann->sms_sent_count }}
                                            </span>
                                            @if($ann->sms_failed_count > 0)
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" title="Failed / Skipped SMS">
                                                    <i class="fa-solid fa-xmark me-1"></i>{{ $ann->sms_failed_count }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted small">No SMS</span>
                                    @endif
                                </td>
                                <td class="small">
                                    <span class="text-dark fw-semibold">{{ $ann->author->name ?? 'Global Admin' }}</span>
                                </td>
                                <td class="text-end pe-4 text-nowrap">
                                    <a href="{{ \Illuminate\Support\Facades\Route::has('admin.announcements.show') ? route('admin.announcements.show', $ann->id) : url('/admin/announcements/' . $ann->id) }}" class="btn btn-sm btn-outline-primary py-0 px-2 rounded shadow-xs" title="View Report and SMS Logs">
                                        <i class="fa-solid fa-chart-pie me-1"></i>Report
                                    </a>
                                    <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('admin.announcements.toggle-publish') ? route('admin.announcements.toggle-publish', $ann->id) : url('/admin/announcements/' . $ann->id . '/toggle-publish') }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm {{ $ann->is_published ? 'btn-outline-warning' : 'btn-outline-success' }} py-0 px-2 rounded shadow-xs" title="Toggle In-App Banner">
                                            <i class="fa-solid {{ $ann->is_published ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ \Illuminate\Support\Facades\Route::has('admin.announcements.destroy') ? route('admin.announcements.destroy', $ann->id) : url('/admin/announcements/' . $ann->id) }}" class="d-inline" onsubmit="return confirm('Delete this announcement record?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 rounded shadow-xs" title="Delete">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-bullhorn fs-1 mb-2 text-secondary d-block"></i>
                                    No announcements or SMS broadcasts sent yet. Use the form above to dispatch your first message!
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($announcements->hasPages())
                <div class="p-3 border-top d-flex justify-content-end">
                    {{ $announcements->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<script>
const templates = {
    new_year: {
        title: "መልካም አዲስ ዓመት! / Happy Ethiopian New Year 2017",
        message: "እንኳን ለ፳፻፲፯ አዲሱ ዓመት በሰላም አደረሳችሁ! አዲሱ ዓመት የሰላም፣ የጤና፣ የስኬት እና የበረከት እንዲሆንላችሁ ከልብ እንመኛለን! — ከወጨጫ ኮንስትራክሽን ኃ/የተ/የግ/ማ"
    },
    genna: {
        title: "መልካም የገና በዓል! / Merry Christmas",
        message: "ለመላው የድርጅታችን ሰራተኞች በሙሉ እንኳን ለብርሃነ ልደቱ በሰላም አደረሳችሁ! መልካም እና የተባረከ የገና በዓል ይሁንላችሁ! — ከወጨጫ ኮንስትራክሽን"
    },
    meskel: {
        title: "እንኳን ለመስቀል ደመራ በዓል በሰላም አደረሳችሁ!",
        message: "ለመላው የእምነቱ ተከታይ ሰራተኞቻችን በሙሉ እንኳን ለመስቀል ደመራ በዓል በሰላም አደረሳችሁ! መልካም የበዓል ጊዜ ይሁንላችሁ! — ከወጨጫ ኮንስትራክሽን"
    },
    eid: {
        title: "መልካም የዒድ በዓል! / Eid Mubarak",
        message: "ለመላው የእስልምና እምነት ተከታይ ሰራተኞቻችን በሙሉ እንኳን ለተከበረው የዒድ በዓል በሰላም አደረሳችሁ! ተቀበለላሁ ሚና ወሚንኩም! — ከወጨጫ ኮንስትራክሽን"
    },
    notice: {
        title: "አጠቃላይ የኩባንያው ማስታወቂያ / Official Company Notice",
        message: "ለኩባንያችን ሰራተኞች በሙሉ፡ ጠቃሚ የኩባንያችን የስራ መመሪያ እና ማስታወቂያ ተላልፏል። እባክዎ በERP ስርዓት ገብተው ዝርዝሩን ይመልከቱ። — ወጨጫ ኮንስትራክሽን"
    }
};

function applyTemplate(key) {
    if (templates[key]) {
        document.getElementById('announcementTitle').value = templates[key].title;
        document.getElementById('announcementMessage').value = templates[key].message;
        updateCharCount();
    }
}

function updateCharCount() {
    const text = document.getElementById('announcementMessage').value || '';
    const charCount = text.length;
    document.getElementById('charCount').innerText = charCount;

    // Detect Unicode (e.g. Amharic Ge'ez)
    const isUnicode = /[^\u0000-\u007F]/.test(text);
    const limitPerSms = isUnicode ? 70 : 160;
    const parts = charCount === 0 ? 1 : Math.ceil(charCount / limitPerSms);

    const badge = document.getElementById('smsPartCount');
    badge.innerText = parts + ' SMS (' + (isUnicode ? 'Unicode / Amharic' : 'Standard GSM') + ')';
    badge.className = parts > 1 ? 'badge bg-warning text-dark' : 'badge bg-secondary';
}

function onTargetTypeChange() {
    const targetType = document.querySelector('input[name="target_type"]:checked').value;
    const deptSec = document.getElementById('deptSelectionSection');
    const projSec = document.getElementById('projectSelectionSection');
    const empSec = document.getElementById('employeeSelectionSection');
    const badge = document.getElementById('audienceSummaryBadge');

    deptSec.style.display = targetType === 'department' ? 'block' : 'none';
    projSec.style.display = targetType === 'project' ? 'block' : 'none';
    empSec.style.display = targetType === 'selected' ? 'block' : 'none';

    if (targetType === 'all') {
        badge.innerText = 'All Active Staff ({{ $totalEmployeesWithPhone }} phone numbers)';
    } else if (targetType === 'department') {
        badge.innerText = 'Targeted Departments';
    } else if (targetType === 'project') {
        badge.innerText = 'Targeted Projects';
    } else {
        badge.innerText = 'Custom Selected Staff';
    }
}

function toggleSmsSection() {
    const sendSms = document.getElementById('sendSmsToggle').checked;
    const testSec = document.getElementById('testSmsSection');
    if (testSec) {
        testSec.style.display = sendSms ? 'block' : 'none';
    }
}

function dispatchTestSms() {
    const phone = document.getElementById('testPhoneNumber').value.trim();
    const msg = document.getElementById('announcementMessage').value.trim();
    const alertBox = document.getElementById('testSmsAlert');
    const btn = document.getElementById('btnSendTestSms');

    if (!phone) {
        alert('Please enter a phone number to receive the test SMS.');
        return;
    }
    if (!msg) {
        alert('Please write a message first before sending test SMS.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Sending...';
    alertBox.style.display = 'none';

    fetch('{{ \Illuminate\Support\Facades\Route::has("admin.announcements.test-sms") ? route("admin.announcements.test-sms") : url("/admin/announcements/test-sms") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ phone: phone, message: msg })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i>Send Test SMS';
        alertBox.style.display = 'block';

        if (data.success) {
            alertBox.className = 'alert alert-success alert-dismissible py-2 px-3 small border-0 shadow-xs';
            alertBox.innerHTML = '<i class="fa-solid fa-check-circle me-1"></i> ' + data.message;
        } else {
            alertBox.className = 'alert alert-danger alert-dismissible py-2 px-3 small border-0 shadow-xs';
            alertBox.innerHTML = '<i class="fa-solid fa-circle-exclamation me-1"></i> ' + (data.message || 'Failed to dispatch test SMS');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-paper-plane me-1"></i>Send Test SMS';
        alertBox.style.display = 'block';
        alertBox.className = 'alert alert-danger py-2 px-3 small border-0 shadow-xs';
        alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Error sending test SMS: ' + err.message;
    });
}

function confirmBroadcast() {
    const sendSms = document.getElementById('sendSmsToggle').checked;
    const targetType = document.querySelector('input[name="target_type"]:checked').value;
    let warn = 'Are you sure you want to publish this announcement?';
    if (sendSms) {
        warn += '\n\n⚠️ IMPORTANT: Bulk SMS will be immediately dispatched to recipient mobile phones via AfroMessage.';
    }
    return confirm(warn);
}

document.addEventListener('DOMContentLoaded', function() {
    updateCharCount();
});
</script>
@endsection
