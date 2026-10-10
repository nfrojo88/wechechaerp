<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterActionLog extends Model
{
    protected $table = 'letter_action_logs';

    public $timestamps = false;

    protected $fillable = [
        'letter_id',
        'user_id',
        'action',
        'description',
        'details',
        'created_at',
    ];

    protected $casts = [
        'details'    => 'array',
        'created_at' => 'datetime',
    ];

    public function letter()
    {
        return $this->belongsTo(Letter::class, 'letter_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Record an audit action log with actor and timestamp.
     */
    public static function record(int $letterId, int $userId, string $action, string $description, array $details = []): static
    {
        return static::create([
            'letter_id'   => $letterId,
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'details'     => empty($details) ? null : $details,
            'created_at'  => now(),
        ]);
    }
}
