@props([
    'preparedTitle' => 'Prepared By',
    'preparedRole'  => 'Designated Officer',
    'checkedTitle'  => 'Checked & Verified By',
    'checkedRole'   => 'Department Head / Audit',
    'approvedTitle' => 'Approved By',
    'approvedRole'  => 'General Manager / Project Director',
])

<div class="wc-print-signature-block">
    <div class="wc-signature-col">
        <div class="fw-bold">{{ $preparedTitle }}</div>
        <div class="text-muted small">{{ $preparedRole }}</div>
        <div class="wc-signature-line"></div>
        <div class="small">Name: ______________________</div>
        <div class="small mt-1">Date: &nbsp;______ / ______ / 202___</div>
    </div>

    <div class="wc-signature-col">
        <div class="fw-bold">{{ $checkedTitle }}</div>
        <div class="text-muted small">{{ $checkedRole }}</div>
        <div class="wc-signature-line"></div>
        <div class="small">Name: ______________________</div>
        <div class="small mt-1">Date: &nbsp;______ / ______ / 202___</div>
    </div>

    <div class="wc-signature-col">
        <div class="fw-bold">{{ $approvedTitle }}</div>
        <div class="text-muted small">{{ $approvedRole }}</div>
        <div class="wc-signature-line"></div>
        <div class="small">Name: ______________________</div>
        <div class="small mt-1">Date: &nbsp;______ / ______ / 202___</div>
    </div>
</div>
