<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryHistory extends Model
{
    use HasFactory;

    protected $table = 'employee_salary_histories';

    protected $fillable = [
        'employee_id',
        'old_basic_salary',
        'new_basic_salary',
        'old_transport_allowance',
        'new_transport_allowance',
        'old_house_allowance',
        'new_house_allowance',
        'old_position_allowance',
        'new_position_allowance',
        'old_gross_salary',
        'new_gross_salary',
        'effective_date',
        'reason',
        'notes',
        'letter_id',
        'employee_letter_id',
        'attachment_path',
        'changed_by',
    ];

    protected $casts = [
        'effective_date'          => 'date',
        'old_basic_salary'        => 'decimal:2',
        'new_basic_salary'        => 'decimal:2',
        'old_transport_allowance' => 'decimal:2',
        'new_transport_allowance' => 'decimal:2',
        'old_house_allowance'     => 'decimal:2',
        'new_house_allowance'     => 'decimal:2',
        'old_position_allowance'  => 'decimal:2',
        'new_position_allowance'  => 'decimal:2',
        'old_gross_salary'        => 'decimal:2',
        'new_gross_salary'        => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(\App\Scopes\NotDeadFileScope::class);
    }

    public function letter()
    {
        return $this->belongsTo(Letter::class, 'letter_id');
    }

    public function employeeLetter()
    {
        return $this->belongsTo(EmployeeLetter::class, 'employee_letter_id');
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (empty($this->attachment_path)) {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($this->attachment_path, ['http://', 'https://'])) {
            return $this->attachment_path;
        }

        if (file_exists(public_path($this->attachment_path))) {
            return asset($this->attachment_path);
        }

        return asset('storage/' . $this->attachment_path);
    }

    public function getSalaryDifferenceAttribute(): float
    {
        return (float) ($this->new_basic_salary - $this->old_basic_salary);
    }
}
