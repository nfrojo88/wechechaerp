<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'letter_id',
        'from_user_id',
        'to_user_id',
        'to_role_name',
        'action',
        'notes',
        'attachment_path',
        'attachment_name',
        'status',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function letter()
    {
        return $this->belongsTo(Letter::class, 'letter_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * Get descriptive recipient label
     */
    public function getRecipientLabelAttribute(): string
    {
        if ($this->to_user_id && $this->toUser) {
            return $this->toUser->name . ' (' . ($this->toUser->email ?? 'User') . ')';
        }

        if ($this->to_role_name) {
            return 'Role: ' . ucfirst(str_replace(['_', '-'], ' ', $this->to_role_name));
        }

        return 'General / Unspecified';
    }
}
