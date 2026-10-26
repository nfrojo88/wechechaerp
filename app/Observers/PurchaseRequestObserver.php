<?php

namespace App\Observers;

use App\Models\PurchaseRequest;
use App\Services\ProcurementTransitionNotifier;
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
                app(ProcurementTransitionNotifier::class)->notify(
                    model: $pr,
                    newStatus: $pr->status,
                    oldStatus: null,
                    actor: auth()->user() ?? $pr->creator
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
                app(ProcurementTransitionNotifier::class)->notify(
                    model: $pr,
                    newStatus: $pr->status,
                    oldStatus: $pr->getOriginal('status'),
                    actor: auth()->user(),
                    extraContext: [
                        'reason' => $pr->rejection_reason,
                    ]
                );
            } catch (\Throwable $e) {
                Log::error("PurchaseRequestObserver updated error for PR #{$pr->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle the PurchaseRequest "deleted" event.
     */
    public function deleted(PurchaseRequest $pr): void
    {
        try {
            app(ProcurementTransitionNotifier::class)->notify(
                model: $pr,
                newStatus: PurchaseRequest::STATUS_CANCELLED,
                oldStatus: $pr->status,
                actor: auth()->user(),
                extraContext: ['is_fyi' => true]
            );
        } catch (\Throwable $e) {
            Log::error("PurchaseRequestObserver deleted error for PR #{$pr->id}: " . $e->getMessage());
        }
    }
}
