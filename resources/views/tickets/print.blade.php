<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Department Problem Report & Suggestion Form - {{ $ticket->ticket_no }}</title>
    <style>
        @page {
            size: A4;
            margin: 12mm 15mm 15mm 15mm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.35;
            color: #111;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .no-print {
            background: #f1f5f9;
            padding: 12px 20px;
            border-bottom: 1px solid #cbd5e1;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        @media print {
            .no-print { display: none !important; }
            body { font-size: 10pt; }
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header h1 {
            font-size: 18pt;
            font-weight: bold;
            margin: 0 0 4px 0;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header h2 {
            font-size: 14pt;
            font-weight: 600;
            margin: 0 0 4px 0;
            color: #1e293b;
        }
        .header p {
            font-size: 9pt;
            color: #475569;
            margin: 0;
        }
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            background-color: #e2e8f0;
            padding: 4px 8px;
            margin-top: 10px;
            margin-bottom: 6px;
            border-left: 4px solid #0284c7;
            text-transform: uppercase;
        }
        table.form-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        table.form-grid th, table.form-grid td {
            border: 1px solid #94a3b8;
            padding: 5px 8px;
            vertical-align: top;
            font-size: 9.5pt;
        }
        table.form-grid th {
            background-color: #f8fafc;
            text-align: left;
            width: 25%;
            font-weight: 600;
            color: #334155;
        }
        .box-text {
            min-height: 40px;
            white-space: pre-wrap;
            font-size: 9pt;
        }
        .check-box {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid #334155;
            margin-right: 4px;
            vertical-align: middle;
            text-align: center;
            line-height: 11px;
            font-size: 10px;
            font-weight: bold;
        }
        .check-item {
            margin-right: 20px;
            display: inline-block;
            font-size: 9.5pt;
        }
        .sla-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 8.5pt;
        }
        .sla-table th, .sla-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 6px;
            text-align: left;
        }
        .sla-table th {
            background-color: #f1f5f9;
        }
        .signature-row {
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
        }
        .sig-box {
            width: 45%;
            border-top: 1px solid #475569;
            padding-top: 6px;
            text-align: center;
            font-size: 9pt;
        }
        .btn-print {
            background: #0284c7;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
        }
        .btn-back {
            background: #64748b;
            color: #fff;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 13px;
        }
    </style>
</head>
<body>

    <div class="no-print">
        <div>
            <strong>IT Department Problem Report & Suggestion Form</strong> — Ref: {{ $ticket->ticket_no }}
        </div>
        <div>
            <a href="javascript:window.history.back()" class="btn-back">← Back</a>
            <button onclick="window.print()" class="btn-print">🖨 Print / Save as PDF</button>
        </div>
    </div>

    <!-- Header -->
    <div class="header">
        <h1>IT Department</h1>
        <h2>Problem Report & Suggestion Form</h2>
        <p>Use this form to report an IT problem or share an idea for improvement. Fill in the sections that apply to you.</p>
    </div>

    <!-- 1. Submitter Information -->
    <div class="section-title">1. Submitter Information</div>
    <table class="form-grid">
        <tr>
            <th>Full name</th>
            <td style="width: 35%;">{{ $ticket->submitter_name ?: ($ticket->user->name ?? '') }}</td>
            <th>Employee ID</th>
            <td style="width: 25%;">{{ $ticket->employee_code ?: ($ticket->user->employee->employee_code ?? 'N/A') }}</td>
        </tr>
        <tr>
            <th>Department</th>
            <td>{{ $ticket->department ?: ($ticket->user->employee->department ?? 'General') }}</td>
            <th>Email / phone</th>
            <td>{{ $ticket->contact_phone ?: ($ticket->user->employee->phone ?? $ticket->user->email) }}</td>
        </tr>
        <tr>
            <th>Date submitted</th>
            <td colspan="3">{{ $ticket->submitted_date ? $ticket->submitted_date->format('d/m/Y') : $ticket->created_at->format('d/m/Y') }}</td>
        </tr>
    </table>

    <!-- 2. Type of Submission -->
    <div class="section-title">2. Type of Submission</div>
    <div style="padding: 4px 8px; border: 1px solid #94a3b8; margin-bottom: 8px;">
        <span class="check-item">
            <span class="check-box">{{ $ticket->submission_type === 'problem' ? '✓' : '' }}</span> Problem / Issue
        </span>
        <span class="check-item">
            <span class="check-box">{{ $ticket->submission_type === 'suggestion' ? '✓' : '' }}</span> Suggestion / Improvement idea
        </span>
        <span class="check-item">
            <span class="check-box">{{ $ticket->submission_type === 'both' ? '✓' : '' }}</span> Both
        </span>
    </div>

    <!-- 3. Problem Report -->
    @if(in_array($ticket->submission_type, ['problem', 'both']) || !empty($ticket->subject))
    <div class="section-title">3. Problem Report</div>
    <table class="form-grid">
        <tr>
            <th>Problem title</th>
            <td colspan="3"><strong>{{ $ticket->subject }}</strong></td>
        </tr>
        <tr>
            <th>Category</th>
            <td>{{ $ticket->category }}</td>
            <th>Affected system or device</th>
            <td>{{ $ticket->affected_system ?: 'General PC / ERP' }}</td>
        </tr>
        <tr>
            <th>Location (building, floor, room)</th>
            <td>{{ $ticket->location ?: 'Not specified' }}</td>
            <th>When did it start?</th>
            <td>{{ $ticket->incident_started_at ?: $ticket->created_at->format('M d, Y H:i') }}</td>
        </tr>
        <tr>
            <th>How often does it happen?</th>
            <td colspan="3">
                <span class="check-item"><span class="check-box">{{ $ticket->frequency === 'Once' ? '✓' : '' }}</span> Once</span>
                <span class="check-item"><span class="check-box">{{ $ticket->frequency === 'Sometimes' ? '✓' : '' }}</span> Sometimes</span>
                <span class="check-item"><span class="check-box">{{ $ticket->frequency === 'Always' ? '✓' : '' }}</span> Always</span>
            </td>
        </tr>
        <tr>
            <th>Description of the problem<br><small style="color:#64748b;font-weight:normal;">(What is happening & expected)</small></th>
            <td colspan="3">
                <div class="box-text">{{ $ticket->description }}</div>
            </td>
        </tr>
        <tr>
            <th>Steps to reproduce</th>
            <td colspan="3">
                <div class="box-text" style="min-height:25px;">{{ $ticket->steps_to_reproduce ?: 'N/A' }}</div>
            </td>
        </tr>
        <tr>
            <th>Error message (if any)</th>
            <td colspan="3">
                <div class="box-text" style="min-height:25px;font-family:monospace;font-size:8.5pt;">{{ $ticket->error_message ?: 'None' }}</div>
            </td>
        </tr>
        <tr>
            <th>Impact and urgency</th>
            <td colspan="3">
                @php $urg = strtolower($ticket->priority ?? $ticket->impact_urgency ?? 'medium'); @endphp
                <span class="check-item"><span class="check-box">{{ $urg === 'critical' ? '✓' : '' }}</span> Critical (cannot work at all / many affected)</span><br>
                <span class="check-item"><span class="check-box">{{ $urg === 'high' ? '✓' : '' }}</span> High (major part of work is blocked)</span><br>
                <span class="check-item"><span class="check-box">{{ $urg === 'medium' ? '✓' : '' }}</span> Medium (can work, but with difficulty)</span><br>
                <span class="check-item"><span class="check-box">{{ $urg === 'low' ? '✓' : '' }}</span> Low (minor inconvenience)</span>
            </td>
        </tr>
        <tr>
            <th>What have you already tried?</th>
            <td colspan="3">
                <div class="box-text" style="min-height:25px;">{{ $ticket->already_tried ?: 'None' }}</div>
            </td>
        </tr>
    </table>
    @endif

    <!-- 4. Suggestion / Improvement Idea -->
    @if(in_array($ticket->submission_type, ['suggestion', 'both']) || !empty($ticket->suggestion_title))
    <div class="section-title">4. Suggestion / Improvement Idea</div>
    <table class="form-grid">
        <tr>
            <th>Suggestion title</th>
            <td colspan="3"><strong>{{ $ticket->suggestion_title ?: $ticket->subject }}</strong></td>
        </tr>
        <tr>
            <th>Area</th>
            <td colspan="3">{{ $ticket->suggestion_area ?: 'Tools & software' }}</td>
        </tr>
        <tr>
            <th>Current situation<br><small style="color:#64748b;font-weight:normal;">(Issue or limitation today)</small></th>
            <td colspan="3">
                <div class="box-text" style="min-height:30px;">{{ $ticket->current_situation ?: 'N/A' }}</div>
            </td>
        </tr>
        <tr>
            <th>Your suggestion<br><small style="color:#64748b;font-weight:normal;">(What to change or add)</small></th>
            <td colspan="3">
                <div class="box-text" style="min-height:30px;">{{ $ticket->suggested_change ?: 'N/A' }}</div>
            </td>
        </tr>
        <tr>
            <th>Expected benefit</th>
            <td colspan="3">
                @php
                    $bens = is_array($ticket->expected_benefits) ? $ticket->expected_benefits : [];
                @endphp
                <span class="check-item"><span class="check-box">{{ in_array('Saves time', $bens) ? '✓' : '' }}</span> Saves time</span>
                <span class="check-item"><span class="check-box">{{ in_array('Reduces cost', $bens) ? '✓' : '' }}</span> Reduces cost</span>
                <span class="check-item"><span class="check-box">{{ in_array('Improves security', $bens) ? '✓' : '' }}</span> Improves security</span>
                <span class="check-item"><span class="check-box">{{ in_array('Improves user experience', $bens) ? '✓' : '' }}</span> Improves user experience</span>
                @if($ticket->expected_benefits_other)
                    <span class="check-item"><span class="check-box">✓</span> Other: {{ $ticket->expected_benefits_other }}</span>
                @endif
            </td>
        </tr>
    </table>
    @endif

    @if($ticket->has_material_request)
    <!-- IT Material Requisition & Store Dispatch Authorization -->
    <div class="section-title" style="border-left-color: #d97706; background-color: #fef3c7;">
        IT Material Requisition & Warehouse Dispatch Authorization
    </div>
    <table class="form-grid">
        <tr>
            <th>Justification</th>
            <td colspan="3">{{ $ticket->mr_justification ?: 'Standard IT equipment requirement' }}</td>
        </tr>
        <tr>
            <th>Target Location / Project</th>
            <td>{{ $ticket->mr_project_location ?: 'Head Office / IT Department' }}</td>
            <th>Urgency Level</th>
            <td>{{ strtoupper($ticket->mr_urgency ?: 'MEDIUM') }}</td>
        </tr>
    </table>

    <table class="form-grid" style="margin-top: 4px;">
        <thead>
            <tr style="background-color: #f1f5f9;">
                <th style="width: 30px; text-align: center;">#</th>
                <th>Item Description</th>
                <th style="width: 60px; text-align: center;">Req. Qty</th>
                <th style="width: 50px;">Unit</th>
                <th>Purpose</th>
                <th style="width: 70px; text-align: center;">Store Qty</th>
                <th style="width: 90px; text-align: center;">Store Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ticket->materialRequestItems as $idx => $item)
                <tr>
                    <td style="text-align: center;">{{ $idx + 1 }}</td>
                    <td><strong>{{ $item->item_name }}</strong></td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td>{{ $item->unit ?: 'pcs' }}</td>
                    <td>{{ $item->purpose ?: '—' }}</td>
                    <td style="text-align: center;">{{ $item->store_dispatch_qty ?: '—' }}</td>
                    <td style="text-align: center;">{{ strtoupper($item->store_dispatch_status ?: 'Pending') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">No individual line items.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- 3-Way Requisition Signatures -->
    <table class="form-grid" style="margin-top: 6px;">
        <tr style="background-color: #f8fafc;">
            <th style="width: 33.3%; text-align: center;">1. General Manager (GM) Approval</th>
            <th style="width: 33.3%; text-align: center;">2. Store Manager Dispatch</th>
            <th style="width: 33.3%; text-align: center;">3. Head Office Secretary Receipt</th>
        </tr>
        <tr>
            <td style="font-size: 8.5pt;">
                <strong>Status:</strong> {{ strtoupper(str_replace('_', ' ', $ticket->mr_gm_status ?: 'Pending Review')) }}<br>
                <strong>Authorized By:</strong> {{ $ticket->mrGmDecidedBy->name ?? 'GM' }}<br>
                <strong>Date:</strong> {{ $ticket->mr_gm_decided_at ? $ticket->mr_gm_decided_at->format('d/m/Y') : '___________' }}<br>
                @if($ticket->mr_gm_notes)
                    <strong>Notes:</strong> {{ $ticket->mr_gm_notes }}<br>
                @endif
                <div style="margin-top: 20px; border-top: 1px dotted #94a3b8; text-align: center; color: #64748b;">GM Signature</div>
            </td>
            <td style="font-size: 8.5pt;">
                <strong>Status:</strong> {{ strtoupper(str_replace('_', ' ', $ticket->mr_store_status ?: 'Pending Store')) }}<br>
                <strong>Dispatched By:</strong> {{ $ticket->mrStoreManagedBy->name ?? 'Store Manager' }}<br>
                <strong>Date:</strong> {{ $ticket->mr_store_dispatched_at ? $ticket->mr_store_dispatched_at->format('d/m/Y') : '___________' }}<br>
                @if($ticket->mr_store_notes)
                    <strong>Notes:</strong> {{ $ticket->mr_store_notes }}<br>
                @endif
                <div style="margin-top: 20px; border-top: 1px dotted #94a3b8; text-align: center; color: #64748b;">Store Manager Signature</div>
            </td>
            <td style="font-size: 8.5pt;">
                <strong>Status:</strong> {{ $ticket->mr_secretary_received ? 'CONFIRMED RECEIVED' : 'PENDING HO DELIVERY' }}<br>
                <strong>Received By:</strong> {{ $ticket->mrSecretaryReceivedBy->name ?? 'HO Secretary' }}<br>
                <strong>Date:</strong> {{ $ticket->mr_secretary_received_at ? $ticket->mr_secretary_received_at->format('d/m/Y') : '___________' }}<br>
                @if($ticket->mr_secretary_notes)
                    <strong>Notes:</strong> {{ $ticket->mr_secretary_notes }}<br>
                @endif
                <div style="margin-top: 20px; border-top: 1px dotted #94a3b8; text-align: center; color: #64748b;">Secretary Signature</div>
            </td>
        </tr>
    </table>
    @endif

    <!-- 5. Attachments -->
    <div class="section-title">5. Attachments</div>
    <table class="form-grid">
        <tr>
            <th>List of attached items:</th>
            <td>
                @if($ticket->attachment_name)
                    <strong>File Attached:</strong> {{ $ticket->attachment_name }} <br>
                @endif
                {{ $ticket->attachments_notes ?: ($ticket->attachment_name ? 'Attachment uploaded to ERP ticket record.' : 'None') }}
            </td>
        </tr>
    </table>

    <!-- 6. For IT Department Use Only -->
    <div class="section-title">6. For IT Department Use Only</div>
    <table class="form-grid">
        <tr>
            <th>Ticket / reference number</th>
            <td style="width: 35%;"><strong>{{ $ticket->ticket_no }}</strong></td>
            <th>Date received</th>
            <td style="width: 25%;">{{ $ticket->date_received ? $ticket->date_received->format('d/m/Y') : ($ticket->created_at->format('d/m/Y')) }}</td>
        </tr>
        <tr>
            <th>Received by</th>
            <td>{{ $ticket->receivedBy->name ?? 'IT Helpdesk' }}</td>
            <th>Assigned to</th>
            <td>{{ $ticket->assignedTo->name ?? 'Unassigned' }}</td>
        </tr>
        <tr>
            <th>Priority</th>
            <td>{{ strtoupper($ticket->priority) }}</td>
            <th>Status</th>
            <td>{{ strtoupper(str_replace('_', ' ', $ticket->status)) }}</td>
        </tr>
        <tr>
            <th>Target resolution date</th>
            <td colspan="3">{{ $ticket->target_resolution_date ? $ticket->target_resolution_date->format('d/m/Y') : 'According to SLA' }}</td>
        </tr>
        <tr>
            <th>Actions taken / root cause</th>
            <td colspan="3">
                <div class="box-text" style="min-height:35px;">{{ $ticket->actions_taken ?: ($ticket->root_cause ?: 'In progress') }}</div>
            </td>
        </tr>
        <tr>
            <th>Resolution or decision<br><small style="color:#64748b;font-weight:normal;">(Approved, planned or declined, and why)</small></th>
            <td colspan="3">
                <div class="box-text" style="min-height:35px;">{{ $ticket->resolution_decision ?: ($ticket->status === 'resolved' ? 'Resolved successfully.' : 'Pending review') }}</div>
            </td>
        </tr>
        <tr>
            <th>Date closed</th>
            <td>{{ $ticket->date_closed ? $ticket->date_closed->format('d/m/Y') : ($ticket->resolved_at ? $ticket->resolved_at->format('d/m/Y') : '____________________') }}</td>
            <th>User confirmed resolved</th>
            <td>
                <span class="check-item"><span class="check-box">{{ $ticket->user_confirmed_resolved === 'yes' ? '✓' : '' }}</span> Yes</span>
                <span class="check-item"><span class="check-box">{{ $ticket->user_confirmed_resolved === 'no' ? '✓' : '' }}</span> No</span>
            </td>
        </tr>
    </table>

    <!-- Response Time Guide -->
    <div style="margin-top: 6px;">
        <div style="font-weight: bold; font-size: 8.5pt; text-transform: uppercase; color: #475569;">Response Time Guide</div>
        <table class="sla-table">
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>First response</th>
                    <th>Target resolution</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Critical</strong></td>
                    <td>1 hour</td>
                    <td>4 hours</td>
                </tr>
                <tr>
                    <td><strong>High</strong></td>
                    <td>4 hours</td>
                    <td>1 business day</td>
                </tr>
                <tr>
                    <td><strong>Medium</strong></td>
                    <td>1 business day</td>
                    <td>3 business days</td>
                </tr>
                <tr>
                    <td><strong>Low</strong></td>
                    <td>2 business days</td>
                    <td>5 business days</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Signatures -->
    <div class="signature-row">
        <div class="sig-box">
            Submitter Signature & Date<br>
            <strong>{{ $ticket->submitter_name ?: ($ticket->user->name ?? '') }}</strong>
        </div>
        <div class="sig-box">
            IT Department / Admin Signature & Date<br>
            <strong>{{ $ticket->receivedBy->name ?? ($ticket->assignedTo->name ?? 'IT Manager') }}</strong>
        </div>
    </div>

</body>
</html>
