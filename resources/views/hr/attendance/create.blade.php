@extends('layouts.app')
@section('title', 'Manual Attendance Entry Removed')
@section('content')
<div class="container py-5 text-center">
    <div class="card border-0 shadow-sm rounded-4 p-5 mx-auto" style="max-width: 600px;">
        <i class="fa-solid fa-fingerprint text-primary fs-1 mb-3"></i>
        <h4 class="fw-bold text-dark">Biometric Machine Attendance Enforced</h4>
        <p class="text-muted small">
            Manual attendance entry has been permanently disabled per company policy.
            All attendance punches must originate directly from ZKTeco biometric devices or approved site deployments.
        </p>
        <a href="{{ route('attendance.index') }}" class="btn btn-primary rounded-3 px-4 mx-auto">
            <i class="fa-solid fa-arrow-left me-1"></i>Return to Attendance Matrix
        </a>
    </div>
</div>
@endsection
