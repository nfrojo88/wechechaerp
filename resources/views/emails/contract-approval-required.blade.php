@component('mail::message')
# Employee Contract Requires Your Approval

Dear Approver,

An employee contract has been submitted for approval at the {{ $approvalLevel }} level.

## Employee Information
- **Name:** {{ $employee->name }}
- **Code:** {{ $employee->code }}
- **Department:** {{ $employee->department->name ?? 'N/A' }}

## Contract Details
- **Contract Number:** {{ $contract->contract_number }}
- **Type:** {{ $contract->contract_type }}
- **Start Date:** {{ $contract->start_date->format('F d, Y') }}
- **End Date:** {{ $contract->end_date->format('F d, Y') }}
- **Salary:** {{ number_format($contract->salary ?? 0, 2) }}

## Action Required
Please review and approve/reject this contract in the system.

@component('mail::button', ['url' => route('contracts.show', $contract->id)])
Review Contract
@endcomponent

Please complete this approval at your earliest convenience.

Best regards,

{{ config('app.name') }} - Contract Management System
@endcomponent
