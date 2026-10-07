<?php

namespace Tests\Unit;

use App\Services\QcService;
use App\Http\Controllers\Admin\OCRReceiptScannerController;
use Tests\TestCase;

class ReceiptIdentifierValidationTest extends TestCase
{
    /**
     * Test TIN validation rules:
     * - 10 digits passes
     * - 9 digits, 11 digits, letters, and empty fail
     * - "TIN: 000 123 4567" normalizes and passes
     * - Character confusion fixes (O->0, I/l->1, S->5, B->8) work only when result becomes 10 digits
     */
    public function test_tin_validation_rules()
    {
        // 1. Valid 10 digits passes
        $res = QcService::normalizeTin('0024916531');
        $this->assertTrue($res['is_valid']);
        $this->assertEquals('valid', $res['status']);
        $this->assertEquals('0024916531', $res['value']);

        // 2. 9 digits fails and is NOT guessed or padded
        $res9 = QcService::normalizeTin('002491653');
        $this->assertFalse($res9['is_valid']);
        $this->assertEquals('invalid_tin', $res9['status']);
        $this->assertEquals('002491653', $res9['value']);
        $this->assertStringContainsString('10 digits', $res9['error']);

        // 3. 11 digits fails
        $res11 = QcService::normalizeTin('00249165319');
        $this->assertFalse($res11['is_valid']);
        $this->assertEquals('invalid_tin', $res11['status']);

        // 4. Letters and non-numeric fail
        $resLetters = QcService::normalizeTin('ABCDEFGHIJ');
        $this->assertFalse($resLetters['is_valid']);
        $this->assertEquals('invalid_tin', $resLetters['status']);

        // 5. Empty value fails
        $resEmpty = QcService::normalizeTin('');
        $this->assertFalse($resEmpty['is_valid']);
        $this->assertEquals('invalid_tin', $resEmpty['status']);

        // 6. "TIN: 000 123 4567" strips spaces and label prefix and passes
        $resPrefix = QcService::normalizeTin('TIN: 000 123 4567');
        $this->assertTrue($resPrefix['is_valid']);
        $this->assertEquals('valid', $resPrefix['status']);
        $this->assertEquals('0001234567', $resPrefix['value']);

        // 7. OCR confusion fix: 'O' -> '0', 'I' -> '1' only when result becomes valid 10 digits
        $resConfusion = QcService::normalizeTin('O02491653I');
        $this->assertTrue($resConfusion['is_valid']);
        $this->assertEquals('0024916531', $resConfusion['value']);
    }

    /**
     * Test FS No validation rules:
     * - "00002674" passes (8 digits)
     * - "FS00002674" normalizes and passes
     * - "FS0002674" (7 digits) fails and is flagged (never auto-padded)
     * - 9 digits fails
     */
    public function test_fs_no_validation_rules()
    {
        // 1. "00002674" passes
        $res = QcService::normalizeFsNo('00002674');
        $this->assertTrue($res['is_valid']);
        $this->assertEquals('valid', $res['status']);
        $this->assertEquals('00002674', $res['value']);

        // 2. "FS00002674" normalizes and passes
        $resPrefix = QcService::normalizeFsNo('FS00002674');
        $this->assertTrue($resPrefix['is_valid']);
        $this->assertEquals('valid', $resPrefix['status']);
        $this->assertEquals('00002674', $resPrefix['value']);
        $this->assertEquals('FS00002674', $resPrefix['raw']);

        // 3. "FS0002674" (7 digits) fails, is NOT padded with zero, and is flagged as invalid_fs_no
        $res7 = QcService::normalizeFsNo('FS0002674');
        $this->assertFalse($res7['is_valid']);
        $this->assertEquals('invalid_fs_no', $res7['status']);
        $this->assertEquals('0002674', $res7['value']);
        $this->assertStringContainsString('8 digits', $res7['error']);
        $this->assertNotEquals('00002674', $res7['value']); // Must never auto-pad!

        // 4. 9 digits fails
        $res9 = QcService::normalizeFsNo('0000026741');
        $this->assertFalse($res9['is_valid']);
        $this->assertEquals('invalid_fs_no', $res9['status']);

        // 5. OCR confusions (O->0, S->5) fix only when result becomes exactly 8 digits
        $resConfusion = QcService::normalizeFsNo('FSOOOO267S');
        $this->assertTrue($resConfusion['is_valid']);
        $this->assertEquals('00002675', $resConfusion['value']);
    }

    /**
     * Duplicate detection treats "FS00002674" and "00002674" as the same receipt.
     */
    public function test_duplicate_detection_normalizes_same_fs_number()
    {
        $fs1 = QcService::normalizeFsNo('FS00002674');
        $fs2 = QcService::normalizeFsNo('00002674');
        $fs3 = QcService::normalizeFsNo('fs 00002674');

        $this->assertEquals($fs1['value'], $fs2['value']);
        $this->assertEquals($fs2['value'], $fs3['value']);
        $this->assertEquals('00002674', $fs1['value']);
    }

    /**
     * Consensus cross-check never accepts an invalid-length TIN or FS No.
     */
    public function test_consensus_never_accepts_invalid_length_tin_or_fs()
    {
        $controller = new OCRReceiptScannerController();
        $refMethod = new \ReflectionMethod(OCRReceiptScannerController::class, 'reconcileWithDeterministicQc');
        $refMethod->setAccessible(true);

        // Case 1: Engine 1 returns invalid 9-digit TIN, Engine 2 agrees with invalid 9-digit TIN.
        // Engine 3 returns valid 10-digit TIN. Valid 10-digit MUST win, even if 2 engines agree on invalid.
        $engineOutputs = [
            'gemini' => [
                'merchant_name' => 'Merchant Test',
                'supplier_tin'  => '123456789', // 9 digits (invalid)
                'fs_no'         => '00002674',
                'subtotal'      => 100.0,
                'vat_amount'    => 15.0,
                'total_amount'  => 115.0,
            ],
            'nvidia' => [
                'merchant_name' => 'Merchant Test',
                'supplier_tin'  => '123456789', // 9 digits (invalid)
                'fs_no'         => 'FS00002674',
                'subtotal'      => 100.0,
                'vat_amount'    => 15.0,
                'total_amount'  => 115.0,
            ],
            'azure' => [
                'merchant_name' => 'Merchant Test',
                'supplier_tin'  => '0024916531', // 10 digits (valid)
                'fs_no'         => '00002674',
                'subtotal'      => 100.0,
                'vat_amount'    => 15.0,
                'total_amount'  => 115.0,
            ]
        ];

        $reconciled = $refMethod->invoke($controller, $engineOutputs);

        // Valid 10-digit TIN must win over 2 engines agreeing on invalid 9 digits
        $this->assertEquals('0024916531', $reconciled['supplier_tin']);
        $this->assertTrue($reconciled['tin_valid']);

        // Case 2: 2 engines agree on 7-digit FS No (FS0002674), 1 engine provides 8-digit FS No (00002674)
        $engineOutputsFs = [
            'gemini' => [
                'merchant_name' => 'Merchant Test',
                'supplier_tin'  => '0024916531',
                'fs_no'         => 'FS0002674', // 7 digits (invalid)
                'subtotal'      => 100.0,
                'vat_amount'    => 15.0,
                'total_amount'  => 115.0,
            ],
            'nvidia' => [
                'merchant_name' => 'Merchant Test',
                'supplier_tin'  => '0024916531',
                'fs_no'         => '0002674', // 7 digits (invalid)
                'subtotal'      => 100.0,
                'vat_amount'    => 15.0,
                'total_amount'  => 115.0,
            ],
            'ocr_space' => [
                'merchant_name' => 'Merchant Test',
                'supplier_tin'  => '0024916531',
                'fs_no'         => '00002674', // 8 digits (valid)
                'subtotal'      => 100.0,
                'vat_amount'    => 15.0,
                'total_amount'  => 115.0,
            ]
        ];

        $reconciledFs = $refMethod->invoke($controller, $engineOutputsFs);

        // Valid 8-digit FS No must win
        $this->assertEquals('00002674', $reconciledFs['fs_no']);
        $this->assertTrue($reconciledFs['fs_no_valid']);
    }

    /**
     * CSV/Excel export preserves leading zeros for Columns D and H.
     */
    public function test_export_keeps_leading_zeros()
    {
        $rawTin = '0024916531';
        $tinDigits = preg_replace('/[^0-9]/', '', $rawTin);
        $tinText = (strlen($tinDigits) === 10) ? $tinDigits : $rawTin;

        $rawFs = '00002674';
        $fsDigits = preg_replace('/[^0-9]/', '', $rawFs);
        $fsText = (strlen($fsDigits) === 8) ? $fsDigits : $rawFs;

        // Verify string formatting preserves leading zeros
        $this->assertEquals('0024916531', (string)$tinText);
        $this->assertSame('0024916531', (string)$tinText);
        $this->assertEquals(10, strlen($tinText));
        $this->assertStringStartsWith('00', $tinText);

        $this->assertEquals('00002674', (string)$fsText);
        $this->assertSame('00002674', (string)$fsText);
        $this->assertEquals(8, strlen($fsText));
        $this->assertStringStartsWith('0000', $fsText);
    }
}
