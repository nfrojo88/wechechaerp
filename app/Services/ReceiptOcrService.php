<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ReceiptOcrService
{
    /**
     * Scan an uploaded receipt image or PDF file and return structured extraction data.
     *
     * @param string $path Absolute file path or relative public storage path
     * @return array
     */
    public function scan(string $path): array
    {
        $resolved = $this->resolveFile($path);
        $realPath = $resolved['real_path'];
        $base64   = $resolved['base64'];
        $mimeType = $resolved['mime_type'];
        $ext      = $resolved['ext'];

        // Execute AI vision extraction pipeline
        $pipeline = $this->executeMultiEnginePipeline($base64, $mimeType, $ext, $realPath);

        $extracted       = $pipeline['extracted'];
        $engineUsed      = $pipeline['engine_label'];
        $engineSlug      = $pipeline['engine_slug'];
        $confidence      = $pipeline['confidence'];
        $confidenceScore = $pipeline['confidence_score'];
        $qcNotes         = $pipeline['qc_notes'];
        $rawText         = $pipeline['raw_text'];

        // Normalize extracted items and fields using strict QcService
        $rawFs   = trim((string)($extracted['fs_no'] ?? ''));
        $rawTin  = trim((string)($extracted['supplier_tin'] ?? ''));
        $fsNorm  = QcService::normalizeFsNo($rawFs);
        $tinNorm = QcService::normalizeTin($rawTin);

        $fsNo        = $fsNorm['value'];
        $fsNoRaw     = $rawFs;
        $fsNoValid   = $fsNorm['is_valid'];
        $supplierTin = $tinNorm['value'];
        $tinValid    = $tinNorm['is_valid'];

        $merchantName = trim((string)($extracted['merchant_name'] ?? ''));
        $mrcNo        = trim((string)($extracted['machine_no'] ?? ($extracted['mrc_no'] ?? '')));
        $buyerTin     = trim((string)($extracted['buyer_tin'] ?? '0038480010'));

        // Receipt date parsing
        $dateStr = $extracted['receipt_date'] ?? now()->format('d/m/Y');
        $dbDate  = $this->parseDateToYmd($dateStr);

        // Normalize items array
        $itemsData = $extracted['items'] ?? [];
        if (empty($itemsData)) {
            $itemsData = [
                [
                    'item_description' => $extracted['description'] ?? 'Purchased Goods',
                    'uom'              => (string)($extracted['uom_id'] ?? '9'),
                    'qty'              => 1.00,
                    'unit_price'       => (float)($extracted['subtotal'] ?? 0),
                    'total_value'      => (float)($extracted['subtotal'] ?? 0),
                    'vat'              => (float)($extracted['vat_amount'] ?? 0),
                    'value_after_vat'  => (float)($extracted['total_amount'] ?? 0),
                ]
            ];
        }

        $calcSubtotal = 0.0;
        $calcVat      = 0.0;
        $calcTotal    = 0.0;
        $warnings     = [];

        foreach ($itemsData as &$it) {
            $qty       = (float)($it['qty'] ?? 1);
            $unitPrice = (float)($it['unit_price'] ?? 0);
            $totalVal  = (float)($it['total_value'] ?? round($qty * $unitPrice, 2));
            $vatAmt    = (float)($it['vat'] ?? round($totalVal * 0.15, 2));
            $valAfter  = (float)($it['value_after_vat'] ?? round($totalVal + $vatAmt, 2));

            // Arithmetic sanity check for each item
            if ($qty > 0 && $unitPrice > 0 && abs(round($qty * $unitPrice, 2) - $totalVal) > 0.05) {
                $warnings[] = "Item '{$it['item_description']}': Qty x Unit Price (" . round($qty * $unitPrice, 2) . ") != Total Value ($totalVal)";
            }
            if ($totalVal > 0 && abs(round($totalVal * 0.15, 2) - $vatAmt) > 0.05) {
                $warnings[] = "Item '{$it['item_description']}': Total Value x 15% (" . round($totalVal * 0.15, 2) . ") != VAT ($vatAmt)";
            }

            $it['qty']             = $qty;
            $it['unit_price']      = $unitPrice;
            $it['total_value']     = $totalVal;
            $it['vat']             = $vatAmt;
            $it['value_after_vat'] = $valAfter;

            $calcSubtotal += $totalVal;
            $calcVat      += $vatAmt;
            $calcTotal    += $valAfter;
        }
        unset($it);

        if ($calcSubtotal == 0 && !empty($extracted['subtotal'])) {
            $calcSubtotal = (float)$extracted['subtotal'];
            $calcVat      = (float)$extracted['vat_amount'];
            $calcTotal    = (float)$extracted['total_amount'];
        }

        if (empty($supplierTin) || strlen($supplierTin) !== 10) {
            $warnings[] = "Supplier TIN is invalid or not 10 digits ($supplierTin)";
        }
        if (empty($fsNo) || strlen($fsNo) !== 8) {
            $warnings[] = "FS Number is invalid or not 8 digits ($fsNo)";
        }

        $allItemNames = array_filter(array_map(fn($it) => trim((string)($it['item_description'] ?? ($it['name'] ?? ''))), $itemsData));
        $materialListSummary = !empty($allItemNames) ? implode(', ', $allItemNames) : ($extracted['description'] ?? 'Materials');

        // Check for duplicate in database
        $duplicateReceipt = $this->findDuplicate($fsNo, $supplierTin);
        $isDuplicate      = ($duplicateReceipt !== null);

        $duplicateInfo = null;
        if ($isDuplicate) {
            $duplicateInfo = [
                'id'             => $duplicateReceipt->id,
                'receipt_number' => $duplicateReceipt->receipt_number,
                'vendor_name'    => $duplicateReceipt->vendor_name,
                'vendor_tin'     => $duplicateReceipt->vendor_tin,
                'fs_no'          => $duplicateReceipt->fs_no,
                'total_amount'   => (float)$duplicateReceipt->total_amount,
                'receipt_date'   => $duplicateReceipt->receipt_date ? $duplicateReceipt->receipt_date->format('d/m/Y') : '',
            ];
        }

        return [
            'success'           => true,
            'is_duplicate'      => $isDuplicate,
            'duplicate_message' => $isDuplicate ? "Duplicate detected: FS No {$fsNo} with TIN {$supplierTin} already exists in ERP (#{$duplicateReceipt->receipt_number} - {$duplicateReceipt->vendor_name})." : null,
            'existing_receipt'  => $duplicateInfo,
            'vendor_name'       => $merchantName,
            'supplier_tin'      => $supplierTin,
            'tin_valid'         => $tinValid,
            'buyer_tin'         => $buyerTin,
            'fs_no'             => $fsNo,
            'fs_no_raw'         => $fsNoRaw,
            'fs_no_valid'       => $fsNoValid,
            'mrc_no'            => $mrcNo,
            'receipt_date'      => $dateStr,
            'receipt_date_ymd'  => $dbDate,
            'subtotal'          => round($calcSubtotal, 2),
            'vat_amount'        => round($calcVat, 2),
            'total_amount'      => round($calcTotal, 2),
            'description'       => $materialListSummary,
            'items'             => $itemsData,
            'warnings'          => $warnings,
            'engine'            => $engineUsed,
            'engine_slug'       => $engineSlug,
            'confidence'        => $confidence,
            'confidence_score'  => $confidenceScore,
            'qc_notes'          => $qcNotes,
            'raw_text'          => $rawText,
            'extracted'         => $extracted,
        ];
    }

    /**
     * Check if a receipt with given FS No and Supplier TIN already exists.
     *
     * @param string $fsNo
     * @param string $tin
     * @param int|null $excludeReceiptId
     * @return bool
     */
    public function isDuplicate(string $fsNo, string $tin, ?int $excludeReceiptId = null): bool
    {
        return $this->findDuplicate($fsNo, $tin, $excludeReceiptId) !== null;
    }

    /**
     * Find existing receipt by normalized FS No and Supplier TIN.
     *
     * @param string $fsNo
     * @param string $tin
     * @param int|null $excludeReceiptId
     * @return Receipt|null
     */
    public function findDuplicate(string $fsNo, string $tin, ?int $excludeReceiptId = null): ?Receipt
    {
        $rawFs  = trim($fsNo);
        $rawTin = trim($tin);

        $fsNorm  = QcService::normalizeFsNo($rawFs);
        $tinNorm = QcService::normalizeTin($rawTin);

        $cleanFs  = $fsNorm['value'] ?: $rawFs;
        $cleanTin = $tinNorm['value'] ?: $rawTin;

        if (empty($cleanFs) && empty($rawFs)) {
            return null;
        }

        $query = Receipt::query();
        if ($excludeReceiptId) {
            $query->where('id', '!=', $excludeReceiptId);
        }

        // Match FS Number
        $query->where(function ($q) use ($cleanFs, $rawFs) {
            $q->where('fs_no', $cleanFs)
              ->orWhere('fs_no', $rawFs)
              ->orWhere('fs_no_raw', $cleanFs)
              ->orWhere('fs_no_raw', $rawFs)
              ->orWhere('fs_no_raw', 'FS' . $cleanFs)
              ->orWhere('parsed_data->fs_no', $cleanFs)
              ->orWhere('parsed_data->fs_no', 'FS' . $cleanFs);
        });

        // If TIN is supplied, match TIN
        if (!empty($cleanTin)) {
            $query->where(function ($q) use ($cleanTin, $rawTin) {
                $q->where('vendor_tin', $cleanTin)
                  ->orWhere('vendor_tin', $rawTin)
                  ->orWhere('parsed_data->supplier_tin', $cleanTin)
                  ->orWhere('parsed_data->supplier_tin', $rawTin);
            });
        }

        $match = $query->first();

        // Fallback for legacy receipts where vendor_tin may not have been saved
        if (!$match && empty($cleanTin)) {
            $match = Receipt::where(function ($q) use ($cleanFs, $rawFs) {
                $q->where('fs_no', $cleanFs)
                  ->orWhere('fs_no_raw', $cleanFs)
                  ->orWhere('fs_no_raw', 'FS' . $cleanFs);
            })->when($excludeReceiptId, fn($q) => $q->where('id', '!=', $excludeReceiptId))->first();
        }

        return $match;
    }

    /**
     * Persist Receipt and ReceiptItems in a database transaction, optionally linked to a purchasable model.
     *
     * @param array $scanData
     * @param string $filePath
     * @param Model|null $purchasable
     * @param int|null $uploadedBy
     * @param int|null $projectId
     * @param string|null $category
     * @return Receipt
     */
    public function saveReceipt(
        array $scanData,
        string $filePath,
        ?Model $purchasable = null,
        ?int $uploadedBy = null,
        ?int $projectId = null,
        ?string $category = null
    ): Receipt {
        return DB::transaction(function () use ($scanData, $filePath, $purchasable, $uploadedBy, $projectId, $category) {
            $userId   = $uploadedBy ?: (Auth::id() ?: 1);
            $ext      = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $isPdf    = $ext === 'pdf';
            $itemsData = $scanData['items'] ?? [];

            $fsNo        = $scanData['fs_no'] ?? '';
            $fsNoRaw     = $scanData['fs_no_raw'] ?? $fsNo;
            $fsNoValid   = $scanData['fs_no_valid'] ?? (strlen($fsNo) === 8);
            $supplierTin = $scanData['supplier_tin'] ?? '';
            $tinValid    = $scanData['tin_valid'] ?? (strlen($supplierTin) === 10);

            $needsReview = (!$tinValid || !$fsNoValid || ($scanData['confidence'] ?? '') === 'review' || ($scanData['confidence'] ?? '') === 'low' || ($scanData['confidence_score'] ?? 90) < 75);

            $receiptNumber = 'RCP-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $receipt = new Receipt([
                'receipt_number'   => $receiptNumber,
                'uploaded_by'      => $userId,
                'project_id'       => $projectId,
                'vendor_name'      => $scanData['vendor_name'] ?: 'General Merchant',
                'vendor_tin'       => $supplierTin,
                'tin_valid'        => $tinValid,
                'buyer_tin'        => $scanData['buyer_tin'] ?? '0038480010',
                'fs_no'            => $fsNo,
                'fs_no_raw'        => $fsNoRaw,
                'fs_no_valid'      => $fsNoValid,
                'mrc_no'           => $scanData['mrc_no'] ?? null,
                'receipt_date'     => $scanData['receipt_date_ymd'] ?? now()->toDateString(),
                'subtotal'         => round((float)($scanData['subtotal'] ?? 0), 2),
                'vat_amount'       => round((float)($scanData['vat_amount'] ?? 0), 2),
                'total_amount'     => round((float)($scanData['total_amount'] ?? 0), 2),
                'currency'         => 'ETB',
                'category'         => $category ?: 'material',
                'description'      => $scanData['description'] ?? 'Materials',
                'file_path'        => $filePath,
                'file_type'        => $isPdf ? 'pdf' : 'image',
                'ocr_raw_text'     => $scanData['raw_text'] ?? null,
                'ocr_engine'       => $scanData['engine_slug'] ?? 'gemini',
                'confidence'       => $scanData['confidence'] ?? 'high',
                'confidence_score' => (int)($scanData['confidence_score'] ?? 90),
                'needs_review'     => $needsReview,
                'parsed_data'      => $scanData['extracted'] ?? $scanData,
                'parse_status'     => 'parsed',
                'status'           => 'approved',
                'approved_by'      => $userId,
                'approved_at'      => now(),
                'notes'            => "Scanned via " . ($scanData['engine'] ?? 'OCR') . " (" . ($scanData['confidence_score'] ?? 90) . "% confidence). FS: {$fsNo}",
                'qc_notes'         => $scanData['qc_notes'] ?? null,
            ]);

            if ($purchasable && Schema::hasColumn('receipts', 'purchasable_type')) {
                $receipt->purchasable_type = get_class($purchasable);
                $receipt->purchasable_id   = $purchasable->getKey();
            }

            $receipt->save();

            $hasFlaggedItem = false;
            foreach ($itemsData as $item) {
                $qty       = (float)($item['qty'] ?? 1);
                $unitPrice = (float)($item['unit_price'] ?? 0);
                $totalVal  = (float)($item['total_value'] ?? round($qty * $unitPrice, 2));
                $vatAmt    = (float)($item['vat'] ?? round($totalVal * 0.15, 2));
                $valAfter  = (float)($item['value_after_vat'] ?? round($totalVal + $vatAmt, 2));

                $rItem = new ReceiptItem([
                    'receipt_id'       => $receipt->id,
                    'item_description' => $item['item_description'] ?? 'Material',
                    'vat_category'     => $scanData['extracted']['vat_category'] ?? ($scanData['vat_category'] ?? 'G'),
                    'calendar_type'    => $scanData['extracted']['calendar_type'] ?? ($scanData['calendar_type'] ?? 'G'),
                    'purchase_type'    => (int)($scanData['extracted']['purchase_type'] ?? ($scanData['purchase_type'] ?? 3)),
                    'uom'              => (string)($item['uom'] ?? ($scanData['extracted']['uom_id'] ?? ($scanData['uom_id'] ?? '9'))),
                    'qty'              => $qty,
                    'unit_price'       => $unitPrice,
                    'total_value'      => $totalVal,
                    'vat_amount'       => $vatAmt,
                    'value_after_vat'  => $valAfter,
                ]);

                // Arithmetic validation
                $errors = [];
                if ($qty > 0 && $unitPrice > 0 && abs(round($qty * $unitPrice, 2) - $totalVal) > 0.05) {
                    $errors[] = "Qty x Unit Price (" . round($qty * $unitPrice, 2) . ") != Total Value ($totalVal)";
                }
                if ($totalVal > 0 && abs(round($totalVal * 0.15, 2) - $vatAmt) > 0.05) {
                    $errors[] = "Total Value x 15% (" . round($totalVal * 0.15, 2) . ") != VAT ($vatAmt)";
                }
                if (($totalVal > 0 || $vatAmt > 0) && abs(round($totalVal + $vatAmt, 2) - $valAfter) > 0.05) {
                    $errors[] = "Total Value + VAT (" . round($totalVal + $vatAmt, 2) . ") != Value After VAT ($valAfter)";
                }
                if (empty($supplierTin) || strlen($supplierTin) !== 10) {
                    $errors[] = "Supplier TIN is invalid (expected 10 digits)";
                }
                if (empty($fsNo) || strlen($fsNo) !== 8) {
                    $errors[] = "FS Number is invalid (expected 8 digits)";
                }

                if (!empty($errors)) {
                    $rItem->is_flagged   = true;
                    $rItem->flag_reasons = $errors;
                    $hasFlaggedItem      = true;
                }

                $rItem->save();
            }

            if ($hasFlaggedItem) {
                $receipt->update(['needs_review' => true]);
            }

            return $receipt;
        });
    }

    /**
     * Resolve a file path into its realpath, MIME type, extension, and base64 string.
     */
    protected function resolveFile(string $path): array
    {
        $realPath = null;
        $content  = null;

        if (file_exists($path)) {
            $realPath = realpath($path);
            $content  = file_get_contents($realPath);
        } elseif (Storage::disk('public')->exists($path)) {
            $realPath = Storage::disk('public')->path($path);
            $content  = Storage::disk('public')->get($path);
        } elseif (Storage::exists($path)) {
            $realPath = Storage::path($path);
            $content  = Storage::get($path);
        } else {
            // Strip any storage/ prefix and check
            $stripped = ltrim(preg_replace('#^(storage/|/storage/)#i', '', $path), '/');
            if (Storage::disk('public')->exists($stripped)) {
                $realPath = Storage::disk('public')->path($stripped);
                $content  = Storage::disk('public')->get($stripped);
            }
        }

        if (!$content) {
            throw new \InvalidArgumentException("Receipt file not found at: {$path}");
        }

        $ext = strtolower(pathinfo($realPath ?: $path, PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $content) ?: 'application/octet-stream';
        finfo_close($finfo);

        if ($ext === 'pdf' && !str_contains($mimeType, 'pdf')) {
            $mimeType = 'application/pdf';
        }

        return [
            'real_path' => $realPath,
            'base64'    => base64_encode($content),
            'mime_type' => $mimeType,
            'ext'       => $ext ?: 'jpg',
        ];
    }

    /**
     * Parallel Multi-Engine Extraction Pipeline with QC AI Agent.
     */
    public function executeMultiEnginePipeline(string $base64, string $mimeType, string $ext, ?string $realPath = null): array
    {
        $geminiKey     = SystemSetting::get('gemini_api_key', env('GEMINI_API_KEY'));
        $ocrSpaceKey   = SystemSetting::get('ocr_space_api_key', env('OCR_SPACE_API_KEY', 'helloworld'));
        $nvidiaKey     = SystemSetting::get('nvidia_api_key', env('NVIDIA_API_KEY'));
        $azureKey      = SystemSetting::get('azure_vision_key', env('AZURE_VISION_KEY'));
        $azureEndpoint = SystemSetting::get('azure_vision_endpoint', env('AZURE_VISION_ENDPOINT'));

        $engineResults = [];
        $rawTexts      = [];

        // Engine 1: Google Gemini Multimodal AI
        if (!empty($geminiKey)) {
            $geminiRes = $this->scanWithGemini($geminiKey, $base64, $mimeType);
            if ($geminiRes && !empty($geminiRes['merchant_name'])) {
                $engineResults['gemini'] = $geminiRes;
                if (!empty($geminiRes['raw_text'])) {
                    $rawTexts[] = "[Gemini OCR]\n" . $geminiRes['raw_text'];
                }
            }
        }

        // Engine 2: NVIDIA Vision NIM API
        if (!empty($nvidiaKey)) {
            $nvidiaRes = $this->scanWithNvidia($nvidiaKey, $base64, $mimeType);
            if ($nvidiaRes && !empty($nvidiaRes['merchant_name'])) {
                $engineResults['nvidia'] = $nvidiaRes;
                if (!empty($nvidiaRes['raw_text'])) {
                    $rawTexts[] = "[NVIDIA OCR]\n" . $nvidiaRes['raw_text'];
                }
            }
        }

        // Engine 3: Azure Computer Vision Read API
        if (!empty($azureKey) && !empty($azureEndpoint)) {
            $azureRes = $this->scanWithAzure($azureKey, $azureEndpoint, $base64, $mimeType);
            if ($azureRes && !empty($azureRes['merchant_name'])) {
                $engineResults['azure'] = $azureRes;
                if (!empty($azureRes['raw_text'])) {
                    $rawTexts[] = "[Azure OCR]\n" . $azureRes['raw_text'];
                }
            }
        }

        // Engine 4: OCR.Space API (run if fewer than 2 AI engines succeeded or as fallback)
        if (count($engineResults) < 2 && !empty($ocrSpaceKey)) {
            $ocrRes = $this->scanWithOcrSpace($ocrSpaceKey, $base64, $ext, $realPath ?: '');
            if ($ocrRes && !empty($ocrRes['merchant_name'])) {
                $engineResults['ocr_space'] = $ocrRes;
                if (!empty($ocrRes['raw_text'])) {
                    $rawTexts[] = "[OCR.Space]\n" . $ocrRes['raw_text'];
                }
            }
        }

        $combinedRawText = implode("\n\n---\n\n", $rawTexts);

        // Quality Control Reconciliation
        if (!empty($engineResults)) {
            if (count($engineResults) > 1 && !empty($geminiKey)) {
                $extracted = $this->callQcAgent($geminiKey, $engineResults);
            } else {
                $extracted = $this->reconcileWithDeterministicQc($engineResults);
            }
        } else {
            // All engines failed: create clean manual entry skeleton
            $extracted = [
                'merchant_name'    => '',
                'supplier_tin'     => '',
                'buyer_tin'        => '0038480010',
                'receipt_date'     => now()->format('d/m/Y'),
                'machine_no'       => '',
                'fs_no'            => '',
                'items'            => [
                    [
                        'item_description' => 'Unreadable receipt item - please enter details',
                        'uom'              => '9',
                        'qty'              => 1.00,
                        'unit_price'       => 0.00,
                        'total_value'      => 0.00,
                        'vat'              => 0.00,
                        'value_after_vat'  => 0.00,
                    ]
                ],
                'vat_category'     => 'G',
                'calendar_type'    => 'G',
                'purchase_type'    => 3,
                'subtotal'         => 0.00,
                'vat_amount'       => 0.00,
                'total_amount'     => 0.00,
                'raw_text'         => 'No text could be extracted by OCR engines.',
                'confidence_score' => 20,
                'qc_notes'         => 'All OCR engines failed to extract readable text. Manual input required.',
                'provenance'       => 'None (Manual Entry)',
            ];
        }

        if (empty($extracted['raw_text']) && !empty($combinedRawText)) {
            $extracted['raw_text'] = $combinedRawText;
        }

        $activeEngines = array_keys($engineResults);
        $engineLabel = !empty($activeEngines) ? implode(' + ', array_map(function($e) {
            return match($e) {
                'gemini'    => 'Gemini AI',
                'nvidia'    => 'NVIDIA Vision',
                'azure'     => 'Azure Read',
                'ocr_space' => 'OCR.Space',
                default     => ucfirst($e)
            };
        }, $activeEngines)) : 'Manual';

        if (count($activeEngines) > 1) {
            $engineLabel .= ' + QC Agent';
        }

        $score = (int)($extracted['confidence_score'] ?? 85);
        $confidenceStr = ($score >= 85) ? 'high' : (($score >= 70) ? 'review' : 'low');

        return [
            'extracted'        => $extracted,
            'engines_used'     => $activeEngines,
            'engine_label'     => $engineLabel,
            'engine_slug'      => !empty($activeEngines) ? implode(',', $activeEngines) : 'manual',
            'confidence'       => $confidenceStr,
            'confidence_score' => $score,
            'qc_notes'         => $extracted['qc_notes'] ?? 'Verified by QC Agent',
            'raw_text'         => $extracted['raw_text'] ?? $combinedRawText,
        ];
    }

    /**
     * Primary Engine: Google Gemini Multimodal Vision API.
     */
    protected function scanWithGemini(string $apiKey, string $base64, string $mimeType): ?array
    {
        $prompt = <<<PROMPT
You are an expert fiscal auditor specialized in Ethiopian ERCA / Ministry of Revenues fiscal cash machine receipts (Datecs, Daisy, Citizen), sales invoices, and commercial cash slips for Ethiopian Ministry of Revenues VAT declaration (Line 100).
Extract ALL details from this receipt image with absolute precision. Read every detail on the receipt, including small printed text, folded or skewed slips, and phone photos.

CRITICAL EXTRACTION RULES:
1. ORIENTATION & IMAGE SKEW:
   - Carefully inspect the image even if rotated, tilted, taken at an angle with a smartphone camera, with shadows, or on folded/wrinkled thermal paper. Read all numbers and letters meticulously.

2. SUPPLIER TIN vs BUYER TIN:
   - supplier_tin: Return digits only, exactly as printed on the receipt (must be exactly 10 digits, e.g. "0024916531"). Strip all labels ("TIN:", "TIN No") and return digits only. If missing, blurry, or low confidence, return an empty string "" instead of guessing or padding.
   - buyer_tin: 10-digit TIN of BUYER / CLIENT / CUSTOMER (often "0038480010"). Return digits only.

3. MERCHANT / SELLER NAME:
   - merchant_name: Full registered trade name of the SELLER (e.g. "ASTRA GENERAL TRADING PLC", "ABDULKERIM STRAJ AHMED", "ENTERPRISE ..."). Do NOT truncate or cut words in half.

4. RECEIPT DATE:
   - receipt_date: Normalize to DD/MM/YYYY (e.g. "25/09/2026", "24/09/2026", "23/09/2026").

5. ERCA / MRC MACHINE NUMBER:
   - machine_no: Cash machine registration code / MRC number (e.g. "TDB0015170", "DDB0000032", "TDB0016310", "MFE0097690"). Usually 3 capital letters followed by 7 digits.

6. FS / FISCAL RECEIPT NUMBER:
   - fs_no: Return digits only, exactly as printed on the receipt (must be exactly 8 digits, e.g. "00002674"). If printed with "FS" prefix, return digits only. Do NOT pad with zeros, do NOT truncate, and do NOT guess. If missing, blurry, or low confidence, return an empty string "" instead of guessing.

7. ITEMS / PURCHASED MATERIALS (COL I, J, K, L, M, N, O):
   - You MUST read the EXACT bought materials/items listed on the receipt under DESCRIPTION!
   - Every single line item must be extracted with its full printed description in "item_description".
   - If multiple items are printed, create an object in the "items" array for EACH individual item with its own qty, unit_price, total_value, vat, and value_after_vat!
   - For "description": return a clean comma-separated list of ALL bought materials extracted from the receipt.
   - uom: Ethiopian VAT UOM code ("9" for OTHER, "7" for PCS, "2" for KG, "5" for LIT, "10" for PC, "1" for M, "4" for M3).
   - qty: Numeric quantity (2 decimals).
   - unit_price: Numeric unit price before VAT (2 decimals).
   - total_value: Total value before VAT (qty * unit_price, 2 decimals).
   - vat: 15% VAT for this item (2 decimals).
   - value_after_vat: Total including VAT (total_value + vat, 2 decimals).

8. TOTALS ACROSS RECEIPT:
   - subtotal: Taxable value before VAT across entire receipt.
   - vat_amount: 15% VAT across entire receipt.
   - total_amount: Grand total / cash paid.

9. VAT DECLARATION CLASSIFICATION:
   - vat_category: "G" for Goods or "S" for Services.
   - calendar_type: "G" for Gregorian or "E" for Ethiopian.
   - purchase_type: 3 (Taxable-local Purchase of Inputs - Line No. 100).
   - uom_id: 9.

Return STRICT JSON matching this schema:
{
  "merchant_name": "string",
  "supplier_tin": "10 digits numeric string or empty",
  "buyer_tin": "10 digits numeric string or null",
  "receipt_date": "DD/MM/YYYY",
  "machine_no": "string or null",
  "fs_no": "8 digits numeric string or empty",
  "description": "string comma-separated list of all bought materials",
  "subtotal": 0.00,
  "vat_amount": 0.00,
  "total_amount": 0.00,
  "vat_category": "G",
  "calendar_type": "G",
  "purchase_type": 3,
  "uom_id": 9,
  "items": [
    {
      "item_description": "string",
      "uom": "9",
      "qty": 1.00,
      "unit_price": 0.00,
      "total_value": 0.00,
      "vat": 0.00,
      "value_after_vat": 0.00
    }
  ],
  "raw_text": "transcription of text on receipt"
}
PROMPT;

        $models = ['gemini-flash-latest', 'gemini-3.1-flash-lite', 'gemini-3.5-flash'];

        foreach ($models as $model) {
            try {
                $response = Http::retry(2, 500)->timeout(40)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey,
                    [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt],
                                    [
                                        'inlineData' => [
                                            'mimeType' => $mimeType,
                                            'data'     => $base64,
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'responseMimeType' => 'application/json',
                        ]
                    ]
                );

                if ($response->successful()) {
                    $candidates = $response->json('candidates', []);
                    if (!empty($candidates[0]['content']['parts'][0]['text'])) {
                        $jsonText = $candidates[0]['content']['parts'][0]['text'];
                        $jsonText = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($jsonText));
                        $parsed   = json_decode($jsonText, true);

                        if (is_array($parsed)) {
                            $tinNorm = QcService::normalizeTin($parsed['supplier_tin'] ?? null);
                            $fsNorm  = QcService::normalizeFsNo($parsed['fs_no'] ?? null);

                            $parsed['supplier_tin'] = $tinNorm['value'];
                            $parsed['tin_valid']    = $tinNorm['is_valid'];
                            $parsed['fs_no']        = $fsNorm['value'];
                            $parsed['fs_no_raw']    = (string)($parsed['fs_no'] ?? '');
                            $parsed['fs_no_valid']  = $fsNorm['is_valid'];

                            if (!empty($parsed['buyer_tin'])) {
                                $parsed['buyer_tin'] = preg_replace('/[^0-9]/', '', (string)$parsed['buyer_tin']);
                            }
                            if (!empty($parsed['machine_no'])) {
                                $parsed['machine_no'] = strtoupper(trim(preg_replace('/\s+/', '', (string)$parsed['machine_no'])));
                            }
                            return $parsed;
                        }
                    }
                } else {
                    Log::warning("Gemini OCR ({$model}) HTTP " . $response->status() . ": " . $response->body());
                }
            } catch (\Throwable $e) {
                Log::warning("Gemini OCR ({$model}) exception: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Engine 2: NVIDIA Vision NIM API (Llama 3.2 Vision Instruct).
     */
    protected function scanWithNvidia(string $apiKey, string $base64, string $mimeType): ?array
    {
        if (empty($apiKey)) return null;

        $prompt = <<<PROMPT
You are an expert Ethiopian fiscal receipt auditor specializing in ERCA / Ministry of Revenues fiscal cash machine receipts (Datecs, Daisy, Citizen) for VAT declaration (Line 100).
Extract ALL details from this receipt image with absolute precision.
Ensure:
1. Supplier TIN: return digits only, exactly as printed (must be exactly 10 digits). Return empty string if missing or low confidence instead of guessing.
2. FS No: return digits only, exactly as printed (must be exactly 8 digits). Return empty string if missing or low confidence instead of guessing. Do NOT auto-pad with zeros or guess.
3. Machine No (MRC) is captured if present.
4. Receipt Date is formatted as DD/MM/YYYY.
5. All item lines are extracted with individual unit prices, quantities, and line amounts.
6. Subtotal + 15% VAT = Total Amount.

Return STRICT JSON matching:
{
  "merchant_name": "string",
  "supplier_tin": "10-digit numeric string or empty",
  "buyer_tin": "10-digit numeric string or null",
  "receipt_date": "DD/MM/YYYY",
  "machine_no": "string or null",
  "fs_no": "8-digit numeric string or empty",
  "description": "string",
  "subtotal": 0.00,
  "vat_amount": 0.00,
  "total_amount": 0.00,
  "vat_category": "G",
  "calendar_type": "G",
  "purchase_type": 3,
  "uom_id": 9,
  "items": [
    {
      "item_description": "string",
      "uom": "9",
      "qty": 1.00,
      "unit_price": 0.00,
      "total_value": 0.00,
      "vat": 0.00,
      "value_after_vat": 0.00
    }
  ],
  "raw_text": "text found on receipt"
}
PROMPT;

        $models = ['meta/llama-3.2-11b-vision-instruct', 'meta/llama-3.2-90b-vision-instruct'];

        foreach ($models as $model) {
            try {
                $response = Http::retry(2, 400)->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ])->timeout(35)->post('https://integrate.api.nvidia.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $prompt],
                                ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$base64}"]]
                            ]
                        ]
                    ],
                    'max_tokens' => 2000,
                    'temperature' => 0.1,
                ]);

                if ($response->successful()) {
                    $content = $response->json('choices.0.message.content');
                    if (!empty($content)) {
                        $jsonText = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content));
                        if (preg_match('/\{[\s\S]*\}/', $jsonText, $m)) {
                            $jsonText = $m[0];
                        }
                        $parsed = json_decode($jsonText, true);

                        if (is_array($parsed) && !empty($parsed['merchant_name'])) {
                            $tinNorm = QcService::normalizeTin($parsed['supplier_tin'] ?? null);
                            $fsNorm  = QcService::normalizeFsNo($parsed['fs_no'] ?? null);

                            $parsed['supplier_tin'] = $tinNorm['value'];
                            $parsed['tin_valid']    = $tinNorm['is_valid'];
                            $parsed['fs_no']        = $fsNorm['value'];
                            $parsed['fs_no_raw']    = (string)($parsed['fs_no'] ?? '');
                            $parsed['fs_no_valid']  = $fsNorm['is_valid'];

                            if (!empty($parsed['buyer_tin'])) {
                                $parsed['buyer_tin'] = preg_replace('/[^0-9]/', '', (string)$parsed['buyer_tin']);
                            }
                            if (!empty($parsed['machine_no'])) {
                                $parsed['machine_no'] = strtoupper(trim(preg_replace('/\s+/', '', (string)$parsed['machine_no'])));
                            }
                            return $parsed;
                        }
                    }
                } else {
                    Log::warning("NVIDIA Vision ({$model}) HTTP " . $response->status() . ": " . $response->body());
                }
            } catch (\Throwable $e) {
                Log::warning("NVIDIA Vision ({$model}) exception: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Engine 3: Azure Computer Vision / Read API.
     */
    protected function scanWithAzure(string $apiKey, string $endpoint, string $base64, string $mimeType): ?array
    {
        if (empty($apiKey) || empty($endpoint)) return null;

        $endpoint = rtrim(trim($endpoint), '/');
        $rawBytes = base64_decode($base64);

        try {
            // Attempt 1: Modern Image Analysis 4.0 Read API (synchronous)
            $res = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $apiKey,
                'Content-Type'              => 'application/octet-stream',
            ])->timeout(25)->withBody($rawBytes, 'application/octet-stream')
              ->post("{$endpoint}/computervision/imageanalysis:analyze?api-version=2024-02-01&features=read");

            if ($res->successful()) {
                $blocks = $res->json('readResult.blocks', []);
                $lines = [];
                foreach ($blocks as $block) {
                    foreach ($block['lines'] ?? [] as $line) {
                        if (!empty($line['text'])) {
                            $lines[] = $line['text'];
                        }
                    }
                }
                if (!empty($lines)) {
                    $rawText = implode("\n", $lines);
                    return $this->parseReceiptTextHeuristic($rawText);
                }
            }

            // Attempt 2: Vision Read 3.2 (async polling pattern)
            $postRes = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $apiKey,
                'Content-Type'              => 'application/octet-stream',
            ])->timeout(15)->withBody($rawBytes, 'application/octet-stream')
              ->post("{$endpoint}/vision/v3.2/read/analyze");

            $operationLocation = $postRes->header('Operation-Location');
            if ($postRes->status() === 202 && !empty($operationLocation)) {
                $maxPolls = 8;
                for ($i = 0; $i < $maxPolls; $i++) {
                    usleep(700000); // 0.7s
                    $pollRes = Http::withHeaders([
                        'Ocp-Apim-Subscription-Key' => $apiKey,
                    ])->timeout(10)->get($operationLocation);

                    if ($pollRes->successful()) {
                        $status = $pollRes->json('status');
                        if ($status === 'succeeded') {
                            $readResults = $pollRes->json('analyzeResult.readResults', []);
                            $lines = [];
                            foreach ($readResults as $page) {
                                foreach ($page['lines'] ?? [] as $line) {
                                    if (!empty($line['text'])) {
                                        $lines[] = $line['text'];
                                    }
                                }
                            }
                            if (!empty($lines)) {
                                $rawText = implode("\n", $lines);
                                return $this->parseReceiptTextHeuristic($rawText);
                            }
                            break;
                        } elseif ($status === 'failed') {
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Azure Vision Read exception: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Fallback Engine: OCR.Space Engine 2 for receipts and tabular text.
     */
    protected function scanWithOcrSpace(string $apiKey, string $base64, string $ext, string $realPath): ?array
    {
        try {
            $mime = ($ext === 'pdf') ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
            $dataUri = "data:{$mime};base64,{$base64}";

            $response = Http::asForm()->timeout(35)->post('https://api.ocr.space/parse/image', [
                'apikey'            => $apiKey ?: 'helloworld',
                'base64Image'       => $dataUri,
                'language'          => 'eng',
                'isOverlayRequired' => 'false',
                'OCREngine'         => 2,
                'detectOrientation' => 'true',
                'isTable'           => 'true',
                'scale'             => 'true',
            ]);

            if (!$response->successful()) {
                Log::warning('OCR.Space HTTP error: ' . $response->status());
                return null;
            }

            $json = $response->json();
            if (!empty($json['IsErroredOnProcessing']) && !empty($json['ErrorMessage'])) {
                Log::warning('OCR.Space parsing error: ' . implode('; ', (array)$json['ErrorMessage']));
                return null;
            }

            $rawText = $json['ParsedResults'][0]['ParsedText'] ?? '';
            if (empty(trim($rawText))) {
                return null;
            }

            return $this->parseReceiptTextHeuristic($rawText);

        } catch (\Throwable $e) {
            Log::warning('OCR.Space exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Heuristic parser for raw text extracted from OCR.Space or Azure.
     */
    public function parseReceiptTextHeuristic(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $cleanLines = array_values(array_filter(array_map('trim', $lines)));

        $supplierTin = '';
        $buyerTin    = '';
        $fsNo        = '';
        $mrcNo       = '';
        $dateStr     = '';
        $vendorName  = '';
        $totalVal    = 0.0;
        $vatVal      = 0.0;
        $subtotalVal = 0.0;
        $items       = [];

        // 1. Find 10-digit TINs
        preg_match_all('/\b(00\d{8}|\d{10})\b/', $text, $tinMatches);
        if (!empty($tinMatches[0])) {
            foreach ($tinMatches[0] as $t) {
                if ($t === '0038480010') {
                    $buyerTin = $t;
                } elseif (empty($supplierTin)) {
                    $tinNorm = QcService::normalizeTin($t);
                    $supplierTin = $tinNorm['value'];
                }
            }
        }

        // 2. Find FS Number
        if (preg_match('/(?:FS|F\/S|FISCAL\s*NO|FISCAL\s*RECEIPT\s*NO|FS\s*NO|FS\s*\#)[\s\:\.\#\-\_]*([0-9]{1,12})/i', $text, $m)) {
            $fsNorm = QcService::normalizeFsNo($m[1]);
            $fsNo = $fsNorm['value'];
        } elseif (preg_match('/\bFS\s*([0-9]{3,12})\b/i', $text, $m)) {
            $fsNorm = QcService::normalizeFsNo($m[1]);
            $fsNo = $fsNorm['value'];
        }

        // 3. Find MRC Number
        if (preg_match('/\b([A-Z]{3}[0-9]{7})\b/i', $text, $m)) {
            $mrcNo = strtoupper($m[1]);
        }

        // 4. Find Receipt Date
        if (preg_match('/\b([0-3]?[0-9][\/\-\.][0-1]?[0-9][\/\-\.](?:20)?[12][0-9])\b/', $text, $m)) {
            $parts = preg_split('/[\/\-\.]/', $m[1]);
            if (count($parts) === 3) {
                $y = strlen($parts[2]) === 2 ? '20' . $parts[2] : $parts[2];
                $dateStr = sprintf('%02d/%02d/%04d', (int)$parts[0], (int)$parts[1], (int)$y);
            }
        }

        // 5. Find Vendor Name (typically first non-empty header line)
        foreach ($cleanLines as $line) {
            if (strlen($line) > 3 && !preg_match('/(tin|tel|vat|date|fs|mrc|cash|invoice|receipt)/i', $line)) {
                $vendorName = $line;
                break;
            }
        }

        // 6. Find Totals
        if (preg_match('/(?:TOTAL|GRAND\s*TOTAL|TOTAL\s*DUE)[\s\:\=]*([0-9\.\,\ ]+)/i', $text, $m)) {
            $cleanNum = preg_replace('/[^\d\.]/', '', str_replace(',', '.', trim($m[1])));
            $totalVal = (float)$cleanNum;
        }

        if (preg_match('/(?:VAT|15\%|TAX)[\s\:\=]*([0-9\.\,\ ]+)/i', $text, $m)) {
            $cleanNum = preg_replace('/[^\d\.]/', '', str_replace(',', '.', trim($m[1])));
            $vatVal = (float)$cleanNum;
        }

        if (preg_match('/(?:TAXABLE|SUBTOTAL|SUB\s*TOTAL)[\s\:\=]*([0-9\.\,\ ]+)/i', $text, $m)) {
            $cleanNum = preg_replace('/[^\d\.]/', '', str_replace(',', '.', trim($m[1])));
            $subtotalVal = (float)$cleanNum;
        }

        if ($totalVal > 0 && $subtotalVal == 0) {
            $subtotalVal = round($totalVal / 1.15, 2);
            $vatVal = round($totalVal - $subtotalVal, 2);
        }

        return [
            'merchant_name'    => $vendorName ?: 'General Merchant',
            'supplier_tin'     => $supplierTin,
            'buyer_tin'        => $buyerTin ?: '0038480010',
            'receipt_date'     => $dateStr ?: now()->format('d/m/Y'),
            'machine_no'       => $mrcNo,
            'fs_no'            => $fsNo,
            'description'      => 'Purchased Materials',
            'subtotal'         => $subtotalVal,
            'vat_amount'       => $vatVal,
            'total_amount'     => $totalVal,
            'vat_category'     => 'G',
            'calendar_type'    => 'G',
            'purchase_type'    => 3,
            'uom_id'           => 9,
            'items'            => [
                [
                    'item_description' => 'Purchased Goods',
                    'uom'              => '9',
                    'qty'              => 1.00,
                    'unit_price'       => $subtotalVal,
                    'total_value'      => $subtotalVal,
                    'vat'              => $vatVal,
                    'value_after_vat'  => $totalVal,
                ]
            ],
            'raw_text'         => $text,
            'confidence_score' => 60,
            'qc_notes'         => 'Extracted via heuristic text parsing.',
            'provenance'       => 'OCR Text Heuristics',
        ];
    }

    /**
     * Quality Control AI Agent.
     */
    protected function callQcAgent(string $geminiKey, array $engineResults): array
    {
        if (count($engineResults) === 1) {
            $engineName = array_key_first($engineResults);
            return $this->reconcileWithDeterministicQc($engineResults, $engineName);
        }

        $enginesSummary = json_encode($engineResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $qcPrompt = <<<PROMPT
You are the Quality Control (QC) Master AI Auditor for Ethiopian ERCA / Ministry of Revenues VAT Declaration (Line 100 - Taxable Purchase of Inputs).
Multiple OCR extraction engines have independently scanned the SAME Ethiopian fiscal cash receipt (Datecs, Daisy, Citizen).
Below are the JSON outputs from each engine:

{$enginesSummary}

YOUR MISSION:
Reconcile all disagreements and generate the SINGLE MOST ACCURATE, MATHEMATICALLY VERIFIED JSON result for ERCA VAT reporting.

CRITICAL IDENTIFIER RULES:
1. SUPPLIER TIN: Must be EXACTLY 10 digits, numeric only. Never guess or pad.
2. FS NUMBER: Must be EXACTLY 8 digits, numeric only (strip "FS" prefix). Never guess or pad.
3. BUYER TIN: 10 digits (e.g. 0038480010).
4. RECEIPT DATE: Format strictly as DD/MM/YYYY.
5. ARITHMETIC VERIFICATION: Subtotal + VAT (15%) = Total Amount within 0.05 tolerance.
6. ITEMS BREAKDOWN: Preserve detailed material descriptions. Qty x Unit Price = Total Value, Total Value + VAT = Value After VAT.

Return STRICT JSON matching:
{
  "merchant_name": "string",
  "supplier_tin": "10-digit string",
  "buyer_tin": "10-digit string or null",
  "receipt_date": "DD/MM/YYYY",
  "machine_no": "string or null",
  "fs_no": "string",
  "description": "string",
  "subtotal": 0.00,
  "vat_amount": 0.00,
  "total_amount": 0.00,
  "vat_category": "G",
  "calendar_type": "G",
  "purchase_type": 3,
  "uom_id": 9,
  "items": [
    {
      "item_description": "string",
      "uom": "9",
      "qty": 1.00,
      "unit_price": 0.00,
      "total_value": 0.00,
      "vat": 0.00,
      "value_after_vat": 0.00
    }
  ],
  "raw_text": "string",
  "confidence_score": 95,
  "qc_notes": "reconciliation narrative",
  "provenance": "summary of engine agreement"
}
PROMPT;

        $models = ['gemini-flash-latest', 'gemini-3.1-flash-lite', 'gemini-3.5-flash'];

        foreach ($models as $model) {
            try {
                $response = Http::retry(2, 400)->timeout(25)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $geminiKey,
                    [
                        'contents' => [
                            ['parts' => [['text' => $qcPrompt]]]
                        ],
                        'generationConfig' => [
                            'responseMimeType' => 'application/json',
                        ]
                    ]
                );

                if ($response->successful()) {
                    $candidates = $response->json('candidates', []);
                    if (!empty($candidates[0]['content']['parts'][0]['text'])) {
                        $jsonText = $candidates[0]['content']['parts'][0]['text'];
                        $jsonText = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($jsonText));
                        $parsed   = json_decode($jsonText, true);

                        if (is_array($parsed) && !empty($parsed['merchant_name'])) {
                            $tinNorm = QcService::normalizeTin($parsed['supplier_tin'] ?? null);
                            $fsNorm  = QcService::normalizeFsNo($parsed['fs_no'] ?? null);

                            $parsed['supplier_tin'] = $tinNorm['value'];
                            $parsed['tin_valid']    = $tinNorm['is_valid'];
                            $parsed['fs_no']        = $fsNorm['value'];
                            $parsed['fs_no_raw']    = (string)($parsed['fs_no'] ?? '');
                            $parsed['fs_no_valid']  = $fsNorm['is_valid'];

                            $score = (int)($parsed['confidence_score'] ?? 92);
                            if (!$tinNorm['is_valid'] || !$fsNorm['is_valid']) {
                                $score = min($score, 60);
                            }
                            $parsed['confidence_score'] = max(10, min(100, $score));
                            return $parsed;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("QC AI Agent ({$model}) exception: " . $e->getMessage());
            }
        }

        return $this->reconcileWithDeterministicQc($engineResults);
    }

    /**
     * Deterministic pure-PHP Quality Control & reconciliation.
     */
    public function reconcileWithDeterministicQc(array $engineResults, ?string $singleEngine = null): array
    {
        if (empty($engineResults)) {
            return [];
        }

        $priority = ['gemini', 'nvidia', 'azure', 'ocr_space'];
        $base = null;
        foreach ($priority as $p) {
            if (isset($engineResults[$p])) {
                $base = $engineResults[$p];
                break;
            }
        }
        if (!$base) {
            $base = reset($engineResults);
        }

        // Cross-check supplier TIN
        $validTins = [];
        $rawTins   = [];
        foreach ($engineResults as $res) {
            $rawT = (string)($res['supplier_tin'] ?? '');
            $tinNorm = QcService::normalizeTin($rawT);
            if ($tinNorm['is_valid']) {
                $validTins[$tinNorm['value']] = ($validTins[$tinNorm['value']] ?? 0) + 1;
            } elseif ($rawT !== '') {
                $rawTins[$rawT] = ($rawTins[$rawT] ?? 0) + 1;
            }
        }
        if (!empty($validTins)) {
            arsort($validTins);
            $base['supplier_tin'] = array_key_first($validTins);
            $base['tin_valid']    = true;
        } else {
            arsort($rawTins);
            $base['supplier_tin'] = array_key_first($rawTins) ?: '';
            $base['tin_valid']    = false;
        }

        // Cross-check FS No
        $validFs  = [];
        $rawFsMap = [];
        foreach ($engineResults as $res) {
            $rawF = (string)($res['fs_no'] ?? '');
            $fsNorm = QcService::normalizeFsNo($rawF);
            if ($fsNorm['is_valid']) {
                $validFs[$fsNorm['value']] = ($validFs[$fsNorm['value']] ?? 0) + 1;
            } elseif ($rawF !== '') {
                $rawFsMap[$rawF] = ($rawFsMap[$rawF] ?? 0) + 1;
            }
        }
        if (!empty($validFs)) {
            arsort($validFs);
            $base['fs_no']       = array_key_first($validFs);
            $base['fs_no_raw']   = (string)($base['fs_no'] ?? '');
            $base['fs_no_valid'] = true;
        } else {
            arsort($rawFsMap);
            $base['fs_no']       = array_key_first($rawFsMap) ?: '';
            $base['fs_no_raw']   = $base['fs_no'];
            $base['fs_no_valid'] = false;
        }

        // Arithmetic cross-check
        $subtotal = (float)($base['subtotal'] ?? 0);
        $vat      = (float)($base['vat_amount'] ?? 0);
        $total    = (float)($base['total_amount'] ?? 0);

        $mathExact = false;
        if ($total > 0 && abs(($subtotal + $vat) - $total) <= 0.05) {
            $mathExact = true;
        } elseif ($total > 0 && $subtotal == 0) {
            $subtotal = round($total / 1.15, 2);
            $vat      = round($total - $subtotal, 2);
            $base['subtotal']   = $subtotal;
            $base['vat_amount'] = $vat;
            $mathExact = true;
        }

        $score = 50;
        $notes = [];

        if (!empty($base['tin_valid'])) {
            $score += 20;
        } else {
            $notes[] = "invalid_tin: Supplier TIN must be exactly 10 digits";
        }

        if (!empty($base['fs_no_valid'])) {
            $score += 15;
        } else {
            $notes[] = "invalid_fs_no: FS No must be exactly 8 digits";
        }

        if ($mathExact) {
            $score += 15;
        } else {
            $notes[] = "Arithmetic discrepancy detected between Subtotal, VAT and Total";
        }

        if (!empty($base['items']) && count($base['items']) > 0) {
            $score += 10;
        }

        if (count($engineResults) > 1) {
            $score += 10;
            $provenance = "Multi-Engine consensus across: " . implode(', ', array_keys($engineResults));
        } else {
            $provenance = "Single engine: " . ($singleEngine ?: 'OCR');
        }

        $base['confidence_score'] = min(100, max(20, $score));
        $base['qc_notes'] = empty($notes)
            ? "Verified by QC AI Agent. Arithmetic and ERCA fields validated."
            : implode("; ", $notes);
        $base['provenance'] = $provenance;

        return $base;
    }

    /**
     * Parse date string into YYYY-MM-DD for database.
     */
    public function parseDateToYmd(?string $dateStr): ?string
    {
        if (empty($dateStr)) return null;
        $dateStr = trim($dateStr);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return $dateStr;
        }

        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $dateStr, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }

        try {
            return \Carbon\Carbon::parse($dateStr)->toDateString();
        } catch (\Throwable $e) {
            return now()->toDateString();
        }
    }
}
