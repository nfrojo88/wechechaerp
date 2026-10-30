@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="h3 mb-0">
                <i class="fas fa-user-chart me-2"></i>{{ $employee->name }} - Performance Profile
            </h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="{{ route('performance-dashboard.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Back
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Employee Info -->
        <div class="col-lg-3 mb-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-user-circle fa-3x text-primary"></i>
                    </div>
                    <h6 class="mb-1">{{ $employee->name }}</h6>
                    <small class="text-muted">{{ $employee->code }}</small>
                    <hr>
                    <small class="d-block text-muted mb-2">Department</small>
                    <p class="mb-3">{{ $employee->department->name ?? 'N/A' }}</p>
                    <small class="d-block text-muted mb-2">Status</small>
                    <p class="mb-0">
                        @if ($employee->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-danger">Inactive</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Latest Performance Review -->
        <div class="col-lg-9 mb-4">
            @if ($latestReview)
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">Latest Performance Review - {{ $latestReview->review_period->format('M Y') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-2 text-center">
                                <div class="p-3 rounded-circle bg-light d-inline-block">
                                    <h5 class="mb-0 text-{{ $latestReview->rating_badge }}">
                                        {{ number_format($latestReview->overall_score, 1) }}/5
                                    </h5>
                                </div>
                                <small class="text-muted d-block mt-2">Overall Score</small>
                            </div>
                            <div class="col-md-10">
                                <div class="row">
                                    <div class="col-md-4">
                                        <small class="text-muted">Technical Skills</small>
                                        <div class="progress mb-2" style="height: 20px;">
                                            <div class="progress-bar" style="width: {{ ($latestReview->technical_skills_score / 5) * 100 }}%">
                                                {{ $latestReview->technical_skills_score }}/5
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Soft Skills</small>
                                        <div class="progress mb-2" style="height: 20px;">
                                            <div class="progress-bar bg-info" style="width: {{ ($latestReview->soft_skills_score / 5) * 100 }}%">
                                                {{ $latestReview->soft_skills_score }}/5
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Attendance</small>
                                        <div class="progress mb-2" style="height: 20px;">
                                            <div class="progress-bar bg-success" style="width: {{ ($latestReview->attendance_score / 5) * 100 }}%">
                                                {{ $latestReview->attendance_score }}/5
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Productivity</small>
                                        <div class="progress mb-2" style="height: 20px;">
                                            <div class="progress-bar bg-warning" style="width: {{ ($latestReview->productivity_score / 5) * 100 }}%">
                                                {{ $latestReview->productivity_score }}/5
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Communication</small>
                                        <div class="progress mb-2" style="height: 20px;">
                                            <div class="progress-bar bg-danger" style="width: {{ ($latestReview->communication_score / 5) * 100 }}%">
                                                {{ $latestReview->communication_score }}/5
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted">Teamwork</small>
                                        <div class="progress mb-2" style="height: 20px;">
                                            <div class="progress-bar bg-success" style="width: {{ ($latestReview->teamwork_score / 5) * 100 }}%">
                                                {{ $latestReview->teamwork_score }}/5
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($latestReview->strengths)
                            <div class="mb-3">
                                <h6>Strengths</h6>
                                <p class="mb-0">{{ $latestReview->strengths }}</p>
                            </div>
                        @endif

                        @if ($latestReview->areas_for_improvement)
                            <div class="mb-3">
                                <h6>Areas for Improvement</h6>
                                <p class="mb-0">{{ $latestReview->areas_for_improvement }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No performance reviews yet</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="row">
        <!-- Active Goals -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">Performance Goals</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Goal</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($activeGoals as $goal)
                                <tr>
                                    <td>
                                        <strong>{{ $goal->goal_title }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $goal->target_date->format('M d, Y') }}</small>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" style="width: {{ $goal->progress_percentage }}%">
                                                {{ intval($goal->progress_percentage) }}%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $goal->priority_badge }}">
                                            {{ ucfirst($goal->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted small">No active goals</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Achievements -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0">Recent Achievements</h6>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    @forelse ($recentAchievements as $achievement)
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-start">
                                <i class="{{ $achievement->type_icon }} fs-5 me-3 mt-1"></i>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">{{ $achievement->title }}</h6>
                                    <small class="text-muted d-block">{{ $achievement->achievement_date->format('M d, Y') }}</small>
                                    <small class="text-muted d-block">{{ $achievement->achievement_type }}</small>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center py-4">No achievements recorded</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Competencies -->
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h6 class="mb-0">Competency Assessment</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Competency</th>
                                <th class="text-center">Current Level</th>
                                <th class="text-center">Target Level</th>
                                <th class="text-center">Gap</th>
                                <th>Last Assessed</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($competencies as $assessment)
                                <tr>
                                    <td>{{ $assessment->competency->name }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-{{ $assessment->level_badge }}">
                                            {{ $assessment->current_level }}/5
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        {{ $assessment->target_level }}/5
                                    </td>
                                    <td class="text-center">
                                        @if ($assessment->gap > 0)
                                            <span class="badge bg-info">+{{ $assessment->gap }}</span>
                                        @else
                                            <span class="badge bg-success">Met</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $assessment->assessed_date->format('M d, Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No competency assessments</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
