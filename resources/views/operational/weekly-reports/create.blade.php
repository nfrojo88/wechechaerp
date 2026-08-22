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
                            <input type="date" name="week_start" class="form-control" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>Week End (Sunday) <span class="text-danger">*</span></label>
                            <input type="date" name="week_end" class="form-control" required>
                        </div>
                        <hr>
                        <div class="form-group mb-3">
                            <label>Planned Progress (%)</label>
                            <input type="number" name="planned_progress_percent" class="form-control" step="0.01" min="0" max="100">
                        </div>
                        <div class="form-group mb-0">
                            <label>Actual Progress (%)</label>
                            <input type="number" name="actual_progress_percent" class="form-control" step="0.01" min="0" max="100">
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
                            <textarea name="executive_summary" class="form-control" rows="4" placeholder="Summarize the overall progress, milestones achieved, and general site status..."></textarea>
                        </div>
                        <div class="form-group mb-4">
                            <label class="font-weight-bold text-danger">Critical Issues & Delays</label>
                            <textarea name="critical_issues" class="form-control border-left-danger" rows="3" placeholder="Describe any roadblocks, material shortages, weather delays, or safety incidents..."></textarea>
                        </div>
                        <div class="form-group mb-0">
                            <label class="font-weight-bold text-info">Plan for Next Week</label>
                            <textarea name="next_week_plan" class="form-control border-left-info" rows="3" placeholder="Outline the main targets and resource requirements for the upcoming week..."></textarea>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Submit Weekly Report</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
