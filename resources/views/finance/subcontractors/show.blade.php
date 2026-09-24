@php
    $subconAgreement = $subconAgreement ?? $subcontractor ?? null;
    if ($subconAgreement && !$subconAgreement->relationLoaded('supplier')) {
        $subconAgreement->load(['project', 'supplier', 'createdBy', 'approvedBy', 'items', 'takeoffItems.section', 'takeoffSheet.items', 'ipcs']);
    }
@endphp
@include('procurement.subcon-agreements.show')
