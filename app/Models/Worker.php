<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    use HasFactory;

    protected $table = 'workers';

    protected $fillable = [
        'worker_code',
        'name',
        'phone',
        'national_id',
        'trade',
        'daily_rate',
        'status',
        'photo_url',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function manpowerLines()
    {
        return $this->hasMany(DailyManpowerLine::class, 'worker_id');
    }

    public function weeklyBatchItems()
    {
        return $this->hasMany(WeeklyBatchItem::class, 'worker_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
