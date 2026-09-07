<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'category', 'description', 'amount',
        'expense_date', 'status', 'created_by', 'approved_by',
        'approved_at', 'notes',
        'receipt_path',
        'audit_receipt_status',
        'audit_receipt_notes',
        'audit_receipt_requested_at',
        'audit_receipt_requested_by',
        'audit_receipt_verified_at',
        'audit_receipt_verified_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'approved_at'  => 'datetime',
        'amount'       => 'decimal:2',
    ];

    public const CATEGORIES = [
        'labour'        => 'Labour',
        'material'      => 'Material',
        'equipment'     => 'Equipment',
        'overhead'      => 'Overhead',
        'subcontractor' => 'Sub-Contractor',
        'other'         => 'Other',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
