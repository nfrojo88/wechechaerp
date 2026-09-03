<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Consumption Slip — {{ $materialUsage->usage_no }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1e293b;
        }
        .printable-sheet {
            max-width: 900px;
            margin: 30px auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .header-title {
            letter-spacing: 1px;
            font-weight: 800;
        }
        .table-items th {
            background-color: #f1f5f9 !important;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .signature-box {
            border-top: 1px solid #94a3b8;
            margin-top: 60px;
            padding-top: 8px;
            text-align: center;
        }
        @media print {
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .printable-sheet {
                box-shadow: none !important;
                margin: 0 !important;
                padding: 20px !important;
                max-width: 100% !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    {{-- Non-printable Action Controls --}}
    <div class="container text-center my-3 no-print">
        <button onclick="window.print()" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm me-2">
            <i class="fa-solid fa-print me-1"></i> Print / Download PDF
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="fa-solid fa-times me-1"></i> Close
        </button>
    </div>

    <div class="printable-sheet">
        {{-- Document Header --}}
        <div class="row align-items-center mb-4 pb-3 border-bottom border-2 border-dark">
            <div class="col-8">
                <h3 class="header-title text-uppercase text-dark mb-1">Construct Pro ERP</h3>
                <h5 class="text-primary fw-bold mb-0 text-uppercase">
                    Daily Material Consumption Slip (ዕለታዊ ፍጆታ)
                </h5>
                <small class="text-muted">Store Issue &amp; Site Consumption Voucher</small>
            </div>
            <div class="col-4 text-end">
                <div class="badge bg-dark text-white fs-6 px-3 py-2 font-monospace">
                    {{ $materialUsage->usage_no }}
                </div>
                @if($materialUsage->slip_number)
                    <div class="small text-muted mt-1">Ref #: <strong>{{ $materialUsage->slip_number }}</strong></div>
                @endif
                <div class="small text-muted mt-1">Date: <strong>{{ optional($materialUsage->usage_date)->format('d M Y') }}</strong></div>
            </div>
        </div>

        {{-- Meta Information Grid --}}
        <div class="row g-3 mb-4 p-3 bg-light rounded border">
            <div class="col-6">
                <div class="small text-muted text-uppercase">Issuing Store:</div>
                <strong class="text-dark fs-6">{{ $materialUsage->store->name ?? 'Store' }}</strong>
                <div class="small text-muted">Store Code: {{ $materialUsage->store->code ?? 'N/A' }}</div>
            </div>
            <div class="col-6">
                <div class="small text-muted text-uppercase">Project Site:</div>
                <strong class="text-dark fs-6">{{ $materialUsage->project->name ?? 'Project' }}</strong>
                <div class="small text-muted">Project Code: {{ $materialUsage->project->code ?? 'N/A' }}</div>
            </div>
            <div class="col-6">
                <div class="small text-muted text-uppercase">Consumed / Received By:</div>
                <strong class="text-dark">{{ $materialUsage->consumed_by_name ?? 'Site Staff' }}</strong>
            </div>
            <div class="col-6">
                <div class="small text-muted text-uppercase">Activity / Site Purpose:</div>
                <strong class="text-dark">{{ $materialUsage->activity_type ?? 'Daily Construction Works' }}</strong>
            </div>
            @if($materialUsage->description)
            <div class="col-12 border-top pt-2 mt-2">
                <div class="small text-muted text-uppercase">Notes &amp; Description:</div>
                <div class="small text-dark">{{ $materialUsage->description }}</div>
            </div>
            @endif
        </div>

        {{-- Itemized Consumed Materials Table --}}
        <table class="table table-bordered table-items align-middle mb-4">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 15%;">Item Code</th>
                    <th style="width: 45%;">Material Description</th>
                    <th style="width: 15%;" class="text-end">Qty Issued</th>
                    <th style="width: 10%;">Unit</th>
                    <th style="width: 10%;">Remarks</th>
                </tr>
            </thead>
            <tbody>
                @foreach($materialUsage->items as $index => $item)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="font-monospace fw-semibold">{{ $item->product->item_code ?? 'PRD' }}</td>
                    <td class="fw-bold">{{ $item->product->name ?? 'Item' }}</td>
                    <td class="text-end fw-bold fs-6">{{ number_format($item->effective_quantity, 3) }}</td>
                    <td>{{ $item->unit ?? ($item->product->unit ?? 'pcs') }}</td>
                    <td class="small text-muted">{{ $item->remarks ?? ($item->notes ?? '—') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="table-light">
                    <th colspan="3" class="text-end">Total Items: {{ $materialUsage->items->count() }}</th>
                    <th class="text-end fw-bold fs-6">{{ number_format($materialUsage->total_quantity, 3) }}</th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
        </table>

        {{-- Signatures Section --}}
        <div class="row pt-4 mt-5">
            <div class="col-4">
                <div class="signature-box">
                    <strong>Issued By (Store Keeper)</strong>
                    <div class="small text-muted mt-1">{{ $materialUsage->createdBy->name ?? 'Store Keeper' }}</div>
                    <div class="small text-muted">Signature &amp; Date</div>
                </div>
            </div>
            <div class="col-4">
                <div class="signature-box">
                    <strong>Received By (Foreman / Site)</strong>
                    <div class="small text-muted mt-1">{{ $materialUsage->consumed_by_name ?? 'Receiver Name' }}</div>
                    <div class="small text-muted">Signature &amp; Date</div>
                </div>
            </div>
            <div class="col-4">
                <div class="signature-box">
                    <strong>Approved By (Supervisor)</strong>
                    <div class="small text-muted mt-1">Project / Store Manager</div>
                    <div class="small text-muted">Signature &amp; Date</div>
                </div>
            </div>
        </div>

        {{-- Footer Note --}}
        <div class="text-center text-muted small mt-5 pt-3 border-top">
            This document is an official record generated by Construct Pro ERP on {{ date('Y-m-d H:i:s') }}.
        </div>
    </div>

</body>
</html>
