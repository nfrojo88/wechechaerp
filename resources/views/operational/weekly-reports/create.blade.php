@extends('layouts.app')
@section('title', 'Draft Weekly Report')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">New Weekly Progress Report</h1>
        <a href="{{ route('weekly-reports.index') }}" class="btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to List
        </a>
    </div>

    <form action="{{ route('weekly-reports.store') }}" method="POST">
        @csrf
        <div class="row">
            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Report Context</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-3">
                            <label class="font-weight-bold">Project <span class="text-danger">*</span></label>
                            <select name="project_id" class="form-control form-select" required>
                                <option value="">-- Select Project --</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" {{ old('project_id', request('project_id')) == $project->id ? 'selected' : '' }}>
                                        {{ $project->name }}{{ $project->code ? ' (' . $project->code . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>Week Start (Monday) <span class="text-danger">*</span></label>
                            <input type="date" name="week_start" class="form-control" value="{{ old('week_start') }}" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Week End (Sunday) <span class="text-danger">*</span></label>
                            <input type="date" name="week_end" class="form-control" value="{{ old('week_end') }}" required>
                        </div>
                        <hr>
                        <div class="form-group mb-3">
                            <label>Planned Progress (%)</label>
                            <input type="number" name="planned_progress_percent" class="form-control" step="0.01" min="0" max="100" value="{{ old('planned_progress_percent') }}">
                        </div>
                        <div class="form-group mb-0">
                            <label>Actual Progress (%)</label>
                            <input type="number" name="actual_progress_percent" class="form-control" step="0.01" min="0" max="100" value="{{ old('actual_progress_percent') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Executive Summaries & Analysis</h6>
                    </div>
                    <div class="card-body">
                        <div class="form-group mb-4">
                            <label class="font-weight-bold">Executive Summary</label>
                            <textarea name="executive_summary" class="form-control" rows="4" placeholder="Summarize the overall progress, milestones achieved, and general site status...">{{ old('executive_summary') }}</textarea>
                        </div>
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-danger">Critical Issues & Delays</label>
                            <textarea name="critical_issues" class="form-control border-left-danger" rows="3" placeholder="Describe any roadblocks, material shortages, weather delays, or safety incidents...">{{ old('critical_issues') }}</textarea>
                        </div>
                        <div class="form-group mb-0">
                            <label class="font-weight-bold text-info">Plan for Next Week</label>
                            <textarea name="next_week_plan" class="form-control border-left-info" rows="3" placeholder="Outline the main targets and resource requirements for the upcoming week...">{{ old('next_week_plan') }}</textarea>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane mr-1"></i> Submit Weekly Report to Planning</button>
                    </div>
                </div>
            </div>

            <!-- Attached Daily Reports Feed (Read-Only Preview) -->
            <div class="col-12 mt-2">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-wrap align-items-center justify-content-between">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <h6 class="m-0 font-weight-bold text-primary mr-2">
                                <i class="fas fa-calendar-day mr-1"></i> Daily Reports for Selected Week
                            </h6>
                            <span class="badge badge-secondary" style="font-size: 0.75rem;">Read-Only</span>
                            <span id="drCountBadge" class="badge badge-primary ml-2" style="display:none;">0 Days</span>
                            <span id="drManpowerBadge" class="badge badge-info ml-1" style="display:none;">0 Manpower</span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-success" id="drSummarizeBtn" style="display:none;">
                                <i class="fas fa-magic mr-1"></i> Auto-fill Summary from Daily Reports
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Initial state prompt -->
                        <div id="drInitialState" class="alert alert-light border text-center py-4 mb-0">
                            <i class="fas fa-calendar-week fa-3x mb-3 text-gray-400"></i>
                            <h6 class="font-weight-bold text-gray-700">Select Project & Week Dates</h6>
                            <p class="text-muted small mb-0">Choose a project and the week dates (Monday to Sunday) above to load the non-editable site daily reports.</p>
                        </div>

                        <!-- Loading State -->
                        <div id="drLoadingState" class="text-center py-4" style="display:none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted small mb-0">Fetching daily reports for the selected week...</p>
                        </div>

                        <!-- Empty State -->
                        <div id="drEmptyState" class="alert alert-warning text-center py-4 mb-0" style="display:none;">
                            <i class="fas fa-exclamation-circle fa-2x mb-2 text-warning"></i>
                            <h6 class="font-weight-bold mb-1">No Daily Reports Found for this Week</h6>
                            <p class="text-muted small mb-0">No site daily reports were found for this project in the selected week. You can still write your weekly summary and submit to Planning.</p>
                        </div>

                        <!-- Daily Reports Container -->
                        <div id="drContainer" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const projectSelect = document.querySelector('select[name="project_id"]');
    const weekStartInput = document.querySelector('input[name="week_start"]');
    const weekEndInput = document.querySelector('input[name="week_end"]');
    const execSummaryTextarea = document.querySelector('textarea[name="executive_summary"]');
    const criticalIssuesTextarea = document.querySelector('textarea[name="critical_issues"]');

    const drInitialState = document.getElementById('drInitialState');
    const drLoadingState = document.getElementById('drLoadingState');
    const drEmptyState = document.getElementById('drEmptyState');
    const drContainer = document.getElementById('drContainer');
    const drCountBadge = document.getElementById('drCountBadge');
    const drManpowerBadge = document.getElementById('drManpowerBadge');
    const drSummarizeBtn = document.getElementById('drSummarizeBtn');

    let loadedReports = [];

    // If user sets week_start, auto calculate week_end (Sunday = +6 days) if empty
    weekStartInput.addEventListener('change', function() {
        if (this.value && !weekEndInput.value) {
            const startDate = new Date(this.value);
            if (!isNaN(startDate.getTime())) {
                const endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + 6);
                weekEndInput.value = endDate.toISOString().split('T')[0];
            }
        }
        fetchDailyReports();
    });

    weekEndInput.addEventListener('change', fetchDailyReports);
    projectSelect.addEventListener('change', fetchDailyReports);

    // Initial check if values are already selected
    if (projectSelect.value && weekStartInput.value && weekEndInput.value) {
        fetchDailyReports();
    }

    function fetchDailyReports() {
        const projectId = projectSelect.value;
        const weekStart = weekStartInput.value;
        const weekEnd = weekEndInput.value;

        if (!projectId || !weekStart || !weekEnd) {
            drInitialState.style.display = 'block';
            drLoadingState.style.display = 'none';
            drEmptyState.style.display = 'none';
            drContainer.style.display = 'none';
            drCountBadge.style.display = 'none';
            drManpowerBadge.style.display = 'none';
            drSummarizeBtn.style.display = 'none';
            return;
        }

        drInitialState.style.display = 'none';
        drLoadingState.style.display = 'block';
        drEmptyState.style.display = 'none';
        drContainer.style.display = 'none';
        drCountBadge.style.display = 'none';
        drManpowerBadge.style.display = 'none';
        drSummarizeBtn.style.display = 'none';

        const url = `{{ route('weekly-reports.daily-reports-ajax') }}?project_id=${encodeURIComponent(projectId)}&week_start=${encodeURIComponent(weekStart)}&week_end=${encodeURIComponent(weekEnd)}`;

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Network error');
            return response.json();
        })
        .then(data => {
            drLoadingState.style.display = 'none';
            if (!data.success || !data.reports || data.reports.length === 0) {
                loadedReports = [];
                drEmptyState.style.display = 'block';
                drContainer.innerHTML = '';
                return;
            }

            loadedReports = data.reports;
            renderDailyReports(loadedReports);
        })
        .catch(err => {
            console.error(err);
            drLoadingState.style.display = 'none';
            drEmptyState.style.display = 'block';
            drEmptyState.querySelector('h6').textContent = 'Unable to Load Daily Reports';
            drEmptyState.querySelector('p').textContent = 'An error occurred while fetching daily reports. Please verify network and try again.';
        });
    }

    function renderDailyReports(reports) {
        let totalManpower = 0;
        let html = '';

        reports.forEach((rep, idx) => {
            totalManpower += (rep.total_manpower || 0);

            let statusBadge = '';
            if (rep.status === 'approved') {
                statusBadge = '<span class="badge badge-success">Approved</span>';
            } else if (rep.status === 'submitted') {
                statusBadge = '<span class="badge badge-info">Submitted</span>';
            } else {
                statusBadge = '<span class="badge badge-secondary">Draft</span>';
            }

            let weatherInfo = rep.weather_conditions ? `<span class="badge badge-light border mr-1"><i class="fas fa-cloud-sun text-warning mr-1"></i>${escapeHtml(rep.weather_conditions)} ${rep.temperature ? rep.temperature + '°C' : ''}</span>` : '';

            let itemsRows = '';
            if (rep.items && rep.items.length > 0) {
                rep.items.forEach((item, itemIdx) => {
                    itemsRows += `
                        <tr>
                            <td class="text-center font-weight-bold" style="width: 40px;">${itemIdx + 1}</td>
                            <td>${escapeHtml(item.work_description || '-')}</td>
                            <td class="text-center">${item.qty_completed > 0 ? Number(item.qty_completed).toFixed(2) : '-'}</td>
                            <td class="text-center">${item.workers_count > 0 ? item.workers_count : '-'}</td>
                            <td>${escapeHtml(item.equipment_used || '-')}</td>
                            <td class="${item.issues ? 'text-danger font-weight-bold' : 'text-muted'}">${escapeHtml(item.issues || '-')}</td>
                        </tr>
                    `;
                });
            } else {
                itemsRows = `<tr><td colspan="6" class="text-center text-muted py-2">No task items recorded for this day.</td></tr>`;
            }

            let notesHtml = '';
            if (rep.general_notes) {
                notesHtml += `<div class="mb-1"><small class="font-weight-bold text-dark">General Notes:</small> <span class="small text-muted">${escapeHtml(rep.general_notes)}</span></div>`;
            }
            if (rep.safety_incidents) {
                notesHtml += `<div class="mb-1 text-danger"><small class="font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i>Safety Incidents:</small> <span class="small">${escapeHtml(rep.safety_incidents)}</span></div>`;
            }
            if (rep.site_diary_remark) {
                notesHtml += `<div class="mb-1"><small class="font-weight-bold text-dark">Site Diary Remark:</small> <span class="small text-muted">${escapeHtml(rep.site_diary_remark)}</span></div>`;
            }
            if (rep.site_book_pic) {
                notesHtml += `<div class="mt-2"><a href="${rep.site_book_pic}" target="_blank" class="btn btn-xs btn-outline-info"><i class="fas fa-image mr-1"></i>View Site Book Photo</a></div>`;
            }

            html += `
                <div class="card mb-3 border-left-primary shadow-sm">
                    <div class="card-header bg-light py-2 px-3 d-flex flex-wrap align-items-center justify-content-between">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="font-weight-bold text-gray-800 mr-2"><i class="far fa-calendar-alt text-primary mr-1"></i>${escapeHtml(rep.formatted_date)}</span>
                            ${statusBadge}
                            <span class="badge badge-primary ml-1"><i class="fas fa-users mr-1"></i>${rep.total_manpower || 0} Workers</span>
                            ${weatherInfo}
                            <input type="hidden" name="daily_report_ids[]" value="${rep.id}">
                        </div>
                        <div class="small text-muted">
                            <i class="fas fa-user-edit mr-1"></i>${escapeHtml(rep.created_by_name)}
                        </div>
                    </div>
                    <div class="card-body p-3">
                        <div class="table-responsive mb-2">
                            <table class="table table-bordered table-sm mb-0">
                                <thead class="thead-light small">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>Work Description</th>
                                        <th class="text-center">Qty Done</th>
                                        <th class="text-center">Workers</th>
                                        <th>Equipment Used</th>
                                        <th>Issues / Delays</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    ${itemsRows}
                                </tbody>
                            </table>
                        </div>
                        ${notesHtml}
                    </div>
                </div>
            `;
        });

        drContainer.innerHTML = html;
        drContainer.style.display = 'block';

        drCountBadge.textContent = `${reports.length} Day${reports.length > 1 ? 's' : ''} Attached`;
        drCountBadge.style.display = 'inline-block';

        drManpowerBadge.textContent = `${totalManpower} Manpower Total`;
        drManpowerBadge.style.display = 'inline-block';

        drSummarizeBtn.style.display = 'inline-block';
    }

    // Auto-fill Summary button
    drSummarizeBtn.addEventListener('click', function() {
        if (!loadedReports || loadedReports.length === 0) return;

        let summaryLines = [];
        let issueLines = [];

        loadedReports.forEach(rep => {
            let dayTasks = [];
            if (rep.items && rep.items.length > 0) {
                rep.items.forEach(it => {
                    let desc = it.work_description;
                    if (it.qty_completed > 0) desc += ` (Qty: ${it.qty_completed})`;
                    dayTasks.push(desc);

                    if (it.issues && it.issues.trim()) {
                        issueLines.push(`[${rep.formatted_date}] ${it.issues.trim()}`);
                    }
                });
            }

            let taskText = dayTasks.length > 0 ? dayTasks.join('; ') : 'Routine site operations';
            let noteText = rep.general_notes ? ` | Notes: ${rep.general_notes}` : '';
            summaryLines.push(`• ${rep.formatted_date}: ${taskText}${noteText}`);

            if (rep.safety_incidents && rep.safety_incidents.trim()) {
                issueLines.push(`[${rep.formatted_date}] Safety Incident: ${rep.safety_incidents.trim()}`);
            }
        });

        const compiledSummary = summaryLines.join('\n');
        const compiledIssues = issueLines.length > 0 ? issueLines.join('\n') : 'No critical safety incidents or delays recorded during daily checks.';

        if (execSummaryTextarea.value.trim() && !confirm('Replace current Executive Summary with compiled daily highlights? Click OK to replace, or Cancel to append.')) {
            execSummaryTextarea.value += '\n\n' + compiledSummary;
        } else {
            execSummaryTextarea.value = compiledSummary;
        }

        if (criticalIssuesTextarea.value.trim() && !confirm('Replace current Critical Issues with compiled daily issues? Click OK to replace, or Cancel to append.')) {
            criticalIssuesTextarea.value += '\n\n' + compiledIssues;
        } else {
            criticalIssuesTextarea.value = compiledIssues;
        }
    });

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>
@endpush
@endsection
