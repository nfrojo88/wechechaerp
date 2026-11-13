<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\ClientIpc;
use App\Models\ClientIpcCollection;
use App\Models\ConstructionContract;
use App\Models\ContractRetentionRelease;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Finance\IpcBillingPostingService;
use App\Services\Finance\JournalEngine;
use App\Services\Finance\RevenueRecognitionService;
use App\Services\Finance\ConstructionWipPostingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

class IpcBillingAndRetentionTest extends TestCase
{
    protected IpcBillingPostingService $ipcService;
    protected RevenueRecognitionService $revRecService;
    protected ConstructionWipPostingService $wipService;
    protected JournalEngine $journalEngine;
    protected User $user;
    protected ConstructionContract $contractA;
    protected ConstructionContract $contractB;
    protected FinancialPeriod $periodAug;
    protected BankAccount $bankAccount;
    protected ChartOfAccount $arAcc;
    protected ChartOfAccount $retentionAcc;
    protected ChartOfAccount $advanceAcc;
    protected ChartOfAccount $assetAcc;
    protected ChartOfAccount $vatAcc;
    protected ChartOfAccount $whtReceivableAcc;
    protected ChartOfAccount $bankAcc;
    protected ChartOfAccount $wipAcc;
    protected ChartOfAccount $cosAcc;
    protected ChartOfAccount $revAcc;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::statement('SET @gl_posting_in_progress = 1;');
            DB::statement('SET @period_lock_override = 0;');
        }

        $this->ensureSchema();
        DB::beginTransaction();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    protected function ensureSchema(): void
    {
        if (Schema::hasTable('journal_entries')) {
            return;
        }
        if (Schema::hasTable('client_ipcs') && Schema::hasTable('journal_entry_lines')) {
            return;
        }

        Schema::dropIfExists('contract_retention_releases');
        Schema::dropIfExists('client_ipc_collections');
        Schema::dropIfExists('client_ipcs');
        Schema::dropIfExists('revenue_recognition_runs');
        Schema::dropIfExists('contract_budget_revisions');
        Schema::dropIfExists('construction_contracts');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('finance_manager');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('chart_of_accounts', function ($table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');
            $table->string('subtype')->nullable();
            $table->string('normal_side')->default('debit');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bank_accounts', function ($table) {
            $table->id();
            $table->string('account_name');
            $table->string('account_number');
            $table->string('bank_name');
            $table->unsignedBigInteger('coa_id')->nullable()->unique();
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('financial_periods', function ($table) {
            $table->id();
            $table->string('name', 50);
            $table->string('code', 20)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
        });

        Schema::create('journal_entries', function ($table) {
            $table->id();
            $table->string('entry_no', 50)->unique();
            $table->date('entry_date');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_locked')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_entry_lines', function ($table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->string('side', 10);
            $table->decimal('amount', 18, 2);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('original_account_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable()->index();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('party_type', 50)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->boolean('is_installed')->default(true);
            $table->boolean('exclude_from_poc')->default(false);
            $table->timestamps();
        });

        Schema::create('construction_contracts', function ($table) {
            $table->id();
            $table->string('contract_number', 50)->unique();
            $table->string('title', 200);
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->decimal('contract_value_exc_vat', 18, 2);
            $table->decimal('vat_rate_percent', 5, 2)->default(15.00);
            $table->decimal('retention_rate_percent', 5, 2)->default(5.00);
            $table->decimal('retention_cap_amount', 18, 2)->nullable();
            $table->decimal('advance_payment_amount', 18, 2)->default(0.00);
            $table->decimal('advance_recovery_rate_percent', 5, 2)->default(20.00);
            $table->string('vat_base_type', 50)->default('net_certified');
            $table->string('vat_base_status', 30)->default('to_be_verified');
            $table->decimal('original_budget_cost', 18, 2)->default(0.00);
            $table->decimal('current_budget_cost', 18, 2)->default(0.00);
            $table->decimal('estimate_to_complete', 18, 2)->default(0.00);
            $table->string('status', 30)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('contract_budget_revisions', function ($table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->string('revision_no', 30);
            $table->string('revision_type', 50);
            $table->decimal('previous_budget_cost', 18, 2);
            $table->decimal('revised_budget_cost', 18, 2);
            $table->decimal('previous_contract_value', 18, 2);
            $table->decimal('revised_contract_value', 18, 2);
            $table->text('justification');
            $table->boolean('is_approved')->default(false);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::create('revenue_recognition_runs', function ($table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('financial_period_id');
            $table->date('as_of_date');
            $table->decimal('contract_price', 18, 2);
            $table->decimal('current_budget_cost', 18, 2);
            $table->decimal('qualifying_incurred_cost', 18, 2);
            $table->decimal('uninstalled_material_cost', 18, 2)->default(0.00);
            $table->decimal('poc_percentage', 8, 4);
            $table->decimal('cumulative_revenue', 18, 2);
            $table->decimal('previously_recognised_revenue', 18, 2)->default(0.00);
            $table->decimal('catch_up_revenue', 18, 2);
            $table->decimal('cumulative_billed', 18, 2)->default(0.00);
            $table->decimal('net_asset_amount', 18, 2)->default(0.00);
            $table->decimal('net_liability_amount', 18, 2)->default(0.00);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->boolean('is_posted')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::create('client_ipcs', function ($table) {
            $table->id();
            $table->string('ipc_no', 50)->unique();
            $table->unsignedBigInteger('contract_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('financial_period_id');
            $table->integer('ipc_number')->default(1);
            $table->date('period_from');
            $table->date('period_to');
            $table->date('submission_date')->nullable();
            $table->json('measurement_sheet_summary')->nullable();
            $table->string('certification_status', 30)->default('draft');
            $table->unsignedBigInteger('certified_by')->nullable();
            $table->timestamp('certified_at')->nullable();
            $table->decimal('gross_certified_amount', 18, 2)->default(0.00);
            $table->decimal('retention_rate_percent', 5, 2)->default(5.00);
            $table->decimal('retention_amount', 18, 2)->default(0.00);
            $table->decimal('advance_recovery_rate_percent', 5, 2)->default(20.00);
            $table->decimal('advance_recovery_amount', 18, 2)->default(0.00);
            $table->string('vat_base_type', 50)->default('net_certified');
            $table->string('vat_base_status', 30)->default('to_be_verified');
            $table->decimal('vat_rate_percent', 5, 2)->default(15.00);
            $table->decimal('vat_amount', 18, 2)->default(0.00);
            $table->decimal('net_certified_payable', 18, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->boolean('is_posted')->default(false);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('client_ipc_collections', function ($table) {
            $table->id();
            $table->unsignedBigInteger('client_ipc_id');
            $table->unsignedBigInteger('bank_account_id');
            $table->date('collection_date');
            $table->decimal('gross_collected_amount', 18, 2);
            $table->decimal('client_wht_rate_percent', 5, 2)->default(2.00);
            $table->decimal('client_wht_amount', 18, 2)->default(0.00);
            $table->string('wht_receipt_number', 100);
            $table->decimal('net_bank_amount', 18, 2);
            $table->boolean('is_posted')->default(false);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });

        Schema::create('contract_retention_releases', function ($table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->string('release_stage', 50);
            $table->decimal('release_amount', 18, 2);
            $table->date('release_date');
            $table->string('certificate_reference', 100)->nullable();
            $table->boolean('is_posted')->default(false);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
        });
    }

    protected function seedTestData(): void
    {
        $this->user = User::create(['name' => 'Finance Director', 'email' => 'cfo@wechecha.com']);

        $this->periodAug = FinancialPeriod::create([
            'name' => 'August 2026', 'code' => 'FP-2026-08', 'start_date' => '2026-08-01', 'end_date' => '2026-08-31', 'status' => 'open'
        ]);

        $this->arAcc            = ChartOfAccount::create(['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset', 'normal_side' => 'debit']);
        $this->retentionAcc     = ChartOfAccount::create(['code' => '1215', 'name' => 'Client Retention Receivable', 'type' => 'asset', 'normal_side' => 'debit']);
        $this->advanceAcc       = ChartOfAccount::create(['code' => '2110', 'name' => 'Client Advance Liability', 'type' => 'liability', 'normal_side' => 'credit']);
        $this->assetAcc         = ChartOfAccount::create(['code' => '1210', 'name' => 'Contract Asset (Unbilled Rev)', 'type' => 'asset', 'normal_side' => 'debit']);
        $this->vatAcc           = ChartOfAccount::create(['code' => '2300', 'name' => 'Output VAT Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        $this->whtReceivableAcc = ChartOfAccount::create(['code' => '1300', 'name' => 'Prepaid / WHT Receivable', 'type' => 'asset', 'normal_side' => 'debit']);
        $this->bankAcc          = ChartOfAccount::create(['code' => '1020', 'name' => 'CBE Main Operations Bank', 'type' => 'asset', 'normal_side' => 'debit']);
        $this->storeAcc         = ChartOfAccount::create(['code' => '1250', 'name' => 'Inventory Stores', 'type' => 'asset', 'normal_side' => 'debit']);
        $this->wipAcc           = ChartOfAccount::create(['code' => '1260', 'name' => 'Construction WIP', 'type' => 'asset', 'normal_side' => 'debit']);
        $this->cosAcc           = ChartOfAccount::create(['code' => '5010', 'name' => 'Construction Cost of Sales', 'type' => 'expense', 'normal_side' => 'debit']);
        $this->revAcc           = ChartOfAccount::create(['code' => '4100', 'name' => 'Construction Income', 'type' => 'revenue', 'normal_side' => 'credit']);

        $this->bankAccount = BankAccount::create([
            'account_name'   => 'CBE Main Operational',
            'account_number' => '10002938481',
            'bank_name'      => 'Commercial Bank of Ethiopia',
            'coa_id'         => $this->bankAcc->id,
            'current_balance'=> 5000000.00,
            'is_active'      => true,
        ]);

        // Contract A: 12M Price, 10M Budget, 2M Advance, 5% Retention, 20% Advance Recovery
        $this->contractA = ConstructionContract::create([
            'contract_number'              => 'CNT-IPC-BOLE',
            'title'                        => 'Bole Highway Project',
            'project_id'                   => 201,
            'contract_value_exc_vat'       => 12000000.00,
            'original_budget_cost'         => 10000000.00,
            'current_budget_cost'          => 10000000.00,
            'advance_payment_amount'       => 2000000.00,
            'advance_recovery_rate_percent'=> 20.00,
            'retention_rate_percent'       => 5.00,
            'retention_cap_amount'         => 600000.00,
            'vat_base_type'                => 'net_certified',
            'vat_base_status'              => 'signed_off', // Signed off
            'status'                       => 'active',
            'created_by'                   => $this->user->id,
        ]);

        // Contract B: 8M Price, 6M Budget, vat_base_status = to_be_verified
        $this->contractB = ConstructionContract::create([
            'contract_number'              => 'CNT-IPC-MEGENAGNA',
            'title'                        => 'Megenagna Overpass Project',
            'project_id'                   => 202,
            'contract_value_exc_vat'       => 8000000.00,
            'original_budget_cost'         => 6000000.00,
            'current_budget_cost'          => 6000000.00,
            'advance_payment_amount'       => 1000000.00,
            'advance_recovery_rate_percent'=> 20.00,
            'retention_rate_percent'       => 5.00,
            'vat_base_type'                => 'net_certified',
            'vat_base_status'              => 'to_be_verified', // NOT signed off!
            'status'                       => 'active',
            'created_by'                   => $this->user->id,
        ]);

        $this->journalEngine = new JournalEngine();
        $this->ipcService    = new IpcBillingPostingService($this->journalEngine);
        $this->revRecService = new RevenueRecognitionService($this->journalEngine);
        $this->wipService    = new ConstructionWipPostingService($this->journalEngine);
    }

    /** @test */
    public function it_blocks_ipc_posting_when_feature_flag_is_off_by_default()
    {
        Config::set('finance.modules.ipc_billing', false);

        $ipc = ClientIpc::create([
            'ipc_no'                 => 'IPC-TEST-01',
            'contract_id'            => $this->contractA->id,
            'project_id'             => 201,
            'financial_period_id'    => $this->periodAug->id,
            'ipc_number'             => 1,
            'period_from'            => '2026-08-01',
            'period_to'              => '2026-08-31',
            'certification_status'   => 'certified',
            'gross_certified_amount' => 1500000.00,
            'created_by'             => $this->user->id,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("IPC GL posting is disabled by feature flag 'finance.modules.ipc_billing'.");

        $this->ipcService->postIpc($ipc->id, $this->user->id, force: false);
    }

    /** @test */
    public function it_blocks_ipc_posting_when_vat_base_status_is_not_signed_off()
    {
        Config::set('finance.modules.ipc_billing', true);

        // Contract B has vat_base_status = 'to_be_verified'
        $ipc = ClientIpc::create([
            'ipc_no'                 => 'IPC-TEST-UNVERIFIED',
            'contract_id'            => $this->contractB->id,
            'project_id'             => 202,
            'financial_period_id'    => $this->periodAug->id,
            'ipc_number'             => 1,
            'period_from'            => '2026-08-01',
            'period_to'              => '2026-08-31',
            'certification_status'   => 'certified',
            'gross_certified_amount' => 1000000.00,
            'created_by'             => $this->user->id,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("vat_base_status is 'to_be_verified'. Formal tax advisor sign-off required");

        $this->ipcService->postIpc($ipc->id, $this->user->id, force: false);
    }

    /** @test */
    public function it_blocks_ipc_posting_when_ipc_is_not_certified()
    {
        Config::set('finance.modules.ipc_billing', true);

        // Uncertified draft IPC
        $ipc = ClientIpc::create([
            'ipc_no'                 => 'IPC-TEST-DRAFT',
            'contract_id'            => $this->contractA->id,
            'project_id'             => 201,
            'financial_period_id'    => $this->periodAug->id,
            'ipc_number'             => 1,
            'period_from'            => '2026-08-01',
            'period_to'              => '2026-08-31',
            'certification_status'   => 'draft',
            'gross_certified_amount' => 1000000.00,
            'created_by'             => $this->user->id,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("is not certified (status: 'draft'). Official supervising engineer certification is strictly required");

        $this->ipcService->postIpc($ipc->id, $this->user->id, force: false);
    }

    /** @test */
    public function it_posts_certified_ipc_billing_with_advance_recovery_and_retention()
    {
        Config::set('finance.modules.ipc_billing', true);

        // Measurement sheet summary detailing items certified
        $measurementSheet = [
            'measurement_book_no' => 'MB-2026-08-A',
            'items' => [
                ['item_code' => '1.01', 'desc' => 'Earthwork Excavation', 'qty' => 5000, 'unit' => 'm3', 'rate' => 200.00, 'amount' => 1000000.00],
                ['item_code' => '2.04', 'desc' => 'C-25 Structural Concrete', 'qty' => 200, 'unit' => 'm3', 'rate' => 5000.00, 'amount' => 1000000.00],
            ],
            'total_measured' => 2000000.00,
        ];

        // Certified IPC for ETB 2,000,000.00
        $ipc = ClientIpc::create([
            'ipc_no'                    => 'IPC-BOLE-001',
            'contract_id'               => $this->contractA->id,
            'project_id'                => 201,
            'financial_period_id'       => $this->periodAug->id,
            'ipc_number'                => 1,
            'period_from'               => '2026-08-01',
            'period_to'                 => '2026-08-31',
            'submission_date'           => '2026-08-31',
            'measurement_sheet_summary' => $measurementSheet,
            'certification_status'      => 'certified',
            'certified_by'              => $this->user->id,
            'certified_at'              => now(),
            'gross_certified_amount'    => 2000000.00,
            'retention_rate_percent'    => 5.00,
            'advance_recovery_rate_percent' => 20.00,
            'vat_base_type'             => 'net_certified',
            'vat_rate_percent'          => 15.00,
            'created_by'                => $this->user->id,
        ]);

        $je = $this->ipcService->postIpc($ipc->id, $this->user->id);
        $this->assertEquals('posted', $je->status);

        // Mathematical Breakdown:
        // Gross Certified: ETB 2,000,000.00
        // Retention 5%:    ETB   100,000.00
        // Advance Rec 20%: ETB   400,000.00
        // Net Pre-VAT Base: 2,000,000 - 100,000 - 400,000 = ETB 1,500,000.00
        // Output VAT 15%:  ETB   225,000.00
        // Net AR Due from Client: 1,500,000 + 225,000 = ETB 1,725,000.00

        $ipcFresh = $ipc->fresh();
        $this->assertTrue($ipcFresh->is_posted);
        $this->assertEquals(100000.00, $ipcFresh->retention_amount);
        $this->assertEquals(400000.00, $ipcFresh->advance_recovery_amount);
        $this->assertEquals(225000.00, $ipcFresh->vat_amount);
        $this->assertEquals(1725000.00, $ipcFresh->net_certified_payable);

        // Verify Journal Lines:
        $arLine = $je->lines->firstWhere('account_id', $this->arAcc->id);
        $this->assertEquals(1725000.00, $arLine->amount);
        $this->assertEquals('debit', $arLine->side);

        $retLine = $je->lines->firstWhere('account_id', $this->retentionAcc->id);
        $this->assertEquals(100000.00, $retLine->amount);
        $this->assertEquals('debit', $retLine->side);

        $advLine = $je->lines->firstWhere('account_id', $this->advanceAcc->id);
        $this->assertEquals(400000.00, $advLine->amount);
        $this->assertEquals('debit', $advLine->side);

        $assetLine = $je->lines->firstWhere('account_id', $this->assetAcc->id);
        $this->assertEquals(2000000.00, $assetLine->amount);
        $this->assertEquals('credit', $assetLine->side);

        $vatLine = $je->lines->firstWhere('account_id', $this->vatAcc->id);
        $this->assertEquals(225000.00, $vatLine->amount);
        $this->assertEquals('credit', $vatLine->side);

        // Total Debits must strictly equal Total Credits
        $totalDebits = $je->lines->where('side', 'debit')->sum('amount');
        $totalCredits = $je->lines->where('side', 'credit')->sum('amount');
        $this->assertEquals(2225000.00, $totalDebits);
        $this->assertEquals(2225000.00, $totalCredits);
    }

    /** @test */
    public function it_posts_ipc_billing_under_full_certified_vat_base_option()
    {
        Config::set('finance.modules.ipc_billing', true);

        // Contract configured for full_certified VAT base
        $this->contractA->update(['vat_base_type' => 'full_certified']);

        $ipc = ClientIpc::create([
            'ipc_no'                 => 'IPC-FULL-VAT-01',
            'contract_id'            => $this->contractA->id,
            'project_id'             => 201,
            'financial_period_id'    => $this->periodAug->id,
            'ipc_number'             => 2,
            'period_from'            => '2026-08-01',
            'period_to'              => '2026-08-31',
            'certification_status'   => 'certified',
            'gross_certified_amount' => 2000000.00,
            'retention_rate_percent' => 5.00,
            'advance_recovery_rate_percent' => 20.00,
            'vat_base_type'          => 'full_certified',
            'vat_rate_percent'       => 15.00,
            'created_by'             => $this->user->id,
        ]);

        $je = $this->ipcService->postIpc($ipc->id, $this->user->id);
        $this->assertEquals('posted', $je->status);

        // Under full_certified:
        // VAT Base = Gross = 2,000,000.00 -> VAT 15% = ETB 300,000.00
        // Net AR = (2,000,000 - 100,000 - 400,000) + 300,000 = ETB 1,800,000.00
        $ipcFresh = $ipc->fresh();
        $this->assertEquals(300000.00, $ipcFresh->vat_amount);
        $this->assertEquals(1800000.00, $ipcFresh->net_certified_payable);

        $vatLine = $je->lines->firstWhere('account_id', $this->vatAcc->id);
        $this->assertEquals(300000.00, $vatLine->amount);
    }

    /** @test */
    public function it_posts_ipc_billing_under_certified_less_advance_only_vat_base_option()
    {
        Config::set('finance.modules.ipc_billing', true);

        // Contract configured for certified_less_advance_only VAT base
        $this->contractA->update(['vat_base_type' => 'certified_less_advance_only']);

        $ipc = ClientIpc::create([
            'ipc_no'                 => 'IPC-ADV-ONLY-VAT-01',
            'contract_id'            => $this->contractA->id,
            'project_id'             => 201,
            'financial_period_id'    => $this->periodAug->id,
            'ipc_number'             => 3,
            'period_from'            => '2026-08-01',
            'period_to'              => '2026-08-31',
            'certification_status'   => 'certified',
            'gross_certified_amount' => 2000000.00,
            'retention_rate_percent' => 5.00,
            'advance_recovery_rate_percent' => 20.00,
            'vat_base_type'          => 'certified_less_advance_only',
            'vat_rate_percent'       => 15.00,
            'created_by'             => $this->user->id,
        ]);

        $je = $this->ipcService->postIpc($ipc->id, $this->user->id);
        $this->assertEquals('posted', $je->status);

        // Under certified_less_advance_only:
        // VAT Base = Gross (2M) - Adv (400k) = 1,600,000.00 -> VAT 15% = ETB 240,000.00
        // Net AR = (2,000,000 - 100,000 - 400,000) + 240,000 = ETB 1,740,000.00
        $ipcFresh = $ipc->fresh();
        $this->assertEquals(240000.00, $ipcFresh->vat_amount);
        $this->assertEquals(1740000.00, $ipcFresh->net_certified_payable);

        $vatLine = $je->lines->firstWhere('account_id', $this->vatAcc->id);
        $this->assertEquals(240000.00, $vatLine->amount);
    }

    /** @test */
    public function it_is_strictly_idempotent_per_ipc()
    {
        Config::set('finance.modules.ipc_billing', true);

        $ipc = ClientIpc::create([
            'ipc_no'                 => 'IPC-BOLE-IDEMP',
            'contract_id'            => $this->contractA->id,
            'project_id'             => 201,
            'financial_period_id'    => $this->periodAug->id,
            'ipc_number'             => 1,
            'period_from'            => '2026-08-01',
            'period_to'              => '2026-08-31',
            'certification_status'   => 'certified',
            'gross_certified_amount' => 1000000.00,
            'created_by'             => $this->user->id,
        ]);

        $firstEntry = $this->ipcService->postIpc($ipc->id, $this->user->id);
        $this->assertEquals('posted', $firstEntry->status);

        $initialLineCount = JournalEntryLine::count();
        $initialArBalance = $this->arAcc->fresh()->ledger_balance;

        // Reposting identical IPC
        $secondEntry = $this->ipcService->postIpc($ipc->id, $this->user->id);

        $this->assertEquals($firstEntry->id, $secondEntry->id);
        $this->assertEquals($initialLineCount, JournalEntryLine::count());
        $this->assertEquals($initialArBalance, $this->arAcc->fresh()->ledger_balance);
    }

    /** @test */
    public function it_posts_client_collection_recognising_client_wht_with_mandatory_receipt_number()
    {
        Config::set('finance.modules.ipc_billing', true);

        // Create and post an IPC for Net AR = 1,725,000.00
        $ipc = ClientIpc::create([
            'ipc_no'                 => 'IPC-BOLE-COLL',
            'contract_id'            => $this->contractA->id,
            'project_id'             => 201,
            'financial_period_id'    => $this->periodAug->id,
            'ipc_number'             => 1,
            'period_from'            => '2026-08-01',
            'period_to'              => '2026-08-31',
            'certification_status'   => 'certified',
            'gross_certified_amount' => 2000000.00,
            'retention_rate_percent' => 5.00,
            'advance_recovery_rate_percent' => 20.00,
            'vat_base_type'          => 'net_certified',
            'vat_rate_percent'       => 15.00,
            'created_by'             => $this->user->id,
        ]);
        $this->ipcService->postIpc($ipc->id, $this->user->id);

        // 1. Missing WHT receipt number must throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Client withholding tax deduction strictly requires an official withholding tax receipt number");

        $this->ipcService->postCollection(
            ipcId: $ipc->id,
            bankAccountId: $this->bankAccount->id,
            grossCollectedAmount: 1725000.00,
            clientWhtRatePercent: 2.00,
            whtReceiptNumber: '', // Empty WHT receipt
            collectionDate: '2026-09-10',
            userId: $this->user->id
        );
    }

    /** @test */
    public function it_successfully_posts_client_collection_with_valid_wht_receipt()
    {
        Config::set('finance.modules.ipc_billing', true);

        $ipc = ClientIpc::create([
            'ipc_no'                 => 'IPC-BOLE-COLL-OK',
            'contract_id'            => $this->contractA->id,
            'project_id'             => 201,
            'financial_period_id'    => $this->periodAug->id,
            'ipc_number'             => 1,
            'period_from'            => '2026-08-01',
            'period_to'              => '2026-08-31',
            'certification_status'   => 'certified',
            'gross_certified_amount' => 2000000.00,
            'retention_rate_percent' => 5.00,
            'advance_recovery_rate_percent' => 20.00,
            'vat_base_type'          => 'net_certified',
            'vat_rate_percent'       => 15.00,
            'created_by'             => $this->user->id,
        ]);
        $this->ipcService->postIpc($ipc->id, $this->user->id);

        // 2. Post valid collection with official WHT receipt
        // Pre-VAT base = 1,725,000 / 1.15 = 1,500,000.00
        // WHT 2% on 1,500,000 = ETB 30,000.00
        // Net Cash wired to bank = 1,725,000 - 30,000 = ETB 1,695,000.00
        $collection = $this->ipcService->postCollection(
            ipcId: $ipc->id,
            bankAccountId: $this->bankAccount->id,
            grossCollectedAmount: 1725000.00,
            clientWhtRatePercent: 2.00,
            whtReceiptNumber: 'WHT-REC-ETH-2026-8812',
            collectionDate: '2026-09-10',
            userId: $this->user->id
        );

        $this->assertTrue($collection->is_posted);
        $this->assertEquals(30000.00, $collection->client_wht_amount);
        $this->assertEquals(1695000.00, $collection->net_bank_amount);
        $this->assertEquals('WHT-REC-ETH-2026-8812', $collection->wht_receipt_number);

        $je = JournalEntry::find($collection->journal_entry_id);
        $this->assertEquals('posted', $je->status);

        $bankLine = $je->lines->firstWhere('account_id', $this->bankAcc->id);
        $this->assertEquals(1695000.00, $bankLine->amount);
        $this->assertEquals('debit', $bankLine->side);

        $whtLine = $je->lines->firstWhere('account_id', $this->whtReceivableAcc->id);
        $this->assertEquals(30000.00, $whtLine->amount);
        $this->assertEquals('debit', $whtLine->side);

        $arLine = $je->lines->firstWhere('account_id', $this->arAcc->id);
        $this->assertEquals(1725000.00, $arLine->amount);
        $this->assertEquals('credit', $arLine->side);

        // Accounts Receivable is cleared back to zero
        $this->assertEquals(0.00, $this->arAcc->fresh()->ledger_balance);
        // Withholding Tax Receivable holds the 30k tax voucher asset
        $this->assertEquals(30000.00, $this->whtReceivableAcc->fresh()->ledger_balance);
    }

    /** @test */
    public function it_posts_retention_release_upon_substantial_completion()
    {
        Config::set('finance.modules.ipc_billing', true);

        // Retain 100k
        $ipc = ClientIpc::create([
            'ipc_no'                 => 'IPC-BOLE-RET',
            'contract_id'            => $this->contractA->id,
            'project_id'             => 201,
            'financial_period_id'    => $this->periodAug->id,
            'ipc_number'             => 1,
            'period_from'            => '2026-08-01',
            'period_to'              => '2026-08-31',
            'certification_status'   => 'certified',
            'gross_certified_amount' => 2000000.00,
            'retention_rate_percent' => 5.00,
            'advance_recovery_rate_percent' => 20.00,
            'vat_base_type'          => 'net_certified',
            'created_by'             => $this->user->id,
        ]);
        $this->ipcService->postIpc($ipc->id, $this->user->id);

        $this->assertEquals(100000.00, $this->retentionAcc->fresh()->ledger_balance);

        // Release 50% upon Substantial Completion (ETB 50,000.00)
        $release = $this->ipcService->postRetentionRelease(
            contractId: $this->contractA->id,
            releaseStage: 'substantial_completion',
            releaseAmount: 50000.00,
            certificateReference: 'TOC-BOLE-SEC1',
            releaseDate: '2026-10-01',
            userId: $this->user->id
        );

        $this->assertTrue($release->is_posted);
        // Retention balance drops from 100k to 50k
        $this->assertEquals(50000.00, $this->retentionAcc->fresh()->ledger_balance);
        // AR holds the 50k receivable due from client
        $arDebit = JournalEntryLine::where('account_id', $this->arAcc->id)->where('journal_entry_id', $release->journal_entry_id)->first();
        $this->assertEquals(50000.00, $arDebit->amount);
    }

    /** @test */
    public function it_nets_contract_level_asset_and_liability_positions_across_two_contracts()
    {
        Config::set('finance.modules.ipc_billing', true);
        Config::set('finance.modules.revenue_recognition', true);

        // --- CONTRACT A (Underbilled Position: Recognised Revenue > Certified Billing) ---
        // 1. Incur 4M on 10M BAC -> PoC = 40% -> Recognised Revenue = 12M * 40% = ETB 4,800,000.00
        $this->wipService->postMaterialIssueToWip($this->contractA->id, 201, 4000000.00, 'SIV-A-01', null, true, $this->user->id, '2026-08-10');
        $this->revRecService->recognizeRevenue($this->contractA->id, $this->periodAug->id, $this->user->id);

        // 2. Bill IPC of 3,000,000.00 on Contract A
        $ipcA = ClientIpc::create([
            'ipc_no'                 => 'IPC-A-BILLING',
            'contract_id'            => $this->contractA->id,
            'project_id'             => 201,
            'financial_period_id'    => $this->periodAug->id,
            'ipc_number'             => 1,
            'period_from'            => '2026-08-01',
            'period_to'              => '2026-08-31',
            'certification_status'   => 'certified',
            'gross_certified_amount' => 3000000.00,
            'retention_rate_percent' => 5.00,
            'advance_recovery_rate_percent' => 0.00,
            'vat_base_type'          => 'net_certified',
            'created_by'             => $this->user->id,
        ]);
        $this->ipcService->postIpc($ipcA->id, $this->user->id);

        $pocA = $this->revRecService->calculatePoc($this->contractA->id, '2026-08-31');
        $this->assertEquals(4800000.00, $pocA['cumulative_revenue']);
        $this->assertEquals(3000000.00, $pocA['cumulative_billed']);
        // Net Contract Asset = 4.8M - 3.0M = ETB 1,800,000.00
        $this->assertEquals(1800000.00, $pocA['net_asset_amount']);
        $this->assertEquals(0.00, $pocA['net_liability_amount']);

        // --- CONTRACT B (Overbilled Position: Certified Billing > Recognised Revenue) ---
        // Contract B vat_base_status signed off for test
        $this->contractB->update(['vat_base_status' => 'signed_off']);
        // Incur 1.5M on 6M BAC -> PoC = 25% -> Revenue = 8M * 25% = ETB 2,000,000.00
        $this->wipService->postMaterialIssueToWip($this->contractB->id, 202, 1500000.00, 'SIV-B-01', null, true, $this->user->id, '2026-08-10');
        $this->revRecService->recognizeRevenue($this->contractB->id, $this->periodAug->id, $this->user->id);

        // Bill IPC of 3,500,000.00 on Contract B (Overbilled by 1.5M)
        $ipcB = ClientIpc::create([
            'ipc_no'                 => 'IPC-B-BILLING',
            'contract_id'            => $this->contractB->id,
            'project_id'             => 202,
            'financial_period_id'    => $this->periodAug->id,
            'ipc_number'             => 1,
            'period_from'            => '2026-08-01',
            'period_to'              => '2026-08-31',
            'certification_status'   => 'certified',
            'gross_certified_amount' => 3500000.00,
            'retention_rate_percent' => 5.00,
            'advance_recovery_rate_percent' => 0.00,
            'vat_base_type'          => 'net_certified',
            'created_by'             => $this->user->id,
        ]);
        $this->ipcService->postIpc($ipcB->id, $this->user->id);

        $pocB = $this->revRecService->calculatePoc($this->contractB->id, '2026-08-31');
        $this->assertEquals(2000000.00, $pocB['cumulative_revenue']);
        $this->assertEquals(3500000.00, $pocB['cumulative_billed']);
        // Net Contract Liability = 3.5M - 2.0M = ETB 1,500,000.00
        $this->assertEquals(0.00, $pocB['net_asset_amount']);
        $this->assertEquals(1500000.00, $pocB['net_liability_amount']);

        // Assert strictly contract-level netting: positions are isolated per contract
        $this->assertNotEquals($pocA['net_asset_amount'], $pocB['net_asset_amount']);
        $this->assertEquals(1800000.00, $pocA['net_asset_amount']);
        $this->assertEquals(1500000.00, $pocB['net_liability_amount']);
    }
}