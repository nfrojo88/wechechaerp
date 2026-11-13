<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientIpc extends Model
{
    use SoftDeletes;

    protected $table = 'client_ipcs';
    protected $guarded = ['id'];

    protected $casts = [
        'period_from'    => 'date',
        'period_to'      => 'date',
        'submission_date'=> 'date',
        'approved_at'    => 'datetime',
        'certified_at'   => 'datetime',
        'measurement_sheet_summary' => 'array',
        'retention_percent' => 'decimal:2',
        'gross_amount'   => 'decimal:2',
        'net_amount'     => 'decimal:2',
        'retention_amount'=> 'decimal:2',
        'cumulative_certified' => 'decimal:2',
        'previous_certified'   => 'decimal:2',
        'gross_certified_amount' => 'decimal:2',
        'retention_rate_percent' => 'decimal:2',
        'advance_recovery_rate_percent' => 'decimal:2',
        'advance_recovery_amount' => 'decimal:2',
        'vat_rate_percent' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'net_certified_payable' => 'decimal:2',
        'is_posted' => 'boolean',
    ];

    // ── Status constants ────────────────────────────────────────────────────────
    const STATUS_DRAFT       = 'draft';
    const STATUS_SUBMITTED   = 'submitted';
    const STATUS_UNDER_REVIEW= 'under_review';
    const STATUS_APPROVED    = 'approved';
    const STATUS_PAID        = 'paid';
    const STATUS_REJECTED    = 'rejected';

    // ── Relationships ───────────────────────────────────────────────────────────

    public function contract()
    {
        return $this->belongsTo(ConstructionContract::class, 'contract_id');
    }

    public function period()
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function collections()
    {
        return $this->hasMany(ClientIpcCollection::class, 'client_ipc_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function isCertified(): bool
    {
        return in_array($this->certification_status, ['certified', 'approved']) 
            || $this->status === 'approved';
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function boq()
    {
        return $this->belongsTo(Boq::class);
    }

    public function items()
    {
        return $this->hasMany(ClientIpcItem::class, 'client_ipc_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'client_ipc_id');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'approved'     => 'badge bg-success',
            'paid'         => 'badge bg-dark',
            'submitted'    => 'badge bg-primary',
            'under_review' => 'badge bg-info',
            'rejected'     => 'badge bg-danger',
            default        => 'badge bg-secondary',
        };
    }

    public function completionPercent(): float
    {
        $boqTotal = $this->boq?->total_amount ?? 0;
        if ($boqTotal <= 0) return 0;
        return round(($this->cumulative_certified / $boqTotal) * 100, 1);
    }

    // ── Scopes ──────────────────────────────────────────────────────────────────

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'submitted', 'under_review']);
    }

    // ── Auto-generate IPC number ─────────────────────────────────────────────────

    public static function generateIpcNo(int $projectId): string
    {
        $count = static::where('project_id', $projectId)->count();
        return 'CIPC-' . $projectId . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    // ── Recalculate totals from items ────────────────────────────────────────────

    public function recalculate(): void
    {
        $gross = $this->items()->sum('current_amount');
        $retention = round($gross * ($this->retention_percent / 100), 2);
        $net = $gross - $retention;

        $previousApproved = static::where('project_id', $this->project_id)
            ->where('id', '<', $this->id)
            ->whereIn('status', ['approved', 'paid'])
            ->sum('gross_amount');

        $this->update([
            'gross_amount'         => $gross,
            'retention_amount'     => $retention,
            'net_amount'           => $net,
            'previous_certified'   => $previousApproved,
            'cumulative_certified' => $previousApproved + $gross,
        ]);
    }
}
