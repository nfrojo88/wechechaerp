<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ExpenseRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'expense_requests';

    protected $fillable = [
        'request_number',
        'user_id',
        'employee_id',
        'maintenance_request_id',
        'category',
        'other_reason',
        'amount',
        'gross_amount',
        'vat_type',
        'vat_rate',
        'vat_amount',
        'has_withholding',
        'withholding_rate',
        'withholding_amount',
        'withholding_receipt',
        'withholding_receipt_number',
        'net_amount',
        'service_type',
        'description',
        'attachment',
        'status',
        'hr_reviewer_id',
        'hr_reviewed_at',
        'gm_reviewer_id',
        'gm_approver_id',
        'gm_reviewed_at',
        'gm_approved_at',
        'rejection_reason',
        'finance_head_id',
        'bank_account_id',
        'coa_id',
        'chart_of_account_id',
        'assigned_finance_staff_id',
        'finance_staff_id',
        'finance_assigned_at',
        'paid_by',
        'paid_at',
        'payment_reference',
        'payment_notes',
    ];


    protected $casts = [
        'amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'has_withholding' => 'boolean',
        'withholding_rate' => 'decimal:2',
        'withholding_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'hr_reviewed_at' => 'datetime',
        'gm_reviewed_at' => 'datetime',
        'gm_approved_at' => 'datetime',
        'finance_assigned_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    // Status Constants
    public const STATUS_PENDING_HR = 'Pending (HR Review)';
    public const STATUS_PENDING_GM = 'Pending (GM Review)';
    public const STATUS_APPROVED_ASSIGNED = 'Approved - Assigned to Finance';
    public const STATUS_ASSIGNED = 'Assigned to Finance';
    public const STATUS_PAID = 'Paid';
    public const STATUS_REJECTED = 'Rejected';

    // Category Constants
    public const CATEGORY_SERVICE = 'Service';
    public const CATEGORY_TRANSPORT = 'Transport';
    public const CATEGORY_LOADING_UNLOADING = 'Loading & Unloading';
    public const CATEGORY_CONTRACT_WORK = 'Contract Work';
    public const CATEGORY_OFFICE_MATERIAL = 'Office Material';
    public const CATEGORY_MAINTENANCE = 'Maintenance';
    public const CATEGORY_OTHER = 'Other';

    /**
     * Categories list with icons and descriptions
     */
    public static function getCategoriesList(): array
    {
        return [
            self::CATEGORY_SERVICE => [
                'label' => 'Service (አገልግሎት)',
                'icon' => 'fa-solid fa-handshake',
                'color' => 'primary',
                'has_tax_options' => true,
                'description' => 'Professional, technical, rental, or general service fees with optional VAT & Withholding'
            ],
            self::CATEGORY_TRANSPORT => [
                'label' => 'Transport (ትራንስፖርት)',
                'icon' => 'fa-solid fa-truck-plane',
                'color' => 'info',
                'has_tax_options' => false,
                'description' => 'Vehicle fuel, freight, taxi, bus, or machinery mobilization transport costs'
            ],
            self::CATEGORY_LOADING_UNLOADING => [
                'label' => 'Loading & Unloading (መጫን እና ማውረድ)',
                'icon' => 'fa-solid fa-dolly',
                'color' => 'warning',
                'has_tax_options' => false,
                'description' => 'Material handling, portage, site loading/unloading labor costs'
            ],
            self::CATEGORY_CONTRACT_WORK => [
                'label' => 'Contract Work (የኮንትራት ስራ)',
                'icon' => 'fa-solid fa-file-signature',
                'color' => 'dark',
                'has_tax_options' => true,
                'description' => 'Subcontractors, task-based construction work, cobblestone/masonry labor'
            ],
            self::CATEGORY_OFFICE_MATERIAL => [
                'label' => 'Office Material (የቢሮ እቃ)',
                'icon' => 'fa-solid fa-boxes-stacked',
                'color' => 'secondary',
                'has_tax_options' => false,
                'description' => 'Stationery, consumables, office utilities and cleaning items'
            ],
            self::CATEGORY_MAINTENANCE => [
                'label' => 'Maintenance & Repairs (ጥገና)',
                'icon' => 'fa-solid fa-screwdriver-wrench',
                'color' => 'danger',
                'has_tax_options' => true,
                'description' => 'Machinery parts, vehicle maintenance, site generator servicing'
            ],
            self::CATEGORY_OTHER => [
                'label' => 'Other (ሌሎች)',
                'icon' => 'fa-solid fa-ellipsis',
                'color' => 'secondary',
                'has_tax_options' => true,
                'description' => 'Uncategorized emergency expense'
            ],
        ];
    }

    /**
     * Get effective payable / disbursed amount (net_amount if taxes applied, otherwise amount).
     */
    public function getEffectivePayableAmountAttribute(): float
    {
        if ($this->net_amount !== null && (float)$this->net_amount > 0) {
            return (float)$this->net_amount;
        }
        return (float)$this->amount;
    }


    /**
     * User who submitted the request.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Employee record linked to the user.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Linked Maintenance Request (if requested from General Service).
     */
    public function maintenanceRequest()
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    /**
     * HR Reviewer User.
     */
    public function hrReviewer()
    {
        return $this->belongsTo(User::class, 'hr_reviewer_id');
    }

    /**
     * GM Approver / Reviewer User.
     */
    public function gmApprover()
    {
        return $this->belongsTo(User::class, 'gm_approver_id');
    }

    public function gmReviewer()
    {
        return $this->belongsTo(User::class, 'gm_reviewer_id');
    }

    /**
     * Finance Head User who assigned the request.
     */
    public function financeHead()
    {
        return $this->belongsTo(User::class, 'finance_head_id');
    }

    /**
     * Assigned Finance Staff User.
     */
    public function financeStaff()
    {
        return $this->belongsTo(User::class, 'finance_staff_id');
    }

    public function assignedFinanceStaff()
    {
        return $this->belongsTo(User::class, 'assigned_finance_staff_id');
    }

    /**
     * Finance Staff/Admin who processed payment.
     */
    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Bank Account selected for payment.
     */
    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * Chart of Account selected for deduction.
     */
    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id')->with('manager');
    }

    /**
     * Resolve the effective chart of account (whichever FK is populated).
     */
    public function getEffectiveCoaAttribute()
    {
        return $this->chartOfAccount ?? $this->coa;
    }

    /**
     * Scopes for query scoping
     */
    public function scopePendingHr(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_PENDING_HR);
    }

    public function scopePendingGm(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_PENDING_GM);
    }

    public function scopePendingFinanceAssignment(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('status', [self::STATUS_APPROVED_ASSIGNED, self::STATUS_ASSIGNED]);
    }

    public function scopeAssignedToUser(\Illuminate\Database\Eloquent\Builder $query, int|string $userId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('assigned_finance_staff_id', $userId)
              ->orWhere('finance_staff_id', $userId);
        })->where('status', self::STATUS_ASSIGNED);
    }

    public function scopePaid(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Strict Database-Level Paid History Scope per Section 3:
     * - Admin / Global Admin: ALL
     * - Finance Head: ALL
     * - HR Manager/Officer: Only requests reviewed by them (hr_reviewer_id == user_id)
     * - GM: Only requests approved by them (gm_approver_id == user_id || gm_reviewer_id == user_id)
     * - Finance Staff: Only requests assigned to them / processed by them (assigned_finance_staff_id == user_id || finance_staff_id == user_id || paid_by == user_id)
     * - Employee: Only requests submitted by them (user_id == user_id)
     */
    public function scopePaidHistoryForUser(\Illuminate\Database\Eloquent\Builder $query, User $user): \Illuminate\Database\Eloquent\Builder
    {
        $roleNames = strtolower(implode(' ', $user->getRoleNames()->toArray()));

        // Admin & Finance Head see ALL paid requests
        if ($user->hasAnyRole(['admin', 'global_admin', 'finance_head', 'finance_manager']) || 
            str_contains($roleNames, 'finance_head') || 
            str_contains($roleNames, 'finance_manager')) {
            return $query->where('status', self::STATUS_PAID);
        }

        return $query->where('status', self::STATUS_PAID)->where(function ($q) use ($user, $roleNames) {
            $conditions = [];

            // 1. Employee's own submitted requests
            $q->where('user_id', $user->id);

            // 2. HR: paid requests they personally reviewed
            if ($user->can('hr.view') || str_contains($roleNames, 'hr')) {
                $q->orWhere('hr_reviewer_id', $user->id);
            }

            // 3. GM: paid requests they personally approved (> 5,000 ETB)
            if (str_contains($roleNames, 'gm') || $user->hasRole('gm')) {
                $q->orWhere('gm_approver_id', $user->id)
                  ->orWhere('gm_reviewer_id', $user->id);
            }

            // 4. Finance Staff: paid requests assigned to them or processed by them
            if (str_contains($roleNames, 'finance') || str_contains($roleNames, 'cashier') || str_contains($roleNames, 'accountant')) {
                $q->orWhere('assigned_finance_staff_id', $user->id)
                  ->orWhere('finance_staff_id', $user->id)
                  ->orWhere('paid_by', $user->id);
            }
        });
    }

    /**
     * Accessor for full attachment URL.
     */
    public function getAttachmentUrlAttribute()
    {
        return \App\Services\FileUploadService::url($this->attachment);
    }

    /**
     * Accessor for Bootstrap status badge markup.
     */
    public function getStatusBadgeAttribute()
    {
        switch ($this->status) {
            case self::STATUS_PENDING_HR:
                return '<span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-half me-1"></i>Pending (HR Review)</span>';
            case self::STATUS_PENDING_GM:
                return '<span class="badge bg-info text-white"><i class="fa-solid fa-user-shield me-1"></i>Pending (GM Review)</span>';
            case self::STATUS_APPROVED_ASSIGNED:
            case self::STATUS_ASSIGNED:
                return '<span class="badge bg-primary"><i class="fa-solid fa-file-invoice-dollar me-1"></i>Assigned to Finance</span>';
            case self::STATUS_PAID:
                return '<span class="badge bg-success"><i class="fa-solid fa-check-circle me-1"></i>Paid</span>';
            case self::STATUS_REJECTED:
                return '<span class="badge bg-danger"><i class="fa-solid fa-times-circle me-1"></i>Rejected</span>';
            default:
                return '<span class="badge bg-secondary">' . e($this->status) . '</span>';
        }
    }

    /**
     * Get the user who rejected the request.
     */
    public function getRejectedByUserAttribute()
    {
        return $this->gmReviewer ?? $this->gmApprover ?? $this->hrReviewer;
    }

    /**
     * Get the role name / title of who rejected the request.
     */
    public function getRejectedByRoleAttribute()
    {
        if ($this->gm_reviewer_id || $this->gm_approver_id) {
            return 'General Manager (GM)';
        }
        if ($this->hr_reviewer_id) {
            return 'HR Officer';
        }
        return 'Reviewer';
    }

    /**
     * Get the timestamp when the request was rejected.
     */
    public function getRejectedAtAttribute()
    {
        return $this->gm_reviewed_at ?? $this->gm_approved_at ?? $this->hr_reviewed_at ?? $this->updated_at;
    }

    /**
     * Get public / accessible URL for withholding tax receipt.
     */
    public function getWithholdingReceiptUrlAttribute(): ?string
    {
        if (empty($this->withholding_receipt)) {
            return null;
        }
        return \App\Services\FileUploadService::url($this->withholding_receipt);
    }
}

