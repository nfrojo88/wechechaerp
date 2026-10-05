<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementSmsSetting extends Model
{
    protected $table = 'procurement_sms_settings';

    protected $fillable = [
        'handoff_key',
        'name',
        'sender_role',
        'target_role',
        'is_enabled',
        'template',
        'description',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
