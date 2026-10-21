<?php

namespace App\Observers;

use App\Models\PurchaseRequest;
use App\Services\ProcurementStatusOwnerService;
use Illuminate\Support\Facades\Log;

class PurchaseRequestObserver
{
    /**
     * Handle events after all database transactions are committed.
     * Ensures rollbacks never send SMS messages.
     *
     * @var bool
     */
    public $afterCommit = true;

    /**
     * Handle the PurchaseRequest "created" event.
     */
    public function created(PurchaseRequest $pr): void
    {
        // Only notify if not a local draft
        if ($pr->status && $pr->status !== PurchaseRequest::STATUS_DRAFT) {
            try {
                app(ProcurementStatusOwnerService::class)->dispatchStatusChangeSms(
                    model: $pr,
                    newStatus: $pr->status,
                    oldStatus: null
                );
            } catch (\Throwable $e) {
                Log::error("PurchaseRequestObserver created error for PR #{$pr->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle the PurchaseRequest "updated" event.
     */
    public function updated(PurchaseRequest $pr): void
    {
        if ($pr->wasChanged('status')) {
            try {
                app(ProcurementStatusOwnerService::class)->dispatchStatusChangeSms(
                    model: $pr,
                    newStatus: $pr->status,
                    oldStatus: $pr->getOriginal('status')
                );
            } catch (\Throwable $e) {
                Log::error("PurchaseRequestObserver updated error for PR #{$pr->id}: " . $e->getMessage());
            }
        }
    }
}
