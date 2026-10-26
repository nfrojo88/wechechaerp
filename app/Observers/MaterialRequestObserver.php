<?php

namespace App\Observers;

use App\Models\MaterialRequest;
use App\Services\ProcurementTransitionNotifier;
use Illuminate\Support\Facades\Log;

class MaterialRequestObserver
{
    /**
     * Handle events after all database transactions are committed.
     * Ensures rollbacks never send SMS messages.
     *
     * @var bool
     */
    public $afterCommit = true;

    /**
     * Handle the MaterialRequest "created" event.
     */
    public function created(MaterialRequest $mr): void
    {
        $status = $mr->status ?? 'submitted';
        try {
            app(ProcurementTransitionNotifier::class)->notify(
                model: $mr,
                newStatus: $status,
                oldStatus: null,
                actor: auth()->user() ?? $mr->creator,
                extraContext: [
                    'reason' => $mr->planning_rejection_reason,
                ]
            );
        } catch (\Throwable $e) {
            Log::error("MaterialRequestObserver created error for MR #{$mr->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the MaterialRequest "updated" event.
     */
    public function updated(MaterialRequest $mr): void
    {
        $statusChanged = $mr->wasChanged('status');
        $planningChanged = $mr->wasChanged('planning_approval_status');

        if ($statusChanged || $planningChanged) {
            $effectiveStatus = $mr->status;
            if ($planningChanged && $mr->planning_approval_status === 'approved' && !in_array($mr->status, ['planning_approved', 'sent_to_store_manager', 'approved_for_petty_cash'])) {
                $effectiveStatus = 'planning_approved';
            } elseif ($planningChanged && $mr->planning_approval_status === 'rejected') {
                $effectiveStatus = 'rejected';
            }

            try {
                app(ProcurementTransitionNotifier::class)->notify(
                    model: $mr,
                    newStatus: $effectiveStatus,
                    oldStatus: $mr->getOriginal('status'),
                    actor: auth()->user() ?? $mr->planningApprover,
                    extraContext: [
                        'reason' => $mr->planning_rejection_reason,
                    ]
                );
            } catch (\Throwable $e) {
                Log::error("MaterialRequestObserver updated error for MR #{$mr->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle the MaterialRequest "deleted" event.
     */
    public function deleted(MaterialRequest $mr): void
    {
        try {
            app(ProcurementTransitionNotifier::class)->notify(
                model: $mr,
                newStatus: 'cancelled',
                oldStatus: $mr->status,
                actor: auth()->user(),
                extraContext: ['is_fyi' => true]
            );
        } catch (\Throwable $e) {
            Log::error("MaterialRequestObserver deleted error for MR #{$mr->id}: " . $e->getMessage());
        }
    }
}
