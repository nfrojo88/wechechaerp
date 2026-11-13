<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialAuditTrail extends Model
{
    protected $table = 'financial_audit_trails';

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'action',
        'user_id',
        'user_role',
        'reason',
        'payload',
        'ip_address',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function auditable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an immutable audit entry.
     */
    public static function logAction(
        Model $auditable,
        string $action,
        ?User $user = null,
        ?string $reason = null,
        ?array $payload = null
    ): ?self {
        if (!\Illuminate\Support\Facades\Schema::hasTable('financial_audit_trails')) {
            return null;
        }

        return static::create([
            'auditable_type' => get_class($auditable),
            'auditable_id'   => $auditable->getKey(),
            'action'         => $action,
            'user_id'        => $user?->id ?? auth()->id(),
            'user_role'      => $user?->role ?? (auth()->check() ? auth()->user()->role : null),
            'reason'         => $reason,
            'payload'        => $payload,
            'ip_address'     => request()?->ip(),
        ]);
    }
}
