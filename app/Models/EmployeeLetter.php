<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeLetter extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'reference_number',
        'letter_type',
        'title',
        'content',
        'severity',
        'issued_date',
        'issued_by',
        'attachment_path',
        'effective_date',
        'action_required',
        'acknowledgement_status',
        'acknowledged_at',
    ];

    protected $casts = [
        'issued_date'     => 'date',
        'effective_date'  => 'date',
        'acknowledged_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->letter_type) {
            'thanks_letter'   => 'Thanks / Appreciation Letter',
            'appreciation'    => 'Letter of Recognition',
            'first_warning'   => 'First Written Warning',
            'second_warning'  => 'Second Written Warning',
            'final_warning'   => 'Final Warning Letter',
            'show_cause'      => 'Show Cause / Query Letter',
            'suspension'      => 'Suspension Letter',
            'termination'     => 'Termination Letter',
            'promotion'       => 'Promotion Letter',
            default           => ucfirst(str_replace('_', ' ', $this->letter_type)),
        };
    }

    public function getBadgeClassAttribute(): string
    {
        return match ($this->letter_type) {
            'thanks_letter', 'appreciation', 'promotion' => 'bg-success text-white',
            'first_warning'   => 'bg-warning text-dark',
            'second_warning'  => 'bg-orange text-white',
            'final_warning'   => 'bg-danger text-white',
            'show_cause'      => 'bg-info text-dark',
            'suspension'      => 'bg-purple text-white',
            'termination'     => 'bg-dark text-white',
            default           => 'bg-secondary text-white',
        };
    }

    public function getIconAttribute(): string
    {
        return match ($this->letter_type) {
            'thanks_letter', 'appreciation', 'promotion' => 'fa-solid fa-award',
            'first_warning', 'second_warning' => 'fa-solid fa-triangle-exclamation',
            'final_warning'   => 'fa-solid fa-circle-exclamation',
            'show_cause'      => 'fa-solid fa-circle-question',
            'suspension', 'termination' => 'fa-solid fa-user-slash',
            default           => 'fa-solid fa-envelope-open-text',
        };
    }
}
