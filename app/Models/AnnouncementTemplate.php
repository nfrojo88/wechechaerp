<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnouncementTemplate extends Model
{
    use HasFactory;

    protected $table = 'announcement_templates';

    protected $fillable = [
        'name',
        'title',
        'message',
        'icon',
        'color',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
