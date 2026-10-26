<?php

namespace App\Services;

use App\Models\MaterialRequest;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProcurementTransitionNotifier
 *
 * Central Transition Dispatcher for the entire Procurement Lifecycle (MR & PR).
 * Called from model observers and services to guarantee instant, non-blocking SMS
 * notifications on every state change and ownership handoff.
 *
 * Guaranteed to execute DB::afterCommit so failed transactions never send SMS.
 */
class ProcurementTransitionNotifier
{
    public function __construct(
        protected ProcurementStatusOwnerService $statusOwnerService
    ) {}

    /**
     * Dispatch an instant SMS notification for an MR or PR status transition.
     * Guaranteed non-blocking and executes post-commit.
     */
    public function notify(
        Model $model,
        string $newStatus,
        ?string $oldStatus = null,
        ?User $actor = null,
        array $extraContext = []
    ): void {
        $runner = function () use ($model, $newStatus, $oldStatus, $actor, $extraContext) {
            try {
                $this->statusOwnerService->dispatchStatusChangeSms(
                    model: $model,
                    newStatus: $newStatus,
                    oldStatus: $oldStatus,
                    actor: $actor,
                    extraContext: $extraContext
                );
            } catch (\Throwable $e) {
                Log::error("ProcurementTransitionNotifier exception: " . $e->getMessage(), [
                    'model'  => get_class($model),
                    'id'     => $model->getKey(),
                    'status' => $newStatus,
                ]);
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($runner);
        } else {
            $runner();
        }
    }

    /**
     * Convenience method for Material Requests.
     */
    public function notifyMR(
        MaterialRequest $mr,
        string $newStatus,
        ?string $oldStatus = null,
        ?User $actor = null,
        array $extraContext = []
    ): void {
        $this->notify($mr, $newStatus, $oldStatus, $actor, $extraContext);
    }

    /**
     * Convenience method for Purchase Requests.
     */
    public function notifyPR(
        PurchaseRequest $pr,
        string $newStatus,
        ?string $oldStatus = null,
        ?User $actor = null,
        array $extraContext = []
    ): void {
        $this->notify($pr, $newStatus, $oldStatus, $actor, $extraContext);
    }
}
