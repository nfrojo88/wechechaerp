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
 *   1. Directly assigned person (assigned_finance_staff_id, driver_employee_id, requested_by, assigned coordinator)
 *   2. Project/Store-scoped staff (target store's store manager, project-assigned coordinator)
 *   3. Role match (planning, coordinator, store_manager, purchase_manager, purchase, general_service, gm, finance_head, store_keeper)
 *   4. Fallback to Global Admin with warning log
 *
 * Non-blocking execution, 60s deduplication, 5s gateway timeout, comprehensive audit logging.
 */
class ProcurementStatusOwnerService
{
    public function __construct(
        protected SmsProviderInterface $smsProvider,
        protected ProcurementSmsService $procurementSms
    ) {}

    /**
     * Source of truth: Audit table rows 1-35 covering the entire Procurement Lifecycle.
     */
    public static function getAuditStatuses(): array
    {
        return [
            // ── Section 4: Material Request (MR: Rows 1 - 11) ─────────────────
            1 => [
                'row'               => 1,
                'model_type'        => 'material_request',
                'status_key'        => 'submitted',
                'map_key'           => 'mr:submitted',
                'event'             => 'Site Engineer creates MR (normal or EMERGENCY)',
                'who_acts'          => 'Site Engineer',
                'owner_role'        => 'planning',
                'what_happened'     => 'Submitted',
                'action_needed'     => 'Review and approve/forward to Coordinator',
                'meaning'           => 'New MR waiting for planning approval. Emergency = [URGENT-EMERGENCY]',
            ],
            2 => [
                'row'               => 2,
                'model_type'        => 'material_request',
                'status_key'        => 'planning_approved',
                'map_key'           => 'mr:planning_approved',
                'event'             => 'Planning APPROVES',
                'who_acts'          => 'Planning Manager',
                'owner_role'        => 'coordinator',
                'what_happened'     => 'Approved by Planning',
                'action_needed'     => 'Dispatch to Store Manager',
                'meaning'           => 'MR approved, forward to store',
            ],
            3 => [
                'row'               => 3,
                'model_type'        => 'material_request',
                'status_key'        => 'rejected',
                'map_key'           => 'mr:rejected',
                'event'             => 'Planning REJECTS',
                'who_acts'          => 'Planning Manager',
                'owner_role'        => 'site_engineer',
                'what_happened'     => 'Rejected by Planning',
                'action_needed'     => 'Review rejection notes and revise',
                'meaning'           => 'MR rejected + reason',
            ],
            4 => [
                'row'               => 4,
                'model_type'        => 'material_request',
                'status_key'        => 'approved_for_petty_cash',
                'map_key'           => 'mr:approved_for_petty_cash',
                'event'             => 'Planning says "Buy with petty cash from site"',
                'who_acts'          => 'Planning Manager',
                'owner_role'        => 'site_engineer',
                'what_happened'     => 'Approved for Site Petty Cash',
                'action_needed'     => 'Purchase via site petty cash',
                'meaning'           => 'Approved for site petty-cash purchase, go buy it',
            ],
            5 => [
                'row'               => 5,
                'model_type'        => 'material_request',
                'status_key'        => 'sent_to_store_manager',
                'map_key'           => 'mr:sent_to_store_manager',
                'event'             => 'Coordinator SENDS TO STORE MANAGER',
                'who_acts'          => 'Coordinator',
                'owner_role'        => 'store_manager',
                'what_happened'     => 'Dispatched by Coordinator',
                'action_needed'     => 'Check store stock and issue SIV or PR',
                'meaning'           => 'MR waiting for stock check',
            ],
            6 => [
                'row'               => 6,
                'model_type'        => 'material_request',
                'status_key'        => 'store_rejected',
                'map_key'           => 'mr:store_rejected',
                'event'             => 'Store Manager REJECTS',
                'who_acts'          => 'Store Manager',
                'owner_role'        => 'planning',
                'what_happened'     => 'Rejected by Store Manager',
                'action_needed'     => 'Review store rejection and re-plan',
                'meaning'           => 'MR rejected by store + reason',
            ],
            7 => [
                'row'               => 7,
                'model_type'        => 'material_request',
                'status_key'        => 'sent_to_pr',
                'map_key'           => 'mr:sent_to_pr',
                'event'             => 'Store Manager says "Send to Purchase" (out of stock)',
                'who_acts'          => 'Store Manager',
                'owner_role'        => 'purchase_manager',
                'what_happened'     => 'Out of Stock - Routed to PR',
                'action_needed'     => 'Assign procurement sourcing method',
                'meaning'           => 'MR converted to PR, needs procurement decision',
            ],
            8 => [
                'row'               => 8,
                'model_type'        => 'material_request',
                'status_key'        => 'transferred',
                'map_key'           => 'mr:transferred',
                'event'             => 'Store Manager says "Create Transfer" (SIV issued)',
                'who_acts'          => 'Store Manager',
                'owner_role'        => 'general_service',
                'what_happened'     => 'Transfer Created / SIV Issued',
                'action_needed'     => 'Assign driver for material transport',
                'meaning'           => 'Transfer needs a driver',
            ],
            9 => [
                'row'               => 9,
                'model_type'        => 'material_request',
                'status_key'        => 'proc_manager_assigned',
                'map_key'           => 'mr:proc_manager_assigned',
                'event'             => 'Procurement Manager sends to PR Team',
                'who_acts'          => 'Procurement Manager',
                'owner_role'        => 'purchase',
                'what_happened'     => 'Sourcing Assigned by Procurement Manager',
                'action_needed'     => 'Start proforma / direct buy sourcing',
                'meaning'           => 'PR assigned, start proforma / direct buy',
            ],
            10 => [
                'row'               => 10,
                'model_type'        => 'material_request',
                'status_key'        => 'proc_manager_returned',
                'map_key'           => 'mr:proc_manager_returned',
                'event'             => 'Procurement Manager SENDS BACK to Store Manager',
                'who_acts'          => 'Procurement Manager',
                'owner_role'        => 'store_manager',
                'what_happened'     => 'PR Returned by Procurement Manager',
                'action_needed'     => 'Review returned request notes',
                'meaning'           => 'PR returned, with reason',
            ],
            11 => [
                'row'               => 11,
                'model_type'        => 'material_request',
                'status_key'        => 'cancelled',
                'map_key'           => 'mr:cancelled',
                'event'             => 'MR deleted / cancelled',
                'who_acts'          => 'Requester or Admin',
                'owner_role'        => 'site_engineer',
                'what_happened'     => 'Cancelled',
                'action_needed'     => 'None (MR Cancelled)',
                'meaning'           => 'MR cancelled',
            ],

            // ── Section 5: Purchase Request (PR: Rows 12 - 35) ────────────────
            12 => [
                'row'               => 12,
                'model_type'        => 'purchase_request',
                'status_key'        => PurchaseRequest::STATUS_PENDING_PLANNING,
                'map_key'           => 'pr:pending_planning_approval',
                'event'             => 'PR submitted',
                'who_acts'          => 'Requester',
                'owner_role'        => 'planning',
                'what_happened'     => 'Submitted for Planning Review',
                'action_needed'     => 'Review and approve/forward to Coordinator',
                'meaning'           => 'PR submitted -> Planning',
            ],
            13 => [
                'row'               => 13,
                'model_type'        => 'purchase_request',
                'status_key'        => PurchaseRequest::STATUS_PENDING_HR_APPROVAL,
                'map_key'           => 'pr:pending_hr_approval',
                'event'             => 'Planning approves',
                'who_acts'          => 'Planning Manager',
                'owner_role'        => 'coordinator',
                'what_happened'     => 'Approved by Planning',
                'action_needed'     => 'Dispatch to Store Manager',
                'meaning'           => 'Planning approves -> Coordinator',
            ],
            14 => [
                'row'               => 14,
                'model_type'        => 'purchase_request',
                'status_key'        => PurchaseRequest::STATUS_PENDING_STORE_REVIEW,
                'map_key'           => 'pr:pending_store_review',
                'event'             => 'Coordinator approves',
                'who_acts'          => 'Coordinator',
                'owner_role'        => 'store_manager',
                'what_happened'     => 'Dispatched by Coordinator',
                'action_needed'     => 'Check warehouse stock / route to PM',
                'meaning'           => 'Coordinator approves -> Store Manager',
            ],
            15 => [
                'row'               => 15,
                'model_type'        => 'purchase_request',
                'status_key'        => PurchaseRequest::STATUS_PENDING_PROC_MANAGER,
                'map_key'           => 'pr:pending_procurement_manager',
                'event'             => 'Store sends to procurement',
                'who_acts'          => 'Store Manager',
                'owner_role'        => 'purchase_manager',
                'what_happened'     => 'Routed to Procurement',
                'action_needed'     => 'Assign sourcing method (proforma or direct buy)',
                'meaning'           => 'Store sends to procurement -> Procurement Manager',
            ],
            16 => [
                'row'               => 16,
                'model_type'        => 'purchase_request',
                'status_key'        => PurchaseRequest::STATUS_PENDING_PROC_TEAM,
                'map_key'           => 'pr:pending_procurement_team',
                'event'             => 'Procurement Manager sends to team',
                'who_acts'          => 'Procurement Manager',
                'owner_role'        => 'purchase',
                'what_happened'     => 'Sourcing Assigned by Procurement Manager',
                'action_needed'     => 'Collect quotations/proformas from vendors',
                'meaning'           => 'Procurement Manager sends to team -> Procurement Team',
            ],
            17 => [
                'row'               => 17,
                'model_type'        => 'purchase_request',
                'status_key'        => PurchaseRequest::STATUS_PENDING_MARKETING,
                'map_key'           => 'pr:pending_marketing_review',
                'event'             => 'Direct Buy submitted',
                'who_acts'          => 'Procurement Officer',
                'owner_role'        => 'purchase_manager',
                'what_happened'     => 'Direct Buy Quotation Submitted',
                'action_needed'     => 'Review direct buy prices and submit to GM',
                'meaning'           => 'Direct Buy submitted -> Procurement Manager',
            ],
            18 => [
                'row'               => 18,
                'model_type'        => 'purchase_request',
                'status_key'        => PurchaseRequest::STATUS_PENDING_PROFORMA_SELECTION,
                'map_key'           => 'pr:pending_proforma_selection',
                'event'             => 'Proforma submitted',
                'who_acts'          => 'Procurement Officer',
                'owner_role'        => 'purchase_manager',
                'what_happened'     => 'Vendor Proformas Uploaded',
                'action_needed'     => 'Select winning proforma and submit variance to GM',
                'meaning'           => 'Proforma submitted -> Procurement Manager',
            ],
            19 => [
                'row'               => 19,
                'model_type'        => 'purchase_request',
                'status_key'        => PurchaseRequest::STATUS_PENDING_GM,
                'map_key'           => 'pr:pending_gm_decision',
                'event'             => 'Variance / proformas sent to GM',
                'who_acts'          => 'Procurement Manager',
                'owner_role'        => 'gm',
                'what_happened'     => 'Sourcing & Price Variance Submitted',
                'action_needed'     => 'Review quotations and decide (approve cash/credit or counter)',
                'meaning'           => 'Variance / proformas sent to GM -> GM',
            ],
            20 => [
                'row'               => 20,
                'model_type'        => 'purchase_request',
                'status_key'        => 'gm_approved_cash',
                'map_key'           => 'pr:gm_approved_cash',
                'event'             => 'GM approves cash',
                'who_acts'          => 'General Manager',
                'owner_role'        => 'finance_head',
                'what_happened'     => 'GM Approved for Cash Payment',
                'action_needed'     => 'Authorize payment and assign cashier',
                'meaning'           => 'GM approves cash -> Finance Head',
            ],
            21 => [
                'row'               => 21,
                'model_type'        => 'purchase_request',
                'status_key'        => 'gm_approved_credit',
                'map_key'           => 'pr:gm_approved_credit',
                'event'             => 'GM approves credit',
                'who_acts'          => 'General Manager',
                'owner_role'        => 'finance_head',
                'what_happened'     => 'GM Approved on Credit Terms',
                'action_needed'     => 'Authorize credit ledger / COA account',
                'meaning'           => 'GM approves credit -> Finance Head',
            ],
            22 => [
                'row'               => 22,
                'model_type'        => 'purchase_request',
                'status_key'        => 'gm_countered',
                'map_key'           => 'pr:gm_countered',
                'event'             => 'GM counters',
                'who_acts'          => 'General Manager',
                'owner_role'        => 'purchase_manager',
                'what_happened'     => 'GM Countered Quotations',
                'action_needed'     => 'Renegotiate vendor rates / revise sourcing',
                'meaning'           => 'GM counters -> Procurement Manager',
            ],
            23 => [
                'row'               => 23,
                'model_type'        => 'purchase_request',
                'status_key'        => 'gm_rejected',
                'map_key'           => 'pr:gm_rejected',
                'event'             => 'GM rejects',
                'who_acts'          => 'General Manager',
                'owner_role'        => 'site_engineer',
                'what_happened'     => 'Rejected by GM',
                'action_needed'     => 'Review rejection notes and revise or cancel',
                'meaning'           => 'GM rejects -> Requester + Procurement Manager',
            ],
            24 => [
                'row'               => 24,
                'model_type'        => 'purchase_request',
                'status_key'        => 'finance_assigned_payment',
                'map_key'           => 'pr:finance_assigned_payment',
                'event'             => 'Finance Head assigns payment',
                'who_acts'          => 'Finance Head',
                'owner_role'        => 'finance',
                'what_happened'     => 'Payment Assigned to Cashier',
                'action_needed'     => 'Disburse funds to purchaser',
                'meaning'           => 'Finance Head assigns payment -> directly assigned finance staff',
            ],
            25 => [
                'row'               => 25,
                'model_type'        => 'purchase_request',
                'status_key'        => PurchaseRequest::STATUS_PENDING_RECEIPT_UPLOAD,
                'map_key'           => 'pr:pending_receipt_upload',
                'event'             => 'Payment done',
                'who_acts'          => 'Cashier',
                'owner_role'        => 'purchase',
                'what_happened'     => 'Funds Disbursed by Cashier',
                'action_needed'     => 'Buy items and upload fiscal receipt',
                'meaning'           => 'Payment done -> Purchaser (receipt upload needed)',
            ],
            26 => [
                'row'               => 26,
                'model_type'        => 'purchase_request',
                'status_key'        => PurchaseRequest::STATUS_PENDING_DRIVER,
                'map_key'           => 'pr:pending_driver_booking',
                'event'             => 'Receipt uploaded',
                'who_acts'          => 'Purchaser',
                'owner_role'        => 'general_service',
                'what_happened'     => 'Fiscal Receipt Uploaded',
                'action_needed'     => 'Book vehicle and dispatch driver to site',
                'meaning'           => 'Receipt uploaded -> General Service / Fleet Manager',
            ],
            27 => [
                'row'               => 27,
                'model_type'        => 'purchase_request',
                'status_key'        => 'receipt_rejected',
                'map_key'           => 'pr:receipt_rejected',
                'event'             => 'Receipt rejected',
                'who_acts'          => 'Store Keeper or Finance',
                'owner_role'        => 'purchase',
                'what_happened'     => 'Receipt Rejected during Verification',
                'action_needed'     => 'Upload valid fiscal tax invoice',
                'meaning'           => 'Receipt rejected -> Purchaser',
            ],
            28 => [
                'row'               => 28,
                'model_type'        => 'purchase_request',
                'status_key'        => 'driver_booked',
                'map_key'           => 'pr:driver_booked',
                'event'             => 'Driver booked',
                'who_acts'          => 'General Service',
                'owner_role'        => 'driver',
                'what_happened'     => 'Driver Booked and Dispatched',
                'action_needed'     => 'Transport materials to site and confirm arrival',
                'meaning'           => 'Driver booked -> directly assigned driver + Store Keeper',
            ],
            29 => [
                'row'               => 29,
                'model_type'        => 'purchase_request',
                'status_key'        => 'delivered',
                'map_key'           => 'pr:delivered',
                'event'             => 'Driver delivered',
                'who_acts'          => 'Driver',
                'owner_role'        => 'store_keeper',
                'what_happened'     => 'Materials Arrived at Site Gate',
                'action_needed'     => 'Inspect items and complete GRN receiving slip',
                'meaning'           => 'Driver delivered -> Site Store Keeper',
            ],
            30 => [
                'row'               => 30,
                'model_type'        => 'purchase_request',
                'status_key'        => PurchaseRequest::STATUS_INTAKE_COMPLETE,
                'map_key'           => 'pr:intake_complete',
                'event'             => 'GRN / intake complete',
                'who_acts'          => 'Site Store Keeper',
                'owner_role'        => 'finance_head',
                'what_happened'     => 'Store Intake (GRN) Completed',
                'action_needed'     => 'Verify PO, Receipt, and GRN to finalize 3-way match',
                'meaning'           => 'GRN / intake complete -> Finance Head',
            ],
            31 => [
                'row'               => 31,
                'model_type'        => 'purchase_request',
                'status_key'        => PurchaseRequest::STATUS_COMPLETED,
                'map_key'           => 'pr:completed',
                'event'             => '3-way match closed (completed)',
                'who_acts'          => 'Finance Head',
                'owner_role'        => 'site_engineer',
                'what_happened'     => '3-Way Match Verified and Closed',
                'action_needed'     => 'None (Lifecycle Complete)',
                'meaning'           => '3-way match closed -> Requester + Procurement Manager (FYI)',
            ],
            32 => [
                'row'               => 32,
                'model_type'        => 'purchase_request',
                'status_key'        => 'emergency_direct_to_finance',
                'map_key'           => 'pr:emergency_direct_to_finance',
                'event'             => 'Emergency direct to finance',
                'who_acts'          => 'Site Engineer / GM',
                'owner_role'        => 'finance_head',
                'what_happened'     => 'Emergency Direct Authorization to Finance',
                'action_needed'     => 'Fast-track emergency fund release',
                'meaning'           => 'Emergency direct to finance -> Finance Head (URGENT)',
            ],
            33 => [
                'row'               => 33,
                'model_type'        => 'purchase_request',
                'status_key'        => 'selective_transfer',
                'map_key'           => 'pr:selective_transfer',
                'event'             => 'Selective transfer',
                'who_acts'          => 'Store Manager',
                'owner_role'        => 'store_keeper',
                'what_happened'     => 'Inter-Store Selective Transfer Issued',
                'action_needed'     => 'Receive incoming material transfer at destination store',
                'meaning'           => 'Selective transfer -> Store Keeper + Requester',
            ],
            34 => [
                'row'               => 34,
                'model_type'        => 'purchase_request',
                'status_key'        => PurchaseRequest::STATUS_CANCELLED,
                'map_key'           => 'pr:cancelled',
                'event'             => 'Cancelled',
                'who_acts'          => 'Requester or Admin',
                'owner_role'        => 'site_engineer',
                'what_happened'     => 'Request Cancelled',
                'action_needed'     => 'None (Request Cancelled)',
                'meaning'           => 'Cancelled -> Requester + current owner (FYI)',
            ],
            35 => [
                'row'               => 35,
                'model_type'        => 'purchase_request',
                'status_key'        => 'reactivated',
                'map_key'           => 'pr:reactivated',
                'event'             => 'Reactivated from rejected',
                'who_acts'          => 'Admin or PM',
                'owner_role'        => 'purchase_manager',
                'what_happened'     => 'Reactivated from Rejected Status',
                'action_needed'     => 'Re-assign sourcing method',
                'meaning'           => 'Reactivated from rejected -> Procurement Manager',
            ],
        ];
    }

    /**
     * Comprehensive Central Status-to-Owner Map covering all stages, constants, and side paths.
     */
    public static function getStatusMap(): array
    {
        $map = [];

        // Build base map from all 35 audit table statuses
        foreach (self::getAuditStatuses() as $row) {
            $map[$row['map_key']] = [
                'model_type'       => $row['model_type'],
                'status_key'       => $row['status_key'],
                'handoff_key'      => str_replace(['mr:', 'pr:'], '', $row['map_key']),
                'name'             => $row['meaning'],
                'owner_role'       => $row['owner_role'],
                'what_happened'    => $row['what_happened'],
                'action_needed'    => $row['action_needed'],
                'default_template' => '{priority}{req_type} #{req_no} ({site}): {what_happened} by {sender_name}. Action needed: {action_needed}. Open: {link}',
                'notify_requester' => in_array($row['row'], [3, 4, 6, 8, 11, 23, 31, 33, 34]),
            ];
        }

        // Additional aliases and specific PR constant mappings to ensure 100% coverage
        $extraMappings = [
            'mr:pending_planning' => [
                'model_type'       => 'material_request',
                'status_key'       => 'pending_planning',
                'handoff_key'      => 'mr_submitted',
                'name'             => 'MR Pending Planning &rarr; Planning Review',
                'owner_role'       => 'planning',
                'what_happened'    => 'Submitted for Planning Review',
                'action_needed'    => 'Review and approve/forward to Coordinator',
                'default_template' => '{priority}MR #{req_no} ({site}): Submitted by {sender_name}. Action needed: Review and forward to Coordinator. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:pending_payment' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_PENDING_PAYMENT,
                'handoff_key'      => 'gm_approved',
                'name'             => 'PR GM Approved &rarr; Finance Head / Cashier Payment',
                'owner_role'       => 'finance_head',
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
            'pr:approved' => [
                'model_type'       => 'purchase_request',
                'status_key'       => PurchaseRequest::STATUS_APPROVED,
                'handoff_key'      => 'coordinator_forwarded',
                'name'             => 'PR Approved &rarr; Store Review',
                'owner_role'       => 'store_manager',
                'what_happened'    => 'Approved by Coordinator',
                'action_needed'    => 'Check warehouse stock / issue SIV or send to PM',
                'default_template' => '{priority}PR #{req_no} ({site}): Approved by {sender_name}. Action needed: Check stock / issue SIV or send to PM. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:approved_petty_cash' => [
                'model_type'       => 'purchase_request',
                'status_key'       => 'approved_petty_cash',
                'handoff_key'      => 'planning_approve_petty_cash',
                'name'             => 'PR Approved for Site Petty Cash &rarr; Requester',
                'owner_role'       => 'site_engineer',
                'what_happened'    => 'Approved for Site Petty Cash',
                'action_needed'    => 'Purchase via site petty cash',
                'default_template' => '{priority}PR #{req_no} ({site}): Approved for petty cash by {sender_name}. Action needed: Purchase via petty cash. Open: {link}',
                'notify_requester' => true,
            ],
            'pr:pm_returned_to_store' => [
                'model_type'       => 'purchase_request',
                'status_key'       => 'pm_returned_to_store',
                'handoff_key'      => 'proc_manager_returned',
                'name'             => 'PR PM Returned &rarr; Store Manager',
                'owner_role'       => 'store_manager',
                'what_happened'    => 'PR Returned by Procurement Manager',
                'action_needed'    => 'Review returned PR notes',
                'default_template' => '{priority}PR #{req_no} ({site}): Returned by PM {sender_name}. Action needed: Review notes. Open: {link}',
                'notify_requester' => false,
            ],
            'pr:finance_returned_to_gm' => [
                'model_type'       => 'purchase_request',
                'status_key'       => 'finance_returned_to_gm',
                'handoff_key'      => 'finance_returned_to_gm',
                'name'             => 'PR Finance Returned &rarr; GM',
                'owner_role'       => 'gm',
                'what_happened'    => 'PR Returned to GM by Finance Head',
                'action_needed'    => 'Review returned notes and re-authorize',
                'default_template' => '{priority}PR #{req_no} ({site}): Returned by Finance Head {sender_name}. Action needed: Review notes. Open: {link}',
                'notify_requester' => false,
            ],
        ];

        foreach ($extraMappings as $k => $item) {
            $map[$k] = $item;
        }

        return $map;
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

            // If not directly mapped, check alias or fallback
            if (!$map) {
                if ($model instanceof PurchaseRequest && in_array($newStatus, [PurchaseRequest::STATUS_PENDING_PAYMENT, 'pending_payment'])) {
                    $map = self::getStatusMap()['pr:pending_payment'] ?? null;
                } elseif ($model instanceof PurchaseRequest && in_array($newStatus, [PurchaseRequest::STATUS_PENDING_FINANCE, 'pending_finance'])) {
                    $map = self::getStatusMap()['pr:pending_finance'] ?? null;
                }
            }

            if (!$map) {
                Log::info("ProcurementStatusOwnerService: No status mapping configured for [{$mapKey}]. Skipping.");
                return;
            }

            // Check admin on/off toggle for this handoff
            $handoffKey = $map['handoff_key'] ?? $newStatus;
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

            // Build actionable message text conforming to pro.doc specification
            $message = $this->formatMessage($model, $map, $senderName, $extraContext);

            // Dispatch to each recipient with deduplication guard and skip logging
            foreach ($recipients as $recipient) {
                $rawPhone = $recipient['phone'] ?? null;
                $phone = $this->procurementSms->normalizePhone($rawPhone ?? '');

                // Requirement 7 & Test 13: Skip and log (do not crash) if a recipient has no phone number
                if (empty($phone)) {
                    $this->logSkippedSms(
                        model: $model,
                        modelType: $modelType,
                        status: $newStatus,
                        handoffKey: $handoffKey,
                        recipient: $recipient,
                        message: $message,
                        senderUser: $senderUser,
                        reason: 'Recipient has no phone number configured'
                    );
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

            // Send FYI copies to requester or others when configured
            if (!empty($map['notify_requester'])) {
                $this->sendFyiToRequester($model, $modelType, $newStatus, $map, $senderName, $extraContext);
            }

            // Special side-path: SIV transfer issues also sends FYI to Coordinator
            if ($mapKey === 'mr:transferred') {
                $this->sendFyiToProjectCoordinator($model, $senderName);
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

        // A. Dynamic direct assignment via context or model
        $assignedUserId = $context['assigned_user_id']
            ?? ($model->assigned_finance_staff_id ?? ($model->payment?->assigned_finance_staff_id ?? null));

        if (!empty($assignedUserId) && in_array($targetRole, ['finance', 'finance_head', 'finance_staff'])) {
            $user = User::with('employee')->find($assignedUserId);
            if ($user) {
                return [[
                    'user_id'     => $user->id,
                    'employee_id' => $user->employee?->id,
                    'role'        => 'finance_staff',
                    'phone'       => $this->procurementSms->resolveUserPhone($user),
                    'name'        => $user->name,
                ]];
            }
        }

        // B. Direct Driver on PR
        if ($targetRole === 'driver') {
            $driverEmpId = $context['driver_employee_id'] ?? ($model->driverBooking?->driver_employee_id ?? null);
            if ($driverEmpId) {
                $driver = Employee::find($driverEmpId);
                if ($driver) {
                    $recipients[] = [
                        'user_id'     => $driver->user_id,
                        'employee_id' => $driver->id,
                        'role'        => 'driver',
                        'phone'       => $driver->phone,
                        'name'        => $driver->full_name,
                    ];
                }
            }

            // Driver booked also notifies Store Keeper of destination store
            if ($storeId) {
                $store = Store::with(['users' => fn($q) => $q->whereHas('roles', fn($r) => $r->whereIn('name', ['store_keeper', 'Store Keeper']))])->find($storeId);
                if ($store && $store->users) {
                    foreach ($store->users as $sku) {
                        $recipients[] = [
                            'user_id'     => $sku->id,
                            'employee_id' => $sku->employee?->id,
                            'role'        => 'store_keeper',
                            'phone'       => $this->procurementSms->resolveUserPhone($sku),
                            'name'        => $sku->name,
                        ];
                    }
                }
            }

            if (!empty($recipients)) {
                return $this->uniqueRecipients($recipients);
            }
        }

        // C. Direct Original Requester (Site Engineer)
        if (in_array($targetRole, ['site_engineer', 'requester'])) {
            $creatorId = $model->requested_by ?? ($model->created_by ?? null);
            if ($creatorId) {
                $creator = User::with('employee')->find($creatorId);
                if ($creator) {
                    return [[
                        'user_id'     => $creator->id,
                        'employee_id' => $creator->employee?->id,
                        'role'        => 'site_engineer',
                        'phone'       => $this->procurementSms->resolveUserPhone($creator),
                        'name'        => $creator->name,
                    ]];
                }
            }
        }

        // D. Direct Coordinator assigned to Project
        if ($targetRole === 'coordinator' && $projectId) {
            $projectCoordinators = $this->getProjectCoordinators($projectId);
            if (!empty($projectCoordinators)) {
                return $projectCoordinators;
            }
        }

        // ── Priority 2: Project / Store-Scoped Staff ──────────────────────────

        // A. Target Store's Store Manager (ONLY that store's manager)
        if ($targetRole === 'store_manager' && $storeId) {
            $store = Store::with(['manager', 'users' => fn($q) => $q->whereHas('roles', fn($r) => $r->whereIn('name', ['store_manager', 'Store Manager']))])->find($storeId);
            if ($store) {
                if ($store->manager) {
                    return [[
                        'user_id'     => $store->manager->id,
                        'employee_id' => $store->manager->employee?->id,
                        'role'        => 'store_manager',
                        'phone'       => $this->procurementSms->resolveUserPhone($store->manager),
                        'name'        => $store->manager->name,
                    ]];
                }
                if ($store->users && $store->users->isNotEmpty()) {
                    foreach ($store->users as $su) {
                        $recipients[] = [
                            'user_id'     => $su->id,
                            'employee_id' => $su->employee?->id,
                            'role'        => 'store_manager',
                            'phone'       => $this->procurementSms->resolveUserPhone($su),
                            'name'        => $su->name,
                        ];
                    }
                    return $this->uniqueRecipients($recipients);
                }
            }
        }

        // B. Target Store's Store Keeper
        if ($targetRole === 'store_keeper' && $storeId) {
            $store = Store::with(['users' => fn($q) => $q->whereHas('roles', fn($r) => $r->whereIn('name', ['store_keeper', 'Store Keeper']))])->find($storeId);
            if ($store && $store->users && $store->users->isNotEmpty()) {
                foreach ($store->users as $su) {
                    $recipients[] = [
                        'user_id'     => $su->id,
                        'employee_id' => $su->employee?->id,
                        'role'        => 'store_keeper',
                        'phone'       => $this->procurementSms->resolveUserPhone($su),
                        'name'        => $su->name,
                    ];
                }
                return $this->uniqueRecipients($recipients);
            }
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
     * Get coordinators specifically assigned to a given project.
     */
    protected function getProjectCoordinators(int $projectId): array
    {
        $recipients = [];

        try {
            $project = Project::with(['users' => fn($q) => $q->whereHas('roles', fn($r) => $r->whereIn('name', ['coordinator', 'Coordinator']))])->find($projectId);
            if ($project && $project->users && $project->users->isNotEmpty()) {
                foreach ($project->users as $u) {
                    $recipients[] = [
                        'user_id'     => $u->id,
                        'employee_id' => $u->employee?->id,
                        'role'        => 'coordinator',
                        'phone'       => $this->procurementSms->resolveUserPhone($u),
                        'name'        => $u->name,
                    ];
                }
            }
        } catch (\Throwable $e) {}

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
     * Format actionable SMS text conforming to pro.doc Requirement Section 3:
     * "{MR/PR} #{number} ({site/project}): {what happened} by {actor name}. Action needed: {action}. Open: {link}"
     * - Emergency requests: prefix "[URGENT-EMERGENCY] "
     * - FYI messages say "FYI:" instead of "Action needed:"
     * - Rejection messages include rejection reason (truncated to ~80 chars)
     * - Keep under 160 characters where possible, shortening the reason first.
     */
    public function formatMessage(Model $model, array $map, string $senderName, array $context = []): string
    {
        $isEmergency = ($model->priority ?? '') === 'emergency'
            || ($model->type ?? '') === 'emergency'
            || ($model->source ?? '') === 'Emergency'
            || !empty($context['is_emergency']);

        $priorityPrefix = $isEmergency ? '[URGENT-EMERGENCY] ' : '';

        $reqType = $model instanceof MaterialRequest ? 'MR' : 'PR';
        $reqNo = $model instanceof MaterialRequest
            ? ($model->reference_number ?: "MR-{$model->id}")
            : ($model->pr_no ?: "PR-{$model->id}");

        $site = $model->project?->name ?? 'HQ';
        $site = mb_substr($site, 0, 18);

        $isFyi = !empty($context['is_fyi']) || !empty($map['is_fyi']);
        $actionPrefix = $isFyi ? 'FYI:' : 'Action needed:';

        $whatHappened = $context['what_happened'] ?? $map['what_happened'];
        $actionNeeded = $context['action_needed'] ?? $map['action_needed'];
        $link = $this->generateDirectLink($model);

        // Rejection reason handling (truncated to ~80 chars per pro.doc spec)
        $reason = $context['reason']
            ?? ($model->planning_rejection_reason
            ?? ($model->rejection_reason ?? null));

        if (!empty($reason) && (str_contains(strtolower($whatHappened), 'reject') || str_contains(strtolower($map['status_key']), 'reject'))) {
            $reason = mb_substr(trim($reason), 0, 80);
            $whatHappened = "Rejected (Reason: {$reason})";
        }

        $amount = '';
        if (isset($context['amount']) && is_numeric($context['amount']) && (float)$context['amount'] > 0) {
            $amount = number_format((float)$context['amount'], 2);
        } elseif (isset($model->estimated_total_cost) && (float)$model->estimated_total_cost > 0) {
            $amount = number_format((float)$model->estimated_total_cost, 2);
        } elseif (isset($model->direct_buy_amount) && (float)$model->direct_buy_amount > 0) {
            $amount = number_format((float)$model->direct_buy_amount, 2);
        }

        if (!empty($amount) && !str_contains($whatHappened, $amount)) {
            $whatHappened = "{$whatHappened} ({$amount} ETB)";
        }

        $message = "{$priorityPrefix}{$reqType} #{$reqNo} ({$site}): {$whatHappened} by {$senderName}. {$actionPrefix} {$actionNeeded}. Open: {$link}";

        // If over 160 characters and reason is longer than 50 chars, shorten reason first
        if (mb_strlen($message) > 160 && !empty($reason) && mb_strlen($reason) > 50) {
            $baseWithoutReason = mb_strlen("{$priorityPrefix}{$reqType} #{$reqNo} ({$site}): Rejected by {$senderName}. {$actionPrefix} {$actionNeeded}. Open: {$link}");
            $avail = max(45, 160 - $baseWithoutReason - 12);
            if ($avail < mb_strlen($reason)) {
                $shortReason = mb_substr($reason, 0, $avail - 3) . '...';
                $whatHappened = "Rejected (Reason: {$shortReason})";
                $message = "{$priorityPrefix}{$reqType} #{$reqNo} ({$site}): {$whatHappened} by {$senderName}. {$actionPrefix} {$actionNeeded}. Open: {$link}";
            }
        }

        return trim($message);
    }

    /**
     * Send SMS with 60s deduplication, 5s timeout, try/catch absorption, and audit logging.
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

        // ── Deduplication Guard: key = model + id + status + recipient_phone, 60s TTL ──
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

        // ── Log Every Attempt to Both Tables ─────────────────────────────────
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
                'purchase_request_id' => $modelType === 'purchase_request' ? $requestId : null,
                'material_request_id' => $modelType === 'material_request' ? $requestId : null,
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
     * Requirement 7 & Test 13: Log skipped SMS when recipient has no phone number.
     * Guaranteed non-blocking and never crashes the user action.
     */
    protected function logSkippedSms(
        Model $model,
        string $modelType,
        string $status,
        string $handoffKey,
        array $recipient,
        string $message,
        ?User $senderUser,
        string $reason
    ): void {
        $requestId = $model->getKey();
        Log::info("ProcurementStatusOwnerService: SMS skipped for {$modelType} #{$requestId} ({$recipient['role']}): {$reason}");

        try {
            NotificationLog::create([
                'request_type'          => $modelType,
                'request_id'            => $requestId,
                'action'                => $handoffKey,
                'sender_user_id'        => $senderUser?->id,
                'recipient_user_id'     => $recipient['user_id'] ?? null,
                'recipient_employee_id' => $recipient['employee_id'] ?? null,
                'role'                  => $recipient['role'] ?? 'owner',
                'phone'                 => 'N/A',
                'message'               => $message,
                'status'                => 'skipped',
                'error'                 => "Skipped: {$reason}",
                'retries'               => 0,
            ]);
        } catch (\Throwable $e) {}

        try {
            DB::table('procurement_sms_logs')->insert([
                'purchase_request_id' => $modelType === 'purchase_request' ? $requestId : null,
                'material_request_id' => $modelType === 'material_request' ? $requestId : null,
                'recipient_phone'     => 'N/A',
                'recipient_role'      => substr($recipient['role'] ?? 'owner', 0, 60),
                'message'             => $message,
                'status'              => 'skipped',
                'error_message'       => "Skipped: {$reason}",
                'sent_at'             => now(),
            ]);
        } catch (\Throwable $e) {}
    }

    /**
     * Send FYI copy to original requester on rejection, transfer, or completion.
     */
    protected function sendFyiToRequester(
        Model $model,
        string $modelType,
        string $newStatus,
        array $map,
        string $senderName,
        array $context = []
    ): void {
        $creatorId = $model->requested_by ?? ($model->created_by ?? null);
        if (!$creatorId) {
            return;
        }

        $creator = User::with('employee')->find($creatorId);
        if (!$creator) {
            return;
        }

        // Do not notify if requester is the actor who triggered the action
        if (Auth::id() && Auth::id() === $creator->id) {
            return;
        }

        // Do not send FYI if requester is already the primary action recipient
        if (in_array($map['owner_role'] ?? '', ['site_engineer', 'requester'])) {
            return;
        }

        $phone = $this->procurementSms->resolveUserPhone($creator);
        $contextFyi = array_merge($context, ['is_fyi' => true]);
        $fyiMessage = $this->formatMessage($model, $map, $senderName, $contextFyi);

        if (empty($phone)) {
            $this->logSkippedSms(
                model: $model,
                modelType: $modelType,
                status: "fyi_{$newStatus}",
                handoffKey: "fyi_{$map['handoff_key']}",
                recipient: ['user_id' => $creator->id, 'role' => 'requester_fyi'],
                message: $fyiMessage,
                senderUser: Auth::user(),
                reason: 'Requester has no phone number configured'
            );
            return;
        }

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
     * Send FYI copy to Project Coordinator (e.g. when Store Manager issues SIV transfer).
     */
    protected function sendFyiToProjectCoordinator(MaterialRequest $mr, string $senderName): void
    {
        if (!$mr->project_id) {
            return;
        }

        $coords = $this->getProjectCoordinators($mr->project_id);
        if (empty($coords)) {
            return;
        }

        $map = self::getStatusMap()['mr:transferred'] ?? [
            'what_happened' => 'Transfer Created',
            'action_needed' => 'Review incoming transfer',
        ];

        $fyiMessage = $this->formatMessage($mr, $map, $senderName, ['is_fyi' => true]);

        foreach ($coords as $coord) {
            $phone = $this->procurementSms->normalizePhone($coord['phone'] ?? '');
            if (empty($phone)) {
                continue;
            }

            $this->sendAndLogSms(
                model: $mr,
                modelType: 'material_request',
                status: 'fyi_coord_transferred',
                handoffKey: 'fyi_coord_transferred',
                recipient: $coord,
                phone: $phone,
                message: $fyiMessage,
                senderUser: Auth::user()
            );
        }
    }

    /**
     * Check if a specific handoff is enabled in DB settings (default true).
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
     * Deduplicate recipient entries by phone number (or user_id if phone is null).
     */
    protected function uniqueRecipients(array $recipients): array
    {
        $seen = [];
        $unique = [];

        foreach ($recipients as $r) {
            $key = !empty($r['phone']) ? $this->procurementSms->normalizePhone($r['phone']) : ('user_' . ($r['user_id'] ?? rand(1000, 9999)));
            if ($key && !isset($seen[$key])) {
                $seen[$key] = true;
                if (!empty($r['phone'])) {
                    $r['phone'] = $this->procurementSms->normalizePhone($r['phone']);
                }
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
