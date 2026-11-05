<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UtilityExpense extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'utility_expenses';

    public const STATUS_PENDING   = 'pending_payment';
    public const STATUS_PAID      = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_WIFI                = 'wifi';
    public const TYPE_RENTAL              = 'rental';
    public const TYPE_ELECTRICITY         = 'electricity';
    public const TYPE_WATER               = 'water';
    public const TYPE_TELEPHONE           = 'telephone';
    public const TYPE_SANITATION_SECURITY = 'sanitation_security';
    public const TYPE_FUEL_GENERATOR      = 'fuel_generator';
    public const TYPE_OTHER               = 'other';

    public const UTILITY_TYPES = [
        self::TYPE_WIFI => [
            'label' => 'WiFi & Internet Payment (የዋይፋይ / ኢንተርኔት ክፍያ)',
            'short_label' => 'WiFi / Internet',
            'icon'  => 'fa-solid fa-wifi',
            'color' => 'primary',
        ],
        self::TYPE_RENTAL => [
            'label' => 'Office Rental (የቢሮ ኪራይ)',
            'short_label' => 'Office Rental',
            'icon'  => 'fa-solid fa-building',
            'color' => 'danger',
        ],
        self::TYPE_ELECTRICITY => [
            'label' => 'Electricity & Power (የመብራት ክፍያ)',
            'short_label' => 'Electricity',
            'icon'  => 'fa-solid fa-bolt',
            'color' => 'warning',
        ],
        self::TYPE_WATER => [
            'label' => 'Water Bill (የውሃ ክፍያ)',
            'short_label' => 'Water Bill',
            'icon'  => 'fa-solid fa-droplet',
            'color' => 'info',
        ],
        self::TYPE_TELEPHONE => [
            'label' => 'Telephone & Mobile (የስልክ ክፍያ)',
            'short_label' => 'Telephone / Mobile',
            'icon'  => 'fa-solid fa-phone',
            'color' => 'secondary',
        ],
        self::TYPE_SANITATION_SECURITY => [
            'label' => 'Sanitation, Cleaning & Security (የጽዳት እና ጥበቃ አገልግሎት)',
            'short_label' => 'Sanitation / Security',
            'icon'  => 'fa-solid fa-broom',
            'color' => 'success',
        ],
        self::TYPE_FUEL_GENERATOR => [
            'label' => 'Generator Fuel / Utility (ለጄኔሬተር ነዳጅ / ፍጆታ)',
            'short_label' => 'Generator Fuel',
            'icon'  => 'fa-solid fa-gas-pump',
            'color' => 'dark',
        ],
        self::TYPE_OTHER => [
            'label' => 'Other Facility / Office Expense (ሌሎች የፍጆታ ወጪዎች)',
            'short_label' => 'Other Utility',
            'icon'  => 'fa-solid fa-receipt',
            'color' => 'secondary',
        ],
    ];

    protected $fillable = [
        'expense_number',
        'utility_type',
        'title',
        'description',
        'billing_period',
        'reference_no',
        'due_date',
        'amount',
        'coa_id',
        'bank_account_id',
        'created_by',
        'assigned_to',
        'status',
        'priority',
        'bill_attachment',
        'payment_method',
        'payment_reference',
        'payment_date',
        'paid_amount',
        'paid_by',
        'paid_at',
        'payment_receipt',
        'payment_notes',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'paid_amount'  => 'decimal:2',
        'due_date'     => 'date',
        'payment_date' => 'date',
        'paid_at'      => 'datetime',
    ];

    // Relations
    public function coa()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedPerson()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function payer()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    // Helpers
    public function getTypeConfigAttribute(): array
    {
        return self::UTILITY_TYPES[$this->utility_type] ?? [
            'label'       => ucfirst($this->utility_type ?? 'Other'),
            'short_label' => ucfirst($this->utility_type ?? 'Other'),
            'icon'        => 'fa-solid fa-receipt',
            'color'       => 'secondary',
        ];
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PAID      => '<span class="badge bg-success-subtle text-success border border-success fw-bold px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i>Paid (የተከፈለ)</span>',
            self::STATUS_CANCELLED => '<span class="badge bg-danger-subtle text-danger border border-danger fw-bold px-2 py-1"><i class="fa-solid fa-circle-xmark me-1"></i>Cancelled</span>',
            default                => '<span class="badge bg-warning text-dark fw-bold px-2 py-1"><i class="fa-solid fa-clock-rotate-left me-1"></i>Assigned to Pay (ክፍያ በመጠባበቅ ላይ)</span>',
        };
    }

    public static function generateNextNumber(): string
    {
        $year = date('Y');
        $prefix = "UTIL-{$year}-";
        $nextSeq = 1;

        try {
            $lastRecord = self::withTrashed()
                ->where('expense_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();

            if ($lastRecord && preg_match('/-(\d+)$/', $lastRecord->expense_number, $matches)) {
                $nextSeq = ((int) $matches[1]) + 1;
            }
        } catch (\Throwable $e) {
            $nextSeq = 1;
        }

        return $prefix . str_pad((string)$nextSeq, 4, '0', STR_PAD_LEFT);
    }
}
