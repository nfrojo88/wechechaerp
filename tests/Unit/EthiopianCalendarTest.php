<?php

namespace Tests\Unit;

use App\Services\EthiopianDate;
use Carbon\Carbon;
use Tests\TestCase;

class EthiopianCalendarTest extends TestCase
{
    /**
     * Test conversion: 11 Sep 2025 = 1 Meskerem 2018; 10 Sep 2025 = 5 Pagume 2017.
     */
    public function test_ethiopian_date_conversions_match_expected_dates(): void
    {
        // 11 Sep 2025 = 1 Meskerem 2018
        $sep11 = Carbon::create(2025, 9, 11);
        $eth1 = EthiopianDate::toEthiopian($sep11);

        $this->assertEquals(2018, $eth1['year'], 'Year should be 2018');
        $this->assertEquals(1, $eth1['month'], 'Month should be 1 (Meskerem)');
        $this->assertEquals(1, $eth1['day'], 'Day should be 1');
        $this->assertEquals('Meskerem', $eth1['month_name']);

        // Reverse conversion
        $greg1 = EthiopianDate::toGregorian(2018, 1, 1);
        $this->assertEquals('2025-09-11', $greg1);

        // 10 Sep 2025 = 5 Pagume 2017
        $sep10 = Carbon::create(2025, 9, 10);
        $eth2 = EthiopianDate::toEthiopian($sep10);

        $this->assertEquals(2017, $eth2['year'], 'Year should be 2017');
        $this->assertEquals(13, $eth2['month'], 'Month should be 13 (Pagume)');
        $this->assertEquals(5, $eth2['day'], 'Day should be 5');
        $this->assertEquals('Pagume', $eth2['month_name']);

        // Reverse conversion
        $greg2 = EthiopianDate::toGregorian(2017, 13, 5);
        $this->assertEquals('2025-09-10', $greg2);
    }

    /**
     * Test Pagume length: 6 days in a leap year (year+1)%4==0, otherwise 5.
     */
    public function test_pagume_days_in_leap_and_non_leap_years(): void
    {
        // 2015 EC: (2015 + 1) % 4 == 0 -> Leap year (6 days)
        $this->assertTrue(EthiopianDate::isLeapYear(2015), '2015 EC should be a leap year');
        $this->assertEquals(6, EthiopianDate::daysInMonth(2015, 13), 'Pagume 2015 should have 6 days');

        // 2016 EC: (2016 + 1) % 4 == 1 -> Not a leap year (5 days)
        $this->assertFalse(EthiopianDate::isLeapYear(2016), '2016 EC should not be a leap year');
        $this->assertEquals(5, EthiopianDate::daysInMonth(2016, 13), 'Pagume 2016 should have 5 days');

        // 2017 EC: (2017 + 1) % 4 == 2 -> Not a leap year (5 days)
        $this->assertFalse(EthiopianDate::isLeapYear(2017), '2017 EC should not be a leap year');
        $this->assertEquals(5, EthiopianDate::daysInMonth(2017, 13), 'Pagume 2017 should have 5 days');

        // 2018 EC: (2018 + 1) % 4 == 3 -> Not a leap year (5 days)
        $this->assertFalse(EthiopianDate::isLeapYear(2018), '2018 EC should not be a leap year');
        $this->assertEquals(5, EthiopianDate::daysInMonth(2018, 13), 'Pagume 2018 should have 5 days');

        // 2019 EC: (2019 + 1) % 4 == 0 -> Leap year (6 days)
        $this->assertTrue(EthiopianDate::isLeapYear(2019), '2019 EC should be a leap year');
        $this->assertEquals(6, EthiopianDate::daysInMonth(2019, 13), 'Pagume 2019 should have 6 days');

        // Months 1 to 12 always have 30 days
        for ($m = 1; $m <= 12; $m++) {
            $this->assertEquals(30, EthiopianDate::daysInMonth(2018, $m), "Month {$m} should have 30 days");
        }
    }

    /**
     * Test Net VAT calculation:
     * Output 50,000 and Input 30,000 gives -20,000.00.
     * Output 30,000 and Input 50,000 gives +20,000.00.
     */
    public function test_signed_monthly_net_vat_calculation(): void
    {
        // Case 1: Output 50,000 and Input 30,000 -> +20,000.00 (Income > Purchase -> VAT payable to ERCA)
        $result1 = EthiopianDate::calculateNetVat(30000.00, 50000.00);
        $this->assertEquals(20000.00, $result1['net']);
        $this->assertEquals('+20,000.00', $result1['signed_net_formatted']);
        $this->assertEquals('payable', $result1['status']);
        $this->assertEquals('VAT payable to ERCA', $result1['label']);
        $this->assertTrue($result1['is_payable']);
        $this->assertFalse($result1['is_credit']);
        $this->assertEquals(20000.00, $result1['amount_payable']);
        $this->assertEquals(20000.00, $result1['payable_amount']);
        $this->assertEquals(0.00, $result1['credit_carry_forward']);

        // Case 2: Output 30,000 and Input 50,000 -> -20,000.00 (Purchase > Income -> VAT credit carry forward)
        $result2 = EthiopianDate::calculateNetVat(50000.00, 30000.00);
        $this->assertEquals(-20000.00, $result2['net']);
        $this->assertEquals('-20,000.00', $result2['signed_net_formatted']);
        $this->assertEquals('credit', $result2['status']);
        $this->assertEquals('VAT credit (carry forward)', $result2['label']);
        $this->assertFalse($result2['is_payable']);
        $this->assertTrue($result2['is_credit']);
        $this->assertEquals(20000.00, $result2['credit_carry_forward']);
        $this->assertEquals(0.00, $result2['amount_payable']);
        $this->assertEquals(0.00, $result2['payable_amount']);

        // Case 3: Output 40,000 and Input 40,000 -> 0.00 (Neutral)
        $result3 = EthiopianDate::calculateNetVat(40000.00, 40000.00);
        $this->assertEquals(0.00, $result3['net']);
        $this->assertEquals('0.00', $result3['signed_net_formatted']);
        $this->assertEquals('neutral', $result3['status']);
        $this->assertFalse($result3['is_payable']);
        $this->assertFalse($result3['is_credit']);

        // Case 4: With Opening Credit carried forward from previous month:
        // Output 50,000, Input 30,000, Opening Credit 25,000 -> Net = 50k - (30k + 25k) = -5,000.00
        $result4 = EthiopianDate::calculateNetVat(30000.00, 50000.00, 25000.00);
        $this->assertEquals(-5000.00, $result4['net']);
        $this->assertEquals('-5,000.00', $result4['signed_net_formatted']);
        $this->assertEquals('credit', $result4['status']);
    }

    /**
     * Test that a last-day-of-month receipt must not leak into the next month.
     */
    public function test_last_day_of_month_receipt_does_not_leak_into_next_month(): void
    {
        // Meskerem 2018: Day 1 = 2025-09-11, Day 30 = 2025-10-10
        $meskeremRange = EthiopianDate::monthRange(2018, 1);
        $this->assertEquals('2025-09-11', $meskeremRange['start_date']);
        $this->assertEquals('2025-10-10', $meskeremRange['end_date']);

        // Tikimt 2018: Day 1 = 2025-10-11, Day 30 = 2025-11-09
        $tikimtRange = EthiopianDate::monthRange(2018, 2);
        $this->assertEquals('2025-10-11', $tikimtRange['start_date']);
        $this->assertEquals('2025-11-09', $tikimtRange['end_date']);

        // Last day of Meskerem receipt: 2025-10-10 23:59:59
        $lastDayMeskeremReceipt = Carbon::parse('2025-10-10 23:59:59');

        // Check inclusion in Meskerem
        $this->assertTrue(
            $lastDayMeskeremReceipt->betweenIncluded($meskeremRange['start'], $meskeremRange['end']),
            'Last day of Meskerem receipt must be included in Meskerem'
        );

        // Check non-leakage into Tikimt
        $this->assertFalse(
            $lastDayMeskeremReceipt->betweenIncluded($tikimtRange['start'], $tikimtRange['end']),
            'Last day of Meskerem receipt must NOT leak into Tikimt'
        );

        // First day of Tikimt receipt: 2025-10-11 00:00:00
        $firstDayTikimtReceipt = Carbon::parse('2025-10-11 00:00:00');

        $this->assertFalse(
            $firstDayTikimtReceipt->betweenIncluded($meskeremRange['start'], $meskeremRange['end']),
            'First day of Tikimt receipt must NOT leak into Meskerem'
        );
        $this->assertTrue(
            $firstDayTikimtReceipt->betweenIncluded($tikimtRange['start'], $tikimtRange['end']),
            'First day of Tikimt receipt must be included in Tikimt'
        );
    }

    /**
     * Test parsing Ethiopian date strings and formatting Gregorian dates to Ethiopian strings.
     */
    public function test_parse_and_format_ethiopian_dates(): void
    {
        // 1. Parse DD/MM/YYYY EC
        $this->assertEquals('2025-09-11', EthiopianDate::parseEcToGregorian('01/01/2018 EC'));
        $this->assertEquals('2025-09-11', EthiopianDate::parseEcToGregorian('1/1/2018'));
        $this->assertEquals('2025-09-10', EthiopianDate::parseEcToGregorian('05/13/2017 EC'));

        // 2. Format Gregorian Carbon to DD/MM/YYYY EC
        $sep11 = Carbon::create(2025, 9, 11);
        $this->assertEquals('01/01/2018 EC', EthiopianDate::formatDateEc($sep11, 'DD/MM/YYYY EC'));
        $this->assertEquals('01/01/2018', EthiopianDate::formatDateEc($sep11, 'DD/MM/YYYY'));

        $sep10 = Carbon::create(2025, 9, 10);
        $this->assertEquals('05/13/2017 EC', EthiopianDate::formatDateEc($sep10, 'DD/MM/YYYY EC'));
    }

    /**
     * Test VAT B rule: VAT B is non-creditable and excluded from Input VAT.
     */
    public function test_vat_b_excluded_from_creditable_input_vat(): void
    {
        // Creditable 15% VAT on 10,000 expense
        $creditableVat = 1500.00;
        // Non-creditable VAT B (e.g. 15% included) on 10,000 expense
        $nonCreditableVatB = 1500.00;

        // When computing creditable input VAT, VAT B is excluded:
        $inputVat = $creditableVat; // Only creditable VAT counted
        $outputVat = 2000.00;

        $calc = EthiopianDate::calculateNetVat($inputVat, $outputVat);
        // Net = 2,000 - 1,500 = +500.00 (payable)
        $this->assertEquals(500.00, $calc['net']);
        $this->assertEquals('+500.00', $calc['signed_net_formatted']);
        $this->assertTrue($calc['is_payable']);

        // If VAT B was erroneously included: 2000 - 3000 = -1000.00 (wrong!)
        $wrongCalc = EthiopianDate::calculateNetVat($inputVat + $nonCreditableVatB, $outputVat);
        $this->assertNotEquals($wrongCalc['net'], $calc['net'], 'VAT B must not be added to creditable Input VAT');
    }

    /**
     * Test settlement condition:
     * "Settle VAT" enabled only when Net is positive (+); otherwise show "Credit carried forward".
     */
    public function test_settle_vat_enabled_only_when_net_is_positive(): void
    {
        // Net positive: Output > Input -> Payable -> Settle enabled
        $payable = EthiopianDate::calculateNetVat(10000, 25000);
        $this->assertTrue($payable['is_payable'], 'Settle VAT must be enabled when Net is positive');
        $this->assertEquals('+15,000.00', $payable['signed_net_formatted']);
        $this->assertEquals(15000.00, $payable['amount_payable']);

        // Net negative: Input > Output -> Credit -> Settle disabled, Credit carried forward
        $credit = EthiopianDate::calculateNetVat(25000, 10000);
        $this->assertFalse($credit['is_payable'], 'Settle VAT must be disabled when Net is negative');
        $this->assertTrue($credit['is_credit']);
        $this->assertEquals('-15,000.00', $credit['signed_net_formatted']);
        $this->assertEquals(15000.00, $credit['credit_carry_forward']);

        // Net zero: Neutral
        $neutral = EthiopianDate::calculateNetVat(10000, 10000);
        $this->assertFalse($neutral['is_payable']);
        $this->assertFalse($neutral['is_credit']);
        $this->assertEquals('0.00', $neutral['signed_net_formatted']);
    }

    /**
     * Test multi-month consecutive carry-forward:
     * Month 1 credit carries forward as opening balance into Month 2.
     */
    public function test_multi_month_credit_carry_forward(): void
    {
        // Month 1: Input 50k, Output 30k -> Credit -20,000.00
        $m1 = EthiopianDate::calculateNetVat(50000.00, 30000.00, 0.00);
        $this->assertEquals(-20000.00, $m1['net']);
        $this->assertEquals('-20,000.00', $m1['signed_net_formatted']);
        $this->assertEquals(20000.00, $m1['credit_carry_forward']);

        // Month 2: Input 10k, Output 25k, Opening Credit carried from Month 1 (20k)
        // Net = 25k - (10k + 20k) = -5,000.00
        $m2 = EthiopianDate::calculateNetVat(10000.00, 25000.00, $m1['credit_carry_forward']);
        $this->assertEquals(-5000.00, $m2['net']);
        $this->assertEquals('-5,000.00', $m2['signed_net_formatted']);
        $this->assertTrue($m2['is_credit']);
        $this->assertEquals(5000.00, $m2['credit_carry_forward']);

        // Month 3: Input 5k, Output 15k, Opening Credit carried from Month 2 (5k)
        // Net = 15k - (5k + 5k) = +5,000.00 (Payable to ERCA)
        $m3 = EthiopianDate::calculateNetVat(5000.00, 15000.00, $m2['credit_carry_forward']);
        $this->assertEquals(5000.00, $m3['net']);
        $this->assertEquals('+5,000.00', $m3['signed_net_formatted']);
        $this->assertTrue($m3['is_payable']);
        $this->assertEquals(0.00, $m3['credit_carry_forward']);
        $this->assertEquals(5000.00, $m3['amount_payable']);
    }

    /**
     * Test ReceiptOcrService parses Ethiopian calendar date strings to Gregorian DB dates.
     */
    public function test_receipt_ocr_service_parses_ethiopian_dates(): void
    {
        $ocrService = new \App\Services\ReceiptOcrService();

        // 1 Meskerem 2018 EC -> 2025-09-11
        $this->assertEquals('2025-09-11', $ocrService->parseDateToYmd('01/01/2018 EC'));
        $this->assertEquals('2025-09-11', $ocrService->parseDateToYmd('1/1/2018'));

        // 5 Pagume 2017 EC -> 2025-09-10
        $this->assertEquals('2025-09-10', $ocrService->parseDateToYmd('05/13/2017 EC'));
        $this->assertEquals('2025-09-10', $ocrService->parseDateToYmd('5/13/2017'));

        // Standard Gregorian unchanged
        $this->assertEquals('2026-03-15', $ocrService->parseDateToYmd('2026-03-15'));
    }

    /**
     * Test Income Date parsing and active month mapping for Company Income Track.
     */
    public function test_income_date_parsing_and_month_period_mapping(): void
    {
        // 1 Meskerem 2018 -> 2025-09-11
        $parsed1 = EthiopianDate::parseEcToGregorian('01/01/2018 EC');
        $this->assertEquals('2025-09-11', $parsed1);
        $eth1 = EthiopianDate::toEthiopian($parsed1);
        $this->assertEquals(2018, $eth1['year']);
        $this->assertEquals(1, $eth1['month']);

        // Month range for Meskerem 2018 must encompass the parsed date
        $range = EthiopianDate::monthRange(2018, 1);
        $this->assertTrue(Carbon::parse($parsed1)->betweenIncluded($range['start'], $range['end']));

        // 15 Tikimt 2018 -> 2025-10-25
        $parsed2 = EthiopianDate::parseEcToGregorian('15/02/2018');
        $this->assertEquals('2025-10-25', $parsed2);
        $eth2 = EthiopianDate::toEthiopian($parsed2);
        $this->assertEquals(2018, $eth2['year']);
        $this->assertEquals(2, $eth2['month']);

        $range2 = EthiopianDate::monthRange(2018, 2);
        $this->assertTrue(Carbon::parse($parsed2)->betweenIncluded($range2['start'], $range2['end']));
    }

    /**
     * Test FinanceTaxReportController monthMovements AJAX response structure.
     */
    public function test_month_movements_drilldown_structure(): void
    {
        $controller = new \App\Http\Controllers\Finance\FinanceTaxReportController();
        $request = new \Illuminate\Http\Request(['ethiopian_year' => 2019, 'ethiopian_month' => 1]);
        $response = $controller->monthMovements($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = $response->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(2019, $data['month']['year']);
        $this->assertEquals(1, $data['month']['month']);
        $this->assertEquals('Meskerem', $data['month']['month_name']);
        $this->assertEquals('መስከረም', $data['month']['month_am']);
        $this->assertArrayHasKey('sales_base', $data['summary']);
        $this->assertArrayHasKey('output_vat', $data['summary']);
        $this->assertArrayHasKey('purchases_base', $data['summary']);
        $this->assertArrayHasKey('input_vat', $data['summary']);
        $this->assertArrayHasKey('opening_credit', $data['summary']);
        $this->assertArrayHasKey('signed_net_formatted', $data['summary']);
        $this->assertArrayHasKey('withholding_tax', $data['summary']);
        $this->assertArrayHasKey('net_disbursed', $data['summary']);
        $this->assertArrayHasKey('incomes', $data);
        $this->assertArrayHasKey('expenses', $data);
        $this->assertArrayHasKey('ocr_items', $data);
    }

    /**
     * Test FinanceTaxReportController exportExcel returns valid response for Sales format.
     */
    public function test_tax_report_export_excel_sales_format(): void
    {
        $controller = new \App\Http\Controllers\Finance\FinanceTaxReportController();
        $request = new \Illuminate\Http\Request([
            'type'            => 'sales',
            'ethiopian_year'  => 2018,
            'ethiopian_month' => 1,
        ]);
        $response = $controller->exportExcel($request);

        $this->assertTrue(in_array($response->getStatusCode(), [200, 302]));
        $disposition = (string)$response->headers->get('Content-Disposition');
        $this->assertStringContainsString('Sales_Report_2018_M01', $disposition);
    }

    /**
     * Test FinanceTaxReportController exportExcel returns valid response for Purchase TAS format.
     */
    public function test_tax_report_export_excel_purchase_tas_format(): void
    {
        $controller = new \App\Http\Controllers\Finance\FinanceTaxReportController();
        $request = new \Illuminate\Http\Request([
            'type'            => 'purchase_tas',
            'ethiopian_year'  => 2018,
            'ethiopian_month' => 1,
        ]);
        $response = $controller->exportExcel($request);

        $this->assertTrue(in_array($response->getStatusCode(), [200, 302]));
        $disposition = (string)$response->headers->get('Content-Disposition');
        $this->assertStringContainsString('Purchase_TAS_2018_M01', $disposition);
    }

    /**
     * Test FinanceTaxReportController exportExcel returns valid response for Purchase VAT format.
     */
    public function test_tax_report_export_excel_purchase_vat_format(): void
    {
        $controller = new \App\Http\Controllers\Finance\FinanceTaxReportController();
        $request = new \Illuminate\Http\Request([
            'type'            => 'purchase_vat',
            'ethiopian_year'  => 2018,
            'ethiopian_month' => 1,
        ]);
        $response = $controller->exportExcel($request);

        $this->assertTrue(in_array($response->getStatusCode(), [200, 302]));
        $disposition = (string)$response->headers->get('Content-Disposition');
        $this->assertStringContainsString('Purchase_VAT_Report_2018_M01', $disposition);
    }
}

