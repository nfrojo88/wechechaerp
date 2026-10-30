@props([
    'excelUrl' => null,
    'csvUrl'   => null,
    'pdfUrl'   => null,
    'print'    => true,
    'label'    => 'Export',
    'btnClass' => 'btn btn-outline-secondary btn-sm rounded-pill px-3 py-2 shadow-xs fw-semibold',
])

@php
    $hasAnyExport = $excelUrl || $csvUrl || $pdfUrl || $print || (isset($slot) && $slot->isNotEmpty());
@endphp

@if($hasAnyExport)
<div class="wc-export-menu-container position-relative d-inline-block" x-data="{ openExportSheet: false }">
    {{-- Desktop Dropdown (≥768px) --}}
    <div class="dropdown d-none d-md-block">
        <button type="button" class="{{ $btnClass }} dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-download me-1.5"></i>
            <span>{{ $label }}</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-1" style="min-width: 210px; border-radius: var(--wc-radius-md);">
            @if($excelUrl)
                <li>
                    <a class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 rounded text-dark" href="{{ $excelUrl }}">
                        <i class="fa-solid fa-file-excel text-success" style="font-size: 1rem;"></i>
                        <span style="font-size: var(--wc-text-sm);">Excel Spreadsheet (.xlsx)</span>
                    </a>
                </li>
            @endif

            @if($csvUrl)
                <li>
                    <a class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 rounded text-dark" href="{{ $csvUrl }}">
                        <i class="fa-solid fa-file-csv text-primary" style="font-size: 1rem;"></i>
                        <span style="font-size: var(--wc-text-sm);">CSV Format (.csv)</span>
                    </a>
                </li>
            @endif

            @if($pdfUrl)
                <li>
                    <a class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 rounded text-dark" href="{{ $pdfUrl }}" target="_blank">
                        <i class="fa-solid fa-file-pdf text-danger" style="font-size: 1rem;"></i>
                        <span style="font-size: var(--wc-text-sm);">PDF Document (.pdf)</span>
                    </a>
                </li>
            @endif

            {{ $slot ?? '' }}

            @if($print)
                @if($excelUrl || $csvUrl || $pdfUrl || (isset($slot) && $slot->isNotEmpty()))
                    <li><hr class="dropdown-divider my-1"></li>
                @endif
                <li>
                    <button type="button" class="dropdown-item py-2 px-3 d-flex align-items-center gap-2 rounded text-dark w-100 text-start" onclick="window.print()">
                        <i class="fa-solid fa-print text-muted" style="font-size: 1rem;"></i>
                        <span style="font-size: var(--wc-text-sm);">Print Document</span>
                    </button>
                </li>
            @endif
        </ul>
    </div>

    {{-- Mobile Trigger & Bottom Sheet (<768px) --}}
    <div class="d-block d-md-none">
        <button type="button" @click="openExportSheet = true" class="{{ $btnClass }}">
            <i class="fa-solid fa-download me-1.5"></i>
            <span>{{ $label }}</span>
        </button>

        <div class="wc-bottom-sheet-backdrop" 
             :class="{ 'active': openExportSheet }"
             @click.self="openExportSheet = false"
             x-cloak>
            <div class="wc-bottom-sheet">
                <div class="wc-sheet-handle"></div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="fa-solid fa-file-arrow-down text-primary me-2"></i>Choose Export Format</h6>
                    <button type="button" @click="openExportSheet = false" class="btn btn-sm btn-link text-muted p-1">
                        <i class="fa-solid fa-xmark fs-5"></i>
                    </button>
                </div>

                <div class="d-flex flex-column gap-2">
                    @if($excelUrl)
                        <a href="{{ $excelUrl }}" class="wc-btn-touch wc-btn-outline w-100 justify-content-start py-3 px-3">
                            <i class="fa-solid fa-file-excel text-success fs-5 me-2"></i>
                            <div class="text-start">
                                <div class="fw-bold text-dark">Excel Spreadsheet (.xlsx)</div>
                                <small class="text-muted">Master corporate spreadsheet</small>
                            </div>
                        </a>
                    @endif

                    @if($csvUrl)
                        <a href="{{ $csvUrl }}" class="wc-btn-touch wc-btn-outline w-100 justify-content-start py-3 px-3">
                            <i class="fa-solid fa-file-csv text-primary fs-5 me-2"></i>
                            <div class="text-start">
                                <div class="fw-bold text-dark">CSV Format (.csv)</div>
                                <small class="text-muted">Plain comma-delimited data</small>
                            </div>
                        </a>
                    @endif

                    @if($pdfUrl)
                        <a href="{{ $pdfUrl }}" target="_blank" class="wc-btn-touch wc-btn-outline w-100 justify-content-start py-3 px-3">
                            <i class="fa-solid fa-file-pdf text-danger fs-5 me-2"></i>
                            <div class="text-start">
                                <div class="fw-bold text-dark">PDF Document (.pdf)</div>
                                <small class="text-muted">Printable signed document</small>
                            </div>
                        </a>
                    @endif

                    @if($print)
                        <button type="button" onclick="window.print()" class="wc-btn-touch wc-btn-outline w-100 justify-content-start py-3 px-3">
                            <i class="fa-solid fa-print text-muted fs-5 me-2"></i>
                            <div class="text-start">
                                <div class="fw-bold text-dark">Print / Save as PDF</div>
                                <small class="text-muted">Direct browser print dialog</small>
                            </div>
                        </button>
                    @endif
                </div>

                <button type="button" @click="openExportSheet = false" class="wc-btn-touch wc-btn-outline w-100 mt-3 text-center">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
@endif
