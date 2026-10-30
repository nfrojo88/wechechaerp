<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeRating extends Model
{
    protected $fillable = [
        'employee_id', 'rated_by', 'rating', 'comment', 'period', 'category',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(\App\Scopes\NotDeadFileScope::class);
    }

    public function ratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }

    /**
     * Get star display string
     */
    public function getStarsHtmlAttribute(): string
    {
        $html = '';
        for ($i = 1; $i <= 5; $i++) {
            $class = $i <= $this->rating ? 'text-warning' : 'text-muted';
            $html .= "<i class='fa-solid fa-star $class' style='font-size:13px;'></i>";
        }
        return $html;
    }
}
