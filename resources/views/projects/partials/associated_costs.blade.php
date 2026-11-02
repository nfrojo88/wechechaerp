<div class="card border-0 shadow-sm rounded-4 overflow-hidden mt-4" id="associated-costs-section">
    <div class="card-header bg-white py-3 px-4 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning rounded-pill px-2.5 py-1 fw-bold small">
                    <i class="fa-solid fa-receipt me-1 text-warning"></i>Associated Costs
                </span>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill small">Not counted in budget meter</span>
            </div>
            <p class="text-muted small mb-0 mt-1">
                Real expenses charged to this project that bypassed or were not counted into the project budget meter.
                <span class="d-block mt-0.5" style="font-size: 0.75rem;">
                    <i class="fa-solid fa-circle-info me-1 text-primary"></i>Procurement is counted when paid; credit is counted when goods are physically received on site.
                </span>
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">Total Unbudgeted:</span>
            <span class="fw-bold fs-6 text-dark font-monospace">ETB {{ number_format($associatedCostSummary['total_associated_cost'] ?? 0, 2) }}</span>
        </div>
    </div>

    {{-- Summary KPI Cards --}}
    <div class="card-body p-4 bg-light bg-opacity-50 border-bottom">
        <div class="row g-3">
            {{-- 1. Total Associated Cost --}}
            <div class="col-6 col-lg-3">
                <div class="p-3 bg-white rounded-4 border shadow-xs h-100">
                    <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Associated Costs</div>
                    <div class="fs-5 fw-bold text-dark font-monospace mt-1">
                        ETB {{ number_format($associatedCostSummary['total_associated_cost'] ?? 0, 2) }}
                    </div>
                    <div class="small text-warning mt-1">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>{{ $associatedCostSummary['total_count'] ?? 0 }} unbudgeted entries
                    </div>
                </div>
            </div>

            {{-- 2. Material Cost --}}
            <div class="col-6 col-lg-3">
                <div class="p-3 bg-white rounded-4 border shadow-xs h-100">
                    <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Material (Unbudgeted)</div>
                    <div class="fs-5 fw-bold text-primary font-monospace mt-1">
                        ETB {{ number_format($associatedCostSummary['total_material_cost'] ?? 0, 2) }}
                    </div>
                    <div class="small text-muted mt-1">
                        Procurement, Petty Cash &amp; Credit
                    </div>
                </div>
            </div>

            {{-- 3. Cash Cost --}}
            <div class="col-6 col-lg-3">
                <div class="p-3 bg-white rounded-4 border shadow-xs h-100">
                    <div class="text-muted small fw-semibold text-uppercase" style="font-size: 0.72rem;">Cash (Unbudgeted)</div>
                    <div class="fs-5 fw-bold text-info font-monospace mt-1">
                        ETB {{ number_format($associatedCostSummary['total_cash_cost'] ?? 0, 2) }}
                    </div>
                    <div class="small text-muted mt-1">
                        Paid Expense Requests &amp; Vouchers
                    </div>
                </div>
            </div>

            {{-- 4. Total Real Project Cost (Budgeted + Associated) --}}
            <div class="col-6 col-lg-3">
                <div class="p-3 bg-white rounded-4 border border-primary border-opacity-25 bg-primary bg-opacity-10 h-100">
                    <div class="text-primary small fw-bold text-uppercase" style="font-size: 0.72rem;">Total Real Project Cost</div>
                    <div class="fs-5 fw-bold text-primary font-monospace mt-1">
                        ETB {{ number_format($associatedCostSummary['total_real_cost'] ?? 0, 2) }}
                    </div>
                    <div class="mt-1" style="font-size: 0.72rem; color: #475569; line-height: 1.25;">
                        <span class="fw-semibold">Budgeted:</span> ETB {{ number_format($associatedCostSummary['budget_consumed'] ?? 0, 2) }} + 
                        <span class="fw-semibold">Unbudgeted:</span> ETB {{ number_format($associatedCostSummary['total_associated_cost'] ?? 0, 2) }}
                        <div class="text-muted mt-0.5" style="font-style: italic;">
                            *Budgeted cost currently includes only records from the expenses table. Procurement is counted when paid and credit when goods are received.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="card-body py-3 px-4 bg-white border-bottom">
        <form method="GET" action="{{ route('projects.show', $project) }}#associated-costs-section" class="row g-2 align-items-center">
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="fa-solid fa-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search reference or description..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <option value="Material" {{ request('category') === 'Material' ? 'selected' : '' }}>Material Only</option>
                    <option value="Cash" {{ request('category') === 'Cash' ? 'selected' : '' }}>Cash Only</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="source_type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Sources</option>
                    <option value="procurement" {{ request('source_type') === 'procurement' ? 'selected' : '' }}>Procurement Payment</option>
                    <option value="petty_cash" {{ request('source_type') === 'petty_cash' ? 'selected' : '' }}>Petty Cash Material</option>
                    <option value="credit_store" {{ request('source_type') === 'credit_store' ? 'selected' : '' }}>Credit Store Purchase</option>
                    <option value="expense_request" {{ request('source_type') === 'expense_request' ? 'selected' : '' }}>Expense Request (Voucher)</option>
                    <option value="expense" {{ request('source_type') === 'expense' ? 'selected' : '' }}>Direct Expense</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <input type="date" name="date_from" class="form-control form-control-sm" placeholder="From" value="{{ request('date_from') }}">
                <input type="date" name="date_to" class="form-control form-control-sm" placeholder="To" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-1 d-flex gap-1 justify-content-end">
                <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3"><i class="fa-solid fa-filter"></i></button>
                @if(request()->hasAny(['search', 'category', 'source_type', 'date_from', 'date_to']))
                    <a href="{{ route('projects.show', $project) }}#associated-costs-section" class="btn btn-sm btn-outline-secondary rounded-pill" title="Clear Filters"><i class="fa-solid fa-xmark"></i></a>
                @endif
            </div>
        </form>
    </div>

    {{-- Data Table --}}
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4 text-secondary text-uppercase small fw-bold">Date</th>
                    <th class="text-secondary text-uppercase small fw-bold">Source</th>
                    <th class="text-secondary text-uppercase small fw-bold">Reference #</th>
                    <th class="text-secondary text-uppercase small fw-bold" style="min-width: 220px;">Description</th>
                    <th class="text-secondary text-uppercase small fw-bold">Category</th>
                    <th class="text-end text-secondary text-uppercase small fw-bold">Amount (ETB)</th>
                    <th class="text-center text-secondary text-uppercase small fw-bold">Status</th>
                    <th class="pe-4 text-end text-secondary text-uppercase small fw-bold">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($associatedCosts as $cost)
                    @php
                        $sourceLabels = [
                            'procurement'     => ['label' => 'Procurement', 'color' => 'primary', 'icon' => 'fa-cart-shopping'],
                            'petty_cash'      => ['label' => 'Petty Cash', 'color' => 'success', 'icon' => 'fa-wallet'],
                            'credit_store'    => ['label' => 'Credit Store', 'color' => 'warning', 'icon' => 'fa-credit-card'],
                            'expense_request' => ['label' => 'Expense Req', 'color' => 'info', 'icon' => 'fa-file-invoice-dollar'],
                            'expense'         => ['label' => 'Direct Expense', 'color' => 'secondary', 'icon' => 'fa-receipt'],
                        ];
                        $src = $sourceLabels[$cost->source_type] ?? ['label' => ucfirst($cost->source_type), 'color' => 'secondary', 'icon' => 'fa-circle'];
                        $dateFormatted = $cost->cost_date ? \Carbon\Carbon::parse($cost->cost_date)->format('d M Y') : '—';
                    @endphp
                    <tr>
                        <td class="ps-4 text-nowrap text-dark font-monospace small">
                            {{ $dateFormatted }}
                        </td>
                        <td>
                            <span class="badge bg-{{ $src['color'] }}-subtle text-{{ $src['color'] }} border border-{{ $src['color'] }}-subtle rounded-pill px-2.5 py-1 small">
                                <i class="fa-solid {{ $src['icon'] }} me-1"></i>{{ $src['label'] }}
                            </span>
                        </td>
                        <td>
                            <span class="fw-bold font-monospace text-dark small">{{ $cost->reference_no }}</span>
                        </td>
                        <td>
                            <div class="text-dark small text-truncate" style="max-width: 320px;" title="{{ $cost->description }}">
                                {{ $cost->description }}
                            </div>
                        </td>
                        <td>
                            @if($cost->category === 'Material')
                                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-2.5 py-1 small fw-semibold">
                                    <i class="fa-solid fa-cube me-1"></i>Material
                                </span>
                            @else
                                <span class="badge bg-info bg-opacity-10 text-info rounded-pill px-2.5 py-1 small fw-semibold">
                                    <i class="fa-solid fa-coins me-1"></i>Cash
                                </span>
                            @endif
                        </td>
                        <td class="text-end font-monospace fw-bold text-dark">
                            {{ number_format($cost->amount, 2) }}
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1 small text-capitalize">
                                {{ str_replace('_', ' ', $cost->status) }}
                            </span>
                        </td>
                        <td class="pe-4 text-end">
                            <a href="{{ url($cost->view_url) }}" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1" target="_blank" title="View Source Record">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="rounded-circle bg-light d-inline-flex p-3 mb-2">
                                <i class="fa-solid fa-circle-check fa-2x text-success opacity-75"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">No unbudgeted expenses for this project</h6>
                            <p class="text-muted small mb-0">Every recorded expense matches the project budget cap, or no unbudgeted costs match your filters.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($associatedCosts->hasPages())
        <div class="card-footer bg-white border-0 py-3 px-4">
            {{ $associatedCosts->appends(request()->query())->fragment('associated-costs-section')->links() }}
        </div>
    @endif
</div>
