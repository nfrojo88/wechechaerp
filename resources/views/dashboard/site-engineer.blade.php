@extends('layouts.app')
@section('title', 'Site Engineer Dashboard')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-hard-hat me-2"></i> Site Engineer Dashboard</h1>
        <span class="badge badge-secondary p-2">{{ now()->format('l, F j Y') }}</span>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">My Material Requests</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['my_material_requests'] }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-cart-flatbed fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Issues Reported</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['issues_reported'] }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-triangle-exclamation fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Present Today (Site)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['attendance_today'] }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Waste Logged (Month)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $kpi['waste_recorded'] }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-trash-can fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-box mr-2"></i> My Recent Material Requests</h6>
                    <a href="{{ route('material-requests.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="thead-light"><tr><th>Request ID</th><th>Project</th><th>Created</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($recentMR as $mr)
                            <tr>
                                <td>#{{ $mr->id }}</td>
                                <td>{{ $mr->project->name ?? 'N/A' }}</td>
                                <td>{{ $mr->created_at->format('M d, Y') }}</td>
                                <td><span class="badge badge-{{ $mr->status == 'approved' ? 'success' : ($mr->status == 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($mr->status) }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted">No requests found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <a href="{{ route('material-requests.create', ['source' => 'Emergency']) }}" class="btn btn-danger btn-block mb-2 font-weight-bold"><i class="fas fa-bolt mr-2"></i> Ask Emergency Material Request</a>
                    <a href="{{ route('issues.create') }}" class="btn btn-secondary btn-block mb-2"><i class="fas fa-bug mr-2"></i> Report Issue</a>
                    <a href="{{ route('waste.create') }}" class="btn btn-warning btn-block mb-2"><i class="fas fa-trash mr-2"></i> Log Waste</a>
                    <a href="{{ route('attendance.create') }}" class="btn btn-success btn-block"><i class="fas fa-user-check mr-2"></i> Mark Attendance</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
