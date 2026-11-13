<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\PettyCashSubledger;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PettyCashSubledgerReconciliationTest extends TestCase
{
    protected ChartOfAccount $pettyControlAcc;
    protected User $cashierBole;
    protected User $cashierKality;

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

        $this->cashierBole = User::create(['name' => 'Abebe Kebede (Bole Cashier)', 'email' => 'abebe@wechecha.com']);
        $this->cashierKality = User::create(['name' => 'Tigist Haile (Kality Cashier)', 'email' => 'tigist@wechecha.com']);

        $this->pettyControlAcc = ChartOfAccount::create([
            'code'               => '1010',
            'name'               => 'Petty Cash Control',
            'type'               => 'asset',
            'normal_side'        => 'debit',
            'is_control_account' => true,
            'subledger_type'     => 'petty_cash',
            'current_balance'    => 250000.00, // Total GL float
        ]);
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
        $requiredTables = ['users', 'journal_entries', 'journal_entry_lines', 'projects', 'chart_of_accounts', 'petty_cash_subledgers'];
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

        Schema::dropIfExists('petty_cash_subledgers');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('site_cashier');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('journal_entries', function ($table) {
            $table->id();
            $table->string('entry_no')->unique();
            $table->date('entry_date');
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('journal_entry_lines', function ($table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->enum('side', ['debit', 'credit']);
            $table->decimal('amount', 18, 2);
            $table->timestamps();
        });

        Schema::create('projects', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
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
            $table->boolean('is_control_account')->default(false);
            $table->string('subledger_type')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('petty_cash_subledgers', function ($table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->unsignedBigInteger('control_account_id');
            $table->unsignedBigInteger('custodian_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->decimal('imprest_limit', 18, 2)->default(0);
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_subledger_totals_reconcile_with_gl_control_account_1010()
    {
        $boleProject = Project::create(['name' => 'Bole Site Project', 'code' => 'PRJ-BOLE']);
        $kalityProject = Project::create(['name' => 'Kality Warehouse Project', 'code' => 'PRJ-KALITY']);

        // Subledger 1: Bole Site Float ETB 150,000
        PettyCashSubledger::create([
            'code'               => 'PC-SUB-BOLE',
            'name'               => 'Bole Site Field Petty Cash',
            'control_account_id' => $this->pettyControlAcc->id,
            'custodian_id'       => $this->cashierBole->id,
            'project_id'         => $boleProject->id,
            'imprest_limit'      => 200000.00,
            'current_balance'    => 150000.00,
        ]);

        // Subledger 2: Kality Site Float ETB 100,000
        PettyCashSubledger::create([
            'code'               => 'PC-SUB-KALITY',
            'name'               => 'Kality Site Field Petty Cash',
            'control_account_id' => $this->pettyControlAcc->id,
            'custodian_id'       => $this->cashierKality->id,
            'project_id'         => $kalityProject->id,
            'imprest_limit'      => 150000.00,
            'current_balance'    => 100000.00,
        ]);

        // Total subledgers: 150,000 + 100,000 = 250,000 == GL 1010 Control Account
        $recon = PettyCashSubledger::reconcileWithControlAccount();

        $this->assertTrue($recon['is_reconciled'], "Subledger total must strictly equal GL 1010 Control Account balance.");
        $this->assertEquals(0.00, $recon['discrepancy'], "Reconciliation discrepancy must be 0.00.");
        $this->assertEquals(250000.00, $recon['subledger_total']);
        $this->assertEquals(250000.00, $recon['control_account_balance']);
    }

    public function test_subledger_flags_discrepancy_when_unreconciled_float_exists()
    {
        // Only 1 subledger of 150,000 created while GL control is 250,000
        PettyCashSubledger::create([
            'code'               => 'PC-SUB-BOLE',
            'name'               => 'Bole Site Field Petty Cash',
            'control_account_id' => $this->pettyControlAcc->id,
            'custodian_id'       => $this->cashierBole->id,
            'current_balance'    => 150000.00,
        ]);

        $recon = PettyCashSubledger::reconcileWithControlAccount();

        $this->assertFalse($recon['is_reconciled'], "Must flag unreconciled difference.");
        $this->assertEquals(100000.00, $recon['discrepancy'], "Discrepancy must equal 100,000 ETB.");
    }
}
