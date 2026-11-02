@extends('layouts.app')
@section('title', 'Add Account')
@section('content')
<div class="container-fluid py-4" style="background-color: #f8f9fc; min-height: 100vh;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1" style="font-weight: 600; color: #2d3748;">Create Account</h1>
            <p class="text-muted" style="font-size: 0.95rem;">Expand the ledger with a new entry</p>
        </div>
        <a href="{{ route('coa.index') }}" class="btn btn-light shadow-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>

    <div class="card shadow-sm border-0 rounded-4" style="max-width: 650px; background-color: #ffffff;">
        <div class="card-body p-4 p-md-5">
            <form action="{{ route('coa.store') }}" method="POST">
                @csrf
                
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark" style="font-size: 0.9rem;">Account Code</label>
                    <input type="text" name="code" class="form-control form-control-lg border-0 bg-light" value="{{ old('code') }}" placeholder="e.g. 1001" required style="border-radius: 12px; font-size: 0.95rem;">
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-dark" style="font-size: 0.9rem;">Account Name</label>
                    <input type="text" name="name" class="form-control form-control-lg border-0 bg-light" value="{{ old('name') }}" placeholder="Account designation" required style="border-radius: 12px; font-size: 0.95rem;">
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-dark" style="font-size: 0.9rem;">Category</label>
                    <input type="hidden" name="type" id="selected_category" value="{{ old('type', 'asset') }}" required>
                    <input type="hidden" name="subtype" id="selected_subtype" value="{{ old('subtype', 'Cash and Bank') }}">
                    
                    <div class="d-flex flex-wrap gap-2 mt-2" id="category-pills">
                        <!-- Assets -->
                        <button type="button" class="btn rounded-pill cat-btn" data-type="asset" data-subtype="Cash and Bank" style="background-color: #d1f4e0; color: #0d9446; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add Cash and Bank</button>
                        <button type="button" class="btn rounded-pill cat-btn" data-type="asset" data-subtype="Receivables" style="background-color: #dbeafe; color: #1e3a8a; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add Receivables</button>
                        <button type="button" class="btn rounded-pill cat-btn" data-type="asset" data-subtype="Debtors" style="background-color: #ede9fe; color: #5b21b6; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add Debtors & Advances</button>
                        <button type="button" class="btn rounded-pill cat-btn" data-type="asset" data-subtype="Inventory" style="background-color: #cffafe; color: #0891b2; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add Inventory</button>
                        <button type="button" class="btn rounded-pill cat-btn" data-type="asset" data-subtype="Other current Asset" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add Other current Asset</button>
                        <button type="button" class="btn rounded-pill cat-btn" data-type="asset" data-subtype="Fixed Assets" style="background-color: #f3e8ff; color: #7e22ce; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add Fixed Assets</button>
                        
                        <!-- Liabilities -->
                        <button type="button" class="btn rounded-pill cat-btn" data-type="liability" data-subtype="Liabilities" style="background-color: #ffe4e6; color: #e11d48; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add Liabilities</button>
                        <button type="button" class="btn rounded-pill cat-btn" data-type="liability" data-subtype="Debt & Borrowings" style="background-color: #fee2e2; color: #b91c1c; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add Debt / Borrowings</button>
                        <button type="button" class="btn rounded-pill cat-btn" data-type="liability" data-subtype="Account payable" style="background-color: #fecdd3; color: #9f1239; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add Acount payeble</button>
                        <button type="button" class="btn rounded-pill cat-btn" data-type="liability" data-subtype="Other current liability" style="background-color: #ffe4e6; color: #881337; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add Other current liablity</button>
                        
                        <!-- Equity -->
                        <button type="button" class="btn rounded-pill cat-btn" data-type="equity" data-subtype="Equity" style="background-color: #f3e8ff; color: #6b21a8; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add Equity</button>
                        <button type="button" class="btn rounded-pill cat-btn" data-type="equity" data-subtype="Equity dose not closed" style="background-color: #e9d5ff; color: #581c87; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add equity dose not closed</button>
                        <button type="button" class="btn rounded-pill cat-btn" data-type="equity" data-subtype="Equity-retend earning" style="background-color: #e0e7ff; color: #4338ca; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add equity-retend earning</button>
                        
                        <!-- Income & Expenses -->
                        <button type="button" class="btn rounded-pill cat-btn" data-type="revenue" data-subtype="Income" style="background-color: #dcfce7; color: #166534; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add Income</button>
                        <button type="button" class="btn rounded-pill cat-btn" data-type="expense" data-subtype="Cost of sale" style="background-color: #fef08a; color: #a16207; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add Cost of sale</button>
                        <button type="button" class="btn rounded-pill cat-btn" data-type="expense" data-subtype="Expenses" style="background-color: #ffedd5; color: #c2410c; border: 1px solid transparent; font-weight: 500; font-size: 0.85rem;"><i class="fas fa-plus-circle me-1"></i> Add Expenss</button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-dark" style="font-size: 0.9rem;">Description</label>
                    <textarea name="description" class="form-control border-0 bg-light p-3" rows="3" placeholder="Brief purpose..." style="border-radius: 12px; font-size: 0.95rem; resize: none;">{{ old('description') }}</textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-dark" style="font-size: 0.9rem;">Assigned Manager (Finance Staff)</label>
                    <select name="assigned_to" class="form-select form-select-lg border-0 bg-light" style="border-radius: 12px; font-size: 0.95rem;">
                        <option value="">-- No Manager Assigned --</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ old('assigned_to') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark" style="font-size: 0.9rem;">Opening Balance (ETB)</label>
                    <input type="number" name="opening_balance" class="form-control form-control-lg border-0 bg-light" step="0.01" value="{{ old('opening_balance', 0) }}" placeholder="e.g. 0.00 or -50000.00 for debt" style="border-radius: 12px; font-size: 0.95rem;">
                    <small class="text-muted d-block mt-1">
                        <i class="fas fa-info-circle me-1 text-primary"></i>This is a one-time setup. For Debt / Debtor / Payable accounts with an outstanding negative balance, enter a negative amount (e.g. <code>-50000.00</code>).
                    </small>
                </div>
                
                <div class="row g-3 mb-4 d-none">
                    <!-- Hidden fields for other required parameters -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Parent Account</label>
                        <select name="parent_id" class="form-select">
                            <option value="">-- None (Root) --</option>
                            @foreach($parents as $p)<option value="{{ $p->id }}" {{ old('parent_id') == $p->id ? 'selected' : '' }}>{{ $p->code }} — {{ $p->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}">
                    </div>
                </div>

                <div class="mt-5 text-end position-relative">
                    <button type="submit" class="btn btn-primary rounded-circle shadow-lg d-inline-flex justify-content-center align-items-center" style="width: 56px; height: 56px; background-color: #3b82f6; border: none; font-size: 1.2rem; transition: transform 0.2s;">
                        <i class="fas fa-arrow-up"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const categoryBtns = document.querySelectorAll('.cat-btn');
    const typeInput = document.getElementById('selected_category');
    const subtypeInput = document.getElementById('selected_subtype');

    function selectCategory(btn) {
        // Remove active styling from all
        categoryBtns.forEach(b => {
            b.style.border = '1px solid transparent';
            b.style.opacity = '0.7';
        });
        
        // Add active styling to selected
        btn.style.border = '1px solid ' + btn.style.color;
        btn.style.opacity = '1';
        
        // Update hidden inputs
        typeInput.value = btn.getAttribute('data-type');
        subtypeInput.value = btn.getAttribute('data-subtype');
    }

    // Initialize state
    let initialType = typeInput.value;
    let initialSubtype = subtypeInput.value;
    let initialBtn = document.querySelector(`.cat-btn[data-subtype="${initialSubtype}"]`);
    if(initialBtn) {
        selectCategory(initialBtn);
    } else if(categoryBtns.length > 0) {
        selectCategory(categoryBtns[0]);
    }

    // Add click listeners
    categoryBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            selectCategory(this);
        });
        // Initial opacity
        if(btn !== initialBtn) btn.style.opacity = '0.7';
    });
});
</script>
@endsection
