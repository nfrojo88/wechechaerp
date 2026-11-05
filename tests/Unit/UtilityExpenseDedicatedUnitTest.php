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
}
