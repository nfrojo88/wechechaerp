<?php

namespace Tests\Unit\Finance;

use Tests\TestCase;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\FixedAssetRegister;
use App\Models\FinancialPeriod;
use App\Models\User;
use App\Services\Finance\FixedAssetDepreciationService;
use App\Services\Finance\JournalEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class FixedAssetDepreciationTest extends TestCase
{
    protected FixedAssetDepreciationService $depreciationService;
    protected JournalEngine $journalEngine;
    protected User $user;
    protected ChartOfAccount $expenseAccount;
    protected ChartOfAccount $accumVehicleAccount;
    protected ChartOfAccount $accumEquipmentAccount;
    protected ChartOfAccount $assetAccount;

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

        $this->user = User::create([
            'name'  => 'Asset Accountant',
            'email' => 'asset.accountant@wechecha.com',
            'role'  => 'finance_officer',
        ]);

        $this->assetAccount = ChartOfAccount::create([
            'code' => '1400', 'name' => 'Machinery & Heavy Equipment', 'type' => 'asset', 'normal_side' => 'debit'
        ]);
        $this->accumVehicleAccount = ChartOfAccount::create([
            'code' => '1405', 'name' => 'Accumulated Depr - Machinery & Vehicles', 'type' => 'asset', 'normal_side' => 'credit'
        ]);
        $this->accumEquipmentAccount = ChartOfAccount::create([
            'code' => '1455', 'name' => 'Accumulated Depr - Heavy Equipment', 'type' => 'asset', 'normal_side' => 'credit'
        ]);
        $this->expenseAccount = ChartOfAccount::create([
            'code' => '6850', 'name' => 'Depreciation & Amortization Expense', 'type' => 'expense', 'normal_side' => 'debit'
        ]);

        FinancialPeriod::create([
            'name'       => 'August 2026',
            'code'       => 'FP-2026-08',
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-31',
            'is_locked'  => false,
        ]);

        $this->journalEngine = new JournalEngine();
        $this->depreciationService = new FixedAssetDepreciationService($this->journalEngine);
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
        $requiredTables = ['users', 'chart_of_accounts', 'financial_periods', 'journal_entries', 'journal_entry_lines', 'financial_audit_trails', 'fixed_asset_register'];
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

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        Schema::dropIfExists('financial_audit_trails');
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('fixed_asset_register');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('users');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role')->default('finance_officer');
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

        Schema::create('financial_periods', function ($table) {
            $table->id();
            $table->string('name', 50);
            $table->string('code', 20)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
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
            $table->unsignedBigInteger('original_account_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->string('party_type', 50)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
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

        Schema::create('fixed_asset_register', function ($table) {
            $table->id();
            $table->string('asset_code', 50)->unique();
            $table->string('name', 255);
            $table->unsignedBigInteger('asset_account_id');
            $table->unsignedBigInteger('accumulated_depr_account_id');
            $table->unsignedBigInteger('depreciation_expense_account_id');
            $table->date('acquisition_date');
            $table->decimal('historical_cost', 18, 2);
            $table->decimal('salvage_value', 18, 2)->default(0.00);
            $table->decimal('audited_cutover_accumulated_depreciation', 18, 2)->default(0.00);
            $table->decimal('current_accumulated_depreciation', 18, 2)->default(0.00);
            $table->string('depreciation_method', 30)->default('straight_line');
            $table->decimal('useful_life_years', 5, 2)->nullable();
            $table->decimal('declining_rate_percent', 5, 2)->nullable();
            $table->decimal('total_expected_units', 18, 2)->nullable();
            $table->decimal('cumulative_units_consumed', 18, 2)->default(0.00);
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_straight_line_depreciation_posts_balanced_journal_and_updates_accumulated_depreciation()
    {
        $asset = FixedAssetRegister::create([
            'asset_code'                              => 'EQ-SINO-001',
            'name'                                    => 'Sino Dump Truck 1',
            'acquisition_date'                        => '2025-07-08',
            'historical_cost'                         => 120000.00,
            'salvage_value'                           => 0.00,
            'audited_cutover_accumulated_depreciation'=> 24000.00,
            'current_accumulated_depreciation'        => 24000.00,
            'depreciation_method'                     => 'straight_line',
            'useful_life_years'                       => 5.00, // 60 months => 2,000 / month
            'asset_account_id'                        => $this->assetAccount->id,
            'accumulated_depr_account_id'             => $this->accumVehicleAccount->id,
            'depreciation_expense_account_id'         => $this->expenseAccount->id,
            'project_id'                              => 101,
            'cost_center_id'                          => 5,
            'site_id'                                 => 2,
        ]);

        $je = $this->depreciationService->postMonthlyDepreciation(
            $asset,
            '2026-08-31',
            $this->user->id
        );

        $this->assertNotNull($je);
        $this->assertEquals('posted', $je->status);
        $this->assertCount(2, $je->lines);

        $debitLine = $je->lines->where('side', 'debit')->first();
        $creditLine = $je->lines->where('side', 'credit')->first();

        // DR 6850 Depreciation Expense 2,000.00
        $this->assertEquals($this->expenseAccount->id, $debitLine->account_id);
        $this->assertEquals(2000.00, (float) $debitLine->amount);
        $this->assertEquals(101, $debitLine->project_id);
        $this->assertEquals(5, $debitLine->cost_center_id);
        $this->assertEquals(2, $debitLine->site_id);

        // CR 1405 Accumulated Depreciation 2,000.00
        $this->assertEquals($this->accumVehicleAccount->id, $creditLine->account_id);
        $this->assertEquals(2000.00, (float) $creditLine->amount);
        $this->assertEquals(101, $creditLine->project_id);

        // Verify asset register updated
        $asset->refresh();
        $this->assertEquals(26000.00, (float) $asset->current_accumulated_depreciation);
        $this->assertEquals(94000.00, (float) $asset->net_book_value);
    }

    public function test_straight_line_depreciation_stops_at_salvage_floor()
    {
        $asset = FixedAssetRegister::create([
            'asset_code'                              => 'EQ-GEN-002',
            'name'                                    => 'Diesel Generator 50kVA',
            'acquisition_date'                        => '2024-01-01',
            'historical_cost'                         => 10000.00,
            'salvage_value'                           => 1000.00, // Maximum allowable total depr = 9,000.00
            'audited_cutover_accumulated_depreciation'=> 8500.00,
            'current_accumulated_depreciation'        => 8500.00,
            'depreciation_method'                     => 'straight_line',
            'useful_life_years'                       => 1.00, // Normally 750.00 / month, but only 500.00 left before floor
            'asset_account_id'                        => $this->assetAccount->id,
            'accumulated_depr_account_id'             => $this->accumEquipmentAccount->id,
            'depreciation_expense_account_id'         => $this->expenseAccount->id,
            'project_id'                              => 102,
        ]);

        $je = $this->depreciationService->postMonthlyDepreciation(
            $asset,
            '2026-08-31',
            $this->user->id
        );

        $this->assertNotNull($je);
        // Should cap exactly at 500.00
        $debitLine = $je->lines->where('side', 'debit')->first();
        $this->assertEquals(500.00, (float) $debitLine->amount);

        $asset->refresh();
        $this->assertEquals(9000.00, (float) $asset->current_accumulated_depreciation);
        $this->assertEquals(1000.00, (float) $asset->net_book_value); // equals salvage value

        // Subsequent depreciation call must return null (already at salvage floor)
        $nextJe = $this->depreciationService->postMonthlyDepreciation(
            $asset,
            '2026-09-30',
            $this->user->id
        );
        $this->assertNull($nextJe);
    }

    public function test_units_of_production_depreciation_requires_operating_hours()
    {
        $asset = FixedAssetRegister::create([
            'asset_code'                              => 'EQ-CAT-320',
            'name'                                    => 'CAT 320 Excavator',
            'acquisition_date'                        => '2025-01-01',
            'historical_cost'                         => 500000.00,
            'salvage_value'                           => 50000.00, // Depreciable = 450,000.00
            'audited_cutover_accumulated_depreciation'=> 0.00,
            'current_accumulated_depreciation'        => 0.00,
            'depreciation_method'                     => 'units_of_production',
            'total_expected_units'                    => 10000.00, // 45.00 ETB / hour
            'cumulative_units_consumed'               => 0.00,
            'asset_account_id'                        => $this->assetAccount->id,
            'accumulated_depr_account_id'             => $this->accumEquipmentAccount->id,
            'depreciation_expense_account_id'         => $this->expenseAccount->id,
            'project_id'                              => 103,
        ]);

        // Attempting without operating hours must throw exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("operating hours input");

        $this->depreciationService->postMonthlyDepreciation(
            $asset,
            '2026-08-31',
            $this->user->id,
            null // missing hours
        );
    }

    public function test_units_of_production_calculates_correct_depreciation_and_updates_hours()
    {
        $asset = FixedAssetRegister::create([
            'asset_code'                              => 'EQ-KOMATSU-01',
            'name'                                    => 'Komatsu Wheel Loader',
            'acquisition_date'                        => '2025-01-01',
            'historical_cost'                         => 500000.00,
            'salvage_value'                           => 50000.00, // Depreciable = 450,000.00
            'audited_cutover_accumulated_depreciation'=> 45000.00, // 1,000 cutover hours
            'current_accumulated_depreciation'        => 45000.00,
            'depreciation_method'                     => 'units_of_production',
            'total_expected_units'                    => 10000.00, // Rate = 45.00 ETB / hour
            'cumulative_units_consumed'               => 1000.00,
            'asset_account_id'                        => $this->assetAccount->id,
            'accumulated_depr_account_id'             => $this->accumEquipmentAccount->id,
            'depreciation_expense_account_id'         => $this->expenseAccount->id,
            'project_id'                              => 104,
            'cost_center_id'                          => 7,
            'site_id'                                 => 3,
        ]);

        // 200 hours consumed this month => 200 * 45 = 9,000.00 ETB
        $je = $this->depreciationService->postMonthlyDepreciation(
            $asset,
            '2026-08-31',
            $this->user->id,
            200.00
        );

        $this->assertNotNull($je);
        $this->assertEquals(9000.00, (float) $je->lines->where('side', 'debit')->first()->amount);
        $this->assertEquals(9000.00, (float) $je->lines->where('side', 'credit')->first()->amount);

        $asset->refresh();
        $this->assertEquals(54000.00, (float) $asset->current_accumulated_depreciation);
        $this->assertEquals(1200.00, (float) $asset->cumulative_units_consumed);
        $this->assertEquals(446000.00, (float) $asset->net_book_value);
    }

    public function test_declining_balance_depreciation()
    {
        $asset = FixedAssetRegister::create([
            'asset_code'                              => 'IT-SRV-001',
            'name'                                    => 'Main Database Server',
            'acquisition_date'                        => '2026-01-01',
            'historical_cost'                         => 100000.00,
            'salvage_value'                           => 10000.00,
            'audited_cutover_accumulated_depreciation'=> 0.00,
            'current_accumulated_depreciation'        => 0.00,
            'depreciation_method'                     => 'declining_balance',
            'declining_rate_percent'                  => 20.00, // 20% annual => 100,000 * 0.20 / 12 = 1,666.67
            'asset_account_id'                        => $this->assetAccount->id,
            'accumulated_depr_account_id'             => $this->accumEquipmentAccount->id,
            'depreciation_expense_account_id'         => $this->expenseAccount->id,
            'project_id'                              => 105,
        ]);

        $je = $this->depreciationService->postMonthlyDepreciation(
            $asset,
            '2026-08-31',
            $this->user->id
        );

        $this->assertNotNull($je);
        $debitLine = $je->lines->where('side', 'debit')->first();
        $this->assertEquals(1666.67, (float) $debitLine->amount);

        $asset->refresh();
        $this->assertEquals(1666.67, (float) $asset->current_accumulated_depreciation);
        $this->assertEquals(98333.33, (float) $asset->net_book_value);
    }
}
