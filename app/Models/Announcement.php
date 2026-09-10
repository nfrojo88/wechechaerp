<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'announcements';

    protected $fillable = [
        'title',
        'message',
        'target_type',
        'target_criteria',
        'send_sms',
        'is_published',
        'expires_at',
        'total_recipients',
        'sms_sent_count',
        'sms_failed_count',
        'created_by',
    ];

    protected $casts = [
        'target_criteria' => 'array',
        'send_sms' => 'boolean',
        'is_published' => 'boolean',
        'expires_at' => 'datetime',
        'total_recipients' => 'integer',
        'sms_sent_count' => 'integer',
        'sms_failed_count' => 'integer',
    ];

    /**
     * User who created the announcement.
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * SMS delivery logs.
     */
    public function smsLogs()
    {
        return $this->hasMany(AnnouncementSmsLog::class, 'announcement_id');
    }

    /**
     * Scope for currently active in-app banners.
     */
    public function scopeActiveBanner($query)
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', now());
            });
    }
}
