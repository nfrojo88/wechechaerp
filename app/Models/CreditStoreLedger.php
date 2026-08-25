<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditStoreLedger extends Model
{
    protected $fillable = [
        'purchase_request_id',
        'pr_no',
        'project_id',
        'supplier_name',
        'credit_amount',
        'paid_amount',
        'coa_account_id',
        'status',
        'authorized_by',
        'authorized_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'credit_amount' => 'decimal:2',
        'paid_amount'   => 'decimal:2',
        'authorized_at' => 'datetime',
    ];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function coaAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_account_id');
    }

    public function authorizedByUser()
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments()
    {
        return $this->hasMany(CreditStorePayment::class, 'credit_store_ledger_id')->latest('payment_date');
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float)$this->credit_amount - (float)$this->paid_amount);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'fully_paid'     => 'success',
            'partially_paid' => 'warning',
            default          => 'danger',
        };
    }
}
