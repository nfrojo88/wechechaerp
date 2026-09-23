<?php

namespace App\Services;

use thiagoalessio\TesseractOCR\TesseractOCR;

/**
 * ReceiptParserService
 *
 * Runs Tesseract OCR on an uploaded receipt image and applies
 * regex / heuristic rules to extract structured fields.
 *
 * Accuracy notes:
 *   - Works best on crisp, printed receipts (inkjet/laser).
 *   - Messy, crumpled, or handwritten receipts will have lower accuracy.
 *   - Tune the regex patterns below as you collect more receipt samples.
 */
class ReceiptParserService
{
    /**
     * Parse a receipt file and return extracted fields.
     *
     * @param  string  $absolutePath  Full path to the stored file
     * @param  string  $mimeType      e.g. 'image/jpeg', 'image/png', 'application/pdf'
     * @return array{
     *     vendor_name: string|null,
     *     vendor_tin:  string|null,
     *     receipt_date: string|null,
     *     subtotal:    float,
     *     vat_amount:  float,
     *     total_amount: float,
     *     category:    string,
     *     description: string|null,
     *     raw_text:    string,
     * }
     */
    public function parse(string $absolutePath, string $mimeType): array
    {
        $rawText = $this->runOcr($absolutePath, $mimeType);

        return array_merge(
            $this->extractFields($rawText),
            ['raw_text' => $rawText]
        );
    }

    // ── OCR ──────────────────────────────────────────────────────────────

    private function runOcr(string $path, string $mimeType): string
    {
        // For PDFs: convert first page to PNG via Imagick, then OCR
        if (str_contains($mimeType, 'pdf')) {
            $path = $this->pdfToImage($path);
        }

        try {
            $ocr = new TesseractOCR($path);
            $ocr->lang('eng');          // add 'amh' if you have Amharic trained data
            $ocr->psm(6);               // PSM 6 = assume a uniform block of text
            $ocr->oem(3);               // LSTM engine
            return (string) $ocr->run();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Tesseract OCR failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Convert the first page of a PDF to a PNG using Imagick (php-imagick).
     * Falls back to returning the original path if Imagick is unavailable.
     */
    private function pdfToImage(string $pdfPath): string
    {
        if (!extension_loaded('imagick')) {
            // No conversion possible; Tesseract may still handle simple PDFs
            return $pdfPath;
        }

        try {
            $imagick = new \Imagick();
            $imagick->setResolution(200, 200);
            $imagick->readImage($pdfPath . '[0]'); // first page only
            $imagick->setImageFormat('png');

            $pngPath = $pdfPath . '_page0.png';
            $imagick->writeImage($pngPath);
            $imagick->clear();
            $imagick->destroy();

            return $pngPath;
        } catch (\Throwable $e) {
            return $pdfPath;
        }
    }

    // ── Field extraction ─────────────────────────────────────────────────

    private function extractFields(string $text): array
    {
        $lines = array_filter(
            array_map('trim', explode("\n", $text)),
            fn($l) => strlen($l) > 1
        );
        $lines = array_values($lines);

        return [
            'vendor_name'  => $this->extractVendor($lines),
            'vendor_tin'   => $this->extractTin($text),
            'receipt_date' => $this->extractDate($text),
            'total_amount' => $this->extractTotal($text),
            'vat_amount'   => $this->extractVat($text),
            'subtotal'     => $this->extractSubtotal($text),
            'category'     => $this->guessCategory($text),
            'description'  => $this->buildDescription($lines),
        ];
    }

    /**
     * Vendor: first non-empty, non-numeric line that looks like a business name.
     */
    private function extractVendor(array $lines): ?string
    {
        foreach ($lines as $line) {
            // Skip lines that are only numbers, punctuation, or very short
            if (preg_match('/^[\d\s\W]+$/', $line)) continue;
            if (strlen($line) < 3)                   continue;
            // Skip common header-noise words
            if (preg_match('/^(receipt|invoice|tax|vat|date|time|cashier|tel|phone|tin|reg)/i', $line)) continue;

            return $line;
        }
        return null;
    }

    /**
     * Ethiopian TIN: 10-digit number sometimes labelled TIN/VAT Reg
     */
    private function extractTin(string $text): ?string
    {
        if (preg_match('/(?:TIN|VAT\s*Reg(?:istration)?|Tax\s*ID)[:\s#]*(\d{10})/i', $text, $m)) {
            return $m[1];
        }
        // Fallback: any standalone 10-digit number
        if (preg_match('/\b(\d{10})\b/', $text, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Date: supports dd/mm/yyyy, dd-mm-yyyy, Month dd yyyy, yyyy-mm-dd
     */
    private function extractDate(string $text): ?string
    {
        $patterns = [
            // yyyy-mm-dd or yyyy/mm/dd
            '/\b(\d{4}[-\/]\d{1,2}[-\/]\d{1,2})\b/',
            // dd/mm/yyyy or dd-mm-yyyy
            '/\b(\d{1,2}[-\/]\d{1,2}[-\/]\d{4})\b/',
            // Month dd, yyyy  (e.g. Sep 23, 2026)
            '/\b((?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s+\d{4})\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                try {
                    return \Carbon\Carbon::parse($m[1])->toDateString();
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }
        return null;
    }

    /**
     * Total: look for lines prefixed with TOTAL / GRAND TOTAL then grab the largest currency value.
     */
    private function extractTotal(string $text): float
    {
        // Explicit TOTAL label (highest priority)
        if (preg_match('/(?:grand\s*total|total\s*(?:amount)?|amount\s*due)[:\s]*([\d,]+\.?\d*)/i', $text, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }
        // Fallback: the largest number that looks like currency
        return $this->largestCurrencyValue($text);
    }

    private function extractVat(string $text): float
    {
        if (preg_match('/(?:VAT|Tax\s*Amount|15%)[:\s]*([\d,]+\.?\d*)/i', $text, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }
        return 0.0;
    }

    private function extractSubtotal(string $text): float
    {
        if (preg_match('/(?:sub\s*total|subtotal|net\s*amount)[:\s]*([\d,]+\.?\d*)/i', $text, $m)) {
            return (float) str_replace(',', '', $m[1]);
        }
        // Derive: total - vat
        $total = $this->extractTotal($text);
        $vat   = $this->extractVat($text);
        return max(0.0, $total - $vat);
    }

    /**
     * Collect all currency-looking numbers (≥ 1.00), return the largest.
     */
    private function largestCurrencyValue(string $text): float
    {
        preg_match_all('/\b(\d{1,9}(?:,\d{3})*(?:\.\d{1,2})?)\b/', $text, $matches);
        $values = array_map(fn($v) => (float) str_replace(',', '', $v), $matches[1] ?? []);
        $values = array_filter($values, fn($v) => $v >= 1.0);
        return $values ? max($values) : 0.0;
    }

    /**
     * Guess category from keywords in the OCR text.
     */
    private function guessCategory(string $text): string
    {
        $text = strtolower($text);
        $map  = [
            'labour'        => ['salary', 'wage', 'payroll', 'labour', 'worker'],
            'material'      => ['material', 'steel', 'cement', 'sand', 'aggregate', 'rebar', 'iron', 'lumber', 'paint', 'tile'],
            'equipment'     => ['equipment', 'machine', 'rental', 'hire', 'excavator', 'crane', 'vehicle', 'fuel', 'oil', 'spare'],
            'subcontractor' => ['subcontract', 'sub-contract', 'contractor'],
            'food'          => ['food', 'catering', 'restaurant', 'meal', 'lunch', 'dinner', 'coffee', 'tea', 'hotel', 'hospitality'],
            'transport'     => ['transport', 'freight', 'shipping', 'delivery', 'taxi', 'bus', 'fuel', 'petrol'],
            'utility'       => ['electricity', 'water', 'internet', 'telephone', 'utility', 'bill'],
            'overhead'      => ['office', 'stationery', 'printing', 'rent', 'cleaning'],
        ];

        foreach ($map as $category => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($text, $kw)) {
                    return $category;
                }
            }
        }
        return 'other';
    }

    /**
     * Combine the first few meaningful lines as a human-readable description.
     */
    private function buildDescription(array $lines): ?string
    {
        $meaningful = array_slice(
            array_filter($lines, fn($l) => strlen($l) >= 4 && !preg_match('/^[\d\s\W]+$/', $l)),
            0, 3
        );
        return $meaningful ? implode(' | ', $meaningful) : null;
    }
}
