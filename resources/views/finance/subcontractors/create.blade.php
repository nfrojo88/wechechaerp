@php
    $projects = $projects ?? \App\Models\Project::orderBy('name')->get();
    $suppliers = $suppliers ?? \App\Models\Supplier::where('status', 'active')->orderBy('name')->get();
    $takeoffs = $takeoffs ?? \App\Models\TakeoffSheet::where('status', 'approved')->with('project')->latest()->get();
@endphp
@include('procurement.subcon-agreements.create')
