<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProformaInvoice extends Model
{
    protected $fillable = [
        'proforma_no', 'purchase_request_id', 'supplier_id', 'proforma_date',
        'valid_until', 'subtotal', 'tax_amount', 'grand_total', 'item_prices', 'status',
        'gm_selected', 'notes', 'file_path',
    ];

    protected $casts = [
        'proforma_date' => 'date',
        'valid_until'   => 'date',
        'gm_selected'   => 'boolean',
        'item_prices'   => 'array',
    ];

    protected static function booted()
    {
        static::creating(function ($proforma) {
            $proforma->proforma_no = static::generateNextNo($proforma->proforma_no);
        });
    }

    /**
     * Generate a guaranteed unique proforma number.
     */
    public static function generateNextNo(?string $preferredNo = null): string
    {
        if (!empty($preferredNo)) {
            $candidate = trim($preferredNo);
            $original = $candidate;
            $suffix = 1;
            while (static::where('proforma_no', $candidate)->exists()) {
                $candidate = $original . '-' . $suffix;
                $suffix++;
            }
            return $candidate;
        }

        $datePrefix = 'PROF-' . date('Ymd') . '-';
        $lastProf = static::where('proforma_no', 'like', $datePrefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $nextSeq = 1;
        if ($lastProf && preg_match('/' . preg_quote($datePrefix, '/') . '(\d+)/', $lastProf->proforma_no, $matches)) {
            $nextSeq = (int)$matches[1] + 1;
        } else {
            $nextSeq = static::count() + 1;
        }

        do {
            $candidate = $datePrefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
            $exists = static::where('proforma_no', $candidate)->exists();
            if ($exists) {
                $nextSeq++;
            }
        } while ($exists);

        return $candidate;
    }

    public function purchaseRequest() { return $this->belongsTo(PurchaseRequest::class); }
    public function supplier()        { return $this->belongsTo(Supplier::class); }
    public function purchaseOrders()  { return $this->hasMany(PurchaseOrder::class); }
}