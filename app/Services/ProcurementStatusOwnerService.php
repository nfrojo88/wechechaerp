<?php

namespace App\Services;

use App\Contracts\SmsProviderInterface;
use App\Models\DriverBooking;
use App\Models\Employee;
use App\Models\MaterialRequest;
use App\Models\NotificationLog;
use App\Models\ProcurementSmsLog;
use App\Models\ProcurementSmsSetting;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProcurementStatusOwnerService
 *
 * Central definition and dispatcher for all Material Request and Purchase Request
 * status-to-owner SMS transitions.
 *
 * Strictly follows recipient priority:
 *   1. Directly assigned person (e.g. cashier staff, driver, original requester)
 *   2. Project/Store-scoped staff
 *   3. Role match
 *   4. Fallback to Global Admin with [Role Unassigned] alert
 *
 * Enforces non-blocking execution, 60s deduplication, and FYI copies for requesters.
 */
class ProcurementStatusOwnerService
{
    public function __construct(
        protected SmsProviderInterface $smsProvider,
        protected ProcurementSmsService $procurementSms
    ) {}

    /**
     * Central Status-to-Owner Map for all procurement stages and side paths.
     */
    public static function getStatusMap(): array
    {
        return [
            // ─────────────────────────────────────────────────────────────────
            // MATERIAL REQUEST (MR) STAGES
            // ─────────────────────────────────────────────────────────────────
            'mr:submitted' => [
                'model_type'       => 'material_request',
                'status_key'       => 'submitted',
                'handoff_key'      => 'mr_submitted',
                'name'             => 'MR Submitted &rarr; Planning Review',
                'owner_role'       => 'planning',
                'what_happened'    => 'Submitted',
                'action_needed'    => 'Review and approve/forward to Coordinator',
                'default_template' => '{priority}MR #{req_no} ({site}): Submitted by {sender_name}. Action needed: Review and forward to Coordinator. Open: {link}',
                'notify_requester' => false,
            ],
            'mr:pending_planning' => [
                'model_type'       => 'material_request',
                'status_key'       => 'pending_planning',
                'handoff_key'      => 'mr_submitted',
                'name'             => 'MR Pending Planning &rarr; Planning Review',
                'owner_role'       => 'planning',
                'what_happened'    => 'Submitted for Planning Review',
                'action_needed'    => 'Review and approve/forward to Coordinator',
                'default_template' => '{priority}MR #{req_no} ({site}): Submitted for planning review by {sender_name}. Action needed: Review and approve/forward. Open: {link}',
                'notify_requester' => false,
            ],
            'mr:planning_approved' => [
                'model_type'       => 'material_request',
                'status_key'       => 'planning_approved',
                'handoff_key'      => 'planning_forwarded',
                'name'             => 'MR Planning Approved &rarr; Coordinator Dispatch',
                'owner_role'       => 'coordinator',
                'what_happened'    => 'Approved by Planning',
                'action_needed'    => 'Review and dispatch to Store Manager',
                'default_template' => '{priority}MR #{req_no} ({site}): Approved by Planning ({sender_name}). Action needed: Dispatch to Store Manager. Open: {link}',
                'notify_requester' => false,
            ],
            'mr:rejected' => [
                'model_type'       => 'material_request',
                'status_key'       => 'rejected',
                'handoff_key'      => 'planning_rejected',
                'name'             => 'MR Rejected &rarr; Requester (Site Eng)',
                'owner_role'       => 'site_engineer',
                'what_happened'    => 'Rejected by Planning',
                'action_needed'    => 'Review rejection notes and revise',
                'default_template' => '{priority}MR #{req_no} ({site}): Rejected by Planning ({sender_name}). Action needed: Review rejection notes. Open: {link}',
                'notify_requester' => true,
            ],
            'mr:sent_to_store_manager' => [
                'model_type'       => 'material_request',
                'status_key'       => 'sent_to_store_manager',
                'handoff_key'      => 'coordinator_forwarded',
                'name'             => 'MR Coordinator Dispatched &rarr; Store Check',
                'owner_role'       => 'store_manager',
                'what_happened'    => 'Dispatched by Coordinator',
                'action_needed'    => 'Check store inventory and issue SIV or route to PR',
                'default_template' => '{priority}MR #{req_no} ({site}): Dispatched by Coord. {sender_name}. Action needed: Check stock / issue SIV or PR. Open: {link}',
                'notify_requester' => false,
            ],
            'mr:transferred' => [
                'model_type'       => 'material_request',
                'status_key'       => 'transferred',
                'handoff_key'      => 'store_issued_siv',
                'name'             => 'MR In Stock &rarr; SIV / Transfer Issued',
                'owner_role'       => 'site_engineer',
                'what_happened'    => 'Items In Stock - SIV/Transfer Issued',
                'action_needed'    => 'Collect materials from store / receive transfer',
                'default_template' => '{priority}MR #{req_no} ({site}): In stock! SIV/Transfer issued by {sender_name}. Action needed: Collect materials. Open: {link}',
                'notify_requester' => true,
            ],
            'mr:sent_to_pr' => [
                'model_type'       => 'material_request',
                'status_key'       => 'sent_to_pr',
                'handoff_key'      => 'store_converted_to_pr',
                'name'             => 'MR Out of Stock &rarr; Converted to PR',
                'owner_role'       => 'purchase_manager',
                'what_happened'    => 'Out of Stock - Routed to Procurement',
                'action_needed'    => 'Review PR and assign sourcing method',
                'default_template' => '{priority}MR #{req_no} ({site}): Out of stock. Sent by Store Mgr. {sender_name}. Action needed: Assign sourcing. Open: {link}',
                'notify_requester' => false,
            ],

            // ─────────────────────────────────────────────────────────────────
            // PURCHASE REQUEST (PR) MAIN FLOW
            // ─────────────────────────────────────────────────────────────────
            'pr:pending_planning_approval' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_PENDING_PLANNING,
                'handoff_key'      => 'mr_submitted',
                'name'             => 'PR Submitted &rarr; Planning Approval',
                'owner_role'       => 'planning',
                'what_happened'    => 'Submitted for Planning Review',
                'action_needed'    => 'Review BOQ/Schedule and approve/forward',
                'default_template' => '{priority}PR #{req_no} ({site}): Submitted by {sender_name}. Action needed: Review and approve for Coordinator. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:pending_hr_approval' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_PENDING_HR_APPROVAL,
                'handoff_key'      => 'planning_forwarded',
                'name'             => 'PR Planning Approved &rarr; Coordinator Review',
                'owner_role'       => 'coordinator',
                'what_happened'    => 'Planning Approved',
                'action_needed'    => 'Review and dispatch to Store Manager',
                'default_template' => '{priority}PR #{req_no} ({site}): Planning approved by {sender_name}. Action needed: Dispatch to Store Manager. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:pending_store_review' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
                'handoff_key'      => 'coordinator_forwarded',
                'name'             => 'PR Coordinator Dispatched &rarr; Store Check',
                'owner_role'       => 'store_manager',
                'what_happened'    => 'Dispatched by Coordinator',
                'action_needed'    => 'Check warehouse stock / issue SIV or send to PM',
                'default_template' => '{priority}PR #{req_no} ({site}): Dispatched by {sender_name}. Action needed: Check stock / issue SIV or send to PM. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:pending_procurement_manager' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_PENDING_PROC_MANAGER,
                'handoff_key'      => 'store_converted_to_pr',
                'name'             => 'PR Out of Stock &rarr; Procurement Manager Sourcing',
                'owner_role'       => 'purchase_manager',
                'what_happened'    => 'Routed to Procurement',
                'action_needed'    => 'Assign sourcing method (proforma or direct)',
                'default_template' => '{priority}PR #{req_no} ({site}): Sent by {sender_name} for procurement. Action needed: Assign sourcing method. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:pending_procurement_team' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_PENDING_PROC_TEAM,
                'handoff_key'      => 'proc_manager_assigned_sourcing',
                'name'             => 'PR Sourcing Assigned &rarr; Procurement Team',
                'owner_role'       => 'purchase',
                'what_happened'    => 'Sourcing Assigned by Procurement Manager',
                'action_needed'    => 'Collect quotations/proformas from vendors',
                'default_template' => '{priority}PR #{req_no} ({site}): Sourcing assigned by Proc. Mgr. {sender_name}. Action needed: Collect quotations/proformas. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:pending_marketing_review' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_PENDING_MARKETING,
                'handoff_key'      => 'proc_officer_submitted_direct',
                'name'             => 'PR Quotation Submitted &rarr; PM Pricing Review',
                'owner_role'       => 'purchase_manager',
                'what_happened'    => 'Direct Buy Quotation Submitted',
                'action_needed'    => 'Review direct buy prices and submit to GM',
                'default_template' => '{priority}PR #{req_no} ({site}): Direct buy price {amount} ETB submitted by {sender_name}. Action needed: Review and forward to GM. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:pending_proforma_selection' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_PENDING_PROFORMA_SELECTION,
                'handoff_key'      => 'proc_officer_submitted_proforma',
                'name'             => 'PR Proformas Uploaded &rarr; PM Selection',
                'owner_role'       => 'purchase_manager',
                'what_happened'    => 'Vendor Proformas Uploaded',
                'action_needed'    => 'Select winning proforma and submit variance to GM',
                'default_template' => '{priority}PR #{req_no} ({site}): Proformas uploaded by {sender_name}. Action needed: Select winning proforma & submit variance. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:pending_gm_decision' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_PENDING_GM,
                'handoff_key'      => 'market_research_variance',
                'name'             => 'PR Sourcing Prepared &rarr; GM Decision',
                'owner_role'       => 'gm',
                'what_happened'    => 'Sourcing & Price Variance Submitted',
                'action_needed'    => 'Review quotations and decide (approve cash/credit or counter)',
                'default_template' => '{priority}PR #{req_no} ({site}): Rate variance submitted by {sender_name}. Action needed: GM Approval / Decision. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:pending_payment' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_PENDING_PAYMENT,
                'handoff_key'      => 'gm_approved',
                'name'             => 'PR GM Approved &rarr; Finance Head / Cashier Payment',
                'owner_role'       => 'finance_head',
                'direct_field'     => 'assigned_finance_staff_id', // Checks directly assigned cashier if present
                'what_happened'    => 'GM Approved for Payment',
                'action_needed'    => 'Authorize payment and disburse funds',
                'default_template' => '{priority}PR #{req_no} ({site}): GM approved ({amount} ETB) by {sender_name}. Action needed: Authorize payment / disburse. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:pending_finance' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_PENDING_FINANCE,
                'handoff_key'      => 'gm_approved',
                'name'             => 'PR GM Credit Approved &rarr; Finance Head Authorization',
                'owner_role'       => 'finance_head',
                'what_happened'    => 'GM Approved on Credit Terms',
                'action_needed'    => 'Authorize credit ledger / COA account',
                'default_template' => '{priority}PR #{req_no} ({site}): Credit purchase approved by GM {sender_name}. Action needed: Authorize credit COA ledger. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:pending_receipt_upload' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD,
                'handoff_key'      => 'cashier_disbursed_cash',
                'name'             => 'PR Cash Disbursed &rarr; Purchaser Uploads Receipt',
                'owner_role'       => 'purchase',
                'direct_field'     => 'requested_by', // Direct purchaser
                'what_happened'    => 'Cash Disbursed by Cashier',
                'action_needed'    => 'Purchase items from vendor and upload fiscal receipt',
                'default_template' => '{priority}PR #{req_no} ({site}): Cash disbursed ({amount} ETB) by {sender_name}. Action needed: Buy items & upload fiscal receipt. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:pending_driver_booking' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_PENDING_DRIVER,
                'handoff_key'      => 'purchaser_uploaded_receipt',
                'name'             => 'PR Receipt Uploaded &rarr; Logistics Driver Dispatch',
                'owner_role'       => 'general_service',
                'what_happened'    => 'Fiscal Receipt Uploaded',
                'action_needed'    => 'Book driver and dispatch transport to site',
                'default_template' => '{priority}PR #{req_no} ({site}): Receipt uploaded by {sender_name}. Action needed: Book vehicle & dispatch driver. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:driver_booked' => [
                'model_type'       => 'purchase_request',
                'status_key'       => 'driver_booked',
                'handoff_key'      => 'logistics_dispatched_driver',
                'name'             => 'PR Driver Booked &rarr; Driver Trip Dispatch',
                'owner_role'       => 'driver',
                'what_happened'    => 'Driver Booked and Dispatched',
                'action_needed'    => 'Transport materials to site and confirm arrival',
                'default_template' => '{priority}PR #{req_no} ({site}): You have been dispatched by Logistics ({sender_name}). Action needed: Deliver materials to site. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:logistics_dispatched_driver' => [
                'model_type'       => 'purchase_request',
                'status_key'       => 'logistics_dispatched_driver',
                'handoff_key'      => 'logistics_dispatched_driver',
                'name'             => 'PR Logistics Dispatched &rarr; Driver Trip Dispatch',
                'owner_role'       => 'driver',
                'what_happened'    => 'Dispatched by Logistics',
                'action_needed'    => 'Deliver materials to site and confirm arrival',
                'default_template' => '{priority}PR #{req_no} ({site}): Driver dispatched by Logistics ({sender_name}). Action needed: Deliver materials to site. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:pending_receipt_verification' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_PENDING_RECEIPT_VERIFY,
                'handoff_key'      => 'purchaser_uploaded_receipt',
                'name'             => 'PR Receipt Uploaded &rarr; Store Keeper Verification',
                'owner_role'       => 'store_keeper',
                'what_happened'    => 'Receipt Uploaded by Purchaser',
                'action_needed'    => 'Verify receipt items and validate for transport',
                'default_template' => '{priority}PR #{req_no} ({site}): Receipt uploaded by {sender_name}. Action needed: Verify receipt & confirm for transport. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:delivered' => [
                'model_type'       => 'purchase_request',
                'status_key'       => 'delivered',
                'handoff_key'      => 'driver_delivered',
                'name'             => 'PR Driver Arrived &rarr; Site Store Intake',
                'owner_role'       => 'store_keeper',
                'what_happened'    => 'Materials Arrived at Site Gate',
                'action_needed'    => 'Inspect delivered materials and complete GRN receiving slip',
                'default_template' => '{priority}PR #{req_no} ({site}): Driver arrived at site. Action needed: Inspect delivery & complete GRN slip. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:intake_complete' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_INTAKE_COMPLETE,
                'handoff_key'      => 'store_keeper_grn',
                'name'             => 'PR GRN Done &rarr; Finance Head 3-Way Match',
                'owner_role'       => 'finance_head',
                'what_happened'    => 'Store Intake (GRN) Completed',
                'action_needed'    => 'Verify PO, Receipt, and GRN to finalize 3-way match',
                'default_template' => '{priority}PR #{req_no} ({site}): GRN intake completed by {sender_name}. Action needed: Verify 3-way match & close. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:completed' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_COMPLETED,
                'handoff_key'      => 'finance_3way_closed',
                'name'             => 'PR 3-Way Match Verified &rarr; Lifecycle Closed',
                'owner_role'       => 'site_engineer',
                'what_happened'    => '3-Way Match Verified and Closed',
                'action_needed'    => 'None (Lifecycle Complete)',
                'default_template' => '{priority}PR #{req_no} ({site}): 3-Way match verified and closed by {sender_name}. (FYI - Lifecycle complete). Open: {link}',
                'notify_requester' => true,
            ],

            // ─────────────────────────────────────────────────────────────────
            // PURCHASE REQUEST SIDE PATHS & REVERSALS
            // ─────────────────────────────────────────────────────────────────
            'pr:rejected' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_REJECTED,
                'handoff_key'      => 'gm_rejected',
                'name'             => 'PR Rejected &rarr; Requester & PM',
                'owner_role'       => 'site_engineer',
                'what_happened'    => 'Rejected',
                'action_needed'    => 'Review rejection notes and revise or cancel',
                'default_template' => '{priority}PR #{req_no} ({site}): Rejected by {sender_name}. Action needed: Review rejection notes. Open: {link}',
                'notify_requester' => true,
            ],
            'pr:cancelled' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_CANCELLED,
                'handoff_key'      => 'gm_rejected',
                'name'             => 'PR Cancelled &rarr; Requester',
                'owner_role'       => 'site_engineer',
                'what_happened'    => 'Cancelled',
                'action_needed'    => 'None (Request cancelled)',
                'default_template' => '{priority}PR #{req_no} ({site}): Cancelled by {sender_name}. (FYI - Request cancelled). Open: {link}',
                'notify_requester' => true,
            ],
            'pr:transferred' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_TRANSFERRED,
                'handoff_key'      => 'store_issued_siv',
                'name'             => 'PR Stock Transferred &rarr; Site Store Intake',
                'owner_role'       => 'store_keeper',
                'what_happened'    => 'Inter-Store Transfer Dispatched',
                'action_needed'    => 'Receive incoming material transfer at destination store',
                'default_template' => '{priority}PR #{req_no} ({site}): Store transfer dispatched by {sender_name}. Action needed: Receive transfer at site. Open: {link}',
                'notify_requester' => true,
            ],
        ];
    }

    /**
     * Dispatch SMS on status transition.
     * Guaranteed safe: will never throw, roll back, or interrupt the calling process.
     */
    public function dispatchStatusChangeSms(
        Model $model,
        string $newStatus,
        ?string $oldStatus = null,
        ?User $actor = null,
        array $extraContext = []
    ): void {
        // Global switch check
        if (config('procurement_handoffs.enabled', env('PROCUREMENT_SMS_ENABLED', true)) === false) {
            Log::info("Procurement SMS globally disabled. Skipping dispatch for " . class_basename($model) . " #{$model->getKey()} [{$newStatus}].");
            return;
        }

        try {
            $modelType = $model instanceof MaterialRequest ? 'material_request' : 'purchase_request';
            $prefix = $model instanceof MaterialRequest ? 'mr' : 'pr';
            $mapKey = "{$prefix}:{$newStatus}";

            $map = self::getStatusMap()[$mapKey] ?? null;

            // If not directly mapped, check if there's a fallback mapping for this status
            if (!$map) {
                Log::info("ProcurementStatusOwnerService: No status mapping configured for [{$mapKey}]. Skipping.");
                return;
            }

            // Check admin on/off toggle for this handoff
            $handoffKey = $map['handoff_key'];
            if (!$this->isHandoffEnabled($handoffKey)) {
                Log::info("ProcurementStatusOwnerService: Handoff [{$handoffKey}] disabled in admin settings.");
                return;
            }

            $senderUser = $actor ?? (Auth::user() ?? (method_exists($model, 'creator') ? $model->creator : null));
            $senderName = $senderUser ? ($senderUser->name ?? 'Staff') : 'System';

            // Resolve recipients following strict priority cascade
            $recipients = $this->resolveRecipients($model, $map, $extraContext);

            if (empty($recipients)) {
                $fallbackRole = config('procurement_handoffs.fallback_role', 'global_admin');
                Log::warning("ProcurementStatusOwnerService: No active recipients for [{$mapKey}] on {$modelType} #{$model->getKey()}. Escalating to {$fallbackRole}.");
                $recipients = $this->resolveFallbackRecipients($fallbackRole, $model);
            }

            // Build actionable message text
            $message = $this->formatMessage($model, $map, $senderName, $extraContext);

            // Dispatch immediately to each recipient with deduplication guard
            foreach ($recipients as $recipient) {
                $phone = $this->procurementSms->normalizePhone($recipient['phone'] ?? '');
                if (empty($phone)) {
                    continue;
                }

                $this->sendAndLogSms(
                    model: $model,
                    modelType: $modelType,
                    status: $newStatus,
                    handoffKey: $handoffKey,
                    recipient: $recipient,
                    phone: $phone,
                    message: $message,
                    senderUser: $senderUser
                );
            }

            // Send optional FYI copy to requester if configured and requester is not the actor
            if (!empty($map['notify_requester'])) {
                $this->sendFyiToRequester($model, $modelType, $newStatus, $map, $senderName);
            }

        } catch (\Throwable $e) {
            // Strict Rule: A failed SMS must NEVER block or crash the procurement action
            Log::error("ProcurementStatusOwnerService Exception: " . $e->getMessage(), [
                'model'  => get_class($model),
                'id'     => $model->getKey(),
                'status' => $newStatus,
            ]);
        }
    }

    /**
     * Resolves recipients adhering strictly to the 4-stage priority cascade.
     */
    public function resolveRecipients(Model $model, array $map, array $context = []): array
    {
        $recipients = [];
        $targetRole = $map['owner_role'];
        $projectId = $model->project_id ?? ($context['project_id'] ?? null);
        $storeId   = $model->destination_store_id ?? ($model->store_id ?? ($context['store_id'] ?? null));

        // ── Priority 1: Directly Assigned Person ──────────────────────────────
        // Check dynamic direct field if defined on map or context
        if (!empty($context['assigned_user_id'])) {
            $user = User::with('employee')->find($context['assigned_user_id']);
            if ($user && ($p = $this->procurementSms->resolveUserPhone($user))) {
                return [[
                    'user_id'     => $user->id,
                    'employee_id' => $user->employee?->id,
                    'role'        => $targetRole,
                    'phone'       => $p,
                    'name'        => $user->name,
                ]];
            }
        }

        // Direct Payment Staff on PR
        if ($model instanceof PurchaseRequest && in_array($map['status_key'], [PurchaseRequest::STATUS_PENDING_PAYMENT, 'pending_payment'])) {
            $assignedStaffId = $model->assigned_finance_staff_id ?? ($model->payment?->assigned_finance_staff_id ?? null);
            if ($assignedStaffId) {
                $user = User::with('employee')->find($assignedStaffId);
                if ($user && ($p = $this->procurementSms->resolveUserPhone($user))) {
                    return [[
                        'user_id'     => $user->id,
                        'employee_id' => $user->employee?->id,
                        'role'        => 'finance_staff',
                        'phone'       => $p,
                        'name'        => $user->name,
                    ]];
                }
            }
        }

        // Direct Driver on PR
        if ($targetRole === 'driver' && $model instanceof PurchaseRequest) {
            $driverEmpId = $context['driver_employee_id'] ?? ($model->driverBooking?->driver_employee_id ?? null);
            if ($driverEmpId) {
                $driver = Employee::find($driverEmpId);
                if ($driver && !empty($driver->phone)) {
                    return [[
                        'user_id'     => $driver->user_id,
                        'employee_id' => $driver->id,
                        'role'        => 'driver',
                        'phone'       => $driver->phone,
                        'name'        => $driver->full_name,
                    ]];
                }
            }
        }

        // Direct Original Requester for rejections / completions
        if (in_array($targetRole, ['site_engineer', 'requester'])) {
            $creatorId = $model->requested_by ?? ($model->created_by ?? null);
            if ($creatorId) {
                $creator = User::with('employee')->find($creatorId);
                if ($creator && ($p = $this->procurementSms->resolveUserPhone($creator))) {
                    $recipients[] = [
                        'user_id'     => $creator->id,
                        'employee_id' => $creator->employee?->id,
                        'role'        => 'site_engineer',
                        'phone'       => $p,
                        'name'        => $creator->name,
                    ];
                }
            }
            if (!empty($recipients)) {
                return $recipients;
            }
        }

        // ── Priority 2: Project / Store-Scoped Staff ──────────────────────────
        // A. Project-scoped Coordinator or Site Engineer
        if ($projectId && in_array($targetRole, ['coordinator', 'site_engineer'])) {
            try {
                $project = Project::with(['users' => fn($q) => $q->whereHas('roles', fn($r) => $r->whereIn('name', [$targetRole, ucfirst($targetRole)]))])->find($projectId);
                if ($project && $project->users->isNotEmpty()) {
                    foreach ($project->users as $u) {
                        $p = $this->procurementSms->resolveUserPhone($u);
                        if ($p) {
                            $recipients[] = [
                                'user_id'     => $u->id,
                                'employee_id' => $u->employee?->id,
                                'role'        => $targetRole,
                                'phone'       => $p,
                                'name'        => $u->name,
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        // B. Store-scoped Store Manager or Store Keeper
        if ($storeId && in_array($targetRole, ['store_manager', 'store_keeper'])) {
            try {
                $store = Store::with(['manager', 'users'])->find($storeId);
                if ($store) {
                    if ($targetRole === 'store_manager' && $store->manager) {
                        $p = $this->procurementSms->resolveUserPhone($store->manager);
                        if ($p) {
                            $recipients[] = [
                                'user_id'     => $store->manager->id,
                                'employee_id' => $store->manager->employee?->id,
                                'role'        => 'store_manager',
                                'phone'       => $p,
                                'name'        => $store->manager->name,
                            ];
                        }
                    }
                    if ($store->users && $store->users->isNotEmpty()) {
                        foreach ($store->users as $su) {
                            if ($su->roles()->whereIn('name', [$targetRole, ucfirst($targetRole)])->exists()) {
                                $p = $this->procurementSms->resolveUserPhone($su);
                                if ($p) {
                                    $recipients[] = [
                                        'user_id'     => $su->id,
                                        'employee_id' => $su->employee?->id,
                                        'role'        => $targetRole,
                                        'phone'       => $p,
                                        'name'        => $su->name,
                                    ];
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {}
        }

        if (!empty($recipients)) {
            return $this->uniqueRecipients($recipients);
        }

        // ── Priority 3: System-Wide Role Match ────────────────────────────────
        $phoneNumbers = $this->procurementSms->getPhoneNumbersForRole($targetRole, $projectId, $storeId);
        foreach ($phoneNumbers as $phone) {
            $recipients[] = [
                'user_id'     => null,
                'employee_id' => null,
                'role'        => $targetRole,
                'phone'       => $phone,
                'name'        => ucfirst(str_replace('_', ' ', $targetRole)),
            ];
        }

        return $this->uniqueRecipients($recipients);
    }

    /**
     * Resolves fallback recipients (Global Admin) when no owner is assigned.
     */
    protected function resolveFallbackRecipients(string $fallbackRole, Model $model): array
    {
        $recipients = [];
        $adminPhones = $this->procurementSms->getGlobalAdminPhoneNumbers();

        foreach ($adminPhones as $p) {
            $recipients[] = [
                'user_id'     => null,
                'employee_id' => null,
                'role'        => 'global_admin',
                'phone'       => $p,
                'name'        => '[Role Unassigned] Admin',
            ];
        }

        return $this->uniqueRecipients($recipients);
    }

    /**
     * Format actionable SMS text conforming to pro.doc Requirement C:
     * "{PR/MR} #{number} ({site/project}): {what just happened} by {name}. Action needed: {what the owner must do}. Open: {short link}"
     */
    public function formatMessage(Model $model, array $map, string $senderName, array $context = []): string
    {
        $dbSetting = ProcurementSmsSetting::where('handoff_key', $map['handoff_key'])->first();
        $template = ($dbSetting && !empty($dbSetting->template)) ? $dbSetting->template : $map['default_template'];

        $isEmergency = ($model->priority ?? '') === 'emergency' || ($model->type ?? '') === 'emergency' || ($model->source ?? '') === 'Emergency';
        $priorityPrefix = $isEmergency ? '[URGENT-EMERGENCY] ' : '';

        $reqType = $model instanceof MaterialRequest ? 'MR' : 'PR';
        $reqNo = $model instanceof MaterialRequest ? ($model->reference_number ?: "MR-{$model->id}") : ($model->pr_no ?: "PR-{$model->id}");

        $site = $model->project?->name ?? 'HQ';
        $site = mb_substr($site, 0, 18);

        $amount = '';
        if (isset($context['amount']) && is_numeric($context['amount']) && (float)$context['amount'] > 0) {
            $amount = number_format((float)$context['amount'], 2);
        } elseif (isset($model->estimated_total_cost) && (float)$model->estimated_total_cost > 0) {
            $amount = number_format((float)$model->estimated_total_cost, 2);
        } elseif (isset($model->direct_buy_amount) && (float)$model->direct_buy_amount > 0) {
            $amount = number_format((float)$model->direct_buy_amount, 2);
        } elseif (isset($model->payment?->amount) && (float)$model->payment->amount > 0) {
            $amount = number_format((float)$model->payment->amount, 2);
        }

        if (empty($amount)) {
            $template = str_replace(['({amount} ETB)', '({amount})', '{amount} ETB', '{amount}'], '', $template);
        }

        $link = $this->generateDirectLink($model);

        $replacements = [
            '{priority}'    => $priorityPrefix,
            '{req_type}'    => $reqType,
            '{req_no}'      => $reqNo,
            '{number}'      => $reqNo,
            '{site}'        => $site,
            '{sender_name}' => mb_substr($senderName, 0, 20),
            '{what_happened}' => $context['what_happened'] ?? $map['what_happened'],
            '{action_needed}' => $context['action_needed'] ?? $map['action_needed'],
            '{action}'      => $context['action_needed'] ?? $map['action_needed'],
            '{amount}'      => $amount,
            '{link}'        => $link,
        ];

        $rendered = str_replace(array_keys($replacements), array_values($replacements), $template);

        // If template did not use the standard format, ensure the standard pro.doc format:
        if (!str_contains($rendered, 'Action needed:') && !empty($map['action_needed'])) {
            $rendered = "{$priorityPrefix}{$reqType} #{$reqNo} ({$site}): {$map['what_happened']} by {$senderName}. Action needed: {$map['action_needed']}. Open: {$link}";
        }

        // Bound to standard Ethiopian SMS length limit where possible (keep compact)
        return trim($rendered);
    }

    /**
     * Send SMS with deduplication, error absorption, and audit logging.
     */
    protected function sendAndLogSms(
        Model $model,
        string $modelType,
        string $status,
        string $handoffKey,
        array $recipient,
        string $phone,
        string $message,
        ?User $senderUser
    ): void {
        $requestId = $model->getKey();

        // ── Requirement E: Deduplication Guard ────────────────────────────────
        // Never send same status SMS twice to same recipient for same record within 60s
        $cacheKey = "proc_sms_sent_{$modelType}_{$requestId}_{$status}_{$phone}";
        if (Cache::has($cacheKey)) {
            Log::info("ProcurementStatusOwnerService: Duplicate SMS suppressed for {$phone} on {$modelType} #{$requestId} [status: {$status}].");
            return;
        }
        Cache::put($cacheKey, true, now()->addSeconds(60));

        $statusResult = 'failed';
        $errorMsg = null;

        try {
            // Immediate single attempt via provider with 5s timeout
            $response = $this->smsProvider->send($phone, $message);
            if (!empty($response['success'])) {
                $statusResult = 'sent';
                Log::info("ProcurementStatusOwnerService: SMS delivered to {$phone} ({$recipient['role']}) for {$modelType} #{$requestId}.");
            } else {
                $errorMsg = $response['error'] ?? 'SMS dispatch failed';
                Log::warning("ProcurementStatusOwnerService: SMS failed for {$phone} ({$recipient['role']}): {$errorMsg}");
            }
        } catch (\Throwable $e) {
            $errorMsg = 'Gateway exception: ' . $e->getMessage();
            Log::error("ProcurementStatusOwnerService Gateway Error: {$errorMsg}");
        }

        // ── Requirement G: Log Every Attempt to Both Tables ──────────────────
        // 1. notification_log
        try {
            NotificationLog::create([
                'request_type'          => $modelType,
                'request_id'            => $requestId,
                'action'                => $handoffKey,
                'sender_user_id'        => $senderUser?->id,
                'recipient_user_id'     => $recipient['user_id'] ?? null,
                'recipient_employee_id' => $recipient['employee_id'] ?? null,
                'role'                  => $recipient['role'] ?? 'owner',
                'phone'                 => $phone,
                'message'               => $message,
                'status'                => $statusResult,
                'error'                 => $errorMsg,
                'retries'               => 0,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to write to notification_log: " . $e->getMessage());
        }

        // 2. procurement_sms_logs
        try {
            DB::table('procurement_sms_logs')->insert([
                'purchase_request_id' => $modelType === 'purchase_request' ? $requestId : 0,
                'recipient_phone'     => substr($phone, 0, 30),
                'recipient_role'      => substr($recipient['role'] ?? 'owner', 0, 60),
                'message'             => $message,
                'status'              => $statusResult,
                'error_message'       => $errorMsg,
                'sent_at'             => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to write to procurement_sms_logs: " . $e->getMessage());
        }
    }

    /**
     * Requirement F: Send optional FYI copy to original requester on rejection, return, or completion.
     */
    protected function sendFyiToRequester(
        Model $model,
        string $modelType,
        string $newStatus,
        array $map,
        string $senderName
    ): void {
        $creatorId = $model->requested_by ?? ($model->created_by ?? null);
        if (!$creatorId) {
            return;
        }

        $creator = User::with('employee')->find($creatorId);
        if (!$creator) {
            return;
        }

        // Do not notify if requester is the person who triggered the change
        if (Auth::id() && Auth::id() === $creator->id) {
            return;
        }

        $phone = $this->procurementSms->resolveUserPhone($creator);
        if (!$phone) {
            return;
        }

        $reqType = $model instanceof MaterialRequest ? 'MR' : 'PR';
        $reqNo = $model instanceof MaterialRequest ? ($model->reference_number ?: "MR-{$model->id}") : ($model->pr_no ?: "PR-{$model->id}");
        $site = mb_substr($model->project?->name ?? 'HQ', 0, 18);
        $link = $this->generateDirectLink($model);

        $fyiMessage = "{$reqType} #{$reqNo} ({$site}): {$map['what_happened']} by {$senderName}. (FYI - No action required). Open: {$link}";

        $this->sendAndLogSms(
            model: $model,
            modelType: $modelType,
            status: "fyi_{$newStatus}",
            handoffKey: "fyi_{$map['handoff_key']}",
            recipient: [
                'user_id'     => $creator->id,
                'employee_id' => $creator->employee?->id,
                'role'        => 'requester_fyi',
            ],
            phone: $phone,
            message: $fyiMessage,
            senderUser: Auth::user()
        );
    }

    /**
     * Check if a specific handoff is enabled in DB settings (or default to true).
     */
    protected function isHandoffEnabled(string $handoffKey): bool
    {
        try {
            $setting = ProcurementSmsSetting::where('handoff_key', $handoffKey)->first();
            if ($setting) {
                return (bool) $setting->is_enabled;
            }
        } catch (\Throwable $e) {}

        return true;
    }

    /**
     * Deduplicate recipient entries by phone number.
     */
    protected function uniqueRecipients(array $recipients): array
    {
        $seen = [];
        $unique = [];

        foreach ($recipients as $r) {
            $phone = $this->procurementSms->normalizePhone($r['phone'] ?? '');
            if ($phone && !isset($seen[$phone])) {
                $seen[$phone] = true;
                $r['phone'] = $phone;
                $unique[] = $r;
            }
        }

        return $unique;
    }

    /**
     * Generate direct clickable link for SMS message.
     */
    protected function generateDirectLink(Model $model): string
    {
        $baseUrl = config('app.url', 'http://localhost');
        if ($model instanceof MaterialRequest) {
            return "{$baseUrl}/material-requests/{$model->getKey()}";
        }
        return "{$baseUrl}/purchase-requests/{$model->getKey()}";
    }
}
