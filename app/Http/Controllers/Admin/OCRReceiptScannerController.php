<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Schema\Blueprint;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;

class OCRReceiptScannerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Check if user is authorized to use the OCR Receipt Scanner.
     */
    private function ensureAuthorized()
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

        if (!$hasRole && empty($user->is_admin) && (method_exists($user, 'can') ? !$user->can('manage_receipts') : false)) {
            abort(403, 'Unauthorized access. The OCR Receipt Scanner requires admin or finance privileges.');
        }
    }

    /**
     * Self-healing runtime schema check.
     * Ensures receipts and receipt_items tables and columns exist without needing manual migration.
     */
    private function ensureSchema()
    {
        try {
            if (Schema::hasTable('receipts')) {
                if (!Schema::hasColumn('receipts', 'fs_no') || !Schema::hasColumn('receipts', 'ocr_engine') || !Schema::hasColumn('receipts', 'confidence')) {
                    Schema::table('receipts', function (Blueprint $table) {
                        if (!Schema::hasColumn('receipts', 'fs_no')) {
                            $table->string('fs_no')->nullable()->index()->after('vendor_tin');
                        }
                        if (!Schema::hasColumn('receipts', 'mrc_no')) {
                            $table->string('mrc_no')->nullable()->after('fs_no');
                        }
                        if (!Schema::hasColumn('receipts', 'buyer_tin')) {
                            $table->string('buyer_tin')->nullable()->after('vendor_tin');
                        }
                        if (!Schema::hasColumn('receipts', 'ocr_engine')) {
                            $table->string('ocr_engine', 50)->default('gemini')->after('ocr_raw_text');
                        }
                        if (!Schema::hasColumn('receipts', 'confidence')) {
                            $table->string('confidence', 50)->default('high')->after('ocr_engine');
                        }
                        if (!Schema::hasColumn('receipts', 'needs_review')) {
                            $table->boolean('needs_review')->default(false)->after('confidence');
                        }
                    });
                }
            }

            if (!Schema::hasTable('receipt_items')) {
                Schema::create('receipt_items', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
                    $table->text('item_description')->nullable();
                    $table->string('vat_category', 10)->default('G');
                    $table->string('calendar_type', 10)->default('G');
                    $table->integer('purchase_type')->default(3);
                    $table->string('uom', 50)->default('9');
                    $table->decimal('qty', 15, 2)->default(1.00);
                    $table->decimal('unit_price', 15, 2)->default(0.00);
                    $table->decimal('total_value', 15, 2)->default(0.00);
                    $table->decimal('vat_amount', 15, 2)->default(0.00);
                    $table->decimal('value_after_vat', 15, 2)->default(0.00);
                    $table->boolean('is_flagged')->default(false);
                    $table->json('flag_reasons')->nullable();
                    $table->timestamps();
                });
            }

            // Backfill legacy receipts that have no items in receipt_items
            $unmigrated = Receipt::doesntHave('items')->get();
            foreach ($unmigrated as $r) {
                $parsed = is_array($r->parsed_data) ? $r->parsed_data : (json_decode($r->parsed_data, true) ?: []);
                $fsNo = $r->fs_no ?: ($parsed['fs_no'] ?? null);
                $mrcNo = $r->mrc_no ?: ($parsed['machine_no'] ?? null);
                $buyerTin = $r->buyer_tin ?: ($parsed['buyer_tin'] ?? null);

                if (empty($r->fs_no) && $fsNo) {
                    $r->fs_no = $fsNo;
                }
                if (empty($r->mrc_no) && $mrcNo) {
                    $r->mrc_no = $mrcNo;
                }
                if (empty($r->buyer_tin) && $buyerTin) {
                    $r->buyer_tin = $buyerTin;
                }
                $r->saveQuietly();

                $items = $parsed['items'] ?? ($parsed['line_items'] ?? []);
                if (!empty($items) && is_array($items)) {
                    foreach ($items as $item) {
                        $desc = $item['name'] ?? ($item['item_description'] ?? ($item['description'] ?? 'Material'));
                        $qty = (float)($item['qty'] ?? 1);
                        $unitPrice = (float)($item['unit_price'] ?? 0);
                        $totalVal = (float)($item['total'] ?? ($item['total_value'] ?? ($qty * $unitPrice)));
                        $vat = (float)($item['vat'] ?? ($item['vat_amount'] ?? round($totalVal * 0.15, 2)));
                        $afterVat = (float)($item['value_after_vat'] ?? round($totalVal + $vat, 2));

                        ReceiptItem::create([
                            'receipt_id'       => $r->id,
                            'item_description' => $desc,
                            'vat_category'     => $parsed['vat_category'] ?? 'G',
                            'calendar_type'    => $parsed['calendar_type'] ?? 'G',
                            'purchase_type'    => (int)($parsed['purchase_type'] ?? 3),
                            'uom'              => (string)($item['uom'] ?? ($parsed['uom_id'] ?? '9')),
                            'qty'              => $qty,
                            'unit_price'       => $unitPrice,
                            'total_value'      => $totalVal,
                            'vat_amount'       => $vat,
                            'value_after_vat'  => $afterVat,
                            'is_flagged'       => false,
                            'created_at'       => $r->created_at,
                            'updated_at'       => $r->updated_at,
                        ]);
                    }
                } else {
                    ReceiptItem::create([
                        'receipt_id'       => $r->id,
                        'item_description' => $r->description ?: 'Purchased Material',
                        'vat_category'     => $parsed['vat_category'] ?? 'G',
                        'calendar_type'    => $parsed['calendar_type'] ?? 'G',
                        'purchase_type'    => (int)($parsed['purchase_type'] ?? 3),
                        'uom'              => (string)($parsed['uom_id'] ?? '9'),
                        'qty'              => 1.00,
                        'unit_price'       => (float)$r->subtotal,
                        'total_value'      => (float)$r->subtotal,
                        'vat_amount'       => (float)$r->vat_amount,
                        'value_after_vat'  => (float)$r->total_amount,
                        'is_flagged'       => false,
                        'created_at'       => $r->created_at,
                        'updated_at'       => $r->updated_at,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ensureSchema warning: ' . $e->getMessage());
        }
    }

    /**
     * Display the OCR Scanner Studio and the persisted Receipt items table (A to O).
     */
    public function index(Request $request)
    {
        $this->ensureAuthorized();
        $this->ensureSchema();

        $query = ReceiptItem::with(['receipt', 'receipt.project', 'receipt.uploader'])
            ->whereHas('receipt')
            ->latest('id');

        // Filter: Needs Review / Flagged
        if ($request->boolean('needs_review')) {
            $query->where(function($q) {
                $q->where('is_flagged', true)
                  ->orWhereHas('receipt', function($rq) {
                      $rq->where('needs_review', true);
                  });
            });
        }

        // Filter: Category
        if ($request->filled('category')) {
            $cat = $request->category;
            $query->whereHas('receipt', function($q) use ($cat) {
                $q->where('category', $cat);
            });
        }

        // Filter: Project
        if ($request->filled('project_id')) {
            $pid = $request->project_id;
            $query->whereHas('receipt', function($q) use ($pid) {
                $q->where('project_id', $pid);
            });
        }

        // Filter: Date Range
        if ($request->filled('date_from')) {
            $df = $request->date_from;
            $query->whereHas('receipt', function($q) use ($df) {
                $q->whereDate('receipt_date', '>=', $df);
            });
        }
        if ($request->filled('date_to')) {
            $dt = $request->date_to;
            $query->whereHas('receipt', function($q) use ($dt) {
                $q->whereDate('receipt_date', '<=', $dt);
            });
        }

        // Filter: Search query (FS No, TIN, Vendor name, Description, MRC)
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function($q) use ($s) {
                $q->where('item_description', 'like', "%{$s}%")
                  ->orWhereHas('receipt', function($rq) use ($s) {
                      $rq->where('fs_no', 'like', "%{$s}%")
                         ->orWhere('vendor_name', 'like', "%{$s}%")
                         ->orWhere('vendor_tin', 'like', "%{$s}%")
                         ->orWhere('mrc_no', 'like', "%{$s}%")
                         ->orWhere('receipt_number', 'like', "%{$s}%");
                  });
            });
        }

        $items = $query->paginate(25)->withQueryString();
        $projects = Project::where('status', '!=', 'cancelled')->orderBy('name')->get();
        $categories = Receipt::CATEGORIES;

        // Statistics
        $totalItemsCount = ReceiptItem::whereHas('receipt')->count();
        $totalReceiptsCount = Receipt::count();
        $totalValue = (float) ReceiptItem::whereHas('receipt')->sum('total_value');
        $totalVat = (float) ReceiptItem::whereHas('receipt')->sum('vat_amount');
        $totalAfterVat = (float) ReceiptItem::whereHas('receipt')->sum('value_after_vat');
        $flaggedCount = ReceiptItem::whereHas('receipt')->where(function($q) {
            $q->where('is_flagged', true)
              ->orWhereHas('receipt', function($rq) {
                  $rq->where('needs_review', true);
              });
        })->count();

        $stats = [
            'total_items'    => $totalItemsCount,
            'total_receipts' => $totalReceiptsCount,
            'total_value'    => $totalValue,
            'total_vat'      => $totalVat,
            'total_after'    => $totalAfterVat,
            'flagged_count'  => $flaggedCount,
        ];

        // Masked keys for modal
        $geminiKey = SystemSetting::get('gemini_api_key', env('GEMINI_API_KEY'));
        $ocrSpaceKey = SystemSetting::get('ocr_space_api_key', env('OCR_SPACE_API_KEY', 'helloworld'));
        $maskedGemini = $this->maskKey($geminiKey);
        $maskedOcrSpace = $this->maskKey($ocrSpaceKey);

        return view('admin.ocr.index', compact(
            'items',
            'projects',
            'categories',
            'stats',
            'maskedGemini',
            'maskedOcrSpace',
            'geminiKey',
            'ocrSpaceKey'
        ));
    }

    /**
     * Upload receipt file and return preview URL.
     */
    public function upload(Request $request)
    {
        $this->ensureAuthorized();

        $request->validate([
            'receipt_file' => 'required|file|mimes:jpeg,jpg,png,webp,pdf|max:25600',
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
     * Direct single-receipt AI scan.
     */
    public function aiScan(Request $request)
    {
        $this->ensureAuthorized();

        $path = $request->input('file_path');
        $fileUrl = null;
        $mimeType = 'image/jpeg';
        $ext = 'jpg';
        $base64 = null;

        if ($request->hasFile('receipt_file')) {
            $file = $request->file('receipt_file');
            $mimeType = $file->getMimeType();
            $ext = strtolower($file->getClientOriginalExtension());
            $filename = 'ocr_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
            $path = $file->storeAs('receipts', $filename, 'public');
            $fileUrl = asset('storage/' . $path);
            $base64 = base64_encode(file_get_contents($file->getRealPath()));
        } elseif (!empty($path) && Storage::disk('public')->exists($path)) {
            $fileUrl = asset('storage/' . $path);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mimeType = ($ext === 'pdf') ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
            $base64 = base64_encode(Storage::disk('public')->get($path));
        } else {
            return response()->json(['success' => false, 'message' => 'No receipt file provided to scan.'], 422);
        }

        $geminiKey = SystemSetting::get('gemini_api_key', env('GEMINI_API_KEY'));
        $extracted = null;
        $engine = 'gemini';
        $confidence = 'high';

        if (!empty($geminiKey)) {
            $extracted = $this->scanWithGemini($geminiKey, $base64, $mimeType);
        }

        if (!$extracted) {
            $ocrSpaceKey = SystemSetting::get('ocr_space_api_key', env('OCR_SPACE_API_KEY', 'helloworld'));
            $realPath = Storage::disk('public')->path($path);
            $extracted = $this->scanWithOcrSpace($ocrSpaceKey, $base64, $ext, $realPath);
            $engine = 'ocr_space';
            $confidence = 'review';
        }

        if (!$extracted) {
            return response()->json([
                'success'   => false,
                'message'   => 'Could not extract text with OCR engines. Please fill manually.',
                'file_path' => $path,
                'file_url'  => $fileUrl,
            ], 422);
        }

        return response()->json([
            'success'    => true,
            'ai'         => true,
            'data'       => $extracted,
            'engine'     => $engine,
            'confidence' => $confidence,
            'file_path'  => $path,
            'file_url'   => $fileUrl,
        ]);
    }

    /**
     * Save receipt & line items into database from the frontend "Add to Table" action.
     */
    public function save(Request $request)
    {
        $this->ensureAuthorized();
        $this->ensureSchema();

        $request->validate([
            'file_path'    => 'required|string',
            'vendor_name'  => 'nullable|string',
            'vendor_tin'   => 'nullable|string',
            'buyer_tin'    => 'nullable|string',
            'fs_no'        => 'nullable|string',
            'machine_no'   => 'nullable|string',
            'receipt_date' => 'nullable|string',
            'subtotal'     => 'nullable|numeric|min:0',
            'vat_amount'   => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'description'  => 'nullable|string',
            'category'     => 'nullable|string',
            'project_id'   => 'nullable|exists:projects,id',
            'line_items'   => 'nullable|array',
        ]);

        $fsNo = strtoupper(preg_replace('/\s+/', '', (string)($request->fs_no ?? '')));
        $supplierTin = preg_replace('/[^0-9]/', '', (string)($request->vendor_tin ?? ''));
        $dbDate = $this->parseDateToYmd($request->receipt_date);

        // Check for duplicate FS No
        if (!empty($fsNo) && !$request->boolean('force_save')) {
            $existing = Receipt::where('fs_no', $fsNo)->orWhere('parsed_data->fs_no', $fsNo)->first();
            if ($existing) {
                return response()->json([
                    'success'           => true,
                    'is_duplicate'      => true,
                    'duplicate_message' => "Receipt with FS No {$fsNo} already exists in ERP (#{$existing->receipt_number} - {$existing->vendor_name}).",
                    'existing_receipt'  => $existing,
                ]);
            }
        }

        $ext = strtolower(pathinfo($request->file_path, PATHINFO_EXTENSION));
        $fileType = ($ext === 'pdf') ? 'pdf' : 'image';

        $subtotal = (float)($request->subtotal ?: 0);
        $vat = (float)($request->vat_amount ?: 0);
        $total = (float)($request->total_amount ?: 0);

        if ($subtotal == 0 && $total > 0 && $vat > 0) {
            $subtotal = max(0, round($total - $vat, 2));
        }

        $receipt = Receipt::create([
            'uploaded_by'  => Auth::id() ?: 1,
            'project_id'   => $request->project_id,
            'vendor_name'  => $request->vendor_name ?: 'General Merchant',
            'vendor_tin'   => $supplierTin,
            'buyer_tin'    => $request->buyer_tin ?: '0038480010',
            'fs_no'        => $fsNo,
            'mrc_no'       => $request->machine_no,
            'receipt_date' => $dbDate ?: now()->toDateString(),
            'subtotal'     => $subtotal,
            'vat_amount'   => $vat,
            'total_amount' => $total,
            'currency'     => 'ETB',
            'category'     => $request->category ?: 'material',
            'description'  => $request->description ?: 'Purchased Goods',
            'file_path'    => $request->file_path,
            'file_type'    => $fileType,
            'ocr_raw_text' => $request->ocr_raw_text,
            'ocr_engine'   => $request->engine ?: 'gemini',
            'confidence'   => $request->confidence ?: 'high',
            'needs_review' => false,
            'parsed_data'  => $request->all(),
            'parse_status' => 'parsed',
            'status'       => 'approved',
            'approved_by'  => Auth::id() ?: 1,
            'approved_at'  => now(),
            'notes'        => 'Added to table via OCR Receipt Scanner Studio.',
        ]);

        $lineItems = $request->input('line_items', []);
        if (empty($lineItems)) {
            $lineItems = [
                [
                    'item_description' => $request->description ?: 'Purchased Material',
                    'uom'              => (string)($request->uom_id ?? '9'),
                    'qty'              => 1.00,
                    'unit_price'       => $subtotal,
                    'total_value'      => $subtotal,
                    'vat_amount'       => $vat,
                    'value_after_vat'  => $total,
                ]
            ];
        }

        $createdItems = [];
        $hasFlagged = false;

        foreach ($lineItems as $it) {
            $qty = (float)($it['qty'] ?? 1);
            $unitPrice = (float)($it['unit_price'] ?? 0);
            $totalVal = (float)($it['total_value'] ?? ($it['total'] ?? round($qty * $unitPrice, 2)));
            $vatAmt = (float)($it['vat_amount'] ?? ($it['vat'] ?? round($totalVal * 0.15, 2)));
            $valAfter = (float)($it['value_after_vat'] ?? round($totalVal + $vatAmt, 2));

            $rItem = new ReceiptItem([
                'receipt_id'       => $receipt->id,
                'item_description' => $it['item_description'] ?? ($it['name'] ?? 'Material'),
                'vat_category'     => $request->input('vat_category', 'G'),
                'calendar_type'    => $request->input('calendar_type', 'G'),
                'purchase_type'    => (int)($request->input('purchase_type', 3)),
                'uom'              => (string)($it['uom'] ?? ($request->uom_id ?? '9')),
                'qty'              => $qty,
                'unit_price'       => $unitPrice,
                'total_value'      => $totalVal,
                'vat_amount'       => $vatAmt,
                'value_after_vat'  => $valAfter,
            ]);

            $errors = [];
            if ($qty > 0 && $unitPrice > 0 && abs(round($qty * $unitPrice, 2) - $totalVal) > 0.05) {
                $errors[] = "Qty x Unit Price ≠ Total Value";
            }
            if ($totalVal > 0 && abs(round($totalVal * 0.15, 2) - $vatAmt) > 0.05) {
                $errors[] = "Total Value x 15% ≠ VAT";
            }
            if (($totalVal > 0 || $vatAmt > 0) && abs(round($totalVal + $vatAmt, 2) - $valAfter) > 0.05) {
                $errors[] = "Total Value + VAT ≠ Value After VAT";
            }

            if (!empty($errors)) {
                $rItem->is_flagged = true;
                $rItem->flag_reasons = $errors;
                $hasFlagged = true;
            }

            $rItem->save();
            $rItem->setRelation('receipt', $receipt);
            $createdItems[] = $rItem;
        }

        if ($hasFlagged) {
            $receipt->update(['needs_review' => true]);
        }

        return response()->json([
            'success'   => true,
            'message'   => "Receipt {$receipt->receipt_number} added to table with " . count($createdItems) . " item row(s)!",
            'receipt'   => $receipt,
            'items'     => $createdItems,
        ]);
    }

    /**
     * Create a new blank or manual row directly in the table.
     */
    public function createManualRow(Request $request)
    {
        $this->ensureAuthorized();
        $this->ensureSchema();

        $receipt = Receipt::create([
            'uploaded_by'  => Auth::id() ?: 1,
            'vendor_name'  => 'New Merchant',
            'vendor_tin'   => '0000000000',
            'receipt_date' => now()->toDateString(),
            'subtotal'     => 0.00,
            'vat_amount'   => 0.00,
            'total_amount' => 0.00,
            'file_path'    => '',
            'file_type'    => 'image',
            'status'       => 'approved',
            'approved_by'  => Auth::id() ?: 1,
            'approved_at'  => now(),
            'notes'        => 'Manually created table row.',
        ]);

        $item = ReceiptItem::create([
            'receipt_id'       => $receipt->id,
            'item_description' => 'New Item',
            'vat_category'     => 'G',
            'calendar_type'    => 'G',
            'purchase_type'    => 3,
            'uom'              => '9',
            'qty'              => 1.00,
            'unit_price'       => 0.00,
            'total_value'      => 0.00,
            'vat_amount'       => 0.00,
            'value_after_vat'  => 0.00,
            'is_flagged'       => true,
            'flag_reasons'     => ['New row - enter receipt details'],
        ]);

        $item->setRelation('receipt', $receipt);

        return response()->json([
            'success' => true,
            'item'    => $item,
            'receipt' => $receipt,
            'message' => 'New row added to table.',
        ]);
    }

    /**
     * Process an uploaded receipt file (Image or PDF).
     * Dual Engine OCR Pipeline: Gemini Multimodal AI -> fallback to OCR.Space.
     * Duplicate check on FS No.
     * Inserts into receipts and receipt_items table.
     */
    public function processFile(Request $request)
    {
        $this->ensureAuthorized();
        $this->ensureSchema();

        $request->validate([
            'receipt_file' => 'required|file|mimes:jpeg,jpg,png,webp,pdf|max:25600',
            'project_id'   => 'nullable|exists:projects,id',
            'category'     => 'nullable|string',
            'force_save'   => 'nullable|boolean',
        ]);

        $file     = $request->file('receipt_file');
        $mimeType = $file->getMimeType();
        $ext      = strtolower($file->getClientOriginalExtension());
        $isPdf    = $ext === 'pdf' || str_contains($mimeType, 'pdf');

        $filename = 'ocr_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
        $path     = $file->storeAs('receipts', $filename, 'public');
        $fileUrl  = asset('storage/' . $path);
        $fileBytes= file_get_contents($file->getRealPath());
        $base64   = base64_encode($fileBytes);

        // OCR Pipeline: 1. Try Gemini Multimodal AI
        $geminiKey = SystemSetting::get('gemini_api_key', env('GEMINI_API_KEY'));
        $extracted = null;
        $engineUsed = 'gemini';
        $confidence = 'high';
        $rawText = '';

        if (!empty($geminiKey)) {
            $extracted = $this->scanWithGemini($geminiKey, $base64, $isPdf ? 'application/pdf' : $mimeType);
            if ($extracted && !empty($extracted['raw_text'])) {
                $rawText = $extracted['raw_text'];
            }
        }

        // If Gemini failed or has no key, fallback to OCR.Space
        if (!$extracted) {
            $ocrSpaceKey = SystemSetting::get('ocr_space_api_key', env('OCR_SPACE_API_KEY', 'helloworld'));
            $extracted = $this->scanWithOcrSpace($ocrSpaceKey, $base64, $ext, $file->getRealPath());
            $engineUsed = 'ocr_space';
            $confidence = 'review';
            if ($extracted && !empty($extracted['raw_text'])) {
                $rawText = $extracted['raw_text'];
            }
        }

        // If both failed, construct a clean skeleton for manual verification
        if (!$extracted) {
            $extracted = [
                'merchant_name' => '',
                'supplier_tin'  => '',
                'buyer_tin'     => '0038480010',
                'receipt_date'  => now()->format('d/m/Y'),
                'machine_no'    => '',
                'fs_no'         => '',
                'items'         => [
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
                'vat_category'  => 'G',
                'calendar_type' => 'G',
                'purchase_type' => 3,
                'subtotal'      => 0.00,
                'vat_amount'    => 0.00,
                'total_amount'  => 0.00,
                'raw_text'      => 'OCR scanning failed to read text from file.',
            ];
            $confidence = 'low';
        }

        // Normalize extracted items and fields
        $fsNo = trim((string)($extracted['fs_no'] ?? ''));
        if (!empty($fsNo)) {
            $fsNo = strtoupper(preg_replace('/\s+/', '', $fsNo));
        }

        $supplierTin = trim((string)($extracted['supplier_tin'] ?? ''));
        $supplierTin = preg_replace('/[^0-9]/', '', $supplierTin);

        $merchantName = trim((string)($extracted['merchant_name'] ?? ''));
        $mrcNo = trim((string)($extracted['machine_no'] ?? ($extracted['mrc_no'] ?? '')));

        // Check for duplicate FS No
        $existingReceipt = null;
        if (!empty($fsNo)) {
            $existingReceipt = Receipt::where('fs_no', $fsNo)
                ->orWhere('parsed_data->fs_no', $fsNo)
                ->first();
        }

        $isDuplicate = false;
        if ($existingReceipt && !$request->boolean('force_save')) {
            $isDuplicate = true;
            return response()->json([
                'success'           => true,
                'is_duplicate'      => true,
                'duplicate_message' => "Duplicate detected: FS No {$fsNo} already exists in ERP (#{$existingReceipt->receipt_number} - {$existingReceipt->vendor_name}).",
                'existing_receipt'  => [
                    'id'             => $existingReceipt->id,
                    'receipt_number' => $existingReceipt->receipt_number,
                    'vendor_name'    => $existingReceipt->vendor_name,
                    'fs_no'          => $existingReceipt->fs_no,
                    'total_amount'   => $existingReceipt->total_amount,
                    'receipt_date'   => $existingReceipt->receipt_date ? $existingReceipt->receipt_date->format('d/m/Y') : '',
                ],
                'extracted'         => $extracted,
                'file_path'         => $path,
                'file_url'          => $fileUrl,
                'engine'            => $engineUsed,
                'confidence'        => $confidence,
            ]);
        }

        // Parse Receipt Date
        $dateStr = $extracted['receipt_date'] ?? now()->format('d/m/Y');
        $dbDate = $this->parseDateToYmd($dateStr);

        // Calculate receipt totals from items
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
        $calcVat = 0.0;
        $calcTotal = 0.0;
        foreach ($itemsData as $it) {
            $calcSubtotal += (float)($it['total_value'] ?? 0);
            $calcVat      += (float)($it['vat'] ?? 0);
            $calcTotal    += (float)($it['value_after_vat'] ?? 0);
        }

        if ($calcSubtotal == 0 && !empty($extracted['subtotal'])) {
            $calcSubtotal = (float)$extracted['subtotal'];
            $calcVat      = (float)$extracted['vat_amount'];
            $calcTotal    = (float)$extracted['total_amount'];
        }

        // Persist Receipt
        $receipt = Receipt::create([
            'uploaded_by'  => Auth::id() ?: 1,
            'project_id'   => $request->project_id,
            'vendor_name'  => $merchantName ?: 'General Merchant',
            'vendor_tin'   => $supplierTin,
            'buyer_tin'    => $extracted['buyer_tin'] ?? '0038480010',
            'fs_no'        => $fsNo,
            'mrc_no'       => $mrcNo,
            'receipt_date' => $dbDate,
            'subtotal'     => round($calcSubtotal, 2),
            'vat_amount'   => round($calcVat, 2),
            'total_amount' => round($calcTotal, 2),
            'currency'     => 'ETB',
            'category'     => $request->category ?: 'material',
            'description'  => $extracted['description'] ?? ($itemsData[0]['item_description'] ?? 'Materials'),
            'file_path'    => $path,
            'file_type'    => $isPdf ? 'pdf' : 'image',
            'ocr_raw_text' => $rawText,
            'ocr_engine'   => $engineUsed,
            'confidence'   => $confidence,
            'needs_review' => ($confidence === 'review' || $confidence === 'low'),
            'parsed_data'  => $extracted,
            'parse_status' => 'parsed',
            'status'       => 'approved',
            'approved_by'  => Auth::id() ?: 1,
            'approved_at'  => now(),
            'notes'        => "Scanned via {$engineUsed} ({$confidence} confidence). FS: {$fsNo}",
        ]);

        // Insert Receipt Items (One row per item)
        $createdItems = [];
        $hasFlaggedItem = false;

        foreach ($itemsData as $item) {
            $qty = (float)($item['qty'] ?? 1);
            $unitPrice = (float)($item['unit_price'] ?? 0);
            $totalVal = (float)($item['total_value'] ?? round($qty * $unitPrice, 2));
            $vatAmt = (float)($item['vat'] ?? round($totalVal * 0.15, 2));
            $valAfter = (float)($item['value_after_vat'] ?? round($totalVal + $vatAmt, 2));

            $rItem = new ReceiptItem([
                'receipt_id'       => $receipt->id,
                'item_description' => $item['item_description'] ?? 'Material',
                'vat_category'     => $extracted['vat_category'] ?? 'G',
                'calendar_type'    => $extracted['calendar_type'] ?? 'G',
                'purchase_type'    => (int)($extracted['purchase_type'] ?? 3),
                'uom'              => (string)($item['uom'] ?? ($extracted['uom_id'] ?? '9')),
                'qty'              => $qty,
                'unit_price'       => $unitPrice,
                'total_value'      => $totalVal,
                'vat_amount'       => $vatAmt,
                'value_after_vat'  => $valAfter,
            ]);

            // Arithmetic validation
            $errors = [];
            if ($qty > 0 && $unitPrice > 0 && abs(round($qty * $unitPrice, 2) - $totalVal) > 0.05) {
                $errors[] = "Qty x Unit Price (" . round($qty * $unitPrice, 2) . ") ≠ Total Value ($totalVal)";
            }
            if ($totalVal > 0 && abs(round($totalVal * 0.15, 2) - $vatAmt) > 0.05) {
                $errors[] = "Total Value x 15% (" . round($totalVal * 0.15, 2) . ") ≠ VAT ($vatAmt)";
            }
            if (($totalVal > 0 || $vatAmt > 0) && abs(round($totalVal + $vatAmt, 2) - $valAfter) > 0.05) {
                $errors[] = "Total Value + VAT (" . round($totalVal + $vatAmt, 2) . ") ≠ Value After VAT ($valAfter)";
            }
            if (empty($supplierTin) || strlen($supplierTin) !== 10) {
                $errors[] = "Supplier TIN is invalid (expected 10 digits)";
            }
            if (empty($fsNo)) {
                $errors[] = "FS Number is missing";
            }

            if (!empty($errors)) {
                $rItem->is_flagged = true;
                $rItem->flag_reasons = $errors;
                $hasFlaggedItem = true;
            }

            $rItem->save();
            $rItem->setRelation('receipt', $receipt);
            $createdItems[] = $rItem;
        }

        if ($hasFlaggedItem) {
            $receipt->update(['needs_review' => true]);
        }

        return response()->json([
            'success'       => true,
            'is_duplicate'  => false,
            'receipt'       => $receipt,
            'items'         => $createdItems,
            'file_url'      => $fileUrl,
            'engine'        => $engineUsed,
            'confidence'    => $confidence,
            'message'       => "Receipt {$receipt->receipt_number} scanned and saved successfully (" . count($createdItems) . " row" . (count($createdItems) > 1 ? 's' : '') . ").",
        ]);
    }

    /**
     * Replace existing duplicate receipt with newly uploaded receipt data.
     */
    public function replaceDuplicate(Request $request)
    {
        $this->ensureAuthorized();

        $request->validate([
            'existing_id'   => 'required|exists:receipts,id',
            'file_path'     => 'required|string',
            'extracted_data'=> 'required|array',
            'engine'        => 'nullable|string',
        ]);

        $receipt = Receipt::findOrFail($request->existing_id);
        $extracted = $request->extracted_data;

        // Delete old receipt file if different
        if ($receipt->file_path && $receipt->file_path !== $request->file_path && Storage::disk('public')->exists($receipt->file_path)) {
            Storage::disk('public')->delete($receipt->file_path);
        }

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
        $calcVat = 0.0;
        $calcTotal = 0.0;
        foreach ($itemsData as $it) {
            $calcSubtotal += (float)($it['total_value'] ?? 0);
            $calcVat      += (float)($it['vat'] ?? 0);
            $calcTotal    += (float)($it['value_after_vat'] ?? 0);
        }

        $fsNo = trim((string)($extracted['fs_no'] ?? ''));
        $supplierTin = preg_replace('/[^0-9]/', '', (string)($extracted['supplier_tin'] ?? ''));

        $receipt->update([
            'vendor_name'  => $extracted['merchant_name'] ?? $receipt->vendor_name,
            'vendor_tin'   => $supplierTin ?: $receipt->vendor_tin,
            'buyer_tin'    => $extracted['buyer_tin'] ?? $receipt->buyer_tin,
            'fs_no'        => $fsNo ?: $receipt->fs_no,
            'mrc_no'       => $extracted['machine_no'] ?? $receipt->mrc_no,
            'receipt_date' => $this->parseDateToYmd($extracted['receipt_date'] ?? null) ?: $receipt->receipt_date,
            'subtotal'     => round($calcSubtotal, 2),
            'vat_amount'   => round($calcVat, 2),
            'total_amount' => round($calcTotal, 2),
            'file_path'    => $request->file_path,
            'ocr_engine'   => $request->engine ?: 'gemini',
            'parsed_data'  => $extracted,
            'ocr_raw_text' => $extracted['raw_text'] ?? $receipt->ocr_raw_text,
            'notes'        => "Replaced with re-scan on " . now()->toDateTimeString(),
        ]);

        // Recreate items
        $receipt->items()->delete();
        $createdItems = [];
        $hasFlagged = false;

        foreach ($itemsData as $item) {
            $qty = (float)($item['qty'] ?? 1);
            $unitPrice = (float)($item['unit_price'] ?? 0);
            $totalVal = (float)($item['total_value'] ?? round($qty * $unitPrice, 2));
            $vatAmt = (float)($item['vat'] ?? round($totalVal * 0.15, 2));
            $valAfter = (float)($item['value_after_vat'] ?? round($totalVal + $vatAmt, 2));

            $rItem = new ReceiptItem([
                'receipt_id'       => $receipt->id,
                'item_description' => $item['item_description'] ?? 'Material',
                'vat_category'     => $extracted['vat_category'] ?? 'G',
                'calendar_type'    => $extracted['calendar_type'] ?? 'G',
                'purchase_type'    => (int)($extracted['purchase_type'] ?? 3),
                'uom'              => (string)($item['uom'] ?? '9'),
                'qty'              => $qty,
                'unit_price'       => $unitPrice,
                'total_value'      => $totalVal,
                'vat_amount'       => $vatAmt,
                'value_after_vat'  => $valAfter,
            ]);

            $errors = [];
            if ($qty > 0 && $unitPrice > 0 && abs(round($qty * $unitPrice, 2) - $totalVal) > 0.05) {
                $errors[] = "Qty x Unit Price ≠ Total Value";
            }
            if ($totalVal > 0 && abs(round($totalVal * 0.15, 2) - $vatAmt) > 0.05) {
                $errors[] = "Total Value x 15% ≠ VAT";
            }
            if (($totalVal > 0 || $vatAmt > 0) && abs(round($totalVal + $vatAmt, 2) - $valAfter) > 0.05) {
                $errors[] = "Total Value + VAT ≠ Value After VAT";
            }

            if (!empty($errors)) {
                $rItem->is_flagged = true;
                $rItem->flag_reasons = $errors;
                $hasFlagged = true;
            }

            $rItem->save();
            $rItem->setRelation('receipt', $receipt);
            $createdItems[] = $rItem;
        }

        $receipt->update(['needs_review' => $hasFlagged]);

        return response()->json([
            'success' => true,
            'message' => "Receipt {$receipt->receipt_number} updated with new scanned data.",
            'receipt' => $receipt,
            'items'   => $createdItems,
        ]);
    }

    /**
     * Inline row save: updates an individual row and its parent receipt details.
     */
    public function saveItem(Request $request)
    {
        $this->ensureAuthorized();

        $request->validate([
            'id'               => 'required|exists:receipt_items,id',
            'vat_category'     => 'nullable|string|in:G,S',
            'calendar_type'    => 'nullable|string|in:G,E',
            'purchase_type'    => 'nullable|integer',
            'supplier_tin'     => 'nullable|string',
            'seller_name'      => 'nullable|string',
            'receipt_date'     => 'nullable|string',
            'mrc_no'           => 'nullable|string',
            'fs_no'            => 'nullable|string',
            'item_description' => 'required|string',
            'uom'              => 'nullable|string',
            'qty'              => 'required|numeric|min:0',
            'unit_price'       => 'required|numeric|min:0',
            'total_value'      => 'required|numeric|min:0',
            'vat_amount'       => 'required|numeric|min:0',
            'value_after_vat'  => 'required|numeric|min:0',
        ]);

        $item = ReceiptItem::with('receipt')->findOrFail($request->id);
        $receipt = $item->receipt;

        $qty       = round((float)$request->qty, 2);
        $unitPrice = round((float)$request->unit_price, 2);
        $totalVal  = round((float)$request->total_value, 2);
        $vat       = round((float)$request->vat_amount, 2);
        $valAfter  = round((float)$request->value_after_vat, 2);

        // Update Item
        $item->update([
            'vat_category'     => $request->vat_category ?: 'G',
            'calendar_type'    => $request->calendar_type ?: 'G',
            'purchase_type'    => (int)($request->purchase_type ?: 3),
            'item_description' => $request->item_description,
            'uom'              => $request->uom ?: '9',
            'qty'              => $qty,
            'unit_price'       => $unitPrice,
            'total_value'      => $totalVal,
            'vat_amount'       => $vat,
            'value_after_vat'  => $valAfter,
        ]);

        // Update Parent Receipt Header fields
        $tin = preg_replace('/[^0-9]/', '', (string)$request->supplier_tin);
        $fs  = strtoupper(preg_replace('/\s+/', '', (string)$request->fs_no));
        $date = $this->parseDateToYmd($request->receipt_date);

        $receiptUpdates = [];
        if ($request->has('seller_name'))  $receiptUpdates['vendor_name'] = $request->seller_name;
        if ($request->has('supplier_tin')) $receiptUpdates['vendor_tin']  = $tin;
        if ($request->has('mrc_no'))       $receiptUpdates['mrc_no']       = $request->mrc_no;
        if ($request->has('fs_no'))        $receiptUpdates['fs_no']        = $fs;
        if ($date)                         $receiptUpdates['receipt_date'] = $date;

        if (!empty($receiptUpdates)) {
            $receipt->update($receiptUpdates);
        }

        // Recalculate parent receipt total amounts
        $receipt->subtotal     = round($receipt->items()->sum('total_value'), 2);
        $receipt->vat_amount   = round($receipt->items()->sum('vat_amount'), 2);
        $receipt->total_amount = round($receipt->items()->sum('value_after_vat'), 2);
        $receipt->save();

        // Re-run validation on this row
        $item->refresh();
        $errors = $item->validateRow();
        $item->is_flagged = !empty($errors);
        $item->flag_reasons = $errors;
        $item->save();

        return response()->json([
            'success'    => true,
            'message'    => "Row #{$item->id} updated successfully.",
            'item'       => $item->load('receipt'),
            'errors'     => $errors,
            'is_flagged' => !empty($errors),
        ]);
    }

    /**
     * Save all dirty/edited rows in batch.
     */
    public function saveAll(Request $request)
    {
        $this->ensureAuthorized();

        $rows = $request->input('rows', []);
        if (empty($rows) || !is_array($rows)) {
            return response()->json(['success' => false, 'message' => 'No rows provided to save.'], 422);
        }

        $savedCount = 0;
        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                if (empty($row['id'])) continue;
                $item = ReceiptItem::with('receipt')->find($row['id']);
                if (!$item) continue;

                $qty       = round((float)($row['qty'] ?? $item->qty), 2);
                $unitPrice = round((float)($row['unit_price'] ?? $item->unit_price), 2);
                $totalVal  = round((float)($row['total_value'] ?? $item->total_value), 2);
                $vat       = round((float)($row['vat_amount'] ?? $item->vat_amount), 2);
                $valAfter  = round((float)($row['value_after_vat'] ?? $item->value_after_vat), 2);

                $item->update([
                    'vat_category'     => $row['vat_category'] ?? $item->vat_category,
                    'calendar_type'    => $row['calendar_type'] ?? $item->calendar_type,
                    'purchase_type'    => (int)($row['purchase_type'] ?? $item->purchase_type),
                    'item_description' => $row['item_description'] ?? $item->item_description,
                    'uom'              => $row['uom'] ?? $item->uom,
                    'qty'              => $qty,
                    'unit_price'       => $unitPrice,
                    'total_value'      => $totalVal,
                    'vat_amount'       => $vat,
                    'value_after_vat'  => $valAfter,
                ]);

                if ($item->receipt) {
                    $rUpdates = [];
                    if (!empty($row['seller_name']))  $rUpdates['vendor_name'] = $row['seller_name'];
                    if (!empty($row['supplier_tin'])) $rUpdates['vendor_tin']  = preg_replace('/[^0-9]/', '', (string)$row['supplier_tin']);
                    if (!empty($row['mrc_no']))       $rUpdates['mrc_no']       = $row['mrc_no'];
                    if (!empty($row['fs_no']))        $rUpdates['fs_no']        = strtoupper(preg_replace('/\s+/', '', (string)$row['fs_no']));
                    if (!empty($row['receipt_date'])) {
                        $parsedDate = $this->parseDateToYmd($row['receipt_date']);
                        if ($parsedDate) $rUpdates['receipt_date'] = $parsedDate;
                    }

                    if (!empty($rUpdates)) {
                        $item->receipt->update($rUpdates);
                    }
                }

                $errors = $item->validateRow();
                $item->is_flagged = !empty($errors);
                $item->flag_reasons = $errors;
                $item->save();

                $savedCount++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error saving rows: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully saved {$savedCount} row(s).",
        ]);
    }

    /**
     * Delete an individual row with confirmation.
     */
    public function destroyItem(ReceiptItem $item)
    {
        $this->ensureAuthorized();

        $receipt = $item->receipt;
        $item->delete();

        // If parent receipt has no remaining items, clean up the receipt
        if ($receipt && $receipt->items()->count() === 0) {
            if ($receipt->file_path && Storage::disk('public')->exists($receipt->file_path)) {
                Storage::disk('public')->delete($receipt->file_path);
            }
            $receipt->delete();
        } elseif ($receipt) {
            // Recalculate parent totals
            $receipt->subtotal     = round($receipt->items()->sum('total_value'), 2);
            $receipt->vat_amount   = round($receipt->items()->sum('vat_amount'), 2);
            $receipt->total_amount = round($receipt->items()->sum('value_after_vat'), 2);
            $receipt->save();
        }

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Row deleted successfully.']);
        }

        return redirect()->route('admin.ocr.index')->with('success', 'Row deleted successfully.');
    }

    /**
     * Export table into Excel (.xlsx) with exact column order A to O.
     * Uses OpenSpout for true numeric cells and proper formats.
     */
    public function exportExcel(Request $request)
    {
        $this->ensureAuthorized();
        $this->ensureSchema();

        $query = ReceiptItem::with(['receipt'])->whereHas('receipt')->latest('id');

        // Apply filters
        if ($request->filled('selected_ids')) {
            $ids = explode(',', $request->selected_ids);
            $query->whereIn('id', $ids);
        } else {
            if ($request->boolean('needs_review')) {
                $query->where(function($q) {
                    $q->where('is_flagged', true)
                      ->orWhereHas('receipt', fn($rq) => $rq->where('needs_review', true));
                });
            }
            if ($request->filled('category')) {
                $cat = $request->category;
                $query->whereHas('receipt', fn($q) => $q->where('category', $cat));
            }
            if ($request->filled('date_from')) {
                $df = $request->date_from;
                $query->whereHas('receipt', fn($q) => $q->whereDate('receipt_date', '>=', $df));
            }
            if ($request->filled('date_to')) {
                $dt = $request->date_to;
                $query->whereHas('receipt', fn($q) => $q->whereDate('receipt_date', '<=', $dt));
            }
            if ($request->filled('search')) {
                $s = trim($request->search);
                $query->where(function($q) use ($s) {
                    $q->where('item_description', 'like', "%{$s}%")
                      ->orWhereHas('receipt', function($rq) use ($s) {
                          $rq->where('fs_no', 'like', "%{$s}%")
                             ->orWhere('vendor_name', 'like', "%{$s}%")
                             ->orWhere('vendor_tin', 'like', "%{$s}%");
                      });
                });
            }
        }

        $items = $query->get();
        $filename = 'VAT_RECEIPT_REPORT_' . now()->format('Y_m_d_His') . '.xlsx';
        $tempPath = storage_path('app/temp_' . uniqid() . '.xlsx');

        try {
            $writer = new XlsxWriter();
            $writer->openToFile($tempPath);

            // Header row with exact ERCA descriptions
            $headerCells = [
                Cell::fromValue("VAT CATEGORY\n(G=GOODS;S=SERVICES)"),
                Cell::fromValue("CALENDAR TYPE\n(E=ETHIOPIAN;G=GREGORIAN)"),
                Cell::fromValue("Types of purchase.\n1 = Taxable-local Purchase of Capital Assets (Line No. 65)\n2 = Taxable-imported Purchase of Capital Assets (Line No. 75)\n3 = Taxable-local Purchase of Inputs (Line No. 100)\n4 = Taxable-imported Purchase of Inputs (Line No. 110)\n5 = Taxable-general Expense Inputs Purchase (Line No. 120)\n6 = Tax Exempted-purchase with no vat or uncollectible inputs\n(Please type 1-6). Mandatory."),
                Cell::fromValue("TIN\nMandatory for local VAT"),
                Cell::fromValue("Seller name\nRegistered Business Trade Name"),
                Cell::fromValue("Date of purchase\nDispatched Date (DD/MM/YYYY)"),
                Cell::fromValue("MRC Number\nMachine Registration Code"),
                Cell::fromValue("Vat receipt number / FS No\nFiscal Receipt Number"),
                Cell::fromValue("Description / Item\nExact purchased material"),
                Cell::fromValue("Unit of Measure\n(2 KG, 5 LIT, 7 PCS, 9 OTHER, 10 PC)"),
                Cell::fromValue("Quantity\nNumeric only"),
                Cell::fromValue("Unit Price\nNumeric only"),
                Cell::fromValue("Total value\nTaxable Subtotal"),
                Cell::fromValue("VAT (15%)\nValue Added Tax"),
                Cell::fromValue("Value after vat\nGrand Total"),
            ];
            $writer->addRow(new Row($headerCells));

            foreach ($items as $item) {
                $r = $item->receipt;
                $dateFormatted = $r && $r->receipt_date ? $r->receipt_date->format('d/m/Y') : now()->format('d/m/Y');
                $tin = $r ? (string)$r->vendor_tin : '';
                $seller = $r ? (string)$r->vendor_name : '';
                $mrc = $r ? (string)$r->mrc_no : '';
                $fs = $r ? (string)$r->fs_no : '';

                $rowCells = [
                    Cell::fromValue((string)($item->vat_category ?: 'G')),
                    Cell::fromValue((string)($item->calendar_type ?: 'G')),
                    Cell::fromValue((int)($item->purchase_type ?: 3)),
                    Cell::fromValue($tin),
                    Cell::fromValue($seller),
                    Cell::fromValue($dateFormatted),
                    Cell::fromValue($mrc),
                    Cell::fromValue($fs),
                    Cell::fromValue((string)($item->item_description ?: 'Material')),
                    Cell::fromValue((string)($item->uom ?: '9')),
                    Cell::fromValue((float)$item->qty),
                    Cell::fromValue((float)$item->unit_price),
                    Cell::fromValue((float)$item->total_value),
                    Cell::fromValue((float)$item->vat_amount),
                    Cell::fromValue((float)$item->value_after_vat),
                ];
                $writer->addRow(new Row($rowCells));
            }

            $writer->close();

            return response()->download($tempPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            Log::error('OpenSpout Excel export error: ' . $e->getMessage());
            // Fallback to CSV with Excel compatibility
            return $this->exportCsv($request);
        }
    }

    /**
     * Export table into CSV matching exact columns A to O with UTF-8 BOM.
     */
    public function exportCsv(Request $request)
    {
        $this->ensureAuthorized();
        $this->ensureSchema();

        $query = ReceiptItem::with(['receipt'])->whereHas('receipt')->latest('id');

        if ($request->filled('selected_ids')) {
            $ids = explode(',', $request->selected_ids);
            $query->whereIn('id', $ids);
        } else {
            if ($request->boolean('needs_review')) {
                $query->where(function($q) {
                    $q->where('is_flagged', true)
                      ->orWhereHas('receipt', fn($rq) => $rq->where('needs_review', true));
                });
            }
            if ($request->filled('category')) {
                $cat = $request->category;
                $query->whereHas('receipt', fn($q) => $q->where('category', $cat));
            }
            if ($request->filled('date_from')) {
                $df = $request->date_from;
                $query->whereHas('receipt', fn($q) => $q->whereDate('receipt_date', '>=', $df));
            }
            if ($request->filled('date_to')) {
                $dt = $request->date_to;
                $query->whereHas('receipt', fn($q) => $q->whereDate('receipt_date', '<=', $dt));
            }
            if ($request->filled('search')) {
                $s = trim($request->search);
                $query->where(function($q) use ($s) {
                    $q->where('item_description', 'like', "%{$s}%")
                      ->orWhereHas('receipt', function($rq) use ($s) {
                          $rq->where('fs_no', 'like', "%{$s}%")
                             ->orWhere('vendor_name', 'like', "%{$s}%")
                             ->orWhere('vendor_tin', 'like', "%{$s}%");
                      });
                });
            }
        }

        $items = $query->get();
        $filename = 'VAT_REPORT_' . now()->format('Y_m_d_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($items) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($out, [
                "VAT CATEGORY\n (G=GOODS;S=SERVICES)",
                "CALENDAR TYPE\n(E=ETHIOPIAN;G=GREGORIAN)",
                "Types of purchase.\n1 = Taxable-local Purchase of Capital Assets (Line No. 65)\n2 = Taxable-imported Purchase of Capital Assets (Line No. 75)\n3 = Taxable-local Purchase of Inputs (Line No. 100)\n4 = Taxable-imported Purchase of Inputs (Line No. 110)\n5 = Taxable-general Expense Inputs Purchase (Line No. 120)\n6= Tax Exempted-purchase with no vat or uncollectible inputs (Line no. 85 or Line no. 130)",
                "TIN",
                "Seller name",
                "Date of purchase\nDispatched Date (dd/mm/yyyy)",
                "MRC Number",
                "Vat receipt number / FS No",
                "Description",
                "Unit of Measure (type ID 2-10)",
                "Quantity",
                "Unit Price",
                "Total value",
                "vat",
                "value after vat"
            ]);

            foreach ($items as $item) {
                $r = $item->receipt;
                $dateFormatted = $r && $r->receipt_date ? $r->receipt_date->format('d/m/Y') : now()->format('d/m/Y');
                $tin = $r ? (string)$r->vendor_tin : '';
                $seller = $r ? (string)$r->vendor_name : '';
                $mrc = $r ? (string)$r->mrc_no : '';
                $fs = $r ? (string)$r->fs_no : '';

                fputcsv($out, [
                    $item->vat_category ?: 'G',
                    $item->calendar_type ?: 'G',
                    $item->purchase_type ?: 3,
                    $tin,
                    $seller,
                    $dateFormatted,
                    $mrc,
                    $fs,
                    $item->item_description ?: 'Material',
                    $item->uom ?: '9',
                    number_format((float)$item->qty, 2, '.', ''),
                    number_format((float)$item->unit_price, 2, '.', ''),
                    number_format((float)$item->total_value, 2, '.', ''),
                    number_format((float)$item->vat_amount, 2, '.', ''),
                    number_format((float)$item->value_after_vat, 2, '.', ''),
                ]);
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Legacy alias for export-vat route.
     */
    public function exportVatReport(Request $request)
    {
        return $this->exportExcel($request);
    }

    /**
     * Save AI and OCR settings securely.
     */
    public function saveSettings(Request $request)
    {
        $this->ensureAuthorized();

        $request->validate([
            'gemini_api_key'    => 'nullable|string',
            'ocr_space_api_key' => 'nullable|string',
        ]);

        if ($request->has('gemini_api_key') && !str_contains($request->gemini_api_key, '...')) {
            SystemSetting::set('gemini_api_key', trim($request->gemini_api_key), 'string', 'ocr', 'Google Gemini AI Key');
        }

        if ($request->has('ocr_space_api_key') && !str_contains($request->ocr_space_api_key, '...')) {
            SystemSetting::set('ocr_space_api_key', trim($request->ocr_space_api_key), 'string', 'ocr', 'OCR.Space API Key');
        }

        return response()->json([
            'success' => true,
            'message' => 'OCR settings saved securely.',
        ]);
    }

    /**
     * Test API Key connectivity for Gemini or OCR.Space.
     */
    public function testApiKey(Request $request)
    {
        $this->ensureAuthorized();

        $engine = $request->input('engine', 'gemini');
        $key = $request->input('key');

        if ($engine === 'gemini') {
            $apiKey = (!empty($key) && !str_contains($key, '...'))
                ? trim($key)
                : SystemSetting::get('gemini_api_key', env('GEMINI_API_KEY'));

            if (empty($apiKey)) {
                return response()->json(['success' => false, 'message' => 'No Gemini API key provided to test.'], 422);
            }

            try {
                $response = Http::timeout(10)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey,
                    [
                        'contents' => [
                            ['parts' => [['text' => 'Respond with {"status":"ok"}']]]
                        ],
                        'generationConfig' => ['responseMimeType' => 'application/json']
                    ]
                );

                if ($response->successful()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Gemini Multimodal AI is connected and working perfectly!',
                    ]);
                }

                $err = $response->json('error.message', 'Gemini returned HTTP ' . $response->status());
                return response()->json(['success' => false, 'message' => 'Gemini test failed: ' . $err], 400);

            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => 'Gemini connection error: ' . $e->getMessage()], 500);
            }
        }

        // Test OCR.Space
        $ocrKey = (!empty($key) && !str_contains($key, '...'))
            ? trim($key)
            : SystemSetting::get('ocr_space_api_key', env('OCR_SPACE_API_KEY', 'helloworld'));

        try {
            // Tiny 1x1 test image base64
            $testPixel = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
            $response = Http::asForm()->timeout(15)->post('https://api.ocr.space/parse/image', [
                'apikey'      => $ocrKey,
                'base64Image' => $testPixel,
                'OCREngine'   => 2,
            ]);

            if ($response->successful()) {
                $json = $response->json();
                if (!empty($json['IsErroredOnProcessing']) && !empty($json['ErrorMessage'])) {
                    return response()->json(['success' => false, 'message' => 'OCR.Space error: ' . implode('; ', (array)$json['ErrorMessage'])], 400);
                }
                return response()->json([
                    'success' => true,
                    'message' => 'OCR.Space API is connected and operational.',
                ]);
            }

            return response()->json(['success' => false, 'message' => 'OCR.Space returned HTTP ' . $response->status()], 400);

        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'OCR.Space error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * View details of a specific scanned receipt.
     */
    public function show(Receipt $receipt)
    {
        $this->ensureAuthorized();
        $receipt->load(['uploader', 'project', 'approver', 'items']);

        return response()->json([
            'success'  => true,
            'receipt'  => $receipt,
            'items'    => $receipt->items,
            'file_url' => asset('storage/' . $receipt->file_path),
        ]);
    }

    /**
     * Delete an entire receipt and all its items.
     */
    public function destroy(Receipt $receipt)
    {
        $this->ensureAuthorized();

        if ($receipt->file_path && Storage::disk('public')->exists($receipt->file_path)) {
            Storage::disk('public')->delete($receipt->file_path);
        }

        $receiptNo = $receipt->receipt_number;
        $receipt->items()->delete();
        $receipt->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => "Receipt {$receiptNo} deleted."]);
        }

        return redirect()->route('admin.ocr.index')->with('success', "Receipt {$receiptNo} deleted successfully.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // OCR Extraction Engines
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Primary Engine: Google Gemini Multimodal Vision API.
     */
    private function scanWithGemini(string $apiKey, string $base64, string $mimeType): ?array
    {
        $prompt = <<<PROMPT
You are an expert fiscal auditor specialized in Ethiopian ERCA / Ministry of Revenues fiscal cash machine receipts (Datecs, Daisy, Citizen), sales invoices, and commercial cash slips for Ethiopian Ministry of Revenues VAT declaration (Line 100).
Extract ALL details from this receipt image with absolute accuracy.

CRITICAL INSTRUCTIONS:
1. SUPPLIER TIN vs BUYER TIN:
   - supplier_tin: 10-digit TIN of the SELLER / MERCHANT / SUPPLIER issuing the receipt (e.g. "0024916531", "0043724322"). Usually at the top near merchant name. This is MANDATORY. Do NOT confuse with Buyer's TIN!
   - buyer_tin: 10-digit TIN of the BUYER / CLIENT / CUSTOMER (often "0038480010" or labeled "Buyer's TIN").
2. MERCHANT / SELLER NAME:
   - merchant_name: Full registered trade name of the SELLER (e.g. "ASTRA GENERAL TRADING", "BERHANU TIEMAY ADHENA", "SEID LIDIA AND FRIENDS").
3. RECEIPT DATE:
   - receipt_date: Normalize to DD/MM/YYYY (e.g. "25/09/2026").
4. ERCA / MRC MACHINE NUMBER:
   - machine_no: Cash machine registration code / MRC number (e.g. "TDB0015170", "MFE0097690", "DFA0029991").
5. FS / FISCAL RECEIPT NUMBER:
   - fs_no: The fiscal receipt sequence number (e.g. "FS00002674", "FS00002564").
6. ITEMS / PURCHASED MATERIALS:
   - If multiple items are purchased, create one object in "items" array for each item!
   - item_description: Description of the bought material for that item.
   - uom: "9" for OTHER, "7" for PCS, "2" for KG, "5" for LIT, "10" for PC.
   - qty: Numeric value with 2 decimals.
   - unit_price: Numeric unit price before VAT with 2 decimals.
   - total_value: Total value before VAT (qty * unit_price) with 2 decimals.
   - vat: 15% VAT for this item with 2 decimals.
   - value_after_vat: Total including VAT (total_value + vat) with 2 decimals.
7. FINANCIAL TOTALS ACROSS RECEIPT:
   - subtotal: Taxable value before VAT across entire receipt.
   - vat_amount: 15% VAT across entire receipt.
   - total_amount: Grand total / value after VAT.
8. VAT DECLARATION CLASSIFICATION:
   - vat_category: "G" for Goods or "S" for Services.
   - calendar_type: "G" for Gregorian or "E" for Ethiopian.
   - purchase_type: 3 (Taxable-local Purchase of Inputs - Line No. 100).
   - uom_id: 9.

Return STRICT JSON matching this schema:
{
  "merchant_name": "string",
  "supplier_tin": "10 digits string",
  "buyer_tin": "10 digits string or null",
  "receipt_date": "DD/MM/YYYY",
  "machine_no": "string or null",
  "fs_no": "string",
  "description": "string summary",
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

        $models = ['gemini-2.5-flash', 'gemini-1.5-flash'];

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
                            // Ensure clean 10-digit TINs
                            if (!empty($parsed['supplier_tin'])) {
                                $parsed['supplier_tin'] = preg_replace('/[^0-9]/', '', (string)$parsed['supplier_tin']);
                            }
                            if (!empty($parsed['buyer_tin'])) {
                                $parsed['buyer_tin'] = preg_replace('/[^0-9]/', '', (string)$parsed['buyer_tin']);
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
     * Fallback Engine: OCR.Space Engine 2 for receipts and tabular text.
     */
    private function scanWithOcrSpace(string $apiKey, string $base64, string $ext, string $realPath): ?array
    {
        try {
            $mime = ($ext === 'pdf') ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
            $dataUri = "data:{$mime};base64,{$base64}";

            $response = Http::asForm()->timeout(35)->post('https://api.ocr.space/parse/image', [
                'apikey'            => $apiKey ?: 'helloworld',
                'base64Image'       => $dataUri,
                'language'          => 'eng',
                'isOverlayRequired' => 'false',
                'OCREngine'         => 2, // Engine 2 is best for tables & receipts
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
     * Heuristic parser for raw text extracted from OCR.Space.
     */
    private function parseReceiptTextHeuristic(string $text): array
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
                    $supplierTin = $t;
                }
            }
        }

        // 2. Find FS Number
        if (preg_match('/\bFS\s*0*([0-9]{4,10})\b/i', $text, $m)) {
            $fsNo = 'FS' . str_pad($m[1], 8, '0', STR_PAD_LEFT);
        } elseif (preg_match('/\b(FS[0-9]{5,10})\b/i', $text, $m)) {
            $fsNo = strtoupper($m[1]);
        }

        // 3. Find MRC Number (typically 3 letters followed by 7 digits)
        if (preg_match('/\b([A-Z]{3}[0-9]{7})\b/i', $text, $m)) {
            $mrcNo = strtoupper($m[1]);
        }

        // 4. Find Receipt Date (DD/MM/YYYY or DD-MM-YYYY)
        if (preg_match('/\b([0-3]?[0-9][\/\-\.][0-1]?[0-9][\/\-\.](?:20)?[12][0-9])\b/', $text, $m)) {
            $parts = preg_split('/[\/\-\.]/', $m[1]);
            if (count($parts) === 3) {
                $d = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                $mon = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                $y = strlen($parts[2]) === 2 ? '20' . $parts[2] : $parts[2];
                $dateStr = "{$d}/{$mon}/{$y}";
            }
        }

        // 5. Merchant Name from first few non-header lines
        foreach ($cleanLines as $line) {
            $upper = strtoupper($line);
            if (str_contains($upper, 'RECEIPT') || str_contains($upper, 'ERCA') || str_contains($upper, 'TIN') || str_contains($upper, 'TEL') || str_contains($upper, 'DATE') || is_numeric($line)) {
                continue;
            }
            if (strlen($line) >= 4 && preg_match('/[A-Za-z]/', $line)) {
                $vendorName = $line;
                break;
            }
        }

        // 6. Find Monetary Totals
        // Look for TOTAL or CASH
        if (preg_match('/(?:TOTAL|CASH|GRAND\s*TOTAL)[\s\:\*\=]+(?:BIRR|ETB)?\s*([0-9,]+\.[0-9]{2})/i', $text, $m)) {
            $totalVal = (float)str_replace(',', '', $m[1]);
        }
        // Look for VAT 15%
        if (preg_match('/(?:TAX1|VAT|TAX\s*15%|VAT\s*15%)[\s\:\*\=]+(?:BIRR|ETB)?\s*([0-9,]+\.[0-9]{2})/i', $text, $m)) {
            $vatVal = (float)str_replace(',', '', $m[1]);
        }
        // Look for Taxable Subtotal
        if (preg_match('/(?:TAXBL1|TAXABLE|SUBTOTAL)[\s\:\*\=]+(?:BIRR|ETB)?\s*([0-9,]+\.[0-9]{2})/i', $text, $m)) {
            $subtotalVal = (float)str_replace(',', '', $m[1]);
        }

        // Reconcile amounts if missing
        if ($totalVal > 0 && $vatVal == 0) {
            $subtotalVal = round($totalVal / 1.15, 2);
            $vatVal = round($totalVal - $subtotalVal, 2);
        } elseif ($subtotalVal > 0 && $totalVal == 0) {
            $vatVal = round($subtotalVal * 0.15, 2);
            $totalVal = round($subtotalVal + $vatVal, 2);
        }

        // Single fallback item
        $items = [
            [
                'item_description' => 'Purchased Material',
                'uom'              => '9',
                'qty'              => 1.00,
                'unit_price'       => $subtotalVal,
                'total_value'      => $subtotalVal,
                'vat'              => $vatVal,
                'value_after_vat'  => $totalVal,
            ]
        ];

        return [
            'merchant_name' => $vendorName ?: 'Merchant',
            'supplier_tin'  => $supplierTin,
            'buyer_tin'     => $buyerTin ?: '0038480010',
            'receipt_date'  => $dateStr ?: now()->format('d/m/Y'),
            'machine_no'    => $mrcNo,
            'fs_no'         => $fsNo,
            'description'   => 'Purchased Material',
            'subtotal'      => $subtotalVal,
            'vat_amount'    => $vatVal,
            'total_amount'  => $totalVal,
            'vat_category'  => 'G',
            'calendar_type' => 'G',
            'purchase_type' => 3,
            'uom_id'        => 9,
            'items'         => $items,
            'raw_text'      => $text,
        ];
    }

    /**
     * Parse date string (DD/MM/YYYY, YYYY-MM-DD, etc.) into YYYY-MM-DD for database.
     */
    private function parseDateToYmd(?string $dateStr): ?string
    {
        if (empty($dateStr)) return null;
        $dateStr = trim($dateStr);

        // Already YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return $dateStr;
        }

        // DD/MM/YYYY or DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $dateStr, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }

        try {
            return \Carbon\Carbon::parse($dateStr)->toDateString();
        } catch (\Throwable $e) {
            return now()->toDateString();
        }
    }

    /**
     * Mask API key for secure client presentation.
     */
    private function maskKey(?string $key): string
    {
        if (empty($key)) return '';
        $len = strlen($key);
        if ($len <= 8) return '••••••••';
        return substr($key, 0, 6) . '••••••••' . substr($key, -4);
    }
}
