<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS Provider Configuration
    |--------------------------------------------------------------------------
    */
    'default_provider' => env('SMS_DRIVER', 'afromessage'), // 'afromessage', 'africastalking', 'log'

    /*
    |--------------------------------------------------------------------------
    | Fallback Admin Configuration
    |--------------------------------------------------------------------------
    | If no employee is assigned to a target role, SMS escalates to this role or phone.
    */
    'fallback_role'  => 'global_admin',
    'fallback_phone' => env('ADMIN_PHONE', env('AFROMESSAGE_BACKUP_PHONE', '')),

    /*
    |--------------------------------------------------------------------------
    | Procurement Handoff Notification Chain Map
    |--------------------------------------------------------------------------
    | Maps each handoff action to its sender role, target recipient role(s),
    | and editable default message template.
    |
    | Placeholders supported in templates:
    |   {req_no}       - Material Request or PR Reference Number
    |   {sender_name}  - Name of the employee/user who performed the action
    |   {sender_role}  - Role of the person who sent the request
    |   {action}       - Human-readable action description
    |   {site}         - Project name / site location
    |   {priority}     - 'URGENT: ' if emergency, otherwise ''
    |   {link}         - Direct URL to the request
    */
    'handoffs' => [
        // 1. Site Engineer -> Planning
        'mr_submitted' => [
            'name'         => 'Site Engineer Submits Material Request',
            'sender_role'  => 'site_engineer',
            'target_roles' => ['planning'],
            'action_label' => 'review and forward',
            'default_template' => '{priority}{req_no} from Site Eng. {sender_name} ({site}) needs your planning review. Open: {link}',
            'description'  => 'Triggered when a Site Engineer creates and submits a new Material Request.',
        ],

        // 2. Planning -> Coordinator
        'planning_forwarded' => [
            'name'         => 'Planning Approves & Forwards to Coordinator',
            'sender_role'  => 'planning',
            'target_roles' => ['coordinator'],
            'action_label' => 'coordinator dispatch',
            'default_template' => '{priority}{req_no} ({site}) approved by Planning ({sender_name}). Needs coordinator dispatch. Open: {link}',
            'description'  => 'Triggered when Planning reviews and forwards request to Project Coordinator.',
        ],

        // 2b. Planning Rejects -> Requester (Site Engineer)
        'planning_rejected' => [
            'name'         => 'Planning Rejects Material Request',
            'sender_role'  => 'planning',
            'target_roles' => ['site_engineer'],
            'action_label' => 'review rejection notes',
            'default_template' => '{priority}{req_no} ({site}) was rejected by Planning ({sender_name}). Please review notes. Open: {link}',
            'description'  => 'Triggered when Planning rejects a Material Request.',
        ],

        // 3. Coordinator -> Store Manager
        'coordinator_forwarded' => [
            'name'         => 'Coordinator Forwards to Store Manager',
            'sender_role'  => 'coordinator',
            'target_roles' => ['store_manager'],
            'action_label' => 'check store stock',
            'default_template' => '{priority}{req_no} ({site}) dispatched by Coord. {sender_name}. Please check stock/issue SIV. Open: {link}',
            'description'  => 'Triggered when Project Coordinator dispatches request to Store Manager.',
        ],

        // 4. Store Manager: In Stock -> Issues Material (SIV) -> Requester + Coordinator
        'store_issued_siv' => [
            'name'         => 'Store Manager Issues Material (SIV/Transfer)',
            'sender_role'  => 'store_manager',
            'target_roles' => ['site_engineer', 'coordinator'],
            'action_label' => 'collect material / SIV issued',
            'default_template' => '{priority}{req_no} ({site}): In stock! SIV/Transfer issued by Store Mgr. {sender_name}. Open: {link}',
            'description'  => 'Triggered when Store Manager finds items in stock and creates SIV / Store Transfer.',
        ],

        // 5. Store Manager: Out of Stock -> Converts to PR -> Procurement Manager
        'store_converted_to_pr' => [
            'name'         => 'Store Manager Routes Out of Stock to Procurement Manager',
            'sender_role'  => 'store_manager',
            'target_roles' => ['purchase_manager'],
            'action_label' => 'assign sourcing',
            'default_template' => '{priority}{req_no} ({site}): Out of stock. Sent by Store Mgr. {sender_name} for procurement sourcing. Open: {link}',
            'description'  => 'Triggered when Store Manager routes out of stock request to Procurement Manager.',
        ],

        // 6. Procurement Manager -> Procurement Officer (Proforma/Direct) or GM (Credit)
        'proc_manager_assigned_sourcing' => [
            'name'         => 'Procurement Manager Assigns Sourcing',
            'sender_role'  => 'purchase_manager',
            'target_roles' => ['purchase'], // or 'gm' for credit
            'action_label' => 'collect quotes / proformas',
            'default_template' => '{priority}{req_no} ({site}) assigned by Proc. Mgr. {sender_name} for sourcing. Open: {link}',
            'description'  => 'Triggered when Procurement Manager assigns PR to Procurement Team or GM for credit.',
        ],

        // 7. Procurement Officer -> Market Research Team (Proforma Route)
        'proc_officer_submitted_proforma' => [
            'name'         => 'Procurement Officer Submits Proformas',
            'sender_role'  => 'purchase',
            'target_roles' => ['market_research'],
            'action_label' => 'verify market rate variance',
            'default_template' => '{priority}{req_no} ({site}): Proformas submitted by {sender_name}. Needs rate variance check. Open: {link}',
            'description'  => 'Triggered when Procurement Officer uploads proforma quotations for Market Research review.',
        ],

        // 8. Market Research Team -> General Manager
        'market_research_variance' => [
            'name'         => 'Market Research Submits Rate Variance to GM',
            'sender_role'  => 'market_research',
            'target_roles' => ['gm'],
            'action_label' => 'approve proforma selection',
            'default_template' => '{priority}{req_no} ({site}): Rate variance submitted by Market Research ({sender_name}). Awaiting GM decision. Open: {link}',
            'description'  => 'Triggered when Market Research submits price variance analysis to General Manager.',
        ],

        // 9. Procurement Officer -> General Manager (Direct Buy)
        'proc_officer_submitted_direct' => [
            'name'         => 'Procurement Officer Submits Direct Buy Quotation to GM',
            'sender_role'  => 'purchase',
            'target_roles' => ['gm'],
            'action_label' => 'review direct buy quotation',
            'default_template' => '{priority}{req_no} ({site}): Direct buy quotation submitted by {sender_name}. Awaiting GM decision. Open: {link}',
            'description'  => 'Triggered when Procurement Officer submits direct buy price quotation to General Manager.',
        ],

        // 10. General Manager -> Finance Head (Approved)
        'gm_approved' => [
            'name'         => 'General Manager Approves Sourcing / Budget',
            'sender_role'  => 'gm',
            'target_roles' => ['finance_head'],
            'action_label' => 'assign payment',
            'default_template' => '{priority}{req_no} ({site}) approved by GM {sender_name}. Needs payment authorization. Open: {link}',
            'description'  => 'Triggered when General Manager approves quotation / sourcing and forwards to Finance Head.',
        ],

        // 11. General Manager -> Procurement Manager (Rejected / Countered)
        'gm_rejected' => [
            'name'         => 'General Manager Rejects or Counters Sourcing',
            'sender_role'  => 'gm',
            'target_roles' => ['purchase_manager'],
            'action_label' => 'revise sourcing',
            'default_template' => '{priority}{req_no} ({site}): GM {sender_name} rejected/countered sourcing. Please re-assign. Open: {link}',
            'description'  => 'Triggered when General Manager rejects or counters quotation.',
        ],

        // 12. Finance Head -> Cashier / Finance Staff
        'finance_approved_payment' => [
            'name'         => 'Finance Head Approves & Assigns Payment',
            'sender_role'  => 'finance_head',
            'target_roles' => ['finance'],
            'action_label' => 'disburse payment',
            'default_template' => '{priority}{req_no} ({site}): Payment authorized by Finance Head {sender_name}. Please disburse cash/cheque. Open: {link}',
            'description'  => 'Triggered when Finance Head approves payment and assigns it to Finance Staff / Cashier.',
        ],

        // 13. Cashier / Finance Staff -> Procurement Officer / Purchaser
        'cashier_disbursed_cash' => [
            'name'         => 'Cashier Disburses Cash to Purchaser',
            'sender_role'  => 'finance',
            'target_roles' => ['purchase'],
            'action_label' => 'purchase materials and get receipt',
            'default_template' => '{priority}{req_no} ({site}): Cash disbursed by Cashier {sender_name}. Please purchase and upload receipt. Open: {link}',
            'description'  => 'Triggered when Cashier / Finance Staff confirms cash disbursement.',
        ],

        // 14. Purchaser -> Logistics Coordinator (Receipt Uploaded)
        'purchaser_uploaded_receipt' => [
            'name'         => 'Purchaser Uploads Fiscal Receipt',
            'sender_role'  => 'purchase',
            'target_roles' => ['general_service'],
            'action_label' => 'book driver and dispatch',
            'default_template' => '{priority}{req_no} ({site}): Receipt uploaded by {sender_name}. Please book driver & dispatch. Open: {link}',
            'description'  => 'Triggered when Purchaser uploads fiscal tax receipt after buying materials.',
        ],

        // 15. Logistics Coordinator -> Driver + Site Store Keeper
        'logistics_dispatched_driver' => [
            'name'         => 'Logistics Coordinator Books Driver & Dispatches',
            'sender_role'  => 'general_service',
            'target_roles' => ['driver', 'store_keeper'],
            'action_label' => 'transport & receive materials',
            'default_template' => '{priority}{req_no} ({site}): Driver dispatched by Logistics ({sender_name}). Materials inbound for delivery. Open: {link}',
            'description'  => 'Triggered when Logistics Coordinator books driver and schedules dispatch.',
        ],

        // 16. Driver -> Site Store Keeper (Marked Delivered)
        'driver_delivered' => [
            'name'         => 'Driver Marks Materials Delivered to Site',
            'sender_role'  => 'driver',
            'target_roles' => ['store_keeper'],
            'action_label' => 'inspect & complete GRN',
            'default_template' => '{priority}{req_no} ({site}): Driver arrived at site. Please inspect items and complete GRN. Open: {link}',
            'description'  => 'Triggered when driver confirms materials arrived at site gate.',
        ],

        // 17. Store Keeper -> Procurement Officer + Finance Head (GRN Completed)
        'store_keeper_grn' => [
            'name'         => 'Store Keeper Completes GRN (Store Intake)',
            'sender_role'  => 'store_keeper',
            'target_roles' => ['purchase', 'finance_head'],
            'action_label' => 'review GRN for 3-way match',
            'default_template' => '{priority}{req_no} ({site}): GRN intake completed by Store Keeper {sender_name}. Ready for 3-way match. Open: {link}',
            'description'  => 'Triggered when Store Keeper verifies items and completes store intake receiving slip (GRN).',
        ],

        // 18. Finance Head -> Requester (Site Eng) + Procurement Manager (3-Way Match Closed)
        'finance_3way_closed' => [
            'name'         => 'Finance Head Completes 3-Way Match & Closes PR',
            'sender_role'  => 'finance_head',
            'target_roles' => ['site_engineer', 'purchase_manager'],
            'action_label' => 'procurement lifecycle closed',
            'default_template' => '{priority}{req_no} ({site}): 3-Way match verified and closed by Finance Head {sender_name}. Lifecycle complete. Open: {link}',
            'description'  => 'Triggered when Finance Head confirms PO, Receipt, and GRN match and finalizes the request.',
        ],
    ],
];
