<?php

namespace App\Observers;

use App\Models\MaterialRequest;
use App\Services\ProcurementStatusOwnerService;
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
            app(ProcurementStatusOwnerService::class)->dispatchStatusChangeSms(
                model: $mr,
                newStatus: $status,
                oldStatus: null
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
            if ($planningChanged && $mr->planning_approval_status === 'approved' && $mr->status !== 'planning_approved') {
                $effectiveStatus = 'planning_approved';
            } elseif ($planningChanged && $mr->planning_approval_status === 'rejected') {
                $effectiveStatus = 'rejected';
            }

            try {
                app(ProcurementStatusOwnerService::class)->dispatchStatusChangeSms(
                    model: $mr,
                    newStatus: $effectiveStatus,
                    oldStatus: $mr->getOriginal('status')
                );
            } catch (\Throwable $e) {
                Log::error("MaterialRequestObserver updated error for MR #{$mr->id}: " . $e->getMessage());
            }
        }
    }
}
