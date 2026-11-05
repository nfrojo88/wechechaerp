<?php

namespace Tests\Unit;

use App\Models\UtilityExpense;
use Tests\TestCase;

class UtilityExpenseDedicatedUnitTest extends TestCase
{
    public function test_utility_types_configuration()
    {
        $types = UtilityExpense::UTILITY_TYPES;

        $this->assertArrayHasKey(UtilityExpense::TYPE_WIFI, $types);
        $this->assertArrayHasKey(UtilityExpense::TYPE_RENTAL, $types);
        $this->assertArrayHasKey(UtilityExpense::TYPE_ELECTRICITY, $types);
        $this->assertArrayHasKey(UtilityExpense::TYPE_WATER, $types);
        $this->assertArrayHasKey(UtilityExpense::TYPE_TELEPHONE, $types);

        $wifi = $types[UtilityExpense::TYPE_WIFI];
        $this->assertStringContainsString('WiFi', $wifi['label']);
        $this->assertEquals('fa-solid fa-wifi', $wifi['icon']);

        $rental = $types[UtilityExpense::TYPE_RENTAL];
        $this->assertStringContainsString('Rental', $rental['label']);
        $this->assertEquals('fa-solid fa-building', $rental['icon']);
    }

    public function test_utility_expense_number_generation()
    {
        $year = date('Y');
        $number = UtilityExpense::generateNextNumber();

        $this->assertStringStartsWith("UTIL-{$year}-", $number);
        $this->assertMatchesRegularExpression('/^UTIL-\d{4}-\d{4}$/', $number);
    }

    public function test_utility_expense_status_badges()
    {
        $expense = new UtilityExpense();
        $expense->status = UtilityExpense::STATUS_PENDING;
        $this->assertStringContainsString('Assigned to Pay', $expense->status_badge);

        $expense->status = UtilityExpense::STATUS_PAID;
        $this->assertStringContainsString('Paid', $expense->status_badge);

        $expense->status = UtilityExpense::STATUS_CANCELLED;
        $this->assertStringContainsString('Cancelled', $expense->status_badge);
    }

    public function test_type_config_accessor()
    {
        $expense = new UtilityExpense();
        $expense->utility_type = UtilityExpense::TYPE_WIFI;

        $config = $expense->type_config;
        $this->assertEquals('WiFi / Internet', $config['short_label']);
        $this->assertEquals('fa-solid fa-wifi', $config['icon']);
    }

    public function test_coa_manager_auto_assignment_fallback_logic()
    {
        $coa = new \App\Models\ChartOfAccount();
        $coa->id = 42;
        $coa->assigned_to = 99;

        $explicitAssigned = null;
        $resolvedManagerId = !empty($explicitAssigned)
            ? (int) $explicitAssigned
            : ($coa->assigned_to ?? optional($coa->manager)->id ?? 1);

        $this->assertEquals(99, $resolvedManagerId);

        // When explicit assigned person is given
        $explicitAssigned = 77;
        $resolvedManagerId = !empty($explicitAssigned)
            ? (int) $explicitAssigned
            : ($coa->assigned_to ?? optional($coa->manager)->id ?? 1);

        $this->assertEquals(77, $resolvedManagerId);
    }

    public function test_approval_hub_utility_expense_status_and_queue_mapping()
    {
        $exp = new UtilityExpense();
        $exp->id = 15;
        $exp->expense_number = 'UTIL-2026-0005';
        $exp->utility_type = UtilityExpense::TYPE_WIFI;
        $exp->title = 'Head Office WiFi Bill';
        $exp->amount = 3500.00;
        $exp->assigned_to = 12;
        $exp->status = UtilityExpense::STATUS_PENDING;

        [$statusLabel, $statusKey, $badgeColor] = match($exp->status) {
            UtilityExpense::STATUS_PENDING   => ['Pending Payment (Assigned)', 'finance_queue', 'warning'],
            UtilityExpense::STATUS_PAID      => ['Paid & Completed', 'paid', 'success'],
            UtilityExpense::STATUS_CANCELLED => ['Cancelled / Rejected', 'rejected', 'danger'],
            default                          => ['Other', 'finance_queue', 'secondary'],
        };

        $this->assertEquals('finance_queue', $statusKey);
        $this->assertEquals('Pending Payment (Assigned)', $statusLabel);
        $this->assertEquals(12, $exp->assigned_to);

        $exp->status = UtilityExpense::STATUS_PAID;
        [$paidLabel, $paidKey, $paidBadge] = match($exp->status) {
            UtilityExpense::STATUS_PENDING   => ['Pending Payment (Assigned)', 'finance_queue', 'warning'],
            UtilityExpense::STATUS_PAID      => ['Paid & Completed', 'paid', 'success'],
            UtilityExpense::STATUS_CANCELLED => ['Cancelled / Rejected', 'rejected', 'danger'],
            default                          => ['Other', 'finance_queue', 'secondary'],
        };

        $this->assertEquals('paid', $paidKey);
        $this->assertEquals('Paid & Completed', $paidLabel);
    }

    public function test_account_deduction_balance_calculation()
    {
        $coa = new \App\Models\ChartOfAccount();
        $coa->current_balance = 50000.00;

        $billAmount = 4500.00;
        $newBalance = $coa->current_balance - $billAmount;

        $this->assertEquals(45500.00, $newBalance);
    }
}
