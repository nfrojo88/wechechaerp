<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'model_type', 'model_id',
        'description', 'module', 'ip_address', 'user_agent', 'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function model()
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    public function subject()
    {
        return $this->morphTo('subject', 'model_type', 'model_id');
    }

    /**
     * Log an activity statically
     */
    public static function log(string $action, string $description, string $module = null, $model = null, array $changes = []): void
    {
        try {
            static::create([
                'user_id'     => auth()->id(),
                'action'      => $action,
                'model_type'  => $model ? get_class($model) : null,
                'model_id'    => $model?->id,
                'description' => $description,
                'module'      => $module,
                'ip_address'  => request()->ip(),
                'user_agent'  => substr(request()->userAgent() ?? '', 0, 512),
                'changes'     => $changes ?: null,
            ]);
        } catch (\Throwable $e) {
            // Never let logging break the app
        }
    }

    /**
     * Get action badge color
     */
    public function getActionColorAttribute(): string
    {
        return match($this->action) {
            'created'  => 'success',
            'updated'  => 'primary',
            'deleted'  => 'danger',
            'login'    => 'info',
            'logout'   => 'secondary',
            'approved' => 'success',
            'rejected' => 'danger',
            default    => 'secondary',
        };
    }

    /**
     * Get action icon
     */
    public function getActionIconAttribute(): string
    {
        return match($this->action) {
            'created'  => 'fa-plus-circle',
            'updated'  => 'fa-pen-to-square',
            'deleted'  => 'fa-trash',
            'login'    => 'fa-right-to-bracket',
            'logout'   => 'fa-right-from-bracket',
            'approved' => 'fa-check-circle',
            'rejected' => 'fa-times-circle',
            default    => 'fa-circle-dot',
        };
    }
}
