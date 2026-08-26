@extends('layouts.app')
@section('title', 'Issue Official Employee Letter')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0 fw-bold" style="color:var(--brand-800)">
                <i class="fa-solid fa-file-pen text-primary me-2"></i>Issue Official Employee Letter
            </h1>
            <p class="text-muted small mb-0">Issue appreciation, written warning, final warning, or other official letters to an employee</p>
        </div>
        <a href="{{ route('employee-letters.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Letter History
        </a>
    </div>

    <form action="{{ route('employee-letters.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="row g-4">
            {{-- Left column: Letter Details --}}
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-file-lines text-primary me-2"></i>Letter Details</h6>
                    </div>
                    <div class="card-body p-4">

                        {{-- Quick Type Selection --}}
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark mb-2">Select Letter Type <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-md-4 col-6">
                                    <input type="radio" class="btn-check" name="letter_type" id="type_thanks" value="thanks_letter" autocomplete="off" {{ old('letter_type', $defaultType) == 'thanks_letter' ? 'checked' : '' }} onchange="loadTemplate('thanks_letter')">
                                    <label class="btn btn-outline-success w-100 text-start py-2 px-3 rounded-3" for="type_thanks">
                                        <i class="fa-solid fa-award me-1"></i><strong>Thanks / Appreciation</strong>
                                    </label>
                                </div>
                                <div class="col-md-4 col-6">
                                    <input type="radio" class="btn-check" name="letter_type" id="type_warn1" value="first_warning" autocomplete="off" {{ old('letter_type', $defaultType) == 'first_warning' ? 'checked' : '' }} onchange="loadTemplate('first_warning')">
                                    <label class="btn btn-outline-warning text-dark w-100 text-start py-2 px-3 rounded-3" for="type_warn1">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i><strong>1st Warning Letter</strong>
                                    </label>
                                </div>
                                <div class="col-md-4 col-6">
                                    <input type="radio" class="btn-check" name="letter_type" id="type_warn2" value="second_warning" autocomplete="off" {{ old('letter_type', $defaultType) == 'second_warning' ? 'checked' : '' }} onchange="loadTemplate('second_warning')">
                                    <label class="btn btn-outline-warning w-100 text-start py-2 px-3 rounded-3" for="type_warn2">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i><strong>2nd Warning Letter</strong>
                                    </label>
                                </div>
                                <div class="col-md-4 col-6">
                                    <input type="radio" class="btn-check" name="letter_type" id="type_final" value="final_warning" autocomplete="off" {{ old('letter_type', $defaultType) == 'final_warning' ? 'checked' : '' }} onchange="loadTemplate('final_warning')">
                                    <label class="btn btn-outline-danger w-100 text-start py-2 px-3 rounded-3" for="type_final">
                                        <i class="fa-solid fa-circle-exclamation me-1"></i><strong>Final Warning Letter</strong>
                                    </label>
                                </div>
                                <div class="col-md-4 col-6">
                                    <input type="radio" class="btn-check" name="letter_type" id="type_showcause" value="show_cause" autocomplete="off" {{ old('letter_type', $defaultType) == 'show_cause' ? 'checked' : '' }} onchange="loadTemplate('show_cause')">
                                    <label class="btn btn-outline-info text-dark w-100 text-start py-2 px-3 rounded-3" for="type_showcause">
                                        <i class="fa-solid fa-circle-question me-1"></i><strong>Show Cause / Query</strong>
                                    </label>
                                </div>
                                <div class="col-md-4 col-6">
                                    <input type="radio" class="btn-check" name="letter_type" id="type_promotion" value="promotion" autocomplete="off" {{ old('letter_type', $defaultType) == 'promotion' ? 'checked' : '' }} onchange="loadTemplate('promotion')">
                                    <label class="btn btn-outline-primary w-100 text-start py-2 px-3 rounded-3" for="type_promotion">
                                        <i class="fa-solid fa-arrow-up-right-dots me-1"></i><strong>Promotion Letter</strong>
                                    </label>
                                </div>
                                <div class="col-md-4 col-6">
                                    <input type="radio" class="btn-check" name="letter_type" id="type_poa" value="power_of_attorney" autocomplete="off" {{ old('letter_type', $defaultType) == 'power_of_attorney' ? 'checked' : '' }} onchange="loadTemplate('power_of_attorney')">
                                    <label class="btn btn-outline-primary text-dark w-100 text-start py-2 px-3 rounded-3 border-2" for="type_poa" style="border-color:#4f46e5 !important; background-color: #eef2ff;">
                                        <i class="fa-solid fa-stamp me-1 text-primary"></i><strong class="text-primary">Power of Attorney / Representation (ውክልና)</strong>
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Subject / Title --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold">Letter Subject / Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="letter_title" class="form-control" placeholder="e.g. Letter of Appreciation & Commendation" value="{{ old('title') }}" required>
                        </div>

                        {{-- Letter Content --}}
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold mb-0">Letter Content &amp; Explanation <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" onclick="resetCurrentTemplate()">
                                    <i class="fa-solid fa-rotate-right me-1"></i>Reset to Standard Template
                                </button>
                            </div>
                            <textarea name="content" id="letter_content" rows="12" class="form-control font-monospace" placeholder="Enter official letter wording..." required>{{ old('content') }}</textarea>
                            <small class="text-muted">This text will be printed on official company letterhead and permanently archived in the employee's history.</small>
                        </div>

                        {{-- Action Required / Corrective Measures --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Action Required / Corrective Expectation (Optional)</label>
                            <textarea name="action_required" id="letter_action" rows="3" class="form-control" placeholder="e.g. Employee must adhere to safety guidelines immediately. Failure will result in summary dismissal.">{{ old('action_required') }}</textarea>
                        </div>

                    </div>
                </div>
            </div>

            {{-- Right column: Employee & Administrative metadata --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-user-check text-primary me-2"></i>Recipient &amp; Reference</h6>
                    </div>
                    <div class="card-body p-4">

                        {{-- Select Employee --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Employee <span class="text-danger">*</span></label>
                            <select name="employee_id" id="employee_id" class="form-select select2" required>
                                <option value="">-- Choose Employee --</option>
                                @foreach($employees as $emp)
                                <option value="{{ $emp->id }}" data-name="{{ $emp->full_name }}" data-code="{{ $emp->employee_code }}" data-role="{{ $emp->role_title ?? $emp->department }}" {{ old('employee_id', $selectedEmployeeId) == $emp->id ? 'selected' : '' }}>
                                    {{ $emp->full_name }} ({{ $emp->employee_code }}) - {{ $emp->role_title ?? $emp->department }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Reference Number --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reference Number (Optional)</label>
                            <input type="text" name="reference_number" class="form-control font-monospace" placeholder="Auto-generated if blank" value="{{ old('reference_number') }}">
                            <small class="text-muted">Leave blank for automatic official sequence.</small>
                        </div>

                        {{-- Issue Date --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold">Date of Issuance <span class="text-danger">*</span></label>
                            <input type="date" name="issued_date" class="form-control" value="{{ old('issued_date', date('Y-m-d')) }}" required>
                        </div>

                        {{-- Effective Date --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Effective Date</label>
                            <input type="date" name="effective_date" class="form-control" value="{{ old('effective_date', date('Y-m-d')) }}">
                        </div>

                        {{-- Signed Document Attachment --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Signed Copy / PDF Attachment</label>
                            <input type="file" name="attachment" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <small class="text-muted">Upload scanned copy with employee's physical signature if available.</small>
                        </div>

                        {{-- Acknowledgement Status --}}
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Acknowledgement Status</label>
                            <select name="acknowledgement_status" class="form-select">
                                <option value="acknowledged" {{ old('acknowledgement_status') == 'acknowledged' ? 'selected' : '' }}>Acknowledged / Received by Employee</option>
                                <option value="pending" {{ old('acknowledgement_status') == 'pending' ? 'selected' : '' }}>Pending Signature / In Transit</option>
                                <option value="refused_to_sign" {{ old('acknowledgement_status') == 'refused_to_sign' ? 'selected' : '' }}>Refused to Sign (Noted on Record)</option>
                            </select>
                        </div>

                        <hr>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                            <i class="fa-solid fa-paper-plane me-1"></i> Issue &amp; Record Letter
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const templates = {
    thanks_letter: {
        title: "Letter of Appreciation & Commendation",
        content: `Dear [EMPLOYEE_NAME],\n\nWe are pleased to formally convey our profound appreciation and commendation for your outstanding dedication, professional conduct, and exemplary contribution to Wechecha Construction.\n\nYour exceptional commitment, high standard of workmanship, and positive attitude have significantly contributed to our project objectives and set a commendable standard across the team.\n\nOn behalf of management, we thank you for your valuable service and look forward to your continued excellence.\n\nSincerely,\nHuman Resources Department\nWechecha Construction`,
        action: "Exemplary performance noted in permanent employee record. Eligible for annual recognition reward."
    },
    first_warning: {
        title: "First Written Warning - Official Disciplinary Notice",
        content: `Dear [EMPLOYEE_NAME],\n\nThis letter serves as an official First Written Warning concerning non-compliance with company policies / performance standards.\n\nIncident Details / Reason:\n[Specify date, incident description, and policy violated here]\n\nPlease be advised that Wechecha Construction upholds high standards of workplace discipline, attendance, and safety. You are expected to demonstrate immediate and sustained improvement in your conduct and performance.\n\nPlease treat this notice with the highest seriousness.\n\nSincerely,\nHuman Resources Department\nWechecha Construction`,
        action: "Employee must rectify the stated behavior immediately. Continued infractions will result in Second Written Warning."
    },
    second_warning: {
        title: "Second Written Warning - Escalated Disciplinary Notice",
        content: `Dear [EMPLOYEE_NAME],\n\nFollowing our previous communications, this letter serves as a formal Second Written Warning regarding ongoing non-compliance with company regulations.\n\nIncident Details / Reason:\n[Specify the recurrent incident and failure to correct previous warning here]\n\nYour failure to meet the required standard poses operational disruption. You are hereby given a strict timeline to rectify this behavior.\n\nSincerely,\nHuman Resources Department\nWechecha Construction`,
        action: "Close monitoring by supervisor. Any further occurrence will lead directly to a Final Warning or Termination."
    },
    final_warning: {
        title: "Final Warning Letter - Strict Disciplinary Notice",
        content: `Dear [EMPLOYEE_NAME],\n\nThis letter constitutes a FINAL WRITTEN WARNING. Despite previous warnings and counseling, your conduct / performance has failed to meet the required standards of Wechecha Construction.\n\nBreach Details:\n[Detail the specific severe violation or repeated failure here]\n\nYou are strictly informed that ANY further violation of company rules, safety guidelines, or neglect of duty will result in IMMEDIATE TERMINATION of your employment contract without further notice.\n\nSincerely,\nHuman Resources Department\nWechecha Construction`,
        action: "Final caution. Immediate termination upon any subsequent violation under Ethiopian labor law and company policy."
    },
    show_cause: {
        title: "Show Cause Notice / Inquiry Query",
        content: `Dear [EMPLOYEE_NAME],\n\nIt has been brought to the attention of management that the following misconduct / irregularity occurred on [DATE]:\n\n[Describe alleged misconduct or unexcused absence here]\n\nYou are hereby directed to submit a written explanation within 48 hours of receipt of this letter, showing cause why disciplinary action should not be taken against you.\n\nSincerely,\nHuman Resources Department\nWechecha Construction`,
        action: "Written response required from employee within 48 hours."
    },
    promotion: {
        title: "Official Letter of Promotion & Role Advancement",
        content: `Dear [EMPLOYEE_NAME],\n\nIn recognition of your exceptional performance, demonstrated leadership, and continuous contribution to Wechecha Construction, management is pleased to officially promote you to the position of [NEW_POSITION].\n\nEffective Date: [EFFECTIVE_DATE]\nNew Department / Project: [DEPARTMENT]\n\nWe congratulate you on this well-deserved career advancement and trust you will continue to achieve great success in your new role.\n\nSincerely,\nHuman Resources Department\nWechecha Construction`,
        action: "HR update employee title, salary grading, and job contract."
    },
    power_of_attorney: {
        title: "Power of Attorney & Official Representation Letter (የውክልና ማስረጃ / መስጫ ደብዳቤ)",
        content: `ለሚመለከተው ሁሉ / To Whom It May Concern:\n\nጉዳዩ፡- የውክልና ስልጣን መስጠትን ይመለከታል (Official Power of Attorney & Corporate Representation)\n\nድርጅታችን ወጨጫ ኮንስትራክሽን (Wechecha Construction PLC) ሰራተኛችን የሆኑትን አቶ/ወ/ሮ/ወ/ሪት [EMPLOYEE_NAME] (መለያ ቁጥር: [EMPLOYEE_CODE]፤ የሥራ መደብ: [EMPLOYEE_ROLE]) ድርጅታችንን በመወከል ከዚህ በታች የተዘረዘሩትን ተግባራት በህጋዊ መንገድ እንዲያከናውኑ ሙሉ ውክልና የሰጠናቸው መሆኑን እናረጋግጣለን።\n\nየውክልናው ስልጣን ወሰንና ተግባራት / Scope of Authority & Representation:\n1. ድርጅታችንን በመወከል በማናቸውም የመንግስት እና የግል መስሪያ ቤቶች፣ ፍርድ ቤቶች፣ ባንኮች፣ ጉምሩክ፣ ማዘጋጃ ቤት፣ የኤሌክትሪክ እና የውሃ አገልግሎት መስሪያ ቤቶች፣ እንዲሁም ሌሎች አጋር ድርጅቶች ቀርበው ጉዳዮችን ለመከታተልና ለማስፈጸም።\n2. ከድርጅቱ የስራ እንቅስቃሴ ጋር የተያያዙ ሰነዶችን፣ ደብዳቤዎችን፣ ፈቃዶችን እና የፍተሻ ማረጋገጫዎችን ለማስገባት፣ ለመፈረም እንዲሁም ለመረከብ።\n3. ለግንባታ ፕሮጀክቶች የሚያስፈልጉ ግብዓቶችን፣ እቃዎችን እና ማሽነሪዎችን ከማናቸውም አቅራቢዎች እና መጋዘኖች ተረክቦ የርክክብ ሰነድ ለመፈረም።\n4. በድርጅቱ የበላይ አመራር የሚሰጡ ማናቸውንም ህጋዊ እና አስተዳደራዊ የስራ ውክልናዎችን በታማኝነት ለማከናወን።\n\nይህ የውክልና ስልጣን ደብዳቤ በይፋ በጽሁፍ እስካልተሻረ ወይም የተሰጣቸው ስራ እስኪጠናቀቅ ድረስ በህግ ፊት የጸና እና ሙሉ ተፈጻሚነት ያለው ነው።\n\nከአክብሮት ሰላምታ ጋር / Authorized Signatory:\n\n___________________________________\nዋና ስራ አስኪያጅ / General Manager\nወጨጫ ኮንስትራክሽን (Wechecha Construction PLC)`,
        action: "Official Power of Attorney registered in corporate registry and permanent employee archive."
    }
};

function formatTemplateText(templateText) {
    const empSelect = document.getElementById('employee_id');
    const selectedOption = empSelect ? empSelect.options[empSelect.selectedIndex] : null;
    const empName = selectedOption ? (selectedOption.getAttribute('data-name') || '[EMPLOYEE_NAME]') : '[EMPLOYEE_NAME]';
    const empCode = selectedOption ? (selectedOption.getAttribute('data-code') || 'EMP-ID') : 'EMP-ID';
    const empRole = selectedOption ? (selectedOption.getAttribute('data-role') || 'Authorized Staff') : 'Authorized Staff';

    return templateText
        .replace(/\[EMPLOYEE_NAME\]/g, empName)
        .replace(/\[EMPLOYEE_CODE\]/g, empCode)
        .replace(/\[EMPLOYEE_ROLE\]/g, empRole);
}

function loadTemplate(type) {
    const t = templates[type];
    if (!t) return;

    document.getElementById('letter_title').value = t.title;
    document.getElementById('letter_content').value = formatTemplateText(t.content);
    document.getElementById('letter_action').value = t.action || '';
}

function resetCurrentTemplate() {
    const checkedType = document.querySelector('input[name="letter_type"]:checked');
    if (checkedType) {
        loadTemplate(checkedType.value);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (!document.getElementById('letter_content').value) {
        const checkedType = document.querySelector('input[name="letter_type"]:checked');
        if (checkedType) {
            loadTemplate(checkedType.value);
        }
    }

    const empSelect = document.getElementById('employee_id');
    if (empSelect) {
        empSelect.addEventListener('change', function() {
            const checkedType = document.querySelector('input[name="letter_type"]:checked');
            if (checkedType && templates[checkedType.value]) {
                document.getElementById('letter_content').value = formatTemplateText(templates[checkedType.value].content);
            }
        });
    }
});
</script>
@endsection
