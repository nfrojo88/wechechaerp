<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $employeeLetter->title }} - {{ $employeeLetter->employee->full_name ?? 'Letter' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            color: #000;
            background: #fff;
            padding: 40px 60px;
            font-size: 14pt;
            line-height: 1.6;
        }
        .letterhead-title {
            font-size: 22pt;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .letter-content {
            white-space: pre-line;
            text-align: justify;
            margin-top: 25px;
            margin-bottom: 40px;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            width: 220px;
            height: 50px;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print mb-4 d-flex justify-content-between align-items-center bg-light p-3 border rounded">
        <div>
            <strong>Print Preview:</strong> Ready for official company letterhead printing.
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-primary btn-sm px-4 fw-bold">
                🖨️ Print Letter Now
            </button>
            <button onclick="window.close()" class="btn btn-secondary btn-sm ms-2">
                Close
            </button>
        </div>
    </div>

    {{-- Company Header --}}
    <div class="text-center border-bottom pb-3 mb-4">
        <div class="letterhead-title">WECHECHA CONSTRUCTION</div>
        <div style="font-size: 12pt; text-transform: uppercase; letter-spacing: 0.5px;">Human Resources &amp; Personnel Administration</div>
        <div style="font-size: 10pt; color: #444;">Addis Ababa, Ethiopia · Tel: +251 11 000 0000 · Email: hr@wechechaconstruction.com</div>
    </div>

    {{-- Reference & Date --}}
    <table style="width: 100%; margin-bottom: 25px;">
        <tr>
            <td style="text-align: left; font-weight: bold;">Ref: {{ $employeeLetter->reference_number }}</td>
            <td style="text-align: right; font-weight: bold;">Date: {{ optional($employeeLetter->issued_date)->format('d F Y') }}</td>
        </tr>
    </table>

    {{-- Recipient Block --}}
    <div style="margin-bottom: 25px;">
        <div><strong>To:</strong> {{ $employeeLetter->employee->full_name }}</div>
        <div><strong>Employee Code:</strong> {{ $employeeLetter->employee->employee_code }}</div>
        <div><strong>Position:</strong> {{ $employeeLetter->employee->role_title ?? 'Employee' }}</div>
        <div><strong>Department:</strong> {{ $employeeLetter->employee->department }}</div>
        <div><strong>Project / Site:</strong> {{ $employeeLetter->employee->project->name ?? 'Head Office' }}</div>
    </div>

    {{-- Subject --}}
    <div style="font-weight: bold; text-decoration: underline; margin-bottom: 20px; font-size: 15pt;">
        SUBJECT: {{ strtoupper($employeeLetter->title) }}
    </div>

    {{-- Content --}}
    <div class="letter-content">
        {{ $employeeLetter->content }}
    </div>

    @if($employeeLetter->action_required)
    <div style="margin-bottom: 30px; font-size: 12pt; background: #f9f9f9; padding: 12px; border-left: 3px solid #000;">
        <strong>Corrective Expectation / Follow-up:</strong><br>
        {{ $employeeLetter->action_required }}
    </div>
    @endif

    {{-- Signatures --}}
    <table style="width: 100%; margin-top: 50px;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <div style="font-weight: bold; margin-bottom: 40px;">For Wechecha Construction:</div>
                <div class="signature-line"></div>
                <div style="margin-top: 5px; font-weight: bold;">{{ $employeeLetter->issuer->name ?? 'HR Department' }}</div>
                <div style="font-size: 11pt;">Authorized Human Resources Officer</div>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <div style="font-weight: bold; margin-bottom: 40px;">Employee Acknowledgement:</div>
                <div class="signature-line"></div>
                <div style="margin-top: 5px; font-weight: bold;">{{ $employeeLetter->employee->full_name }}</div>
                <div style="font-size: 11pt;">Signature &amp; Date</div>
            </td>
        </tr>
    </table>

</body>
</html>
