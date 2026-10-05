<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class OCRReceiptScannerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Check if user is authorized to use the OCR Receipt Scanner.
     */
    private function ensureGlobalAdmin()
    {
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        $allowedRoles = ['global_admin', 'admin', 'finance_officer', 'finance_manager', 'store_keeper', 'store_manager', 'general_manager'];

        $hasRole = false;
        if (method_exists($user, 'hasAnyRole')) {
            $hasRole = $user->hasAnyRole($allowedRoles);
        } elseif (method_exists($user, 'hasRole')) {
            foreach ($allowedRoles as $role) {
                if ($user->hasRole($role)) {
                    $hasRole = true;
                    break;
                }
            }
        }

        if (!$hasRole && !$user->is_admin && !$user->can('manage_receipts')) {
            abort(403, 'Unauthorized access. The OCR Receipt Scanner requires admin or finance privileges.');
        }
    }

    /**
     * Display the OCR Scanner Studio and recent scans.
     */
    public function index(Request $request)
    {
        $this->ensureGlobalAdmin();

        $query = Receipt::with(['uploader', 'project'])->latest();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('receipt_number', 'like', "%{$s}%")
                  ->orWhere('vendor_name', 'like', "%{$s}%")
                  ->orWhere('vendor_tin', 'like', "%{$s}%")
                  ->orWhere('ocr_raw_text', 'like', "%{$s}%");
            });
        }

        $receipts = $query->paginate(15)->withQueryString();
        $projects = Project::where('status', '!=', 'cancelled')->orderBy('name')->get();
        $categories = Receipt::CATEGORIES;

        $stats = [
            'total_scanned'   => Receipt::count(),
            'total_value'     => Receipt::sum('total_amount'),
            'today_scanned'   => Receipt::whereDate('created_at', now()->toDateString())->count(),
            'total_vat'       => Receipt::sum('vat_amount'),
        ];

        return view('admin.ocr.index', compact('receipts', 'projects', 'categories', 'stats'));
    }

    /**
     * Upload receipt image and prepare for OCR parsing.
     */
    public function upload(Request $request)
    {
        $this->ensureGlobalAdmin();

        $request->validate([
            'receipt_file' => 'required|file|mimes:jpeg,jpg,png,webp,pdf|max:15360',
        ]);

        $file     = $request->file('receipt_file');
        $mimeType = $file->getMimeType();
        $ext      = strtolower($file->getClientOriginalExtension());
        $isPdf    = $ext === 'pdf' || str_contains($mimeType, 'pdf');

        $filename = 'ocr_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
        $path     = $file->storeAs('receipts', $filename, 'public');
        $fileUrl  = asset('storage/' . $path);

        return response()->json([
            'success'   => true,
            'file_path' => $path,
            'file_url'  => $fileUrl,
            'file_type' => $isPdf ? 'pdf' : 'image',
            'file_name' => $file->getClientOriginalName(),
            'file_size' => round($file->getSize() / 1024, 1) . ' KB',
        ]);
    }

    /**
     * Scan receipt directly using Google Gemini Multimodal Vision API.
     */
    public function aiScan(Request $request)
    {
        $this->ensureGlobalAdmin();

        $request->validate([
            'receipt_file' => 'required|file|mimes:jpeg,jpg,png,webp,pdf|max:15360',
        ]);

        $file     = $request->file('receipt_file');
        $mimeType = $file->getMimeType();
        $ext      = strtolower($file->getClientOriginalExtension());
        $isPdf    = $ext === 'pdf' || str_contains($mimeType, 'pdf');

        $filename = 'ocr_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
        $path     = $file->storeAs('receipts', $filename, 'public');
        $fileUrl  = asset('storage/' . $path);

        $apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
        $base64 = base64_encode(file_get_contents($file->getRealPath()));

        $prompt = <<<PROMPT
You are an expert fiscal auditor specialized in Ethiopian ERCA / MOR fiscal cash machine receipts (Datecs, Daisy, Citizen), sales invoices, and commercial cash slips for Ethiopian Ministry of Revenues VAT declaration (Line 100).
Extract all information from this receipt image with absolute accuracy.

CRITICAL INSTRUCTIONS FOR ETHIOPIAN ERCA RECEIPTS:
1. SUPPLIER TIN vs BUYER TIN:
   - supplier_tin: 10-digit TIN of the SELLER / MERCHANT / SUPPLIER issuing the receipt (e.g. "0024916531", "0043724322"). This is usually at the top near the merchant name/header. This is MANDATORY. Do NOT confuse with Buyer's TIN!
   - buyer_tin: 10-digit TIN of the BUYER / CLIENT / CUSTOMER (often "0038480010" or labeled "Buyer's TIN", "Customer TIN").
2. MERCHANT / SELLER NAME:
   - merchant_name: Full registered trade name of the SELLER (e.g. "ASTRA GENERAL TRADING", "BERHANU TIEMAY ADHENA", "SEID LIDIA AND FRIENDS", "NEFAS SILK PAINTS").
   - proprietor_name: Proprietor / manager name if listed.
3. RECEIPT DATE:
   - receipt_date: Format as YYYY-MM-DD (e.g. "2026-09-25"). Often printed on receipt as DD/MM/YYYY.
   - date_formatted: Format as DD/MM/YYYY.
4. ERCA / MRC MACHINE NUMBER:
   - machine_no: Cash machine registration code / MRC number (e.g. "TDB0015170", "MFE0097690", "DFA0029991", "BIB0118931", "DDJ0006391"). Often printed next to MRC or at the footer next to ERCA.
5. FS / FISCAL RECEIPT NUMBER:
   - fs_no: The fiscal receipt sequence number (e.g. "FS00002674", "FS00002564", "00002674").
6. DESCRIPTION / ITEMS:
   - description: Summary of goods/materials purchased (e.g. "WATER PROOF AND WIRE", "ROUND PIPE , FLAT BAR", "SILCON GLUE", "CONSTRUCTION WORK", "WINDOW SILL", "NORMAL NAIL").
   - line_items: Array of objects with keys: name (string), qty (float), unit_price (float), total (float), uom (string, e.g. "PCS", "KG", "LIT", "OTHER").
7. FINANCIAL AMOUNTS (ETB):
   - subtotal: Taxable value before VAT (often labeled TAXBL1 or Taxable Amount, e.g. 44086.97).
   - vat_amount: 15% VAT (often labeled TAX1 15% or VAT, e.g. 6613.05).
   - total_amount: Grand total / value after VAT (labeled TOTAL or CASH Birr, e.g. 50700.02).
8. VAT DECLARATION CLASSIFICATION (Ethiopian ERCA / MOR):
   - vat_category: "G" for Goods or "S" for Services.
   - calendar_type: "G" for Gregorian or "E" for Ethiopian.
   - purchase_type: 3 (Taxable-local Purchase of Inputs - Line No. 100).
   - uom_id: 7 for PCS, 2 for KG, 5 for LIT, 9 for OTHER.

Return a strictly valid JSON object:
{
  "merchant_name": "string",
  "proprietor_name": "string or null",
  "supplier_tin": "10 digits string",
  "buyer_tin": "10 digits string or null",
  "receipt_date": "YYYY-MM-DD",
  "date_formatted": "DD/MM/YYYY",
  "machine_no": "string or null",
  "fs_no": "string",
  "description": "string",
  "subtotal": 0.00,
  "vat_amount": 0.00,
  "total_amount": 0.00,
  "category": "material",
  "vat_category": "G",
  "calendar_type": "G",
  "purchase_type": 3,
  "uom_id": 9,
  "supplier_address": "string or null",
  "supplier_phone": "string or null",
  "line_items": [
    {"name": "string", "qty": 1, "unit_price": 0.00, "total": 0.00, "uom": "OTHER"}
  ],
  "raw_text": "transcription string"
}
PROMPT;

        $models = ['gemini-3.8-flash', 'gemini-2.5-flash'];
        $lastError = null;

        foreach ($models as $model) {
            try {
                $response = \Illuminate\Support\Facades\Http::retry(2, 600)
                    ->timeout(45)
                    ->post(
                        "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey,
                        [
                            'contents' => [
                                [
                                    'parts' => [
                                        ['text' => $prompt],
                                        [
                                            'inlineData' => [
                                                'mimeType' => $isPdf ? 'application/pdf' : $mimeType,
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
                            // Normalize numeric amounts
                            $cleanNum = function($v) {
                                if (is_numeric($v)) return (float)$v;
                                if (is_string($v)) {
                                    $c = preg_replace('/[^0-9.]/', '', str_replace(',', '', $v));
                                    return is_numeric($c) ? (float)$c : 0.0;
                                }
                                return 0.0;
                            };

                            $subtotal = $cleanNum($parsed['subtotal'] ?? 0);
                            $vat      = $cleanNum($parsed['vat_amount'] ?? 0);
                            $total    = $cleanNum($parsed['total_amount'] ?? 0);

                            if ($total <= 0 && $subtotal > 0) {
                                $vat   = $vat > 0 ? $vat : round($subtotal * 0.15, 2);
                                $total = round($subtotal + $vat, 2);
                            } elseif ($subtotal <= 0 && $total > 0) {
                                $subtotal = round($total / 1.15, 2);
                                $vat      = round($total - $subtotal, 2);
                            }

                            $parsed['subtotal']     = $subtotal;
                            $parsed['vat_amount']   = $vat;
                            $parsed['total_amount'] = $total;

                            // Ensure clean 10-digit TINs
                            if (!empty($parsed['supplier_tin'])) {
                                $parsed['supplier_tin'] = preg_replace('/[^0-9]/', '', (string)$parsed['supplier_tin']);
                            }
                            if (!empty($parsed['buyer_tin'])) {
                                $parsed['buyer_tin'] = preg_replace('/[^0-9]/', '', (string)$parsed['buyer_tin']);
                            }

                            return response()->json([
                                'success'   => true,
                                'ai'        => true,
                                'file_path' => $path,
                                'file_url'  => $fileUrl,
                                'data'      => $parsed,
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                \Illuminate\Support\Facades\Log::warning("Gemini OCR ({$model}) API error: " . $lastError);
            }
        }

        return response()->json([
            'success'   => true,
            'ai'        => false,
            'file_path' => $path,
            'file_url'  => $fileUrl,
            'message'   => 'AI Vision temporarily busy; using high-precision in-browser engine.',
        ]);
    }

    /**
     * Save the OCR-extracted data into the receipts database.
     */
    public function save(Request $request)
    {
        $this->ensureGlobalAdmin();

        $request->validate([
            'file_path'    => 'required|string',
            'vendor_name'  => 'nullable|string|max:255',
            'vendor_tin'   => 'nullable|string|max:50',
            'receipt_date' => 'nullable|date',
            'subtotal'     => 'nullable|numeric|min:0',
            'vat_amount'   => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'category'     => 'nullable|string',
            'project_id'   => 'nullable|exists:projects,id',
            'description'  => 'nullable|string',
            'ocr_raw_text' => 'nullable|string',
        ]);

        $ext = strtolower(pathinfo($request->file_path, PATHINFO_EXTENSION));
        $fileType = ($ext === 'pdf') ? 'pdf' : 'image';

        $subtotal = (float)($request->subtotal ?: 0);
        $vat = (float)($request->vat_amount ?: 0);
        $total = (float)($request->total_amount ?: 0);

        if ($subtotal == 0 && $total > 0 && $vat > 0) {
            $subtotal = max(0, $total - $vat);
        }

        $desc = $request->description ?: '';
        if ($request->filled('fs_no') && !str_contains($desc, $request->fs_no)) {
            $desc = trim($desc . " (FS No: {$request->fs_no})");
        }

        $receipt = Receipt::create([
            'uploaded_by'  => Auth::id(),
            'project_id'   => $request->project_id,
            'vendor_name'  => $request->vendor_name ?: 'General Merchant',
            'vendor_tin'   => $request->vendor_tin,
            'receipt_date' => $request->receipt_date ?: now()->toDateString(),
            'subtotal'     => $subtotal,
            'vat_amount'   => $vat,
            'total_amount' => $total,
            'currency'     => 'ETB',
            'category'     => $request->category ?: 'other',
            'description'  => $desc,
            'file_path'    => $request->file_path,
            'file_type'    => $fileType,
            'ocr_raw_text' => $request->ocr_raw_text,
            'parsed_data'  => [
                'vendor'        => $request->vendor_name,
                'proprietor'    => $request->proprietor_name,
                'tin'           => $request->vendor_tin,
                'buyer_tin'     => $request->buyer_tin,
                'fs_no'         => $request->fs_no,
                'machine_no'    => $request->machine_no,
                'address'       => $request->vendor_address,
                'phone'         => $request->vendor_phone,
                'date'          => $request->receipt_date,
                'subtotal'      => $subtotal,
                'vat'           => $vat,
                'total'         => $total,
                'category'      => $request->category,
                'items'         => $request->input('line_items', []),
                'vat_category'  => $request->input('vat_category', 'G'),
                'calendar_type' => $request->input('calendar_type', 'G'),
                'purchase_type' => $request->input('purchase_type', 3),
                'uom_id'        => $request->input('uom_id', 9),
                'scanned_by'    => Auth::user()->name,
                'scanned_at'    => now()->toIso8601String(),
            ],
            'parse_status' => 'parsed',
            'status'       => 'approved', // Auto-approved when scanned and confirmed by Admin/Finance
            'approved_by'  => Auth::id(),
            'approved_at'  => now(),
            'notes'        => 'Scanned and verified via OCR Scanner Studio.' . ($request->fs_no ? " FS: {$request->fs_no}" : ""),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Receipt {$receipt->receipt_number} saved and recorded successfully!",
            'receipt' => $receipt,
        ]);
    }

    /**
     * Export scanned receipts into exact Ethiopian ERCA VAT Report Excel/CSV format (Line 100).
     * Matches VAT REPORT SEMPTMBER 2026.xlsx exactly.
     */
    public function exportVatReport(Request $request)
    {
        $this->ensureAuthorized();

        $query = Receipt::with(['project'])->latest();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $receipts = $query->get();

        $filename = 'VAT_REPORT_' . now()->format('Y_m_d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($receipts) {
            $out = fopen('php://output', 'w');
            
            // UTF-8 BOM for Microsoft Excel compatibility
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            // Exact 15 columns matching VAT REPORT SEMPTMBER 2026.xlsx
            fputcsv($out, [
                "VAT CATEGORY\n (G=GOODS;S=SERVICES)",
                "CALENDAR TYPE\n(E=ETHIOPIAN;G=GREGORIAN)",
                "Types of purchase.\n1 = Taxable-local Purchase of Capital Assets (Line No. 65)\n2 = Taxable-imported Purchase of Capital Assets (Line No. 75)\n3 = Taxable-local Purchase of Inputs (Line No. 100)\n4 = Taxable-imported Purchase of Inputs (Line No. 110)\n5 = Taxable-general Expense Inputs Purchase (Line No. 120)\n6= Tax Exempted-purchase with no vat or uncollectible inputs (Line no. 85 or Line no. 130) \n\n (Please type 1 or 2 or 3 or 4 or 5 or 6).This field is mandatory.",
                "TIN..This field\n is not mandatory.",
                "Seller name (if Seller has no TIN or item is not locally purchased)\nThis field is not mandatory.",
                "Date of purchase/Customs Declaration No.\n Dispatched Date (Please use  dd/mm/yyyy date format). \nThis field is mandatory.",
                "MRC Number..This field is not mandatory.",
                "Vat receipt number/ Customs Declaration Number.This field is mandatory.",
                "Description.This field is mandatory.",
                "Unit of Measure (type ID 2-10).\n2 KG\n3 ML\n4 GM\n5 LIT\n6 MT\n7 PCS\n8 CT\n9 OTHER\n10 PC\nThis field is mandatory.",
                "Quantity.\nEnter number.Don't use comma (,) or Quatation (\"\")\nThis field is  mandatory.",
                "Unit Price.\nEnter number only .Don't use comma (,) or Quatation (\"\")\nThis field is  mandatory.",
                "Total value",
                "vat",
                "value after vat"
            ]);

            foreach ($receipts as $r) {
                $p = is_array($r->parsed_data) ? $r->parsed_data : (json_decode($r->parsed_data, true) ?: []);
                
                $vatCat = $p['vat_category'] ?? ($r->category === 'transport' || $r->category === 'utility' ? 'S' : 'G');
                $calType = $p['calendar_type'] ?? 'G';
                $purchType = $p['purchase_type'] ?? 3;
                $tin = $r->vendor_tin ?: ($p['tin'] ?? '');
                $name = $r->vendor_name ?: ($p['vendor'] ?? '');
                $dateFormatted = $r->receipt_date ? $r->receipt_date->format('d/m/Y') : now()->format('d/m/Y');
                $mrc = $p['machine_no'] ?? '';
                $fs = $p['fs_no'] ?? '';
                if ($fs && !str_starts_with(strtoupper($fs), 'FS') && !str_starts_with(strtoupper($fs), 'M')) {
                    $fs = 'FS' . $fs;
                }
                $desc = $r->description ?: ($p['description'] ?? 'Building materials');
                $desc = preg_replace('/\(FS No:[^)]+\)/i', '', $desc);
                $desc = trim($desc) ?: 'MATERIAL';

                $uom = $p['uom_id'] ?? 9;
                $qty = 1;
                $subtotal = round((float)$r->subtotal, 2);
                $vat = round((float)$r->vat_amount, 2);
                $total = round((float)$r->total_amount, 2);

                fputcsv($out, [
                    $vatCat,
                    $calType,
                    $purchType,
                    $tin,
                    $name,
                    $dateFormatted,
                    $mrc,
                    $fs,
                    $desc,
                    $uom,
                    $qty,
                    $subtotal,
                    $subtotal,
                    $vat,
                    $total,
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * View details of a specific scanned receipt.
     */
    public function show(Receipt $receipt)
    {
        $this->ensureAuthorized();
        $receipt->load(['uploader', 'project', 'approver']);

        return response()->json([
            'success' => true,
            'receipt' => $receipt,
            'file_url'=> asset('storage/' . $receipt->file_path),
        ]);
    }

    /**
     * Delete a scanned receipt.
     */
    public function destroy(Receipt $receipt)
    {
        $this->ensureAuthorized();

        if ($receipt->file_path && Storage::disk('public')->exists($receipt->file_path)) {
            Storage::disk('public')->delete($receipt->file_path);
        }

        $receiptNo = $receipt->receipt_number;
        $receipt->delete();

        return redirect()->route('admin.ocr.index')->with('success', "Receipt {$receiptNo} deleted successfully.");
    }
}
