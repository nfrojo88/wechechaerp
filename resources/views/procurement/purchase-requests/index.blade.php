@extends('layouts.app')

@section('title', 'Procurement — My Queue')
@section('content')
<script>
    window.location.replace("{{ route('procurement.my-queue') }}");
</script>
<meta http-equiv="refresh" content="0;url={{ route('procurement.my-queue') }}">
<div class="container-fluid px-4 py-5 text-center">
    <div class="spinner-border text-primary mb-3" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <h5 class="text-dark fw-bold">Redirecting to Procurement — My Queue...</h5>
    <p class="text-muted">Purchase Requests are now unified in the Procurement Queue.</p>
    <a href="{{ route('procurement.my-queue') }}" class="btn btn-primary">Go to Procurement Queue</a>
</div>
@endsection
