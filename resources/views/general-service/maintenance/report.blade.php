<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance &amp; Service Report — {{ $maintenanceRequest->request_no }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .report-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            max-width: 900px;
            margin: 30px auto;
            padding: 40px;
        }
        .header-brand {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 6px;
            margin-top: 25px;
            margin-bottom: 15px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .info-item {
            background: #f8fafc;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #edf2f7;
        }
        .info-item .label {
            font-size: 0.72rem;
            text-transform: uppercase;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 2px;
        }
        .info-item .value {
            font-size: 0.92rem;
            font-weight: 600;
            color: #0f172a;
        }
        .signature-box {
            border-top: 1px dashed #cbd5e1;
            padding-top: 10px;
            margin-top: 40px;
            text-align: center;
        }
        @media print {
            body {
                background-color: #ffffff;
                color: #000000;
            }
            .report-card {
                box-shadow: none;
                margin: 0;
                padding: 0;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>

<div class="container py-3">

    {{-- Top Action Bar (No Print) --}}
    <div class="d-flex justify-content-between align-items-center mb-3 no-print max-width-wrapper" style="max-width: 900px; margin: 0 auto;">
        <a href="{{ route('general-service.maintenance.show', $maintenanceRequest) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fa-solid fa-arrow-left me-1"></i>Back to Ticket Details
        </a>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm rounded-pill px-4 fw-bold shadow-sm">
                <i class="fa-solid fa-print me-1"></i>Print Report
            </button>
        </div>
    </div>

    {{-- Printable Report Sheet --}}
    <div class="report-card">

        {{-- Header & Company Info --}}
        <div class="header-brand d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h3 class="fw-bold mb-1" style="color:#0f172a;">
                    <i class="fa-solid fa-screwdriver-wrench text-warning me-2"></i>EQUIPMENT &amp; ASSET SERVICE REPORT
                </h3>
                <div class="text-muted small">ConstructPro ERP — General Service &amp; Asset Management Division</div>
            </div>
            <div class="text-end">
                <span class="badge bg-dark font-monospace fs-6 px-3 py-2">{{ $maintenanceRequest->request_no }}</span>
                <div class="text-muted small mt-1">Date: {{ $maintenanceRequest->created_at->format('d M Y, H:i') }}</div>
            </div>
        </div>

        {{-- 1. Asset & Custody Information --}}
        <div class="section-title">
            <i class="fa-solid fa-truck-monster me-1 text-primary"></i> 1. Asset &amp; Operator Identification
        </div>
        <div class="info-grid mb-3">
            <div class="info-item">
                <div class="label">Asset / Equipment Name</div>
                <div class="value">{{ $maintenanceRequest->asset_name }}</div>
            </div>
            <div class="info-item">
                <div class="label">Asset Code / Tag #</div>
                <div class="value font-monospace">{{ $maintenanceRequest->asset_code ?: 'N/A' }}</div>
            </div>
            @if($maintenanceRequest->fixedAssetUnit)
                <div class="info-item">
                    <div class="label">Brand &amp; Model</div>
                    <div class="value">{{ $maintenanceRequest->fixedAssetUnit->brand ?: '' }} {{ $maintenanceRequest->fixedAssetUnit->model ?: 'Standard Unit' }}</div>
                </div>
                @if($maintenanceRequest->fixedAssetUnit->plate_number)
                <div class="info-item">
                    <div class="label">Plate Number</div>
                    <div class="value font-monospace">{{ $maintenanceRequest->fixedAssetUnit->plate_number }}</div>
                </div>
                @endif
                @if($maintenanceRequest->fixedAssetUnit->serial_number)
                <div class="info-item">
                    <div class="label">Serial / Chassis #</div>
                    <div class="value font-monospace">{{ $maintenanceRequest->fixedAssetUnit->serial_number }}</div>
                </div>
                @endif
                @if($maintenanceRequest->fixedAssetUnit->current_location)
                <div class="info-item">
                    <div class="label">Current Location / Site</div>
                    <div class="value">{{ $maintenanceRequest->fixedAssetUnit->current_location }}</div>
                </div>
                @endif
            @endif
            <div class="info-item">
                <div class="label">Operator / Custodian</div>
                <div class="value">{{ $maintenanceRequest->employee->full_name ?? ($maintenanceRequest->reportedBy->name ?? 'Staff') }}</div>
            </div>
            <div class="info-item">
                <div class="label">General Service Technician</div>
                <div class="value">{{ $maintenanceRequest->assignedTo->name ?? 'General Service Team' }}</div>
            </div>
            <div class="info-item">
                <div class="label">Current Ticket Status</div>
                <div class="value text-capitalize">{{ str_replace('_', ' ', $maintenanceRequest->status) }}</div>
            </div>
        </div>

        {{-- 2. Issue Fault Description & Service Diagnosis --}}
        <div class="section-title">
            <i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i> 2. Fault Diagnosis &amp; Technical Findings
        </div>
        <div class="p-3 bg-light rounded-3 mb-3 border">
            <div class="d-flex justify-content-between mb-2">
                <strong><span class="text-muted small text-uppercase">Reported Issue Category:</span> {{ $maintenanceRequest->issue_type_label }}</strong>
                <span class="badge bg-secondary text-uppercase">{{ $maintenanceRequest->urgency }} Priority</span>
            </div>
            <p class="mb-0" style="white-space: pre-wrap; font-size: 0.9rem;">{{ $maintenanceRequest->description }}</p>
        </div>

        @if($maintenanceRequest->admin_notes)
        <div class="p-3 bg-light rounded-3 mb-3 border border-warning border-opacity-25" style="border-left: 4px solid #f59e0b !important;">
            <div class="small text-muted fw-bold text-uppercase mb-1">General Service Workshop Notes &amp; Repair Directives:</div>
            <p class="mb-0 small" style="white-space: pre-wrap;">{{ $maintenanceRequest->admin_notes }}</p>
        </div>
        @endif

        {{-- 3. Materials & Spare Parts Requested from Store --}}
        <div class="section-title">
            <i class="fa-solid fa-boxes-stacked me-1 text-primary"></i> 3. Spare Parts &amp; Material Issuance (Store / Procurement)
        </div>
        @if($maintenanceRequest->materialRequests->isNotEmpty())
            <table class="table table-sm table-bordered align-middle mb-3" style="font-size: 0.85rem;">
                <thead class="table-light">
                    <tr>
                        <th>Material Request #</th>
                        <th>Store Location</th>
                        <th>Requested Item &amp; Specifications</th>
                        <th class="text-center">Quantity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($maintenanceRequest->materialRequests as $mr)
                        @foreach($mr->items as $item)
                        <tr>
                            <td class="font-monospace fw-semibold text-primary">{{ $mr->reference_number }}</td>
                            <td>{{ $mr->store->name ?? 'General Store' }}</td>
                            <td>
                                <strong>{{ $item->product->name ?? 'Spare Part' }}</strong>
                                @if($item->notes)
                                    <small class="text-muted d-block">{{ $item->notes }}</small>
                                @endif
                            </td>
                            <td class="text-center fw-bold">{{ (float)$item->quantity_requested }} {{ $item->product->unit ?? 'pcs' }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ ucfirst(str_replace('_', ' ', $mr->status)) }}</span>
                            </td>
                        </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-muted fst-italic small mb-3">No spare parts or material requests attached to this maintenance ticket.</p>
        @endif

        {{-- 4. Expenses & Repair Budget (Ask Money) --}}
        <div class="section-title">
            <i class="fa-solid fa-hand-holding-dollar me-1 text-success"></i> 4. Financial &amp; Labour Expenses (Ask Money)
        </div>
        @if($maintenanceRequest->expenseRequests->isNotEmpty())
            <table class="table table-sm table-bordered align-middle mb-3" style="font-size: 0.85rem;">
                <thead class="table-light">
                    <tr>
                        <th>Expense Request #</th>
                        <th>Purpose / Service Details</th>
                        <th class="text-end">Amount (ETB)</th>
                        <th>Approval Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($maintenanceRequest->expenseRequests as $exp)
                    <tr>
                        <td class="font-monospace fw-semibold text-success">{{ $exp->request_number }}</td>
                        <td>{{ $exp->description }}</td>
                        <td class="text-end fw-bold font-monospace">ETB {{ number_format($exp->amount, 2) }}</td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $exp->status_label ?? $exp->status }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td colspan="2" class="text-end">Total Cash Expense Requested:</td>
                        <td class="text-end font-monospace text-success">ETB {{ number_format($maintenanceRequest->expenseRequests->sum('amount'), 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        @else
            <p class="text-muted fst-italic small mb-3">No direct cash or external expense requests attached to this maintenance ticket.</p>
        @endif

        {{-- 5. Custody Verification & Sign-Off --}}
        <div class="section-title">
            <i class="fa-solid fa-signature me-1 text-secondary"></i> 5. Verification &amp; Authorization Sign-Off
        </div>
        <div class="row g-4 pt-2">
            <div class="col-4">
                <div class="signature-box">
                    <div class="fw-bold small">{{ $maintenanceRequest->assignedTo->name ?? 'General Service Officer' }}</div>
                    <div class="text-muted small" style="font-size:0.75rem;">General Service / Technician</div>
                    <div class="text-muted small mt-1" style="font-size:0.7rem;">Date: ____________________</div>
                </div>
            </div>
            <div class="col-4">
                <div class="signature-box">
                    <div class="fw-bold small">Store Manager / Custodian</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Parts Verification &amp; Release</div>
                    <div class="text-muted small mt-1" style="font-size:0.7rem;">Date: ____________________</div>
                </div>
            </div>
            <div class="col-4">
                <div class="signature-box">
                    <div class="fw-bold small">General Manager / Approver</div>
                    <div class="text-muted small" style="font-size:0.75rem;">Authorized Signatory</div>
                    <div class="text-muted small mt-1" style="font-size:0.7rem;">Date: ____________________</div>
                </div>
            </div>
        </div>

    </div>

</div>

</body>
</html>
