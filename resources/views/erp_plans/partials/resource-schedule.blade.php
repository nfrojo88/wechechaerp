{{-- Resource Schedule Partial --}}
{{-- Variables: $resourceType, $title, $icon, $color, $tasks --}}
@php
    $filtered = $tasks->flatMap(function($task) use ($resourceType) {
        return $task->resources->where('resource_type', $resourceType)->map(function($r) use ($task) {
            $r->task_name = $task->name;
            $r->task_wbs  = $task->wbs_code;
            $r->task_start= $task->start_date;
            $r->task_end  = $task->end_date;
            return $r;
        });
    });
    $totalQty  = $filtered->sum('quantity');
    $totalCost = $filtered->sum('total_cost');
@endphp

<div class="res-schedule">
    <div class="res-schedule-head">
        <i class="fa-solid {{ $icon }}" style="color:{{ $color }};font-size:1.1rem;"></i>
        <h6 class="mb-0 fw-bold">{{ $title }}</h6>
        <span class="badge ms-2" style="background:{{ $color }}20;color:{{ $color }};">
            {{ $filtered->count() }} entries
        </span>
    </div>

    @if($filtered->isEmpty())
    <div class="text-center py-5 text-muted">
        <i class="fa-solid {{ $icon }} fa-3x mb-3 d-block" style="opacity:.15;"></i>
        No {{ strtolower($title) }} resources found in this plan.
    </div>
    @else
    <div class="table-responsive">
        <table class="rtable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Task (WBS)</th>
                    <th>Resource Name</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th class="text-end">Rate (ETB)</th>
                    <th class="text-end">Total Cost (ETB)</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($filtered as $idx => $res)
                <tr>
                    <td class="text-muted small">{{ $idx + 1 }}</td>
                    <td>
                        <span class="fw-semibold">{{ $res->task_name }}</span>
                        <br><code class="small text-muted">{{ $res->task_wbs }}</code>
                    </td>
                    <td class="fw-semibold">{{ $res->resource_name }}</td>
                    <td class="fw-bold" style="color:{{ $color }};">{{ number_format($res->quantity, 3) }}</td>
                    <td class="text-muted small">{{ $res->unit ?: '—' }}</td>
                    <td class="text-end small">{{ $res->rate ? number_format($res->rate, 2) : '—' }}</td>
                    <td class="text-end fw-semibold">{{ $res->total_cost ? number_format($res->total_cost, 2) : '—' }}</td>
                    <td class="small text-muted">{{ $res->task_start->format('d M Y') ?? '—' }}</td>
                    <td class="small text-muted">{{ $res->task_end->format('d M Y') ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot style="background:#f8fafc;border-top:2px solid #e2e8f0;">
                <tr>
                    <td colspan="3" class="fw-bold text-end pe-3 py-3">Totals:</td>
                    <td class="fw-bold" style="color:{{ $color }};">{{ number_format($totalQty, 3) }}</td>
                    <td></td>
                    <td></td>
                    <td class="text-end fw-bold">{{ number_format($totalCost, 2) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif
</div>
