<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IncomeRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'income_no',
        'project_id',
        'category',
        'income_date',
        'amount',
        'payment_method',
        'bank_account_id',
        'chart_of_account_id',
        'is_deposited',
        'description',
        'notes',
        'status',
        'created_by',
        'journal_entry_id',
        'seller_tin',
        'buyer_tin',
        'buyer_name',
        'transaction_type',
        'receipt_category',
        'receipt_used',
        'mrc_no',
        'receipt_no',
        'commodity_type',
        'quantity',
        'unit_price',
        'receipt_date',
        'encoded_by',
        'receipt_file_path',
        'vat_settled',
        'vat_settlement_id',
        'tax_settled_at',
    ];

    protected $casts = [
        'income_date'    => 'date',
        'receipt_date'   => 'date',
        'amount'         => 'decimal:2',
        'unit_price'     => 'decimal:2',
        'quantity'       => 'decimal:2',
        'is_deposited'   => 'boolean',
        'vat_settled'    => 'boolean',
        'tax_settled_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function (IncomeRecord $income) {
            if (empty($income->description)) {
                $income->description = !empty($income->buyer_name)
                    ? "Income from {$income->buyer_name} (" . ucfirst($income->category ?: 'General') . ")"
                    : "Income receipt - " . ucfirst($income->category ?: 'General');
            }
        });

        static::updating(function (IncomeRecord $income) {
            if (empty($income->description)) {
                $income->description = !empty($income->buyer_name)
                    ? "Income from {$income->buyer_name} (" . ucfirst($income->category ?: 'General') . ")"
                    : "Income receipt - " . ucfirst($income->category ?: 'General');
            }
        });
    }

    public function project()        { return $this->belongsTo(Project::class); }
    public function bankAccount()    { return $this->belongsTo(BankAccount::class); }
    public function chartOfAccount() { return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id'); }
    public function createdBy()      { return $this->belongsTo(User::class, 'created_by'); }
    public function journalEntry()   { return $this->belongsTo(JournalEntry::class); }
    public function vatSettlement()  { return $this->belongsTo(TaxSettlement::class, 'vat_settlement_id'); }

    /**
     * Get the accessible public URL for the attached receipt.
     */
    public function getReceiptUrlAttribute(): ?string
    {
        if (empty($this->receipt_file_path)) {
            return null;
        }

        if (str_starts_with($this->receipt_file_path, 'http://') || str_starts_with($this->receipt_file_path, 'https://')) {
            return $this->receipt_file_path;
        }

        return asset('storage/' . ltrim($this->receipt_file_path, '/'));
    }

    /**
     * Display label for the deposit account (COA or Bank Account).
     */
    public function getDepositAccountNameAttribute(): string
    {
        if ($this->chartOfAccount) {
            $name = "[{$this->chartOfAccount->code}] {$this->chartOfAccount->name}";
            if ($this->bankAccount) {
                $name .= " ({$this->bankAccount->bank_name})";
            }
            return $name;
        }
        if ($this->bankAccount) {
            return "{$this->bankAccount->bank_name} ({$this->bankAccount->account_number})";
        }
        return '—';
    }

    /**
     * Normalized Seller TIN without leading zeros (default company TIN 38480010).
     */
    public function getFormattedSellerTinAttribute(): string
    {
        $tin = $this->seller_tin ?: SystemSetting::get('purchaser_tin', SystemSetting::get('company_tin', '38480010'));
        $digits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$tin));
        return ($digits !== '') ? $digits : '38480010';
    }

    /**
     * Normalized Buyer TIN without leading zeros.
     */
    public function getFormattedBuyerTinAttribute(): string
    {
        $tin = (string)($this->buyer_tin ?? '');
        $digits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', $tin));
        return ($digits !== '') ? $digits : $tin;
    }

    /**
     * Normalized Receipt No (with FS prefix + 8 digits if numeric).
     */
    public function getFormattedReceiptNoAttribute(): string
    {
        $clean = trim((string)($this->receipt_no ?? ''));
        if ($clean === '') {
            return '';
        }
        if (preg_match('/^fs/i', $clean)) {
            return strtoupper($clean);
        }
        if (preg_match('/^\d+$/', $clean)) {
            return 'FS' . str_pad($clean, 8, '0', STR_PAD_LEFT);
        }
        return $clean;
    }
}
