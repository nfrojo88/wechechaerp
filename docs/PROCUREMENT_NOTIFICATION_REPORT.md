# Procurement Lifecycle Notification Report
**System:** Wechecha Construction PLC ERP (Laravel)  
**Module:** Procurement Lifecycle (Material Request & Purchase Request Workflows)  
**Audit Date:** November 14, 2026  
**Auditor:** DeepMind Code Audit Agent  
**Audit Scope:** End-to-End Procurement Lifecycle (Request Creation through 3-Way Match & Completion)

---

## Executive Summary

This report documents every notification sent during the procurement lifecycle in the Wechecha ERP codebase. It is based on a direct audit of the source code, including:
- [`app/Services/ProcurementStatusOwnerService.php`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementStatusOwnerService.php)
- [`app/Services/ProcurementLifecycleService.php`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php)
- [`app/Services/ProcurementHandoffNotificationService.php`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementHandoffNotificationService.php)
- [`app/Services/ProcurementSmsService.php`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementSmsService.php)
- [`app/Observers/PurchaseRequestObserver.php`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Observers/PurchaseRequestObserver.php) & [`app/Observers/MaterialRequestObserver.php`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Observers/MaterialRequestObserver.php)
- [`app/Http/Controllers/PurchaseRequestController.php`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/PurchaseRequestController.php) & [`app/Http/Controllers/MaterialRequestController.php`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/MaterialRequestController.php)
- [`app/Http/Controllers/ProcurementLifecycleController.php`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/ProcurementLifecycleController.php) & [`resources/views/procurement/lifecycle/my-queue.blade.php`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/resources/views/procurement/lifecycle/my-queue.blade.php)
- Database migrations: [`pr_workflow_logs`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/database/migrations/2026_08_13_004_create_pr_workflow_logs_table.php), [`procurement_sms_logs`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/database/migrations/2026_10_26_030000_update_procurement_sms_logs_support_mr_and_skipped.php), and [`notification_log`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/database/migrations/2026_10_05_160000_create_notification_log_and_sms_settings_tables.php)

### Key Architectural Discovery: 3 Overlapping Notification Layers
The ERP implements **three parallel notification dispatch mechanisms**:
1. **Observer-Driven Layer**: `PurchaseRequestObserver` and `MaterialRequestObserver` hook into Eloquent `created`, `updated`, and `deleted` events, dispatching status-change SMS via `ProcurementTransitionNotifier` &rarr; `ProcurementStatusOwnerService`.
2. **Explicit Service-Level Layer**: `ProcurementLifecycleService` methods invoke `$this->notifyNextOwner()`, which directly calls `ProcurementStatusOwnerService::dispatchStatusChangeSms()`.
3. **Action-Driven Handoff Layer**: `ProcurementLifecycleService` and controllers invoke `$this->handoffSms->triggerHandoff()`, which resolves templates from `config/procurement_handoffs.php` and table `procurement_sms_settings`.
4. **Direct Controller / Service SMS**: Custom formatted string SMS sent directly via `$this->lifecycle->notifyStageRole()` or `$this->sms->notifyRole()`.

---

## Part A: Stage-by-Stage Notification Matrix

| # | Stage Name | Trigger Event & Status Transition | Source File, Class, Method & Lines | Recipient Role & Resolution Logic | Channel | Exact Message Template & Payload | Execution & Retry Handling | `pr_workflow_logs` Written? | Suppression Conditions |
|---|---|---|---|---|---|---|---|---|---|
| **1a** | **1. Request Creation (MR)** | Site Engineer submits Material Request (`[null] -> submitted` / `pending_planning`) | [`app/Observers/MaterialRequestObserver.php#L22-L37`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Observers/MaterialRequestObserver.php#L22-L37), calling [`ProcurementStatusOwnerService#L633`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementStatusOwnerService.php#L633) | **Planning Manager** (`planning`). Resolved via system role match / employee title. Fallback: `global_admin`. | SMS | `{priority}MR #{req_no} ({site}): Submitted by {sender_name}. Action needed: Review and approve/forward to Coordinator. Open: {link}` | Sync post-DB-commit (`DB::afterCommit`). Single HTTP attempt (5s timeout). No auto-retry. Logs to `notification_log` & `procurement_sms_logs`. | **No** (logged in `activity_log`, no PR log yet) | Master switch `PROCUREMENT_SMS_ENABLED=false`; 60s cache deduplication. |
| **1b** | **1. Request Creation (Direct PR)** | Requester creates PR directly (`[null] -> draft`) | [`app/Http/Controllers/PurchaseRequestController.php#L256-L265`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/PurchaseRequestController.php#L256-L265) | **Project Coordinator** (`coordinator`). Resolved to project-assigned coordinator or role match. Fallback: `global_admin`. | SMS | `ConstructPro: New PR #{pr_no} created for Project: {project_name}. Awaiting coordinator review. Open: {url}` | Sync. Single HTTP attempt. Deduplicated in cache (30s TTL). Logs to `procurement_sms_logs` & `notification_log`. | **No** (created as `draft`, initial log written upon submission) | Master switch disabled; `PurchaseRequestObserver` ignores drafts (`status !== 'draft'`). |
| **2a** | **2. Planning Review (Approve)** | Planning approves request (`pending_planning_approval -> pending_coordinator_approval` in PR; `planning_approved` in MR) | [`app/Http/Controllers/PurchaseRequestController.php#L2446-L2489`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/PurchaseRequestController.php#L2446-L2489), [`app/Http/Controllers/MaterialRequestController.php#L315-L375`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/MaterialRequestController.php#L315-L375) | **Project Coordinator** (`coordinator`). Resolved to project's assigned coordinator(s). Fallback: `global_admin`. | SMS | **Controller:** `ConstructPro: PR #{pr_no} approved by Planning for Project: {project}. Awaiting coordinator review. Open: {url}`<br/>**Observer:** `{priority}PR #{req_no} ({site}): Approved by Planning by {sender_name}. Action needed: Dispatch to Store Manager. Open: {link}` | Sync post-commit (`DB::afterCommit`). Single attempt. Logs to `notification_log` and `procurement_sms_logs`. | **Yes:** `action='planning_approve_to_coordinator'`, `actor_role='planning'`, `from='pending_planning_approval'`, `to='pending_coordinator_approval'` | Master switch disabled; deduplication guard (60s). |
| **2b** | **2. Planning Review (Reject)** | Planning rejects request (`pending_planning_approval -> rejected`) | [`app/Http/Controllers/PurchaseRequestController.php#L2493-L2528`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/PurchaseRequestController.php#L2493-L2528), [`app/Observers/PurchaseRequestObserver.php#L42-L59`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Observers/PurchaseRequestObserver.php#L42-L59) | **Site Engineer / Requester** (`site_engineer`). Resolved to `requested_by` / `created_by` user's phone. | SMS | `{priority}PR #{req_no} ({site}): Rejected (Reason: {reason}) by {sender_name}. Action needed: Review rejection notes and revise or cancel. Open: {link}` | Sync post-commit. Truncates reason to &le;80 chars to maintain &le;160 char SMS. | **Yes:** `action='planning_reject'`, `actor_role='planning'`, notes contain rejection reason | Master switch disabled; actor is creator (suppressed self-notification). |
| **2c** | **2. Planning Review (Petty Cash)** | Planning approves for Site Petty Cash (`pending_planning_approval -> approved_petty_cash` / `approved_for_petty_cash`) | [`app/Http/Controllers/MaterialRequestController.php#L384-L432`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/MaterialRequestController.php#L384-L432) | **Site Engineer / Requester** (`site_engineer`) & Store Keeper (in-app queue). | SMS | `{priority}MR #{req_no} ({site}): Approved for Site Petty Cash by {sender_name}. Action needed: Purchase via site petty cash. Open: {link}` | Sync post-commit. Logs to `notification_log` and `procurement_sms_logs`. | **Yes:** `action='planning_approve_petty_cash'`, `actor_role='planning'`, `to='approved_petty_cash'` | Master switch disabled; non-emergency requests routed to standard procurement. |
| **3a** | **3. Project Coordination (Dispatch)** | Coordinator forwards request to warehouse (`pending_coordinator_approval -> pending_store_review` in PR; `sent_to_store_manager` in MR) | [`app/Http/Controllers/PurchaseRequestController.php#L2531-L2573`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/PurchaseRequestController.php#L2531-L2573), [`app/Http/Controllers/MaterialRequestController.php#L792-L845`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/MaterialRequestController.php#L792-L845) | **Store Manager** (`store_manager`). Resolved strictly to destination store's manager (`store->manager_id`). Fallback: Store users &rarr; role match &rarr; `global_admin`. | SMS | **Controller:** `ConstructPro: PR #{pr_no} dispatched by Coordinator to Store Manager for Project: {project}. Open: {url}`<br/>**Observer:** `{priority}PR #{req_no} ({site}): Dispatched by Coordinator by {sender_name}. Action needed: Check warehouse stock / route to PM. Open: {link}` | Sync post-commit. 60s deduplication cache. Logs to `notification_log` and `procurement_sms_logs`. | **Yes:** `action='coordinator_dispatch_to_store'`, `actor_role='coordinator'`, `from='pending_coordinator_approval'`, `to='pending_store_review'` | Master switch disabled. |
| **3b** | **3. Project Coordination (Reject)** | Coordinator rejects request (`pending_coordinator_approval -> rejected`) | [`app/Http/Controllers/PurchaseRequestController.php#L2575-L2606`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/PurchaseRequestController.php#L2575-L2606) | **Site Engineer / Requester** (`site_engineer`). Resolved via creator ID. | SMS | `{priority}PR #{req_no} ({site}): Rejected (Reason: {reason}) by {sender_name}. Action needed: Review rejection notes and revise or cancel. Open: {link}` | Sync post-commit via `PurchaseRequestObserver`. | **Yes:** `action='coordinator_reject'`, `actor_role='coordinator'`, notes contain rejection reason | Master switch disabled; actor is creator. |
| **4a** | **4. Store Stock Check (Out of Stock &rarr; PR)** | Store Manager sends to procurement (`pending_store_review -> pending_procurement_manager`) | [`app/Services/ProcurementLifecycleService.php#L173-L196`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L173-L196) | **Procurement Manager** (`purchase_manager`). Resolved via role match aliases (`procurement_manager`). Fallback: `global_admin`. | SMS | **notifyNextOwner:** `{priority}PR #{req_no} ({site}): Routed to Procurement by {sender_name}. Action needed: Review PR & Assign Sourcing Method. Open: {link}`<br/>**Handoff:** `{priority}{req_no} ({site}): Out of stock! Converted to PR by Store Mgr. {sender_name}. Open: {link}` | Sync. Dual dispatch (calls `notifyNextOwner` AND `triggerHandoff('store_converted_to_pr')`). Deduplication key differs between layers. | **Yes:** `action='send_to_procurement_manager'`, `actor_role='store_manager'`, `to='pending_procurement_manager'` | Master switch disabled; handoff key `store_converted_to_pr` disabled in settings. |
| **4b** | **4. Store Stock Check (In-Stock &rarr; SIV / Transfer)** | Store Manager issues SIV / Transfer (`pending_store_review -> transferred`) | [`app/Http/Controllers/MaterialRequestController.php#L958-L985`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/MaterialRequestController.php#L958-L985), [`app/Http/Controllers/PurchaseRequestController.php#L730-L885`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/PurchaseRequestController.php#L730-L885) | **General Service / Logistics** (`general_service`) + **Requester** (`site_engineer`) + **Coordinator** (FYI) + Destination **Store Keeper** (`store_keeper`). | SMS | **Observer:** `{priority}PR #{req_no} ({site}): Inter-Store Transfer Dispatched by {sender_name}. Action needed: Receive incoming material transfer at destination store. Open: {link}`<br/>**FYI Coordinator:** `{priority}MR #{req_no} ({site}): Transfer Created by {sender_name}. FYI: Review incoming transfer. Open: {link}`<br/>**Transfer Direct:** `ConstructPro: Store Transfer {no} created for PR #{pr_no}... to store '{store}'.` | Sync post-commit. Multi-recipient broadcast. | **Yes:** `action='selective_store_transfer'` or `'transferred_to_store_transfer'`, `actor_role='store_manager'` | Master switch disabled. |
| **4c** | **4. Store Stock Check (PM Return)** | Procurement Manager sends back to Store Manager (`pending_procurement_manager -> pending_store_review`) | [`app/Services/ProcurementLifecycleService.php#L202-L213`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L202-L213) | **Store Manager** (`store_manager`). Resolved to origin store's manager. | SMS | `{priority}PR #{req_no} ({site}): PR Returned by Procurement Manager by {sender_name}. Action needed: Returned to Store: {reason}. Open: {link}` | Sync. Single attempt. Logs to `notification_log` and `procurement_sms_logs`. | **Yes:** `action='send_back_to_store_manager'`, `actor_role='purchase_manager'`, notes contain reason | Master switch disabled. |
| **5a** | **5. Sourcing Strategy (Proforma / Direct)** | PM assigns sourcing to team (`pending_procurement_manager -> pending_procurement_team`) | [`app/Services/ProcurementLifecycleService.php#L215-L241`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L215-L241) | **Procurement Team / Officer** (`purchase`) (or `gm` if sourcing method is credit). Resolved via role alias. | SMS | **notifyNextOwner:** `{priority}PR #{req_no} ({site}): Sourcing Assigned by Procurement Manager by {sender_name}. Action needed: Sourcing: {methodLabel}. Open: {link}`<br/>**Handoff:** `{priority}{req_no} ({site}): Sourcing assigned by Proc. Mgr. {sender_name}. Action needed: Start proforma / direct buy sourcing. Open: {link}` | Sync. Dual dispatch (`notifyNextOwner` + `triggerHandoff('proc_manager_assigned_sourcing')`). | **Yes:** `action='send_to_proc_team_proforma'` or `'send_to_proc_team_direct_buy'`, `actor_role='purchase_manager'` | Master switch disabled. |
| **6a** | **6. Quotations (Direct Buy Submitted)** | Purchaser submits Direct Buy prices (`pending_procurement_team -> pending_marketing_review`) | [`app/Services/ProcurementLifecycleService.php#L247-L317`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L247-L317) | **Procurement Manager** (`purchase_manager`) via `notifyNextOwner`, and **GM** (`gm`) via `handoffSms`. | SMS | **notifyNextOwner:** `{priority}PR #{req_no} ({site}): Direct Buy Quotation Submitted ({amount} ETB) by {sender_name}. Action needed: Direct Buy Pricing Review ({amount} ETB). Open: {link}`<br/>**Handoff:** `{priority}{req_no} ({site}): Direct buy prices submitted by {sender_name}. Needs GM decision. Open: {link}` | Sync. Dual dispatch (`notifyNextOwner` sends to PM, `triggerHandoff('proc_officer_submitted_direct')` sends to GM). | **Yes:** `action='submit_direct_buy_pricing'`, `actor_role='purchase'`, notes contain pricing notes | Master switch disabled. |
| **6b** | **6. Quotations (Proformas Submitted)** | Purchaser uploads proformas (`pending_procurement_team -> pending_proforma_selection`) | [`app/Services/ProcurementLifecycleService.php#L319-L342`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L319-L342) | **Procurement Manager** (`purchase_manager`) via `notifyNextOwner`; **Market Research** (`market_research`) via `handoffSms`. | SMS | **notifyNextOwner:** `{priority}PR #{req_no} ({site}): Vendor Proformas Uploaded by {sender_name}. Action needed: Review & Select Submitted Proformas. Open: {link}`<br/>**Handoff:** `{priority}{req_no} ({site}): Proformas uploaded by {sender_name}. Please review variance against market. Open: {link}` | Sync. Dual dispatch (`notifyNextOwner` & `triggerHandoff('proc_officer_submitted_proforma')`). | **Yes:** `action='submit_proformas'`, `actor_role='purchase'` | Master switch disabled. |
| **6c** | **6. Quotations (Variance & Proformas &rarr; GM)** | PM submits price variance / proformas to GM (`pending_proforma_selection` or `pending_marketing_review -> pending_gm_decision`) | [`app/Services/ProcurementLifecycleService.php#L348-L399`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L348-L399) | **General Manager** (`gm`). Resolved via role match aliases (`general_manager`, `gm`). Fallback: `global_admin`. | SMS | **Direct Buy Variance:** `{priority}PR #{req_no} ({site}): Sourcing & Price Variance Submitted by {sender_name}. Action needed: GM Decision on Direct Buy with Pricing Variance. Open: {link}`<br/>**Proforma Selection:** `{priority}PR #{req_no} ({site}): Sourcing & Price Variance Submitted by {sender_name}. Action needed: GM Decision on {n} Selected Proforma Quote(s). Open: {link}` | Sync. Note: `addMarketingVariance` calls `triggerHandoff('market_research_variance')`, while `sendProformasToGm` only calls `notifyNextOwner`. | **Yes:** `action='add_marketing_variance'` or `'send_proformas_to_gm'`, `actor_role='purchase_manager'` | Master switch disabled. |
| **7a** | **7. GM Decision (Cash Approved)** | GM approves cash purchase (`pending_gm_decision -> pending_payment`) | [`app/Services/ProcurementLifecycleService.php#L474-L750`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L474-L750) | **Finance Head** (`finance_head`) + **Coordinator** (`coordinator`). Resolved via role aliases. | SMS | **notifyNextOwner:** `{priority}PR #{req_no} ({site}): GM Approved for Payment ({amount} ETB) by {sender_name}. Action needed: Select Funding Account & Assign Staff... Open: {link}`<br/>**Direct Coordinator:** `ConstructPro: PR #{pr_no} ({project}) has been APPROVED by GM ({amount} ETB). Open: {url}`<br/>**Handoff:** `{priority}{req_no} ({site}): Approved by GM {sender_name}. Ready for payment authorization. Open: {link}` | Sync. Triple dispatch (`notifyNextOwner` + direct `notifyRole('coordinator')` + `triggerHandoff('gm_approved')`). | **Yes:** `action='gm_approve_pay_and_buy'`, `actor_role='gm'`, notes contain GM decision notes | Master switch disabled. |
| **7b** | **7. GM Decision (Credit Approved)** | GM approves buy by credit (`pending_gm_decision -> pending_store_review`) | [`app/Services/ProcurementLifecycleService.php#L603-L750`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L603-L750) | **Store Keeper** (`store_keeper`) + **Coordinator** (`coordinator`) + **Finance Head** (`finance_head` via handoff). | SMS | **notifyNextOwner:** `{priority}PR #{req_no} ({site}): Dispatched by Coordinator by {sender_name}. Action needed: Material Intake (Credit Authorized COA 5110: {amount} ETB). Open: {link}`<br/>**Direct Coordinator:** `ConstructPro: PR #{pr_no} ({project}) has been APPROVED by GM ({amount} ETB). Open: {url}`<br/>**Handoff:** `{priority}{req_no} ({site}): Approved by GM {sender_name}. Ready for payment authorization. Open: {link}` | Sync. Auto-creates `CreditStoreLedger` (COA 5110) & pre-sets `ProcurementPayment` to credit. Bypasses cashier cash disbursement. | **Yes:** `action='gm_approve_buy_by_credit'`, `actor_role='gm'` | Master switch disabled. |
| **7c** | **7. GM Decision (Counter / Return)** | GM counters price / returns for revision (`pending_gm_decision -> pending_procurement_manager`) | [`app/Services/ProcurementLifecycleService.php#L457-L473`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L457-L473) | **Procurement Manager** (`purchase_manager`). | SMS | **notifyNextOwner:** `{priority}PR #{req_no} ({site}): Routed to Procurement by {sender_name}. Action needed: Returned by GM for Revision: {notes}. Open: {link}`<br/>**Handoff:** `{priority}{req_no} ({site}): GM {sender_name} rejected/countered sourcing. Please re-assign. Open: {link}` | Sync. Dual dispatch (`notifyNextOwner` + `triggerHandoff('gm_rejected')`). | **Yes:** `action='gm_send_back'`, `actor_role='gm'`, notes contain GM counter remarks | Master switch disabled. |
| **7d** | **7. GM Decision (Reject)** | GM rejects request (`pending_gm_decision -> rejected`) | [`app/Services/ProcurementLifecycleService.php#L423-L456`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L423-L456) | **Procurement Manager** (`purchase_manager`) + **Coordinator** (`coordinator`) + **Site Engineer / Requester** (`site_engineer` via Observer). | SMS | **PM Direct:** `ConstructPro: PR #{pr_no} was REJECTED by GM. Reason: {notes}. Open: {url}`<br/>**Coord Direct:** `ConstructPro: PR #{pr_no} ({project}) was REJECTED by GM. Reason: {notes}.`<br/>**Handoff:** `{priority}{req_no} ({site}): GM {sender_name} rejected/countered sourcing. Please re-assign. Open: {link}`<br/>**Observer Requester:** `{priority}PR #{req_no} ({site}): Rejected (Reason: {notes}) by {sender_name}. Action needed: Review rejection notes. Open: {link}` | Sync. Quadruple notification dispatch across layers. | **Yes:** `action='gm_reject'`, `actor_role='gm'`, notes contain rejection reason | Master switch disabled. |
| **8a** | **8. Finance Authorization (Assign Staff)** | Finance Head assigns funding COA & cashier (`pending_payment -> pending_payment`, owner &rarr; `finance`) | [`app/Services/ProcurementLifecycleService.php#L843-L900`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L843-L900) | **Assigned Finance Staff / Cashier** (`finance_staff`). Resolved directly to `$staffUserId` User model & phone number. | SMS | **notifyNextOwner:** `{priority}PR #{req_no} ({site}): GM Approved for Payment by {sender_name}. Action needed: Disburse Cash Payment of {amount} ETB. Open: {link}`<br/>**Handoff:** `{priority}{req_no} ({site}): Payment authorized by Finance Head {sender_name}. Please disburse cash/cheque. Open: {link}` | Sync. Directly targets chosen employee's phone number. Dual dispatch (`notifyNextOwner` + `triggerHandoff('finance_approved_payment')`). | **Yes:** `action='finance_head_assign_payment'`, `actor_role='finance_head'`, notes contain funding details | Master switch disabled. |
| **8b** | **8. Finance Payment Execution (Pay)** | Cashier disburses payment (`pending_payment -> pending_store_review`) | [`app/Services/ProcurementLifecycleService.php#L906-L1029`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L906-L1029) | **Store Keeper** (`store_keeper`) + **Store Manager** (`store_manager`). Resolved to destination store staff. | SMS | **notifyNextOwner:** `{priority}PR #{req_no} ({site}): Dispatched by Coordinator by {sender_name}. Action needed: Payment Disbursed ({amount} ETB) — Perform Store Material Intake. Open: {link}`<br/>**Handoff:** `{priority}{req_no} ({site}): Cash disbursed by Cashier {sender_name}. Please purchase and upload receipt. Open: {link}` | Sync. Advances PR status directly to `pending_store_review`, bypassing blocking receipt upload. Automatically creates journal entry. | **Yes:** `action='finance_staff_paid_direct_to_store'`, `actor_role='finance'`, notes contain voucher details | Master switch disabled. |
| **8c** | **8. Finance Head (Send Back to GM)** | Finance Head returns PR to GM (`pending_payment -> pending_gm_decision`) | [`app/Services/ProcurementLifecycleService.php#L1689-L1720`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L1689-L1720) | **General Manager** (`gm`). | SMS | `{priority}PR #{req_no} ({site}): Sourcing & Price Variance Submitted by {sender_name}. Action needed: Returned to GM by Finance Head: {reason}. Open: {link}` | Sync. Updates companion `ProcurementPayment` to `returned_to_gm`. | **Yes:** `action='finance_send_back_to_gm'`, `actor_role='finance_head'`, notes contain reason | Master switch disabled. |
| **9a** | **9. Purchase & Receipt (Upload to Store)** | Purchaser uploads fiscal receipt (`pending_store_review -> pending_store_review`, sendToStore=true) | [`app/Services/ProcurementLifecycleService.php#L1067-L1146`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L1067-L1146) | **Store Keeper** (`store_keeper`) via `notifyNextOwner`; **General Service** (`general_service`) via `handoffSms`. | SMS | **notifyNextOwner:** `{priority}PR #{req_no} ({site}): Dispatched by Coordinator by {sender_name}. Action needed: Receipt Uploaded — Perform Store Material Intake. Open: {link}`<br/>**Handoff:** `{priority}{req_no} ({site}): Receipt uploaded by {sender_name}. Please book driver & dispatch. Open: {link}` | Sync. Creates verified `ProcurementReceipt`. Dual dispatch (`notifyNextOwner` sends to Store Keeper, `triggerHandoff` sends to General Service). | **Yes:** `action='receipt_uploaded_sent_to_store'`, `actor_role='purchase'` | Master switch disabled. |
| **9b** | **9. Purchase & Receipt (Upload to Finance)** | Purchaser uploads receipt requiring finance audit (`-> pending_receipt_verification`, sendToStore=false) | [`app/Services/ProcurementLifecycleService.php#L1126-L1146`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L1126-L1146) | **Finance Staff** (`finance`). | SMS | `{priority}PR #{req_no} ({site}): Receipt Uploaded by Purchaser by {sender_name}. Action needed: Verify Vendor Purchase Receipt. Open: {link}` | Sync. Single attempt. Logs to `notification_log` and `procurement_sms_logs`. | **Yes:** `action='receipt_uploaded'`, `actor_role='purchase'` | Master switch disabled. |
| **9c** | **9. Purchase & Receipt (Skip to Store)** | Purchaser skips receipt upload (`-> pending_store_review`) | [`app/Services/ProcurementLifecycleService.php#L1034-L1061`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L1034-L1061) | **Store Keeper** (`store_keeper`) + **Store Manager** (`store_manager`). | SMS | **notifyNextOwner:** `{priority}PR #{req_no} ({site}): Dispatched by Coordinator by {sender_name}. Action needed: Receipt Stage Bypassed — Perform Store Material Intake. Open: {link}`<br/>**Handoff:** `{priority}{req_no} ({site}): Receipt stage bypassed. Material ready for intake. Open: {link}` | Sync. Dual dispatch (`notifyNextOwner` + `triggerHandoff('receipt_skipped_jump_to_store')`). | **Yes:** `action='skip_receipt_jump_to_store'`, `actor_role='purchase'` | Master switch disabled. |
| **9d** | **9. Receipt Verification (Verified)** | Finance verifies fiscal receipt (`pending_receipt_verification -> pending_driver_booking`) | [`app/Services/ProcurementLifecycleService.php#L1148-L1168`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L1148-L1168) | **General Service / Fleet** (`general_service`). | SMS | `{priority}PR #{req_no} ({site}): Fiscal Receipt Uploaded by {sender_name}. Action needed: Receipt Verified — Book Delivery Driver. Open: {link}` | Sync. Marks receipt as verified. | **Yes:** `action='receipt_verified'`, `actor_role='finance'` | Master switch disabled. |
| **9e** | **9. Receipt Verification (Rejected)** | Finance rejects fiscal receipt (`pending_receipt_verification -> pending_receipt_upload`) | [`app/Services/ProcurementLifecycleService.php#L1169-L1178`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L1169-L1178) | **Procurement Officer** (`purchase`). | SMS | `{priority}PR #{req_no} ({site}): Receipt Rejected during Verification by {sender_name}. Action needed: Receipt Rejected — Please Re-upload: {verificationNotes}. Open: {link}` | Sync. Rejection reason appended to SMS. | **Yes:** `action='receipt_rejected'`, `actor_role='finance'`, notes contain verification notes | Master switch disabled. |
| **10a** | **10. Fleet Dispatch (Book Driver)** | General Service books vehicle & driver (`pending_driver_booking -> pending_store_review`) | [`app/Services/ProcurementLifecycleService.php#L1184-L1218`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L1184-L1218) | **Driver** (`driver`, resolved to Employee `$driverEmployeeId`) + Destination **Store Keeper** (`store_keeper`). | SMS | **notifyNextOwner:** `{priority}PR #{req_no} ({site}): Dispatched by Coordinator by {sender_name}. Action needed: Driver Booked — Material Intake on Delivery. Open: {link}`<br/>**Handoff:** `{priority}{req_no} ({site}): Driver dispatched by Logistics ({sender_name}). Materials inbound for delivery. Open: {link}` | Sync. Resolves driver's personal employee mobile phone. Dual dispatch (`notifyNextOwner` + `triggerHandoff('logistics_dispatched_driver')`). | **Yes:** `action='driver_booked'`, `actor_role='general_service'`, notes contain vehicle details | Master switch disabled. |
| **10b** | **10. Fleet Dispatch (Driver Delivered)** | Driver marks materials delivered at site gate (status unchanged) | [`app/Services/ProcurementLifecycleService.php#L1536-L1558`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L1536-L1558) | **Store Keeper** (`store_keeper`). Resolved to destination store staff. | SMS | `{priority}{req_no} ({site}): Driver arrived at site. Please inspect items and complete GRN. Open: {link}` | Sync. Updates `driver_bookings.delivered_at`. Handoff dispatch `driver_delivered`. | **Yes:** `action='driver_marked_delivered'`, `actor_role='driver'` | Master switch disabled. |
| **11a** | **11. Store Intake & GRN (Full Intake)** | Store Keeper records full intake (`pending_store_review -> intake_complete`) | [`app/Services/ProcurementLifecycleService.php#L1224-L1530`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L1224-L1530) | **Requester** (`site_engineer`) + **Coordinator** (`coordinator`) + **Procurement Officer** (`purchase`) + **Finance Head** (`finance_head`). | SMS | **Requester Direct:** `ConstructPro: Your PR #{pr_no} items have been fully received (Slip #{slip_no}) and added to store inventory. Project: {project}.`<br/>**Coordinator Direct:** `ConstructPro: PR #{pr_no} store intake recorded (Slip #{slip_no}). Project: {project}. Status: Full Delivery`<br/>**Handoff:** `{priority}{req_no} ({site}): GRN intake completed by Store Keeper {sender_name}. Ready for 3-way match. Open: {link}`<br/>**Observer (Finance Head):** `{priority}PR #{req_no} ({site}): Store Intake (GRN) Completed by {sender_name}. Action needed: Verify PO, Receipt, and GRN to finalize 3-way match. Open: {link}` | Sync. Increments inventory stock, consumes Model 19 slip sequence, creates `DeliveryReceipt`. Multi-layer broadcast. | **Yes:** `action='store_intake_complete'`, `actor_role='store_keeper'`, notes contain slip number and intake details | Master switch disabled; physical receiving slip upload is mandatory. |
| **11b** | **11. Store Intake & GRN (Partial Intake)** | Store Keeper records partial intake (`pending_store_review -> pending_store_review`) | [`app/Services/ProcurementLifecycleService.php#L1470-L1515`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L1470-L1515) | **Requester** (`site_engineer`) + **Coordinator** (`coordinator`). | SMS | **Requester Direct:** `ConstructPro: Partial delivery received for PR #{pr_no} under Slip #{slip_no}. Remaining balance: {remText}.`<br/>**Coordinator Direct:** `ConstructPro: PR #{pr_no} store intake recorded (Slip #{slip_no}). Project: {project}. Status: Partial Delivery` | Sync. PR remains in `pending_store_review` waiting for remaining items balance. | **Yes:** `action='partial_store_intake'`, `actor_role='store_keeper'`, notes contain detailed remaining item breakdown | Master switch disabled. |
| **12** | **12. 3-Way Match & Completion** | Finance Head closes 3-way match (`intake_complete -> completed`) | [`app/Services/ProcurementLifecycleService.php#L1565-L1586`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L1565-L1586) | **Site Engineer / Requester** (`site_engineer`) + **Procurement Manager** (`purchase_manager`). | SMS | **Handoff:** `{priority}{req_no} ({site}): 3-Way match verified and closed by Finance Head {sender_name}. Lifecycle complete. Open: {link}`<br/>**Observer FYI Requester:** `{priority}PR #{req_no} ({site}): 3-Way Match Verified and Closed by {sender_name}. FYI: None (Lifecycle Complete). Open: {link}` | Sync. Populates `three_way_matched_at` and `three_way_matched_by`. Dual dispatch (`triggerHandoff('finance_3way_closed')` + Observer FYI). | **Yes:** `action='three_way_match_closed'`, `actor_role='finance_head'`, notes confirm PO, GRN, and Receipt match | Master switch disabled. |
| **Spec** | **Emergency Direct Authorization** | Admin/GM fast-tracks PR directly to Finance (`[any] -> pending_payment`) | [`app/Http/Controllers/PurchaseRequestController.php#L1850-L1873`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/PurchaseRequestController.php#L1850-L1873) | **Finance Head** (`finance_head`). | SMS | `ConstructPro: PR #{pr_no} ({amount} ETB) forwarded directly to Finance by {user}. Open: {url}` | Sync. Automatically creates `ExpenseRequest` in `approved_assigned` state. | **Yes:** `action='send_to_finance_direct'`, `actor_role='admin'`, notes contain fast-track amount | Master switch disabled. |

---

## Part B: Per-Role View

This section details every notification each of the 12 key procurement roles **receives** and every notification their actions **trigger**.

### 1. Site Engineer / Requester
- **Notifications They RECEIVE:**
  - **Stage 2b:** Planning Rejection notification (`planning_reject`) with truncated reason (&le;80 chars).
  - **Stage 2c:** Planning approval for Site Petty Cash (`planning_approve_petty_cash`).
  - **Stage 3b:** Coordinator Rejection notification (`coordinator_reject`).
  - **Stage 4b:** Store Manager SIV / Transfer dispatch notification.
  - **Stage 7d:** GM Rejection notification (`gm_reject`) with GM reason.
  - **Stage 11a/11b:** Store Keeper Full Intake (GRN Slip #) or Partial Delivery Remaining Balance SMS.
  - **Stage 12:** 3-Way Match Closed & Lifecycle Complete notification.
- **Notifications Their Actions TRIGGER:**
  - **Stage 1a / 1b:** Creating an MR / PR triggers notification to **Planning Manager** (`mr_submitted`) or **Project Coordinator** (`coordinator`).

### 2. Planning Manager
- **Notifications They RECEIVE:**
  - **Stage 1a:** New Material Request submitted by Site Engineer (`mr_submitted`).
- **Notifications Their Actions TRIGGER:**
  - **Stage 2a:** Approving MR/PR triggers notification to **Project Coordinator** (`planning_forwarded`).
  - **Stage 2b:** Rejecting MR/PR triggers notification to **Site Engineer / Requester**.
  - **Stage 2c:** Approving for Petty Cash triggers notification to **Site Engineer** and in-app queue routing to **Store Keeper**.

### 3. Project Coordinator
- **Notifications They RECEIVE:**
  - **Stage 1b:** Direct PR created by Requester.
  - **Stage 2a:** Planning Manager approves and forwards request (`planning_forwarded`).
  - **Stage 4b:** Store Manager issues SIV / Transfer (receives FYI copy).
  - **Stage 7a / 7b:** GM approval notification (cash or credit approval amount).
  - **Stage 7d:** GM rejection notification (direct SMS alert).
  - **Stage 11a / 11b:** Store Keeper intake recorded (Full Delivery vs Partial Delivery).
- **Notifications Their Actions TRIGGER:**
  - **Stage 3a:** Dispatches request to **Store Manager** (`coordinator_forwarded`).
  - **Stage 3b:** Rejects request, notifying **Site Engineer / Requester**.
  - **Stage 3c:** Routes emergency request to **Store Keeper** for Site Petty Cash.

### 4. Store Manager
- **Notifications They RECEIVE:**
  - **Stage 3a:** Project Coordinator dispatches request to warehouse (`coordinator_forwarded`).
  - **Stage 4c:** Procurement Manager returns request to Store (`send_back_to_store_manager`).
  - **Stage 8b:** Cashier disburses payment (`cashier_disbursed_cash` alert).
  - **Stage 9c:** Purchaser skips receipt upload to store.
- **Notifications Their Actions TRIGGER:**
  - **Stage 4a:** Sourcing Out of Stock triggers notification to **Procurement Manager** (`store_converted_to_pr`).
  - **Stage 4b:** In Stock SIV / Transfer triggers notification to **General Service**, **Driver**, **Store Keeper**, and **Coordinator**.

### 5. Procurement Manager
- **Notifications They RECEIVE:**
  - **Stage 4a:** Store Manager routes out-of-stock request to Procurement (`store_converted_to_pr`).
  - **Stage 6a:** Procurement Officer submits Direct Buy prices (`proc_officer_submitted_direct`).
  - **Stage 6b:** Procurement Officer uploads vendor proformas (`proc_officer_submitted_proforma`).
  - **Stage 7c:** General Manager counters quotations or returns request for price renegotiation (`gm_rejected`).
  - **Stage 7d:** General Manager rejects request.
  - **Stage 12:** Finance Head closes 3-way match (`finance_3way_closed`).
- **Notifications Their Actions TRIGGER:**
  - **Stage 4c:** Returns request to **Store Manager** (`send_back_to_store_manager`).
  - **Stage 5a:** Assigns sourcing method, notifying **Procurement Team / Officer** (`proc_manager_assigned_sourcing`).
  - **Stage 6c:** Forwards price variance / winning proformas to **General Manager** (`market_research_variance`).

### 6. Procurement Team / Officer (Purchaser)
- **Notifications They RECEIVE:**
  - **Stage 5a:** Procurement Manager assigns sourcing methodology (`proc_manager_assigned_sourcing`).
  - **Stage 9e:** Finance rejects uploaded fiscal receipt (`receipt_rejected`).
  - **Stage 11a:** Store Keeper completes GRN intake (`store_keeper_grn`).
- **Notifications Their Actions TRIGGER:**
  - **Stage 6a:** Submits Direct Buy pricing, notifying **Procurement Manager** and **GM**.
  - **Stage 6b:** Submits proformas, notifying **Procurement Manager** and **Market Research**.
  - **Stage 9a:** Uploads fiscal receipt, notifying **General Service** (`purchaser_uploaded_receipt`) and **Store Keeper**.
  - **Stage 9c:** Skips receipt to store, notifying **Store Keeper** and **Store Manager**.

### 7. General Manager (GM)
- **Notifications They RECEIVE:**
  - **Stage 6a:** Direct Buy pricing submitted for executive review.
  - **Stage 6c:** Procurement Manager submits pricing variance and winning proformas (`market_research_variance`).
  - **Stage 8c:** Finance Head returns PR for executive review/reassessment.
- **Notifications Their Actions TRIGGER:**
  - **Stage 7a:** Approves cash purchase, notifying **Finance Head** (`gm_approved`) and **Coordinator**.
  - **Stage 7b:** Approves credit purchase, notifying **Store Keeper**, **Coordinator**, and **Finance Head**.
  - **Stage 7c:** Counters / returns request, notifying **Procurement Manager** (`gm_rejected`).
  - **Stage 7d:** Rejects request, notifying **Procurement Manager**, **Coordinator**, and **Site Engineer**.

### 8. Finance Head
- **Notifications They RECEIVE:**
  - **Stage 7a:** GM approves cash payment (`gm_approved`).
  - **Stage 7b:** GM approves credit purchase (notified via handoff).
  - **Stage 11a:** Store Keeper completes GRN intake, notifying Finance Head to execute 3-way match.
  - **Spec:** Admin / GM fast-tracks emergency PR directly to Finance (`emergency_direct_to_finance`).
- **Notifications Their Actions TRIGGER:**
  - **Stage 7b (Credit):** Authorizes credit COA 5110 ledger, notifying **Store Keeper**.
  - **Stage 8a:** Assigns payment to cashier, notifying the **Assigned Finance Staff / Cashier** directly by phone.
  - **Stage 8c:** Sends PR back to **General Manager** with reasons.
  - **Stage 12:** Confirms 3-way match, notifying **Site Engineer** and **Procurement Manager** (`finance_3way_closed`).

### 9. Cashier / Assigned Finance Staff
- **Notifications They RECEIVE:**
  - **Stage 8a:** Finance Head assigns cash disbursement directly to their user account (`finance_approved_payment`).
- **Notifications Their Actions TRIGGER:**
  - **Stage 8b:** Disburses cash/cheque payment, notifying **Store Keeper** and **Store Manager** (`cashier_disbursed_cash`).

### 10. General Service / Logistics Coordinator
- **Notifications They RECEIVE:**
  - **Stage 4b:** Store Manager creates transfer requiring vehicle dispatch.
  - **Stage 9a:** Purchaser uploads fiscal receipt, requesting driver booking (`purchaser_uploaded_receipt`).
  - **Stage 9d:** Finance verifies fiscal receipt, requesting vehicle booking.
- **Notifications Their Actions TRIGGER:**
  - **Stage 10a:** Books driver and vehicle, notifying the **Driver** directly by phone and destination **Store Keeper** (`logistics_dispatched_driver`).

### 11. Driver
- **Notifications They RECEIVE:**
  - **Stage 4b / 10a:** Logistics assigns material delivery trip with vehicle plate and scheduled time (`logistics_dispatched_driver`).
- **Notifications Their Actions TRIGGER:**
  - **Stage 10b:** Confirms arrival at site gate, notifying **Site Store Keeper** (`driver_delivered`).

### 12. Store Keeper
- **Notifications They RECEIVE:**
  - **Stage 2c:** Emergency MR approved for Site Petty Cash purchase appears in Store Keeper queue.
  - **Stage 4b:** In-stock store transfer dispatched to their destination store.
  - **Stage 7b:** Credit purchase authorized by GM/Finance, ready for intake.
  - **Stage 8b:** Cashier disburses payment, materials ready for delivery intake.
  - **Stage 9a / 9c:** Receipt uploaded or skipped, materials ready for physical intake.
  - **Stage 10a:** Logistics books driver, alert of inbound delivery.
  - **Stage 10b:** Driver marks materials arrived at site gate (`driver_delivered`).
- **Notifications Their Actions TRIGGER:**
  - **Stage 11a:** Completes full store intake (GRN Slip #), notifying **Site Engineer**, **Coordinator**, **Procurement Officer**, and **Finance Head** (`store_keeper_grn`).
  - **Stage 11b:** Records partial store intake, notifying **Site Engineer** and **Coordinator** of remaining balance.

---

## Part C: Branch Coverage

| Branch Name | Notification Exists? | Recipients & Channel | Code Citation & Trigger Details |
|---|---|---|---|
| **1. Planning Reject** | **YES** | Site Engineer / Requester (SMS) | [`PurchaseRequestController.php#L2493`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/PurchaseRequestController.php#L2493), [`MaterialRequestController.php#L434`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/MaterialRequestController.php#L434). Triggered via `PurchaseRequestObserver` and `MaterialRequestObserver`. Rejection reason truncated to &le;80 chars. |
| **2. Assign to Petty Cash** | **YES** | Site Engineer / Requester (SMS) + Store Keeper (In-App Queue) | [`MaterialRequestController.php#L384`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/MaterialRequestController.php#L384). Sets MR status to `approved_for_petty_cash`, triggers `MaterialRequestObserver`. Routes into Store Keeper's Petty Cash Buy Material queue. |
| **3. Store In-Stock (SIV / Transfer)** | **YES** | General Service, Driver, Store Keeper, Requester, Coordinator (SMS) | [`MaterialRequestController.php#L958`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/MaterialRequestController.php#L958), [`PurchaseRequestController.php#L730`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/PurchaseRequestController.php#L730), [`TransferController.php#L180`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/TransferController.php#L180). Multi-stage broadcast across SIV generation and vehicle dispatch. |
| **4. GM Counter (Back to Procurement)** | **YES** | Procurement Manager (SMS) | [`ProcurementLifecycleService.php#L457`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L457). GM decision `send_back`. Sets status to `pending_procurement_manager`. Dual SMS dispatch via `notifyNextOwner` and `triggerHandoff('gm_rejected')`. |
| **5. GM Reject** | **YES** | Procurement Manager, Coordinator, Site Engineer (SMS) | [`ProcurementLifecycleService.php#L423`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L423). Quadruple notification dispatch across direct SMS, handoff service, and model observer. |
| **6. Credit Approval Path** | **YES** | Store Keeper, Coordinator, Finance Head (SMS) | [`ProcurementLifecycleService.php#L603`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L603), [`#L756`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L756). Automatically books `CreditStoreLedger` (COA 5110). Bypasses cashier payment; routes directly to Store Keeper for intake. |
| **7. Rejected-Request Closure or Return** | **YES** | Target Roles (SMS) | Covers: PM send back to store ([`#L202`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L202)), Receipt rejection ([`#L1169`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L1169)), Finance send back to GM ([`#L1689`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L1689)). Every return event logs notes and notifies the upstream role. |
| **8. Urgent-Emergency Requests** | **YES** | Target Roles with `[URGENT-EMERGENCY]` prefix (SMS) | [`ProcurementStatusOwnerService.php#L950-L955`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementStatusOwnerService.php#L950-L955). Detects `priority='emergency'` or `source='Emergency'` and prefixes message with `[URGENT-EMERGENCY]`. Includes dedicated `emergency_direct_to_finance` status. |

---

## Part D: Gaps, Inconsistencies & Technical Risks

### 1. Status Name Divergence: `pending_coordinator_approval`
- **Issue:** In Stage 2, Planning Manager approval sets `purchase_requests.status = 'pending_coordinator_approval'` ([`PurchaseRequestController.php#L2452`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Http/Controllers/PurchaseRequestController.php#L2452)), even though HR has no role in this workflow.
- **Code Reality:** The system sets `current_owner_role = 'coordinator'` and writes action `'planning_approve_to_coordinator'`. The database column value remains `'pending_coordinator_approval'` as a legacy schema artifact.
- **Risk:** High confusion for new developers, external API integrations, or auditors reading raw database records.

### 2. Multi-Layer Notification Duplication
- **Issue:** Several lifecycle transitions trigger SMS dispatches through 2 or 3 separate services simultaneously:
  1. `PurchaseRequestObserver::updated()` &rarr; `ProcurementStatusOwnerService`
  2. `ProcurementLifecycleService::notifyNextOwner()` &rarr; `ProcurementStatusOwnerService`
  3. `ProcurementLifecycleService` &rarr; `ProcurementHandoffNotificationService::triggerHandoff()`
  4. Direct calls to `ProcurementSmsService::notifyRole()`
- **Deduplication Flaw:** Each service uses a different cache key structure:
  - `ProcurementStatusOwnerService`: `proc_sms_sent_{modelType}_{id}_{status}_{phone}`
  - `ProcurementHandoffNotificationService`: `proc_sms_sent_{requestType}_{id}_{handoffKey}_{phone}`
  - `ProcurementSmsService`: `proc_sms_sent_{purchaseRequestId}_{formattedPhone}`
- **Impact:** The deduplication in one layer fails to block dispatches from the other layer, leading to duplicate SMS messages delivered to the same recipient within seconds.

### 3. Synchronous Execution & Lack of Automated Retries
- **Issue:** All SMS transmissions execute synchronously in the web request cycle via cURL / HTTP clients ([`ProcurementSmsService.php#L74`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementSmsService.php#L74), [`ProcurementStatusOwnerService.php#L1039`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementStatusOwnerService.php#L1039)).
- **Risk:** Gateway slowdowns introduce 3–5 second latency to UI clicks. If an SMS fails, it is marked as `failed` in `notification_log` without any background queuing, exponential backoff, or automated retry. Retrying is only possible manually via admin dashboard.

### 4. Hard-Coded Fallback User IDs and Random Cache Keys
- **Issue:** 
  - `ProcurementLifecycleService.php#L151` and lines `#L1289`, `#L1311`, `#L1331`: Hardcoded fallback to `Auth::id() ?: 1` or `1`.
  - `ProcurementSmsService.php#L337`: Hardcoded fallback to `User::find(1)`.
  - `ProcurementStatusOwnerService.php#L1267`: Random key generation for unassigned users: `'user_' . rand(1000, 9999)`.
- **Risk:** If User ID 1 is deleted, deactivated, or not an administrator in production, notifications fail or link to invalid user records.

### 5. Status Transitions Without `pr_workflow_logs` Entries
- **Initial Draft Creation:** When a PR is created via `PurchaseRequestController::store` (`status = 'draft'`), no workflow log entry is written until the draft is submitted.
- **Direct Material Request Submission:** When an MR is submitted, it logs to `activity_log`, but does not populate `pr_workflow_logs` until companion PR synchronization occurs.

### 6. Missing Delivery Confirmation (DLR)
- **Issue:** An SMS marked `sent` in `procurement_sms_logs` and `notification_log` only confirms that the gateway (AfroMessage or Africa's Talking) returned an HTTP 200 response.
- **Risk:** No webhook endpoint exists to record delivery receipts (DLR) when the SMS is actually delivered to the recipient's handset.

### 7. Missing Handoff Trigger on `sendProformasToGm`
- **Issue:** In [`ProcurementLifecycleService.php#L383-L399`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/app/Services/ProcurementLifecycleService.php#L383-L399), `sendProformasToGm` calls `notifyNextOwner`, but omits `triggerHandoff()`, unlike its sibling method `addMarketingVariance`.

---

## Part E: Recommendations (Ranked by Impact)

### Priority 1: Unify Notification Dispatch (Eliminate Redundancy)
- **Impact:** High (prevents duplicate SMS costs and user annoyance).
- **Action:** Consolidate `ProcurementHandoffNotificationService`, `ProcurementStatusOwnerService`, and direct controller SMS into a single pipeline. Route all notifications through `ProcurementTransitionNotifier`, using a single unified deduplication key: `proc_sms_{model}_{id}_{targetRole}_{phone}` with 120s TTL.

### Priority 2: Asynchronous Queued SMS Dispatch
- **Impact:** High (application responsiveness & delivery reliability).
- **Action:** Move SMS dispatch from synchronous cURL execution to Laravel Queued Jobs (`SendProcurementSmsJob`) backed by Redis/database queue. Implement automated retries (3 attempts with 30s exponential backoff) before marking as `failed`.

### Priority 3: Clean Schema Status Naming (`pending_coordinator_approval` &rarr; `pending_coordinator_review`)
- **Impact:** Medium (maintainability & clean domain logic).
- **Action:** Create a database migration to update `purchase_requests.status` from `'pending_coordinator_approval'` to `'pending_coordinator_review'` (or `'pending_coordinator'`), updating `PurchaseRequest::STATUS_PENDING_HR_APPROVAL` with backward-compatible model accessors.

### Priority 4: Replace Hard-Coded User ID 1 Fallbacks
- **Impact:** Medium (system resilience).
- **Action:** Replace `User::find(1)` and `Auth::id() ?: 1` with a query resolving the primary system administrator: `User::role('global_admin')->where('is_active', true)->first()?->id`.

### Priority 5: Implement Webhook Delivery Receipts (DLR)
- **Impact:** Low to Medium (audit certainty).
- **Action:** Expose an AfroMessage DLR webhook route (e.g. `/api/webhooks/afromessage-dlr`) to update `notification_log.status` from `sent` to `delivered` when the handset receives the message.

---

## Summary Statistics

- **Total Distinct Notification Triggers Audited:** 32 notification events across the procurement lifecycle.
- **Notification Count per Stage:**
  - Stage 1 (Request Creation): 2
  - Stage 2 (Planning Review): 3
  - Stage 3 (Project Coordination): 2
  - Stage 4 (Store Stock Check): 3
  - Stage 5 (Sourcing Strategy): 2
  - Stage 6 (Quotations & Variance): 3
  - Stage 7 (GM Decision): 4
  - Stage 8 (Finance Authorization & Payment): 3
  - Stage 9 (Purchase & Fiscal Receipt): 5
  - Stage 10 (Fleet Dispatch): 2
  - Stage 11 (Store Intake & GRN): 2
  - Stage 12 (3-Way Match & Completion): 1
- **Notification Coverage by Role:**
  - Site Engineer / Requester: 7 received, 2 triggered
  - Planning Manager: 1 received, 3 triggered
  - Project Coordinator: 6 received, 3 triggered
  - Store Manager: 4 received, 2 triggered
  - Procurement Manager: 6 received, 3 triggered
  - Procurement Officer: 3 received, 4 triggered
  - General Manager: 3 received, 4 triggered
  - Finance Head: 4 received, 4 triggered
  - Cashier / Finance Staff: 1 received, 1 triggered
  - General Service: 3 received, 1 triggered
  - Driver: 1 received, 1 triggered
  - Store Keeper: 7 received, 2 triggered
- **Count per Channel:**
  - SMS: 32 triggers (100% of external notifications)
  - In-App Queue Dashboard: Accessible by all roles via [`/procurement/my-queue`](file:///home/nfojo/.copilot/chats/b824d92d-57f2-4fef-a1e5-f827278697c0/wechechaerp/resources/views/procurement/lifecycle/my-queue.blade.php)
  - Email: 0 (No email notifications implemented in procurement lifecycle)
- **Number of Technical Gaps & Inconsistencies Identified:** 7 critical architectural findings.
