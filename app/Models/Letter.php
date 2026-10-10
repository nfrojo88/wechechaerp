<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Letter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'letter_number',
        'is_reference_locked',
        'type',
        'date',
        'subject',
        'specification',
        'category',
        'addressed_to_user_id',
        'sender',
        'sender_department',
        'recipient_organization',
        'priority',
        'status',
        'sent_at',
        'registered_by',
        'registered_at',
        'created_by',
        'closed_by',
        'closed_at',
        'closing_notes',
        'resolution_attachment_path',
        'payment_amount',
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
        'payment_reference',
        'paid_from_account',
        'chart_of_account_id',
        'bank_account_id',
        'expense_request_id',
        'expense_id',
        'payment_voucher_path',
        'paid_at',
        'paid_by',
    ];

    protected $casts = [
        'date'                => 'date',
        'sent_at'             => 'datetime',
        'registered_at'       => 'datetime',
        'closed_at'           => 'datetime',
        'paid_at'             => 'datetime',
        'is_reference_locked' => 'boolean',
        'payment_amount'      => 'decimal:2',
        'gross_amount'        => 'decimal:2',
        'vat_rate'            => 'decimal:2',
        'vat_amount'          => 'decimal:2',
        'has_withholding'     => 'boolean',
        'withholding_rate'    => 'decimal:2',
        'withholding_amount'  => 'decimal:2',
        'net_amount'          => 'decimal:2',
    ];

    // Status Constants
    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_REGISTERED = 'registered';
    const STATUS_PENDING = 'pending';
    const STATUS_VIEWED = 'viewed';
    const STATUS_REDIRECTED = 'redirected';
    const STATUS_CLOSED = 'closed';

    // Category Constants
    const CATEGORY_LEAVE_LETTER = 'Leave Letter';
    const CATEGORY_ADVANCE_LOAN_LETTER = 'Advance Loan Letter';
    const CATEGORY_PAYMENT = 'Payment';
    const CATEGORY_GOVERNMENT = 'Government';
    const CATEGORY_BANK_INSURANCE = 'Bank & Insurance';

    const CATEGORIES = [
        self::CATEGORY_LEAVE_LETTER,
        self::CATEGORY_ADVANCE_LOAN_LETTER,
        self::CATEGORY_PAYMENT,
        self::CATEGORY_GOVERNMENT,
        self::CATEGORY_BANK_INSURANCE,
    ];

    // Type Constants
    const TYPE_INCOMING = 'incoming';
    const TYPE_OUTGOING = 'outgoing';

    // Priority Constants
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_URGENT = 'urgent';

    /**
     * Relationship: Creator (Secretary or Admin)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Closer (User who marked closed)
     */
    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Relationship: Payer (Finance staff who paid)
     */
    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Relationship: Chart of Account from which payment was deducted
     */
    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    /**
     * Relationship: Bank Account used
     */
    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * Relationship: Linked Expense Request (Ask Money)
     */
    public function expenseRequest()
    {
        return $this->belongsTo(ExpenseRequest::class, 'expense_request_id');
    }

    /**
     * Relationship: Linked General / Project Expense
     */
    public function expense()
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }

    /**
     * Relationship: Multi-file Attachments
     */
    public function attachments()
    {
        return $this->hasMany(LetterAttachment::class, 'letter_id');
    }

    /**
     * Relationship: Routing / Recipients / Hand-offs
     */
    public function recipients()
    {
        return $this->hasMany(LetterRecipient::class, 'letter_id')->orderBy('id', 'asc');
    }

    /**
     * Relationship: Latest Active Recipient / Hand-off
     */
    public function latestRecipient()
    {
        return $this->hasOne(LetterRecipient::class, 'letter_id')->latestOfMany('id');
    }

    /**
     * Relationship: Notifications
     */
    public function notifications()
    {
        return $this->hasMany(LetterNotification::class, 'letter_id');
    }

    /**
     * Relationship: Person addressed to / handled by (selected by Secretary)
     */
    public function addressedTo()
    {
        return $this->belongsTo(User::class, 'addressed_to_user_id');
    }

    /**
     * Relationship: Secretary / Admin who registered the letter
     */
    public function registeredBy()
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * Relationship: Comprehensive action history audit logs
     */
    public function actionLogs()
    {
        return $this->hasMany(LetterActionLog::class, 'letter_id')->orderBy('id', 'asc');
    }

    /**
     * Status Helper Checks
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isRegistered(): bool
    {
        return $this->status === self::STATUS_REGISTERED;
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT      => 'bg-secondary text-white',
            self::STATUS_SENT       => 'bg-warning text-dark',
            self::STATUS_REGISTERED => 'bg-success text-white',
            self::STATUS_CLOSED     => 'bg-dark text-white',
            default                 => 'bg-info text-white',
        };
    }

    /**
     * Generate next suggested letter number
     */
    public static function generateSuggestedNumber(string $type = 'incoming'): string
    {
        return LetterSequence::generateNext();
    }

    /**
     * Check if a specific user can view / access this letter
     */
    public function isAccessibleBy(User $user): bool
    {
        // 1. If it's a draft, STRICTLY only the creator can access it
        if ($this->status === self::STATUS_DRAFT) {
            return $this->created_by === $user->id;
        }

        // 2. The author always has access to their own sent/registered letters
        if ($this->created_by === $user->id) {
            return true;
        }

        // 3. Secretary and Global Admin/Admin can view letters sent to secretary
        if ($user->hasRole(['admin', 'global_admin', 'secretary'])) {
            return true;
        }

        // 4. The person selected/addressed to can view the letter once assigned/registered
        if ($this->addressed_to_user_id === $user->id) {
            return true;
        }

        // 5. Direct user recipient in routing log
        if ($this->recipients()->where('to_user_id', $user->id)->exists()) {
            return true;
        }

        // 6. Role-based recipient
        $userRoles = $user->getRoleNames()->toArray();
        if (!empty($userRoles) && $this->recipients()->whereIn('to_role_name', $userRoles)->exists()) {
            return true;
        }

        // 7. Forwarder / Question Sender in routing chain
        if ($this->recipients()->where('from_user_id', $user->id)->exists()) {
            return true;
        }

        // 8. Management / Executive Hierarchy
        if ($user->hasAnyRole(['gm', 'general_manager', 'managing_director', 'director', 'ceo', 'deputy_general_manager'])) {
            return true;
        }

        return false;
    }
}
