<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Finance\ProcurementGlPostingService;
use App\Services\Finance\JournalEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ThreeWayMatchPurchaseFlowTest extends TestCase
{
    protected ProcurementGlPostingService $procService;
    protected JournalEngine $journalEngine;
    protected User $user;

        protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::statement('SET @gl_posting_in_progress = 1;');
            DB::statement('SET @period_lock_override = 0;');
        }

        $this->ensureSchema();
        DB::beginTransaction();

        DB::table('tax_rates')->insert([
            'tax_code'              => 'WHT_GOODS_2PCT',
            'name'                  => 'WHT on Goods',
            'category'              => 'withholding_goods',
            'standard_rate_percent' => 2.00,
            'non_tin_rate_percent'  => 30.00,
            'threshold_amount'      => 10000.00,
            'applies_to'            => 'goods',
            'effective_from'        => '2026-07-08',
            'is_active'             => true,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        $this->user = User::create(['name' => 'Proc Officer', 'email' => 'proc@wechecha.com']);
        $this->procService = new ProcurementGlPostingService();
        $this->journalEngine = new JournalEngine();
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
        $requiredTables = ['users', 'chart_of_accounts', 'journal_entries', 'journal_entry_lines', 'financial_periods', 'financial_audit_trails', 'tax_rates'];
        $allExist = true;
        foreach ($requiredTables as $t) {
            if (!Schema::hasTable($t)) {
                $allExist = false;
                break;
            }
        }
        if ($allExist) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') { DB::statement('PRAGMA foreign_keys = OFF;'); } else { DB::statement('SET FOREIGN_KEY_CHECKS=0;'); }

        Schema::dropIfExists('financial_audit_trails');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('procurement_officer');
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

        Schema::create('journal_entries', function ($table) {
            $table->id();
            $table->string('entry_no', 50)->unique();
            $table->date('entry_date');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->unsignedBigInteger('reopened_by')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->boolean('is_reversal')->default(false);
            $table->unsignedBigInteger('reversal_of_id')->nullable();
            $table->unsignedBigInteger('reversed_by_id')->nullable();
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
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('party_type', 50)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_periods', function ($table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('fiscal_year', 20)->default('2026');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
        });

        Schema::create('financial_audit_trails', function ($table) {
            $table->id();
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->string('action', 50);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_role', 50)->nullable();
            $table->text('reason')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('tax_rates', function ($table) {
            $table->id();
            $table->string('tax_code', 50)->unique();
            $table->string('name', 100);
            $table->string('category', 50);
            $table->decimal('standard_rate_percent', 5, 2);
            $table->decimal('non_tin_rate_percent', 5, 2)->default(30.00);
            $table->decimal('threshold_amount', 18, 2)->default(0.00);
            $table->string('applies_to', 20)->default('goods');
            $table->unsignedBigInteger('receivable_account_id')->nullable();
            $table->unsignedBigInteger('payable_account_id')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_end_to_end_three_way_match_purchase_flow_with_zero_double_counting()
    {
        // 1. Establish Accounts
        $bankAcc  = ChartOfAccount::create(['code' => '1011', 'name' => 'CBE Checking', 'type' => 'asset', 'normal_side' => 'debit']);
        $vatAcc   = ChartOfAccount::create(['code' => '1230', 'name' => 'Input VAT Receivable', 'type' => 'asset', 'normal_side' => 'debit']);
        $invAcc   = ChartOfAccount::create(['code' => '1250', 'name' => 'Inventory on Hand', 'type' => 'asset', 'normal_side' => 'debit']);
        $wipAcc   = ChartOfAccount::create(['code' => '1260', 'name' => 'Construction WIP', 'type' => 'asset', 'normal_side' => 'debit']);
        $apAcc    = ChartOfAccount::create(['code' => '2001', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        $grniAcc  = ChartOfAccount::create(['code' => '2003', 'name' => 'GRNI Accrual', 'type' => 'liability', 'normal_side' => 'credit']);
        $whtAcc   = ChartOfAccount::create(['code' => '2600', 'name' => 'WHT Payable', 'type' => 'liability', 'normal_side' => 'credit']);

        // Step 1: Store Intake GRN for ETB 100,000 of Reinforcement Steel
        // DR 1250 Inventory 100,000 / CR 2003 GRNI 100,000
        $this->procService->postGoodsReceipt('GRN-2026-101', 100000.00, 55, 10, null, null, $this->user->id, '2026-10-05');

        $this->assertEquals(100000.00, $invAcc->fresh()->ledger_balance);
        $this->assertEquals(100000.00, $grniAcc->fresh()->ledger_balance);
        $this->assertEquals(0.00, $apAcc->fresh()->ledger_balance);

        // Step 2: Vendor Tax Invoice arrives (Base ETB 100,000 + 15% VAT ETB 15,000 = Gross ETB 115,000)
        // DR 2003 GRNI 100,000 (cleared)
        // DR 1230 Input VAT 15,000
        // CR 2001 AP 115,000
        $this->procService->postVendorInvoiceMatch('INV-STEEL-889', 'GRN-2026-101', 100000.00, 15000.00, 55, $this->user->id, '2026-10-08');

        $this->assertEquals(0.00, $grniAcc->fresh()->ledger_balance, "GRNI must be exactly cleared upon invoice match.");
        $this->assertEquals(15000.00, $vatAcc->fresh()->ledger_balance);
        $this->assertEquals(115000.00, $apAcc->fresh()->ledger_balance);

        // Step 3: Store Issue Voucher SIV to Bole Project Site
        // DR 1260 Construction WIP 100,000 (by project)
        // CR 1250 Inventory on Hand 100,000 (cleared)
        $this->procService->postStoreIssue('SIV-2026-554', 100000.00, 10, 1, 100, $this->user->id, '2026-10-12');

        $this->assertEquals(0.00, $invAcc->fresh()->ledger_balance, "Store inventory is fully relieved upon site issue.");
        $this->assertEquals(100000.00, $wipAcc->fresh()->ledger_balance, "Material cost accumulates in WIP by project.");

        // Step 4: Disburse payment to supplier with 2% WHT calculated on pre-VAT base (2% of 100,000 = 2,000)
        // Gross AP cleared: 115,000
        // Net Wire: 115,000 - 2,000 = 113,000
        // WHT Payable: 2,000
        $this->procService->postSupplierPayment(
            'INV-STEEL-889',
            115000.00,
            100000.00,
            $bankAcc->id,
            55,
            true,
            'goods',
            '2026-10-08',
            $this->user->id,
            '2026-10-20'
        );

        $this->assertEquals(0.00, $apAcc->fresh()->ledger_balance, "Trade payables must be fully cleared.");
        $this->assertEquals(-113000.00, $bankAcc->fresh()->ledger_balance, "Net cash disbursed reflects gross minus pre-VAT WHT.");
        $this->assertEquals(2000.00, $whtAcc->fresh()->ledger_balance, "WHT payable to ERCA.");
        $this->assertEquals(100000.00, $wipAcc->fresh()->ledger_balance, "Zero double-counting: expense exists only once in WIP.");
    }

    public function test_payment_for_pre_8_july_historical_invoice_debits_ap_directly_not_expense()
    {
        $bankAcc = ChartOfAccount::create(['code' => '1011', 'name' => 'CBE Checking', 'type' => 'asset', 'normal_side' => 'debit']);
        $apAcc   = ChartOfAccount::create(['code' => '2001', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_side' => 'credit']);
        $whtAcc  = ChartOfAccount::create(['code' => '2600', 'name' => 'WHT Payable', 'type' => 'liability', 'normal_side' => 'credit']);

        // Suppose invoice was dated 2026-06-25 (Pre-opening date) for ETB 50,000
        // Payment disbursed on 2026-08-15
        $je = $this->procService->postSupplierPayment(
            'HIST-INV-2026-01',
            50000.00,
            50000.00,
            $bankAcc->id,
            12,
            true,
            'goods',
            '2026-06-25',
            $this->user->id,
            '2026-08-15'
        );

        $this->assertEquals('posted', $je->status);
        $debitLine = $je->lines->firstWhere('side', 'debit');

        // Must debit 2001 AP directly!
        $this->assertEquals($apAcc->id, $debitLine->account_id);
        $this->assertEquals(50000.00, $debitLine->amount);
        $this->assertStringContainsString('Pre-8-July Historical Invoice', $debitLine->description);
    }
}
