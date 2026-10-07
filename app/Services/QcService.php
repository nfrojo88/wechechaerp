<?php

namespace App\Services;

use App\Models\SystemSetting;

class QcService
{
    /**
     * Strict Supplier TIN normalization and validation.
     * Rules:
     * - Must be exactly 10 digits, numeric only.
     * - Strip spaces, dashes, dots, and label text ("TIN:", "TIN No", "TIN #", etc.).
     * - Fix common OCR confusions ONLY when the result becomes valid 10 digits:
     *   O/o -> 0, I/l/| -> 1, S/s -> 5, B -> 8.
     * - If still not exactly 10 digits, do NOT guess or pad. Return raw and invalid.
     */
    public static function normalizeTin(?string $rawTin): array
    {
        $raw = trim((string)$rawTin);
        if ($raw === '') {
            return [
                'value'    => '',
                'raw'      => '',
                'is_valid' => false,
                'status'   => 'invalid_tin',
                'error'    => 'Supplier TIN is missing (expected 10 digits)',
                'rule'     => 'tin_is_10_digits',
            ];
        }

        // Strip label text prefixes like "TIN:", "TIN No", "TIN #", "T.I.N."
        $cleaned = preg_replace('/^(?:TIN|TIN\s*NO|TIN\s*#|T\.I\.N\.)[\s\:\-\.]*/i', '', $raw);

        // Strip spaces, dashes, dots, commas, slashes
        $cleaned = preg_replace('/[\s\-\.\,\/\#]/', '', $cleaned);

        // Check if already 10 digits
        if (preg_match('/^\d{10}$/', $cleaned)) {
            return [
                'value'    => $cleaned,
                'raw'      => $raw,
                'is_valid' => true,
                'status'   => 'valid',
                'error'    => null,
                'rule'     => 'tin_is_10_digits',
            ];
        }

        // Fix common OCR character confusions ONLY when the result becomes 10 digits
        $fixed = strtr($cleaned, [
            'O' => '0', 'o' => '0',
            'I' => '1', 'l' => '1', '|' => '1',
            'S' => '5', 's' => '5',
            'B' => '8',
        ]);

        if (preg_match('/^\d{10}$/', $fixed)) {
            return [
                'value'    => $fixed,
                'raw'      => $raw,
                'is_valid' => true,
                'status'   => 'valid',
                'error'    => null,
                'rule'     => 'tin_is_10_digits',
            ];
        }

        // If it still is not exactly 10 digits, do NOT guess or pad
        $digitsOnly = preg_replace('/[^0-9]/', '', $cleaned);
        $len = strlen($digitsOnly);

        return [
            'value'    => $digitsOnly !== '' ? $digitsOnly : $cleaned,
            'raw'      => $raw,
            'is_valid' => false,
            'status'   => 'invalid_tin',
            'error'    => "TIN must be exactly 10 digits (found {$len})",
            'rule'     => 'tin_is_10_digits',
        ];
    }

    /**
     * Strict Fiscal Sales (FS No) receipt number normalization and validation.
     * Rules:
     * - Must be exactly 8 digits, numeric only (configurable FS_NO_LENGTH).
     * - If scanned value has prefix "FS" (case-insensitive), strip prefix and validate remaining digits.
     * - Strip spaces, dashes, dots.
     * - Fix common OCR confusions on digits: O->0, I/l->1, S->5, B->8 only if result becomes 8 digits.
     * - If fewer or more than 8 digits (e.g. 7 digits like FS0002674), do NOT auto-pad or guess!
     */
    public static function normalizeFsNo(?string $rawFsNo, ?int $expectedLength = null, ?bool $allowPrefix = null): array
    {
        $length = $expectedLength ?? (int)SystemSetting::get('fs_no_length', env('FS_NO_LENGTH', 8));
        if ($length <= 0) $length = 8;

        $permitPrefix = $allowPrefix ?? (bool)SystemSetting::get('fs_no_allow_prefix', env('FS_NO_ALLOW_PREFIX', true));

        $raw = trim((string)$rawFsNo);
        if ($raw === '') {
            return [
                'value'    => '',
                'raw'      => '',
                'is_valid' => false,
                'status'   => 'invalid_fs_no',
                'error'    => "FS No is missing (expected {$length} digits)",
                'rule'     => 'fs_no_is_8_digits',
            ];
        }

        $cleaned = $raw;
        if ($permitPrefix) {
            // Strip FS prefix e.g. "FS", "fs", "F.S.", "FS-", "FS:"
            $cleaned = preg_replace('/^(?:FS|F\.S\.)[\s\:\-\.]*/i', '', $cleaned);
        }

        // Strip spaces, dashes, dots, commas, slashes
        $cleaned = preg_replace('/[\s\-\.\,\/\#]/', '', $cleaned);

        // Check if exactly expected digits
        if (preg_match('/^\d{' . $length . '}$/', $cleaned)) {
            return [
                'value'    => $cleaned,
                'raw'      => $raw,
                'is_valid' => true,
                'status'   => 'valid',
                'error'    => null,
                'rule'     => 'fs_no_is_8_digits',
            ];
        }

        // Fix common OCR confusions ONLY when the result becomes valid expected digits
        $fixed = strtr($cleaned, [
            'O' => '0', 'o' => '0',
            'I' => '1', 'l' => '1', '|' => '1',
            'S' => '5', 's' => '5',
            'B' => '8',
        ]);

        if (preg_match('/^\d{' . $length . '}$/', $fixed)) {
            return [
                'value'    => $fixed,
                'raw'      => $raw,
                'is_valid' => true,
                'status'   => 'valid',
                'error'    => null,
                'rule'     => 'fs_no_is_8_digits',
            ];
        }

        // If fewer or more than expected digits, do NOT auto-pad with zeros or guess
        $digitsOnly = preg_replace('/[^0-9]/', '', $cleaned);
        $len = strlen($digitsOnly);

        return [
            'value'    => $digitsOnly !== '' ? $digitsOnly : $cleaned,
            'raw'      => $raw,
            'is_valid' => false,
            'status'   => 'invalid_fs_no',
            'error'    => "FS No must be exactly {$length} digits (found {$len})",
            'rule'     => 'fs_no_is_8_digits',
        ];
    }
}
