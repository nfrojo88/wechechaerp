@extends('layouts.app')

@section('title', 'BOQ Details')

@section('content')
<div class="d-flex align-items-center mb-4">
    <a href="{{ route('boqs.index') }}" class="btn btn-sm btn-outline-secondary me-3">
        <i class="fa-solid fa-arrow-left"></i>
    </a>
    <h1 class="h3 mb-0 me-3">BOQ: {{ $boq->reference_number }}</h1>
    
    @php
        $badge = match($boq->status) {
            'draft' => 'secondary',
            'approved' => 'success',
            'revised' => 'warning',
            default => 'secondary'
        };
    @endphp
    <span class="badge bg-{{ $badge }} me-3">{{ strtoupper($boq->status) }}</span>
    
    <div class="ms-auto d-flex gap-2">
        @if($boq->status === 'draft')
            @can('boq.edit')
            <a href="{{ route('boqs.edit', $boq) }}" class="btn btn-outline-primary">
                <i class="fa-solid fa-pen me-1"></i> Edit Header
            </a>
            @endcan
            @can('boq.approve')
            <form method="POST" action="{{ route('boqs.approve', $boq) }}" onsubmit="return confirm('Are you sure you want to approve this BOQ? No further items can be added or edited.');">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-check-double me-1"></i> Approve BOQ
                </button>
            </form>
            @endcan
            @can('boq.delete')
            <form method="POST" action="{{ route('boqs.destroy', $boq) }}" onsubmit="return confirm('Are you sure you want to delete this BOQ?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </form>
            @endcan
        @endif
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">{{ $boq->title }}</h5>
                <h6 class="text-muted mb-4">
                    @if($boq->project)
                        <a href="{{ route('projects.show', $boq->project) }}" class="text-decoration-none"><i class="fa-solid fa-building me-1"></i>{{ $boq->project->name }}</a>
                    @else
                        <i class="fa-solid fa-building me-1"></i> N/A
                    @endif
                </h6>
                
                <div class="mb-0 text-secondary">
                    {{ $boq->description ?? 'No scope description provided.' }}
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm bg-light h-100">
            <div class="card-body">
                <h6 class="text-muted text-uppercase small fw-bold mb-3">Financial Summary</h6>
                <div class="mb-3">
                    <div class="text-muted small">Total Estimated Amount</div>
                    <div class="fs-3 fw-bold text-primary">{{ number_format($boq->total_amount, 2) }} ETB</div>
                </div>
                <div class="row text-muted small">
                    <div class="col-6">
                        <div>Created By:</div>
                        <div class="fw-semibold text-dark">{{ $boq->creator->name }}</div>
                        <div>{{ $boq->created_at->format('d M Y') }}</div>
                    </div>
                    <div class="col-6">
                        <div>Approved By:</div>
                        <div class="fw-semibold text-dark">{{ $boq->approver->name ?? '—' }}</div>
                        <div>{{ $boq->approved_at->format('d M Y') ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- BOQ Items Table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Bill of Quantities Items</h5>
        @if($boq->status === 'draft')
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i class="fa-solid fa-plus me-1"></i> Add Work Item
        </button>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th>Unit</th>
                        <th class="text-end">Tender Qty</th>
                        <th class="text-end">Actual Qty</th>
                        <th class="text-end">Unit Rate (ETB)</th>
                        <th class="text-end">Amount (ETB)</th>
                        @if($boq->status === 'draft')
                        <th class="text-end">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($boq->items as $item)
                    <tr>
                        <td class="text-muted font-monospace small">{{ $item->item_code ?? '—' }}</td>
                        <td>
                            <div class="fw-semibold">{{ $item->description }}</div>
                            @if($item->scheduleTask)
                            <div class="small text-muted"><i class="fa-regular fa-calendar-check me-1"></i>Task: {{ $item->scheduleTask->name }}</div>
                            @endif
                        </td>
                        <td>{{ $item->unit }}</td>
                        <td class="text-end">{{ number_format($item->tender_quantity, 3) }}</td>
                        <td class="text-end">{{ number_format($item->quantity, 3) }}</td>
                        <td class="text-end">{{ number_format($item->unit_rate, 2) }}</td>
                        <td class="text-end fw-bold">{{ number_format($item->amount, 2) }}</td>
                        
                        @if($boq->status === 'draft')
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editItemModal{{ $item->id }}">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <form method="POST" action="{{ route('boq_items.destroy', $item) }}" class="d-inline" onsubmit="return confirm('Remove this item?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    
                    @if($boq->status === 'draft')
                    <!-- Edit Item Modal -->
                    <div class="modal fade" id="editItemModal{{ $item->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('boq_items.update', $item) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Item</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label">Item Code</label>
                                                <input type="text" name="item_code" class="form-control" value="{{ $item->item_code }}">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Description <span class="text-danger">*</span></label>
                                                <textarea name="description" class="form-control" required rows="2">{{ $item->description }}</textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Linked Schedule Task (Optional)</label>
                                                <select name="schedule_task_id" class="form-select">
                                                    <option value="">— No schedule task —</option>
                                                    @foreach($scheduleTasks as $task)
                                                    <option value="{{ $task->id }}" @selected($item->schedule_task_id == $task->id)>{{ $task->wbs_code }} - {{ $task->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-3">
                                                <label class="form-label">Unit <span class="text-danger">*</span></label>
                                                <select name="unit" class="form-select" required>
                                                    <option value="m2" @selected($item->unit == 'm2')>m2</option>
                                                    <option value="m3" @selected($item->unit == 'm3')>m3</option>
                                                    <option value="lm" @selected($item->unit == 'lm')>lm (Linear Meter)</option>
                                                    <option value="kg" @selected($item->unit == 'kg')>kg</option>
                                                    <option value="ton" @selected($item->unit == 'ton')>ton</option>
                                                    <option value="pcs" @selected($item->unit == 'pcs')>pcs</option>
                                                    <option value="ls" @selected($item->unit == 'ls')>ls (Lump Sum)</option>
                                                    <option value="day" @selected($item->unit == 'day')>day</option>
                                                    <option value="hr" @selected($item->unit == 'hr')>hr</option>
                                                    <option value="month" @selected($item->unit == 'month')>month</option>
                                                </select>
                                            </div>
                                            <div class="col-3">
                                                <label class="form-label">Tender Qty <span class="text-danger">*</span></label>
                                                <input type="number" step="0.001" min="0" name="tender_quantity" class="form-control" value="{{ $item->tender_quantity }}" required>
                                            </div>
                                            <div class="col-3">
                                                <label class="form-label">Actual Qty</label>
                                                <input type="number" step="0.001" min="0" name="quantity" class="form-control" value="{{ $item->quantity }}" readonly>
                                            </div>
                                            <div class="col-3">
                                                <label class="form-label">Unit Rate <span class="text-danger">*</span></label>
                                                <input type="number" step="0.01" min="0" name="unit_rate" class="form-control" value="{{ $item->unit_rate }}" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Update Item</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endif
                    
                    @empty
                    <tr>
                        <td colspan="{{ $boq->status === 'draft' ? 7 : 6 }}" class="text-center text-muted py-4">
                            No items have been added to this BOQ yet.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th colspan="5" class="text-end text-uppercase">Total Estimated Cost:</th>
                        <th class="text-end fs-5 text-primary">{{ number_format($boq->total_amount, 2) }} ETB</th>
                        @if($boq->status === 'draft')
                        <th></th>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@if($boq->status === 'draft')
<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('boq_items.store', $boq) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Work Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Item Code</label>
                            <input type="text" name="item_code" class="form-control" placeholder="e.g. 1.01.a">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" required rows="2" placeholder="Excavation in bulk..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Linked Schedule Task (Optional)</label>
                            <select name="schedule_task_id" class="form-select">
                                <option value="">— No schedule task —</option>
                                @foreach($scheduleTasks as $task)
                                <option value="{{ $task->id }}">{{ $task->wbs_code }} - {{ $task->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <select name="unit" class="form-select" id="itemUnit" required>
                                <option value="" disabled selected>— Select —</option>
                                <option value="m2">m2</option>
                                <option value="m3">m3</option>
                                <option value="lm">lm (Linear Meter)</option>
                                <option value="kg">kg</option>
                                <option value="ton">ton</option>
                                <option value="pcs">pcs</option>
                                <option value="ls">ls (Lump Sum)</option>
                                <option value="day">day</option>
                                <option value="hr">hr</option>
                                <option value="month">month</option>
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="form-label">Tender Qty <span class="text-danger">*</span></label>
                            <input type="number" step="0.001" min="0" name="tender_quantity" class="form-control" required>
                        </div>
                        <div class="col-3">
                            <label class="form-label">Actual Qty</label>
                            <input type="number" step="0.001" min="0" name="quantity" class="form-control" id="itemActualQty" value="0" readonly>
                        </div>
                        <div class="col-3">
                            <label class="form-label">Unit Rate <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="unit_rate" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection
