<?php

namespace App\Http\Controllers;

use App\Services\ReceiptOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PurchaseReceiptScanController extends Controller
{
    protected ReceiptOcrService $ocrService;

    public function __construct(ReceiptOcrService $ocrService)
    {
        $this->middleware('auth');
        $this->ocrService = $ocrService;
    }

    /**
     * Handle AJAX receipt scanning for purchasing screens.
     * Validates file, runs OCR & QC, checks duplicates without persisting, and returns JSON.
     */
    public function scan(Request $request): JsonResponse
    {
        $this->authorizePurchasingRole();

        $request->validate([
            'receipt_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $file     = $request->file('receipt_file');
        $tempPath = null;

        try {
            // Save to temporary storage for processing
            $tempExt  = strtolower($file->getClientOriginalExtension());
            $tempName = 'scan_temp_' . uniqid() . '.' . $tempExt;
            $tempPath = $file->storeAs('temp_scans', $tempName, 'local');
            $fullPath = Storage::disk('local')->path($tempPath);

            $scanResult = $this->ocrService->scan($fullPath);

            $fsNo = $scanResult['fs_no'] ?? '';
            $tin  = $scanResult['supplier_tin'] ?? '';

            $isDuplicate = $this->ocrService->isDuplicate($fsNo, $tin);
            $duplicateInfo = null;

            if ($isDuplicate) {
                $duplicateModel = $this->ocrService->findDuplicate($fsNo, $tin);
                if ($duplicateModel) {
                    $duplicateInfo = [
                        'id'             => $duplicateModel->id,
                        'receipt_number' => $duplicateModel->receipt_number,
                        'vendor_name'    => $duplicateModel->vendor_name,
                        'vendor_tin'     => $duplicateModel->vendor_tin,
                        'fs_no'          => $duplicateModel->fs_no,
                        'total_amount'   => (float)$duplicateModel->total_amount,
                        'receipt_date'   => $duplicateModel->receipt_date ? $duplicateModel->receipt_date->format('d/m/Y') : '',
                    ];
                }
            }

            return response()->json([
                'success'           => true,
                'is_duplicate'      => $isDuplicate,
                'duplicate_message' => $isDuplicate ? "Warning: Receipt with FS No '{$fsNo}' and Supplier TIN '{$tin}' already exists in ERP (#" . ($duplicateInfo['receipt_number'] ?? 'N/A') . " - " . ($duplicateInfo['vendor_name'] ?? 'N/A') . ")." : null,
                'existing_receipt'  => $duplicateInfo,
                'data'              => [
                    'vendor_name'      => $scanResult['vendor_name'],
                    'supplier_tin'     => $scanResult['supplier_tin'],
                    'tin_valid'        => $scanResult['tin_valid'],
                    'buyer_tin'        => $scanResult['buyer_tin'],
                    'fs_no'            => $scanResult['fs_no'],
                    'fs_no_raw'        => $scanResult['fs_no_raw'],
                    'fs_no_valid'      => $scanResult['fs_no_valid'],
                    'mrc_no'           => $scanResult['mrc_no'],
                    'receipt_date'     => $scanResult['receipt_date'],
                    'receipt_date_ymd' => $scanResult['receipt_date_ymd'],
                    'subtotal'         => $scanResult['subtotal'],
                    'vat_amount'       => $scanResult['vat_amount'],
                    'total_amount'     => $scanResult['total_amount'],
                    'description'      => $scanResult['description'],
                    'items'            => $scanResult['items'],
                ],
                'warnings'         => $scanResult['warnings'],
                'engine'           => $scanResult['engine'],
                'confidence'       => $scanResult['confidence'],
                'confidence_score' => $scanResult['confidence_score'],
                'qc_notes'         => $scanResult['qc_notes'],
            ]);

        } catch (\Throwable $e) {
            Log::error("Purchase receipt scanning error: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'OCR scanning failed: ' . $e->getMessage(),
            ], 422);

        } finally {
            // Clean up temporary file
            if ($tempPath && Storage::disk('local')->exists($tempPath)) {
                Storage::disk('local')->delete($tempPath);
            }
        }
    }

    /**
     * Authorize user against purchasing, store, finance, and admin roles.
     */
    protected function authorizePurchasingRole(): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $allowedRoles = [
            'admin',
            'global_admin',
            'general_manager',
            'purchase',
            'purchaser',
            'buyer',
            'procurement_team',
            'purchase_manager',
            'store_keeper',
            'store_manager',
            'finance',
            'finance_officer',
            'finance_manager',
            'finance_head',
        ];

        if (method_exists($user, 'hasAnyRole')) {
            if (!$user->hasAnyRole($allowedRoles)) {
                abort(403, 'Unauthorized. Purchaser, storekeeper, finance, or admin role required.');
            }
        } elseif (method_exists($user, 'hasRole')) {
            $hasAny = false;
            foreach ($allowedRoles as $role) {
                if ($user->hasRole($role)) {
                    $hasAny = true;
                    break;
                }
            }
            if (!$hasAny) {
                abort(403, 'Unauthorized. Purchaser, storekeeper, finance, or admin role required.');
            }
        }
    }
}
