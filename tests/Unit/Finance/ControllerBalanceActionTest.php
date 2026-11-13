<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\BankAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\FinancialPeriod;
use App\Models\FinancialAuditTrail;
use App\Models\User;
use App\Services\Finance\JournalEngine;
use App\Services\Finance\ProcurementGlPostingService;
use App\Services\Finance\TaxRemittanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ControllerBalanceActionTest extends TestCase
{
    protected JournalEngine $journalEngine;
    protected ProcurementGlPostingService $procurementPosting;
    protected TaxRemittanceService $taxRemittance;
    protected User $user;
    protected ChartOfAccount $cbeCoa;
    protected BankAccount $cbeBank;
    protected ChartOfAccount $abyssiniaCoa;
    protected BankAccount $abyssiniaBank;
    protected ChartOfAccount $apCoa;
    protected ChartOfAccount $whtCoa;
    protected ChartOfAccount $vatCoa;
    protected ChartOfAccount $expenseCoa;
    protected ChartOfAccount $pettyCashCoa;
    protected ChartOfAccount $revenueCoa;
    protected FinancialPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::statement('SET @gl_posting_in_progress = 0;');
        }

        $this->ensureSchema();
        DB::beginTransaction();
        $this->seedBaselineData();
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
        $requiredTables = ['financial_audit_trails', 'financial_periods', 'journal_entry_lines', 'journal_entries', 'bank_accounts', 'chart_of_accounts', 'users'];
        $allExist = true;
        foreach ($requiredTables as $t) {
            if (!Schema::hasTable($t)) {
                $allExist = false;
                break;
            }
        }
        if ($allExist && Schema::hasColumn('bank_accounts', 'coa_id') && Schema::hasColumn('financial_audit_trails', 'reason') && Schema::hasColumn('journal_entry_lines', 'party_type')) {
            return;
        }

        Schema::dropIfExists('financial_audit_trails');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
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
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number')->unique();
            $table->string('currency', 3)->default('ETB');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->unsignedBigInteger('chart_of_account_id')->nullable()->unique();
            $table->unsignedBigInteger('coa_id')->nullable();
            $table->boolean('is_active')->default(true);
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
            $table->string('party_type', 50)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_periods', function ($table) {
            $table->id();
            $table->string('name', 50);
            $table->string('code', 20)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('fiscal_year', 20)->default('2026');
            $table->string('status', 30)->default('open');
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
            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_coa_block_direct_balance_update;");
            DB::unprepared("
                CREATE TRIGGER trg_coa_block_direct_balance_update
                BEFORE UPDATE ON chart_of_accounts
                FOR EACH ROW
                BEGIN
                    IF NOT (OLD.current_balance <=> NEW.current_balance) THEN
                        IF @gl_posting_in_progress IS NULL OR @gl_posting_in_progress != 1 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Direct update of chart_of_accounts.current_balance is blocked. Balances derive only from posted journals.';
                        END IF;
                    END IF;
                END;
            ");

            DB::unprepared("DROP TRIGGER IF EXISTS trg_bank_account_block_direct_balance_update;");
            DB::unprepared("
                CREATE TRIGGER trg_bank_account_block_direct_balance_update
                BEFORE UPDATE ON bank_accounts
                FOR EACH ROW
                BEGIN
                    IF NOT (OLD.current_balance <=> NEW.current_balance) THEN
                        IF @gl_posting_in_progress IS NULL OR @gl_posting_in_progress != 1 THEN
                            SIGNAL SQLSTATE '45000'
                            SET MESSAGE_TEXT = 'Direct update of bank_accounts.current_balance is blocked. Balances derive only from posted journals.';
                        END IF;
                    END IF;
                END;
            ");
        }
    }

    protected function seedBaselineData(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
        $this->user = User::create([
            'name'  => 'Senior Finance Head',
            'email' => 'finance.head@wechecha.com',
            'role'  => 'finance_head',
        ]);

        $this->period = FinancialPeriod::create([
            'name'       => 'August 2026',
            'code'       => 'FP-2026-08',
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-31',
            'is_locked'  => false,
        ]);

        // Key COA accounts
        $this->cbeCoa = ChartOfAccount::create([
            'code'            => '1011',
            'name'            => 'Commercial Bank of Ethiopia',
            'type'            => 'asset',
            'subtype'         => 'Cash and Cash Equivalents',
            'normal_side'     => 'debit',
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
        ]);

        $this->cbeBank = BankAccount::create([
            'account_name'   => 'CBE Main Checking',
            'account_number' => '100012345678',
            'bank_name'      => 'Commercial Bank of Ethiopia',
            'coa_id'         => $this->cbeCoa->id,
            'current_balance'=> 0.00,
            'is_active'      => true,
        ]);

        $this->abyssiniaCoa = ChartOfAccount::create([
            'code'            => '1012',
            'name'            => 'Bank of Abyssinia',
            'type'            => 'asset',
            'subtype'         => 'Cash and Cash Equivalents',
            'normal_side'     => 'debit',
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
        ]);

        $this->abyssiniaBank = BankAccount::create([
            'account_name'   => 'Abyssinia Operating',
            'account_number' => '200012345678',
            'bank_name'      => 'Bank of Abyssinia',
            'coa_id'         => $this->abyssiniaCoa->id,
            'current_balance'=> 0.00,
            'is_active'      => true,
        ]);

        $this->apCoa = ChartOfAccount::create([
            'code'            => '2001',
            'name'            => 'Accounts Payable - Trade',
            'type'            => 'liability',
            'subtype'         => 'Accounts Payable',
            'normal_side'     => 'credit',
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
        ]);

        $this->whtCoa = ChartOfAccount::create([
            'code'            => '2600',
            'name'            => 'Withholding Tax Payable',
            'type'            => 'liability',
            'subtype'         => 'Tax Payable',
            'normal_side'     => 'credit',
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
        ]);

        $this->vatCoa = ChartOfAccount::create([
            'code'            => '2500',
            'name'            => 'VAT Payable',
            'type'            => 'liability',
            'subtype'         => 'Tax Payable',
            'normal_side'     => 'credit',
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
        ]);

        $this->expenseCoa = ChartOfAccount::create([
            'code'            => '5020',
            'name'            => 'Utility & Operational Expense',
            'type'            => 'expense',
            'subtype'         => 'Operating Expense',
            'normal_side'     => 'debit',
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
        ]);

        $this->pettyCashCoa = ChartOfAccount::create([
            'code'            => '1010',
            'name'            => 'Petty Cash Imprest Fund',
            'type'            => 'asset',
            'subtype'         => 'Cash',
            'normal_side'     => 'debit',
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
        ]);

        $this->revenueCoa = ChartOfAccount::create([
            'code'            => '4010',
            'name'            => 'Construction & Transport Revenue',
            'type'            => 'revenue',
            'subtype'         => 'Revenue',
            'normal_side'     => 'credit',
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
        ]);

        $this->journalEngine = app(JournalEngine::class);
        $this->procurementPosting = app(ProcurementGlPostingService::class);
        $this->taxRemittance = app(TaxRemittanceService::class);

        // Fund CBE Account with initial 500,000 ETB for testing disbursements
        $this->journalEngine->createEntry(
            referenceType: 'initial_funding',
            referenceId: 1,
            description: 'Funding CBE Bank Account for operations',
            lines: [
                ['account_id' => $this->cbeCoa->id, 'side' => 'debit', 'amount' => 500000.00, 'description' => 'Bank debit'],
                ['account_id' => $this->revenueCoa->id, 'side' => 'credit', 'amount' => 500000.00, 'description' => 'Initial capital / revenue'],
            ],
            createdBy: $this->user->id,
            approvedBy: $this->user->id,
            entryDate: '2026-08-01'
        );

        $this->cbeBank->refresh();
        $this->cbeCoa->refresh();
    }

    public function test_1_expense_request_disbursement_posts_balanced_entry_and_updates_bank()
    {
        $initialBankBal = $this->cbeBank->ledger_balance;
        $this->assertEquals(500000.00, $initialBankBal);

        // Real action: Disburse 15,000 ETB expense request
        $je = $this->journalEngine->createEntry(
            referenceType: 'expense_request',
            referenceId: 101,
            description: 'Disbursement for Approved Expense Request #EXP-101',
            lines: [
                ['account_id' => $this->expenseCoa->id, 'side' => 'debit', 'amount' => 15000.00, 'description' => 'Office operational expense'],
                ['account_id' => $this->cbeCoa->id, 'side' => 'credit', 'amount' => 15000.00, 'description' => 'Disbursed via CBE checking'],
            ],
            createdBy: $this->user->id,
            approvedBy: $this->user->id,
            entryDate: '2026-08-05'
        );

        $this->assertEquals('posted', $je->status);
        $this->assertEquals(15000.00, $je->total_debits);
        $this->assertEquals(15000.00, $je->total_credits);

        $this->cbeBank->refresh();
        $this->cbeCoa->refresh();
        $this->assertEquals(485000.00, (float)$this->cbeBank->current_balance);
        $this->assertEquals(485000.00, (float)$this->cbeBank->ledger_balance);
        $this->assertEquals(485000.00, (float)$this->cbeCoa->ledger_balance);
    }

    public function test_2_finance_tax_report_wht_and_vat_remittance_posts_balanced_entry_and_updates_bank()
    {
        $initialBankBal = $this->cbeBank->ledger_balance;

        // Post WHT Remittance to Ministry of Revenues
        $je = $this->taxRemittance->postWhtRemittance(
            whtAmount: 24000.00,
            bankAccountId: $this->cbeCoa->id,
            userId: $this->user->id,
            entryDate: '2026-08-10',
            returnPeriod: '2026-08'
        );

        $this->assertEquals('posted', $je->status);
        $this->assertEquals(24000.00, $je->total_debits);
        $this->assertEquals(24000.00, $je->total_credits);

        // Lines: DR 2600 WHT Payable / CR 1011 CBE Bank
        $debitLine = $je->lines->where('side', 'debit')->first();
        $creditLine = $je->lines->where('side', 'credit')->first();
        $this->assertEquals($this->whtCoa->id, $debitLine->account_id);
        $this->assertEquals($this->cbeCoa->id, $creditLine->account_id);

        $this->cbeBank->refresh();
        $this->assertEquals($initialBankBal - 24000.00, (float)$this->cbeBank->ledger_balance);
        $this->assertEquals($initialBankBal - 24000.00, (float)$this->cbeBank->current_balance);
    }

    public function test_3_credit_store_supplier_payment_posts_balanced_lines_with_wht()
    {
        $initialBankBal = $this->cbeBank->ledger_balance;

        // Supplier invoice gross ETB 100,000, 3% WHT = 3,000, net bank payment = 97,000
        $je = $this->procurementPosting->postSupplierPayment(
            invoiceNo: 'PINV-501',
            grossInvoiceAmount: 100000.00,
            baseAmountBeforeVat: 100000.00,
            bankAccountId: $this->cbeCoa->id,
            supplierId: 1,
            hasValidTin: true,
            itemType: 'services',
            userId: $this->user->id,
            paymentDate: '2026-08-12'
        );

        $this->assertEquals('posted', $je->status);
        $this->assertEquals(100000.00, $je->total_debits);
        $this->assertEquals(100000.00, $je->total_credits);

        // Lines: DR 2001 Accounts Payable 100,000 / CR 1011 CBE Bank 97,000 / CR 2600 WHT Payable 3,000
        $apLine = $je->lines->where('account_id', $this->apCoa->id)->where('side', 'debit')->first();
        $bankLine = $je->lines->where('account_id', $this->cbeCoa->id)->where('side', 'credit')->first();
        $whtLine = $je->lines->where('account_id', $this->whtCoa->id)->where('side', 'credit')->first();

        $this->assertNotNull($apLine);
        $this->assertEquals(100000.00, (float)$apLine->amount);
        $this->assertNotNull($bankLine);
        $this->assertEquals(97000.00, (float)$bankLine->amount);
        $this->assertNotNull($whtLine);
        $this->assertEquals(3000.00, (float)$whtLine->amount);

        $this->cbeBank->refresh();
        $this->assertEquals($initialBankBal - 97000.00, (float)$this->cbeBank->ledger_balance);
        $this->assertEquals($initialBankBal - 97000.00, (float)$this->cbeBank->current_balance);
    }

    public function test_4_utility_expense_disbursement_posts_balanced_entry()
    {
        $initialBankBal = $this->cbeBank->ledger_balance;

        $je = $this->journalEngine->createEntry(
            referenceType: 'utility_payment',
            referenceId: 301,
            description: 'Monthly Electric Utility Payment - Addis Ababa Main Site',
            lines: [
                ['account_id' => $this->expenseCoa->id, 'side' => 'debit', 'amount' => 8500.00, 'description' => 'Electricity utility charge'],
                ['account_id' => $this->cbeCoa->id, 'side' => 'credit', 'amount' => 8500.00, 'description' => 'CBE direct bank transfer'],
            ],
            createdBy: $this->user->id,
            approvedBy: $this->user->id,
            entryDate: '2026-08-14'
        );

        $this->assertEquals('posted', $je->status);
        $this->assertEquals(8500.00, $je->total_debits);
        $this->assertEquals(8500.00, $je->total_credits);

        $this->cbeBank->refresh();
        $this->assertEquals($initialBankBal - 8500.00, (float)$this->cbeBank->ledger_balance);
    }

    public function test_5_income_receipt_posts_balanced_entry_and_increments_bank()
    {
        $initialBankBal = $this->cbeBank->ledger_balance;

        $je = $this->journalEngine->createEntry(
            referenceType: 'client_receipt',
            referenceId: 401,
            description: 'Customer Payment for Equipment Rental Invoice #INV-401',
            lines: [
                ['account_id' => $this->cbeCoa->id, 'side' => 'debit', 'amount' => 80000.00, 'description' => 'Inflow to CBE Operating'],
                ['account_id' => $this->revenueCoa->id, 'side' => 'credit', 'amount' => 80000.00, 'description' => 'Equipment rental revenue'],
            ],
            createdBy: $this->user->id,
            approvedBy: $this->user->id,
            entryDate: '2026-08-16'
        );

        $this->assertEquals('posted', $je->status);
        $this->assertEquals(80000.00, $je->total_debits);
        $this->assertEquals(80000.00, $je->total_credits);

        $this->cbeBank->refresh();
        $this->assertEquals($initialBankBal + 80000.00, (float)$this->cbeBank->ledger_balance);
        $this->assertEquals($initialBankBal + 80000.00, (float)$this->cbeBank->current_balance);
    }

    public function test_6_letter_disbursement_posts_balanced_entry()
    {
        $initialBankBal = $this->cbeBank->ledger_balance;

        $je = $this->journalEngine->createEntry(
            referenceType: 'letter_payment',
            referenceId: 601,
            description: 'Approved Official Letter Disbursement #LTR-601',
            lines: [
                ['account_id' => $this->expenseCoa->id, 'side' => 'debit', 'amount' => 5000.00, 'description' => 'Municipal inspection fee'],
                ['account_id' => $this->cbeCoa->id, 'side' => 'credit', 'amount' => 5000.00, 'description' => 'Disbursed from CBE'],
            ],
            createdBy: $this->user->id,
            approvedBy: $this->user->id,
            entryDate: '2026-08-18'
        );

        $this->assertEquals('posted', $je->status);
        $this->assertEquals(5000.00, $je->total_debits);
        $this->assertEquals(5000.00, $je->total_credits);

        $this->cbeBank->refresh();
        $this->assertEquals($initialBankBal - 5000.00, (float)$this->cbeBank->ledger_balance);
    }

    public function test_7_office_supply_request_disbursement_posts_balanced_entry()
    {
        $initialBankBal = $this->cbeBank->ledger_balance;

        $je = $this->journalEngine->createEntry(
            referenceType: 'office_supply_payment',
            referenceId: 701,
            description: 'Office stationery and printing supplies #OSR-701',
            lines: [
                ['account_id' => $this->expenseCoa->id, 'side' => 'debit', 'amount' => 4200.00, 'description' => 'Office supplies expense'],
                ['account_id' => $this->cbeCoa->id, 'side' => 'credit', 'amount' => 4200.00, 'description' => 'CBE checking payment'],
            ],
            createdBy: $this->user->id,
            approvedBy: $this->user->id,
            entryDate: '2026-08-20'
        );

        $this->assertEquals('posted', $je->status);
        $this->assertEquals(4200.00, $je->total_debits);
        $this->assertEquals(4200.00, $je->total_credits);

        $this->cbeBank->refresh();
        $this->assertEquals($initialBankBal - 4200.00, (float)$this->cbeBank->ledger_balance);
    }

    public function test_8_coa_transfer_between_banks_posts_balanced_entry_and_updates_both_banks()
    {
        $initialCbe = $this->cbeBank->ledger_balance;
        $initialAbyssinia = $this->abyssiniaBank->ledger_balance;
        $this->assertEquals(0.00, $initialAbyssinia);

        // Transfer 100,000 ETB from CBE to Bank of Abyssinia
        $je = $this->journalEngine->createEntry(
            referenceType: 'coa_transfer',
            referenceId: 801,
            description: 'Inter-bank liquidity transfer from CBE to Abyssinia',
            lines: [
                ['account_id' => $this->abyssiniaCoa->id, 'side' => 'debit', 'amount' => 100000.00, 'description' => 'Inflow to Abyssinia'],
                ['account_id' => $this->cbeCoa->id, 'side' => 'credit', 'amount' => 100000.00, 'description' => 'Outflow from CBE'],
            ],
            createdBy: $this->user->id,
            approvedBy: $this->user->id,
            entryDate: '2026-08-22'
        );

        $this->assertEquals('posted', $je->status);
        $this->assertEquals(100000.00, $je->total_debits);
        $this->assertEquals(100000.00, $je->total_credits);

        $this->cbeBank->refresh();
        $this->abyssiniaBank->refresh();

        $this->assertEquals($initialCbe - 100000.00, (float)$this->cbeBank->ledger_balance);
        $this->assertEquals($initialCbe - 100000.00, (float)$this->cbeBank->current_balance);

        $this->assertEquals(100000.00, (float)$this->abyssiniaBank->ledger_balance);
        $this->assertEquals(100000.00, (float)$this->abyssiniaBank->current_balance);
    }

    public function test_9_petty_cash_material_purchase_replenishment_posts_balanced_entry()
    {
        $initialBankBal = $this->cbeBank->ledger_balance;
        $initialPettyCashBal = $this->pettyCashCoa->ledger_balance;

        // Disburse 20,000 ETB to replenish site petty cash fund
        $je = $this->journalEngine->createEntry(
            referenceType: 'petty_cash_replenishment',
            referenceId: 901,
            description: 'Petty cash fund replenishment disbursement #PCR-901',
            lines: [
                ['account_id' => $this->pettyCashCoa->id, 'side' => 'debit', 'amount' => 20000.00, 'description' => 'Petty cash fund debit'],
                ['account_id' => $this->cbeCoa->id, 'side' => 'credit', 'amount' => 20000.00, 'description' => 'Disbursed from CBE checking'],
            ],
            createdBy: $this->user->id,
            approvedBy: $this->user->id,
            entryDate: '2026-08-24'
        );

        $this->assertEquals('posted', $je->status);
        $this->assertEquals(20000.00, $je->total_debits);
        $this->assertEquals(20000.00, $je->total_credits);

        $this->cbeBank->refresh();
        $this->pettyCashCoa->refresh();

        $this->assertEquals($initialBankBal - 20000.00, (float)$this->cbeBank->ledger_balance);
        $this->assertEquals($initialBankBal - 20000.00, (float)$this->cbeBank->current_balance);

        $this->assertEquals($initialPettyCashBal + 20000.00, (float)$this->pettyCashCoa->ledger_balance);
        $this->assertEquals($initialPettyCashBal + 20000.00, (float)$this->pettyCashCoa->current_balance);
    }

    public function test_10_behavior_when_live_posting_is_disabled()
    {
        // When live_posting_enabled is false:
        config(['finance.live_posting_enabled' => false]);
        $this->assertFalse(config('finance.live_posting_enabled'));

        $bankBefore = $this->cbeBank->ledger_balance;
        $storedBefore = $this->cbeBank->current_balance;

        // A draft entry created while live posting is off remains in 'draft' status
        $draftJe = JournalEntry::create([
            'entry_no'       => 'JE-DRAFT-OFF-001',
            'entry_date'     => '2026-08-26',
            'status'         => 'draft',
            'created_by'     => $this->user->id,
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $draftJe->id,
            'account_id'       => $this->expenseCoa->id,
            'side'             => 'debit',
            'amount'           => 12000.00,
            'description'      => 'Unposted expense line',
        ]);
        JournalEntryLine::create([
            'journal_entry_id' => $draftJe->id,
            'account_id'       => $this->cbeCoa->id,
            'side'             => 'credit',
            'amount'           => 12000.00,
            'description'      => 'Unposted bank line',
        ]);

        $this->assertEquals('draft', $draftJe->status);

        // Bank ledger_balance and stored balance MUST NOT change from draft entries
        $this->cbeBank->refresh();
        $this->assertEquals($bankBefore, (float)$this->cbeBank->ledger_balance);
        $this->assertEquals($storedBefore, (float)$this->cbeBank->current_balance);

        // Furthermore, any attempt to directly modify stored balance outside JournalEngine is strictly blocked
        $this->expectException(\RuntimeException::class);
        $this->cbeBank->current_balance -= 12000.00;
        $this->cbeBank->save();
    }
}
