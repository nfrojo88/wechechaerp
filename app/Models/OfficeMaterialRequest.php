<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeMaterialRequest extends Model
{
    use HasFactory;

    protected $table = 'office_material_requests';

    // Status Constants
    public const STATUS_PENDING_HR             = 'pending_hr';
    public const STATUS_APPROVED_BY_HR         = 'approved_by_hr';
    public const STATUS_ASSIGNED_TO_FINANCE    = 'assigned_to_finance';
    public const STATUS_PAID                   = 'paid';
    public const STATUS_REJECTED               = 'rejected';

    protected $fillable = [
        'request_no',
        'requested_by',
        'office_purpose',
        'justification',
        'required_date',
        'urgency',
        'attachment',
        'status',
        // Step 2: HR money addition
        'amount',
        'hr_reviewer_id',
        'hr_reviewed_at',
        'hr_notes',
        // Step 3: Finance Head assignment
        'finance_head_id',
        'coa_id',
        'bank_account_id',
        'assigned_finance_staff_id',
        'finance_assigned_at',
        'finance_head_notes',
        // Step 4: Payment
        'paid_by',
        'paid_at',
        'payment_reference',
        'payment_notes',
        // Rejection
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'amount'              => 'decimal:2',
        'required_date'       => 'date',
        'hr_reviewed_at'      => 'datetime',
        'finance_assigned_at' => 'datetime',
        'paid_at'             => 'datetime',
        'rejected_at'         => 'datetime',
    ];

    // Relationships
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function hrReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_reviewer_id');
    }

    public function financeHead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_head_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_finance_staff_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function coa(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OfficeMaterialRequestItem::class, 'office_material_request_id');
    }

    // Helper: Formatted status badge
    public function getStatusBadgeAttribute(): array
    {
        return match($this->status) {
            self::STATUS_PENDING_HR => [
                'label' => 'Pending HR Review',
                'badge' => '<span class="badge bg-warning text-dark px-3 py-1"><i class="fa-solid fa-clock me-1"></i> Pending HR Review</span>',
                'color' => 'warning',
            ],
            self::STATUS_APPROVED_BY_HR => [
                'label' => 'Approved by HR & Awaiting Finance',
                'badge' => '<span class="badge text-white px-3 py-1" style="background:#7c3aed;"><i class="fa-solid fa-wallet me-1"></i> Finance Queue (Awaiting Assignment)</span>',
                'color' => 'purple',
            ],
            self::STATUS_ASSIGNED_TO_FINANCE => [
                'label' => 'Assigned to Finance Staff',
                'badge' => '<span class="badge bg-info text-dark px-3 py-1"><i class="fa-solid fa-user-check me-1"></i> Assigned (Pending Payment)</span>',
                'color' => 'info',
            ],
            self::STATUS_PAID => [
                'label' => 'Paid & Completed',
                'badge' => '<span class="badge bg-success text-white px-3 py-1"><i class="fa-solid fa-circle-check me-1"></i> Paid & Completed</span>',
                'color' => 'success',
            ],
            self::STATUS_REJECTED => [
                'label' => 'Rejected',
                'badge' => '<span class="badge bg-danger text-white px-3 py-1"><i class="fa-solid fa-circle-xmark me-1"></i> Rejected</span>',
                'color' => 'danger',
            ],
            default => [
                'label' => ucfirst(str_replace('_', ' ', $this->status)),
                'badge' => '<span class="badge bg-secondary text-white px-3 py-1">' . e($this->status) . '</span>',
                'color' => 'secondary',
            ],
        };
    }
}
