<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\SystemSetting;
use App\Services\QcService;
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
                Schema::table('receipts', function (Blueprint $table) {
                    if (!Schema::hasColumn('receipts', 'tin_valid')) {
                        $table->boolean('tin_valid')->default(true)->after('vendor_tin');
                    }
                    if (!Schema::hasColumn('receipts', 'fs_no')) {
                        $table->string('fs_no')->nullable()->index()->after('tin_valid');
                    }
                    if (!Schema::hasColumn('receipts', 'fs_no_raw')) {
                        $table->string('fs_no_raw')->nullable()->after('fs_no');
                    }
                    if (!Schema::hasColumn('receipts', 'fs_no_valid')) {
                        $table->boolean('fs_no_valid')->default(true)->after('fs_no_raw');
                    }
                    if (!Schema::hasColumn('receipts', 'mrc_no')) {
                        $table->string('mrc_no')->nullable()->after('fs_no_valid');
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
                    if (!Schema::hasColumn('receipts', 'confidence_score')) {
                        $table->integer('confidence_score')->default(90)->after('confidence');
                    }
                    if (!Schema::hasColumn('receipts', 'needs_review')) {
                        $table->boolean('needs_review')->default(false)->after('confidence_score');
                    }
                    if (!Schema::hasColumn('receipts', 'qc_notes')) {
                        $table->text('qc_notes')->nullable()->after('notes');
                    }
                });

                // Ensure indexes on fs_no and vendor_tin
                try {
                    Schema::table('receipts', function (Blueprint $table) {
                        $table->index('fs_no');
                    });
                } catch (\Throwable $e) {}
                try {
                    Schema::table('receipts', function (Blueprint $table) {
                        $table->index('vendor_tin');
                    });
                } catch (\Throwable $e) {}
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

        // Filter: Search query (FS No, TIN, Vendor name, Description, MRC, or Natural language queries)
        if ($request->filled('search')) {
            $s = trim($request->search);
            $lower = strtolower($s);

            if ((str_contains($lower, 'invalid tin') && str_contains($lower, 'fs')) || str_contains($lower, 'invalid identifier') || str_contains($lower, 'invalid tin or fs')) {
                // e.g. "show receipts with invalid TIN or FS number"
                $query->whereHas('receipt', function($rq) {
                    $rq->where('tin_valid', false)
                       ->orWhere('fs_no_valid', false);
                });
            } elseif (str_contains($lower, 'invalid tin')) {
                // e.g. "show receipts with invalid TIN"
                $query->whereHas('receipt', function($rq) {
                    $rq->where('tin_valid', false);
                });
            } elseif (str_contains($lower, 'invalid fs')) {
                // e.g. "show receipts with invalid FS number"
                $query->whereHas('receipt', function($rq) {
                    $rq->where('fs_no_valid', false);
                });
            } elseif (str_contains($lower, 'needs review')) {
                $query->where(function($q) {
                    $q->where('is_flagged', true)
                      ->orWhereHas('receipt', function($rq) {
                          $rq->where('needs_review', true);
                      });
                });
            } else {
                $query->where(function($q) use ($s) {
                    $q->where('item_description', 'like', "%{$s}%")
                      ->orWhereHas('receipt', function($rq) use ($s) {
                          $rq->where('fs_no', 'like', "%{$s}%")
                             ->orWhere('fs_no_raw', 'like', "%{$s}%")
                             ->orWhere('vendor_name', 'like', "%{$s}%")
                             ->orWhere('vendor_tin', 'like', "%{$s}%")
                             ->orWhere('mrc_no', 'like', "%{$s}%")
                             ->orWhere('receipt_number', 'like', "%{$s}%");
                      });
                });
            }
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

        // Masked keys for modal & engine status
        $geminiKey = SystemSetting::get('gemini_api_key', env('GEMINI_API_KEY'));
        $ocrSpaceKey = SystemSetting::get('ocr_space_api_key', env('OCR_SPACE_API_KEY', 'helloworld'));
        $nvidiaKey = SystemSetting::get('nvidia_api_key', env('NVIDIA_API_KEY'));
        $azureKey = SystemSetting::get('azure_vision_key', env('AZURE_VISION_KEY'));
        $azureEndpoint = SystemSetting::get('azure_vision_endpoint', env('AZURE_VISION_ENDPOINT'));

        $maskedGemini = $this->maskKey($geminiKey);
        $maskedOcrSpace = $this->maskKey($ocrSpaceKey);
        $maskedNvidia = $this->maskKey($nvidiaKey);
        $maskedAzure = $this->maskKey($azureKey);

        $configuredEnginesCount = (!empty($geminiKey) ? 1 : 0)
            + (!empty($ocrSpaceKey) ? 1 : 0)
            + (!empty($nvidiaKey) ? 1 : 0)
            + (!empty($azureKey) && !empty($azureEndpoint) ? 1 : 0);

        return view('admin.ocr.index', compact(
            'items',
            'projects',
            'categories',
            'stats',
            'maskedGemini',
            'maskedOcrSpace',
            'maskedNvidia',
            'maskedAzure',
            'geminiKey',
            'ocrSpaceKey',
            'nvidiaKey',
            'azureKey',
            'azureEndpoint',
            'configuredEnginesCount'
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
        if (!empty($path)) {
            $path = ltrim(preg_replace('#^https?://[^/]+/storage/#i', '', $path), '/');
            $path = ltrim(preg_replace('#^(storage/|/storage/)#i', '', $path), '/');
        }
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

        $realPath = $request->hasFile('receipt_file')
            ? $file->getRealPath()
            : (Storage::disk('public')->exists($path) ? Storage::disk('public')->path($path) : null);

        $pipeline  = $this->executeMultiEnginePipeline($base64, $mimeType, $ext, $realPath);
        $extracted = $pipeline['extracted'];

        return response()->json([
            'success'          => true,
            'ai'               => true,
            'data'             => $extracted,
            'engine'           => $pipeline['engine_label'],
            'engine_slug'      => $pipeline['engine_slug'],
            'confidence'       => $pipeline['confidence'],
            'confidence_score' => $pipeline['confidence_score'],
            'qc_notes'         => $pipeline['qc_notes'],
            'file_path'        => $path,
            'file_url'         => $fileUrl,
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

        $rawFs = trim((string)($request->fs_no ?? ''));
        $rawTin = trim((string)($request->vendor_tin ?? ''));
        $fsNorm = QcService::normalizeFsNo($rawFs);
        $tinNorm = QcService::normalizeTin($rawTin);

        $fsNo = $fsNorm['value'];
        $fsNoRaw = $rawFs;
        $fsNoValid = $fsNorm['is_valid'];
        $supplierTin = $tinNorm['value'];
        $tinValid = $tinNorm['is_valid'];

        $dbDate = $this->parseDateToYmd($request->receipt_date);

        // Validation gate: block invalid values unless user checked "Save anyway (mark as Needs Review)"
        $allowInvalid = $request->boolean('allow_invalid_identifiers') || $request->boolean('force_save');
        if ((!$tinValid || !$fsNoValid) && !$allowInvalid) {
            $errs = [];
            if (!$tinValid) $errs[] = $tinNorm['error'];
            if (!$fsNoValid) $errs[] = $fsNorm['error'];
            return response()->json([
                'success'               => false,
                'is_invalid_identifier' => true,
                'message'               => implode('; ', $errs) . '. Tick "Save anyway (mark as Needs Review)" to add.',
                'tin_error'             => $tinNorm['error'],
                'fs_error'              => $fsNorm['error'],
            ], 422);
        }

        $receiptId = $request->input('receipt_id');

        // Check for duplicate FS No using normalized 8-digit FS No (excluding self if updating existing receipt)
        if (!empty($fsNo) && !$request->boolean('force_save')) {
            $dupQuery = Receipt::where(function($q) use ($fsNo) {
                $q->where('fs_no', $fsNo)
                  ->orWhere('fs_no_raw', $fsNo)
                  ->orWhere('fs_no_raw', 'FS' . $fsNo)
                  ->orWhere('parsed_data->fs_no', $fsNo)
                  ->orWhere('parsed_data->fs_no', 'FS' . $fsNo);
            });
            if ($receiptId) {
                $dupQuery->where('id', '!=', $receiptId);
            }
            $existing = $dupQuery->first();
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

        $receipt = null;
        if ($receiptId) {
            $receipt = Receipt::find($receiptId);
        }

        if ($receipt) {
            $receipt->update([
                'project_id'       => $request->project_id ?: $receipt->project_id,
                'vendor_name'      => $request->vendor_name ?: 'General Merchant',
                'vendor_tin'       => $supplierTin ?: $receipt->vendor_tin,
                'tin_valid'        => $tinValid,
                'buyer_tin'        => $request->buyer_tin ?: ($receipt->buyer_tin ?: '0038480010'),
                'fs_no'            => $fsNo ?: $receipt->fs_no,
                'fs_no_raw'        => $fsNoRaw ?: $receipt->fs_no_raw,
                'fs_no_valid'      => $fsNoValid,
                'mrc_no'           => $request->machine_no ?: $receipt->mrc_no,
                'receipt_date'     => $dbDate ?: $receipt->receipt_date,
                'subtotal'         => $subtotal,
                'vat_amount'       => $vat,
                'total_amount'     => $total,
                'category'         => $request->category ?: $receipt->category,
                'description'      => $request->description ?: $receipt->description,
                'file_path'        => $request->file_path ?: $receipt->file_path,
                'file_type'        => $fileType,
                'ocr_raw_text'     => $request->ocr_raw_text ?: $receipt->ocr_raw_text,
                'ocr_engine'       => $request->engine ?: ($receipt->ocr_engine ?: 'gemini'),
                'confidence'       => $request->confidence ?: ($receipt->confidence ?: 'high'),
                'confidence_score' => (int)($request->confidence_score ?: ($receipt->confidence_score ?: 90)),
                'qc_notes'         => $request->qc_notes ?: 'Updated via OCR Receipt Scanner Studio.',
                'needs_review'     => (!$tinValid || !$fsNoValid || $request->boolean('needs_review')),
                'parsed_data'      => $request->all(),
            ]);
            $receipt->items()->delete();
        } else {
            $receipt = Receipt::create([
                'uploaded_by'      => Auth::id() ?: 1,
                'project_id'       => $request->project_id,
                'vendor_name'      => $request->vendor_name ?: 'General Merchant',
                'vendor_tin'       => $supplierTin,
                'tin_valid'        => $tinValid,
                'buyer_tin'        => $request->buyer_tin ?: '0038480010',
                'fs_no'            => $fsNo,
                'fs_no_raw'        => $fsNoRaw,
                'fs_no_valid'      => $fsNoValid,
                'mrc_no'           => $request->machine_no,
                'receipt_date'     => $dbDate ?: now()->toDateString(),
                'subtotal'         => $subtotal,
                'vat_amount'       => $vat,
                'total_amount'     => $total,
                'currency'         => 'ETB',
                'category'         => $request->category ?: 'material',
                'description'      => $request->description ?: 'Purchased Goods',
                'file_path'        => $request->file_path,
                'file_type'        => $fileType,
                'ocr_raw_text'     => $request->ocr_raw_text,
                'ocr_engine'       => $request->engine ?: 'gemini',
                'confidence'       => $request->confidence ?: 'high',
                'confidence_score' => (int)($request->confidence_score ?: 90),
                'qc_notes'         => $request->qc_notes ?: 'Added to table via OCR Receipt Scanner Studio.',
                'needs_review'     => (!$tinValid || !$fsNoValid || $request->boolean('needs_review')),
                'parsed_data'      => $request->all(),
                'parse_status'     => 'parsed',
                'status'           => 'approved',
                'approved_by'      => Auth::id() ?: 1,
                'approved_at'      => now(),
                'notes'            => 'Added to table via OCR Receipt Scanner Studio.',
            ]);
        }

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

        // Run Parallel Multi-Engine Extraction Pipeline & Quality Control Agent
        $pipeline = $this->executeMultiEnginePipeline(
            $base64,
            $isPdf ? 'application/pdf' : $mimeType,
            $ext,
            $file->getRealPath()
        );

        $extracted       = $pipeline['extracted'];
        $engineUsed      = $pipeline['engine_label'];
        $engineSlug      = $pipeline['engine_slug'];
        $confidence      = $pipeline['confidence'];
        $confidenceScore = $pipeline['confidence_score'];
        $qcNotes         = $pipeline['qc_notes'];
        $rawText         = $pipeline['raw_text'];

        // Normalize extracted items and fields using strict QcService
        $rawFs = trim((string)($extracted['fs_no'] ?? ''));
        $rawTin = trim((string)($extracted['supplier_tin'] ?? ''));
        $fsNorm = QcService::normalizeFsNo($rawFs);
        $tinNorm = QcService::normalizeTin($rawTin);

        $fsNo = $fsNorm['value'];
        $fsNoRaw = $rawFs;
        $fsNoValid = $fsNorm['is_valid'];
        $supplierTin = $tinNorm['value'];
        $tinValid = $tinNorm['is_valid'];

        $merchantName = trim((string)($extracted['merchant_name'] ?? ''));
        $mrcNo = trim((string)($extracted['machine_no'] ?? ($extracted['mrc_no'] ?? '')));

        // Check for duplicate FS No using normalized 8-digit FS No
        $existingReceipt = null;
        if (!empty($fsNo)) {
            $existingReceipt = Receipt::where('fs_no', $fsNo)
                ->orWhere('fs_no_raw', $fsNo)
                ->orWhere('fs_no_raw', 'FS' . $fsNo)
                ->orWhere('parsed_data->fs_no', $fsNo)
                ->orWhere('parsed_data->fs_no', 'FS' . $fsNo)
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
                'engine_slug'       => $engineSlug,
                'confidence'        => $confidence,
                'confidence_score'  => $confidenceScore,
                'qc_notes'          => $qcNotes,
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

        $needsReview = (!$tinValid || !$fsNoValid || $confidence === 'review' || $confidence === 'low' || $confidenceScore < 75);

        $allItemNames = array_filter(array_map(fn($it) => trim((string)($it['item_description'] ?? ($it['name'] ?? ''))), $itemsData));
        $materialListSummary = !empty($allItemNames) ? implode(', ', $allItemNames) : ($extracted['description'] ?? 'Materials');

        // Persist Receipt
        $receipt = Receipt::create([
            'uploaded_by'  => Auth::id() ?: 1,
            'project_id'   => $request->project_id,
            'vendor_name'  => $merchantName ?: 'General Merchant',
            'vendor_tin'   => $supplierTin,
            'tin_valid'    => $tinValid,
            'buyer_tin'    => $extracted['buyer_tin'] ?? '0038480010',
            'fs_no'        => $fsNo,
            'fs_no_raw'    => $fsNoRaw,
            'fs_no_valid'  => $fsNoValid,
            'mrc_no'       => $mrcNo,
            'receipt_date' => $dbDate,
            'subtotal'     => round($calcSubtotal, 2),
            'vat_amount'   => round($calcVat, 2),
            'total_amount' => round($calcTotal, 2),
            'currency'     => 'ETB',
            'category'     => $request->category ?: 'material',
            'description'  => $materialListSummary,
            'file_path'    => $path,
            'file_type'        => $isPdf ? 'pdf' : 'image',
            'ocr_raw_text'     => $rawText,
            'ocr_engine'       => $engineSlug,
            'confidence'       => $confidence,
            'confidence_score' => $confidenceScore,
            'needs_review'     => $needsReview,
            'parsed_data'      => $extracted,
            'parse_status'     => 'parsed',
            'status'           => 'approved',
            'approved_by'      => Auth::id() ?: 1,
            'approved_at'      => now(),
            'notes'            => "Scanned via {$engineUsed} ({$confidenceScore}% confidence). FS: {$fsNo}",
            'qc_notes'         => $qcNotes,
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
            'success'          => true,
            'is_duplicate'     => false,
            'receipt'          => $receipt,
            'items'            => $createdItems,
            'file_url'         => $fileUrl,
            'engine'           => $engineUsed,
            'engine_slug'      => $engineSlug,
            'confidence'       => $confidence,
            'confidence_score' => $confidenceScore,
            'qc_notes'         => $qcNotes,
            'message'          => "Receipt {$receipt->receipt_number} scanned via {$engineUsed} (QC Score: {$confidenceScore}%) and saved successfully (" . count($createdItems) . " row" . (count($createdItems) > 1 ? 's' : '') . ").",
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
        $date = $this->parseDateToYmd($request->receipt_date);

        $receiptUpdates = [];
        if ($request->has('seller_name'))  $receiptUpdates['vendor_name'] = $request->seller_name;
        if ($request->has('supplier_tin')) {
            $tinNorm = QcService::normalizeTin($request->supplier_tin);
            $receiptUpdates['vendor_tin'] = $tinNorm['value'];
            $receiptUpdates['tin_valid']  = $tinNorm['is_valid'];
        }
        if ($request->has('mrc_no'))       $receiptUpdates['mrc_no'] = $request->mrc_no;
        if ($request->has('fs_no')) {
            $fsNorm = QcService::normalizeFsNo($request->fs_no);
            $receiptUpdates['fs_no']       = $fsNorm['value'];
            $receiptUpdates['fs_no_raw']   = (string)$request->fs_no;
            $receiptUpdates['fs_no_valid'] = $fsNorm['is_valid'];
        }
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
                    if (!empty($row['supplier_tin'])) {
                        $tinNorm = QcService::normalizeTin($row['supplier_tin']);
                        $rUpdates['vendor_tin'] = $tinNorm['value'];
                        $rUpdates['tin_valid']  = $tinNorm['is_valid'];
                    }
                    if (!empty($row['mrc_no']))       $rUpdates['mrc_no'] = $row['mrc_no'];
                    if (!empty($row['fs_no'])) {
                        $fsNorm = QcService::normalizeFsNo($row['fs_no']);
                        $rUpdates['fs_no']       = $fsNorm['value'];
                        $rUpdates['fs_no_raw']   = (string)$row['fs_no'];
                        $rUpdates['fs_no_valid'] = $fsNorm['is_valid'];
                    }
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
     * Re-scan an existing receipt item with Gemini AI to repair/extract Seller Name, FS No & Items.
     */
    public function rescanItem($id)
    {
        $this->ensureAuthorized();

        $item = ReceiptItem::with('receipt')->findOrFail($id);
        $receipt = $item->receipt;

        if (!$receipt || empty($receipt->file_path)) {
            return response()->json(['success' => false, 'message' => 'Original receipt file not found on record.'], 404);
        }

        $path = $receipt->file_path;
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['success' => false, 'message' => 'Receipt file missing from server storage.'], 404);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = ($ext === 'pdf') ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
        $base64 = base64_encode(Storage::disk('public')->get($path));

        $geminiKey = SystemSetting::get('gemini_api_key', env('GEMINI_API_KEY'));
        $extracted = null;

        $realPath = Storage::disk('public')->path($path);
        $pipeline  = $this->executeMultiEnginePipeline($base64, $mimeType, $ext, $realPath);
        $extracted = $pipeline['extracted'];

        if (empty($extracted) || empty($extracted['merchant_name'])) {
            return response()->json(['success' => false, 'message' => 'Multi-Engine AI re-scan could not read text from receipt image.'], 422);
        }

        // Update Receipt with clean details
        if (!empty($extracted['merchant_name'])) {
            $receipt->vendor_name = trim($extracted['merchant_name']);
        }
        if (!empty($extracted['supplier_tin'])) {
            $receipt->vendor_tin = preg_replace('/[^0-9]/', '', $extracted['supplier_tin']);
        }
        if (!empty($extracted['fs_no'])) {
            $receipt->fs_no = strtoupper(preg_replace('/\s+/', '', $extracted['fs_no']));
        }
        if (!empty($extracted['machine_no'])) {
            $receipt->mrc_no = strtoupper(preg_replace('/\s+/', '', $extracted['machine_no']));
        }
        if (!empty($extracted['receipt_date'])) {
            $receipt->receipt_date = $this->parseDateToYmd($extracted['receipt_date']);
        }
        if (!empty($extracted['subtotal'])) {
            $receipt->subtotal = (float)$extracted['subtotal'];
        }
        if (!empty($extracted['vat_amount'])) {
            $receipt->vat_amount = (float)$extracted['vat_amount'];
        }
        if (!empty($extracted['total_amount'])) {
            $receipt->total_amount = (float)$extracted['total_amount'];
        }
        $receipt->ocr_engine       = $pipeline['engine_slug'];
        $receipt->confidence       = $pipeline['confidence'];
        $receipt->confidence_score = $pipeline['confidence_score'];
        $receipt->qc_notes         = $pipeline['qc_notes'];
        $receipt->needs_review     = ($pipeline['confidence_score'] < 75);
        $receipt->save();

        // Update Receipt Item
        $itemsData = $extracted['items'] ?? [];
        if (!empty($itemsData)) {
            $first = $itemsData[0];
            $item->item_description = $first['item_description'] ?? ($receipt->vendor_name . ' Material');
            $item->uom = (string)($first['uom'] ?? '9');
            $item->qty = (float)($first['qty'] ?? 1);
            $item->unit_price = (float)($first['unit_price'] ?? ($item->qty > 0 ? round($receipt->subtotal / $item->qty, 2) : $receipt->subtotal));
            $item->total_value = (float)($first['total_value'] ?? $receipt->subtotal);
            $item->vat_amount = (float)($first['vat'] ?? $receipt->vat_amount);
            $item->value_after_vat = (float)($first['value_after_vat'] ?? $receipt->total_amount);
            $item->is_flagged = false;
            $item->save();

            // Create extra line items if multiple were found on this receipt
            if (count($itemsData) > 1 && ReceiptItem::where('receipt_id', $receipt->id)->count() <= 1) {
                for ($i = 1; $i < count($itemsData); $i++) {
                    $extra = $itemsData[$i];
                    ReceiptItem::create([
                        'receipt_id'       => $receipt->id,
                        'item_description' => $extra['item_description'] ?? 'Additional Material',
                        'vat_category'     => $extracted['vat_category'] ?? 'G',
                        'calendar_type'    => $extracted['calendar_type'] ?? 'G',
                        'purchase_type'    => 3,
                        'uom'              => (string)($extra['uom'] ?? '9'),
                        'qty'              => (float)($extra['qty'] ?? 1),
                        'unit_price'       => (float)($extra['unit_price'] ?? 0),
                        'total_value'      => (float)($extra['total_value'] ?? 0),
                        'vat_amount'       => (float)($extra['vat'] ?? 0),
                        'value_after_vat'  => (float)($extra['value_after_vat'] ?? 0),
                        'is_flagged'       => false,
                    ]);
                }
            }
        } else {
            $item->item_description = $extracted['description'] ?? ($receipt->vendor_name . ' Supplies');
            $item->total_value = (float)$receipt->subtotal;
            $item->vat_amount = (float)$receipt->vat_amount;
            $item->value_after_vat = (float)$receipt->total_amount;
            $item->unit_price = (float)$receipt->subtotal;
            $item->qty = 1.00;
            $item->is_flagged = false;
            $item->save();
        }

        $item->load('receipt');
        $rowErrors = $item->validateRow();

        return response()->json([
            'success'   => true,
            'message'   => "Row #{$item->id} rescanned with {$pipeline['engine_label']} (QC Score: {$pipeline['confidence_score']}%)! Seller, FS No & Items updated.",
            'item'      => $item,
            'receipt'   => $receipt,
            'rowErrors' => $rowErrors,
        ]);
    }

    /**
     * Bulk re-scan multiple items with AI.
     */
    public function rescanBulk(Request $request)
    {
        $this->ensureAuthorized();

        $itemIds = $request->input('item_ids', []);
        if (empty($itemIds)) {
            return response()->json(['success' => false, 'message' => 'No items selected to re-scan.'], 422);
        }

        $updatedCount = 0;
        $failedCount = 0;
        $items = ReceiptItem::with('receipt')->whereIn('id', $itemIds)->get();

        foreach ($items as $item) {
            $receipt = $item->receipt;
            if (!$receipt || empty($receipt->file_path) || !Storage::disk('public')->exists($receipt->file_path)) {
                $failedCount++;
                continue;
            }

            try {
                $path = $receipt->file_path;
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $mimeType = ($ext === 'pdf') ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
                $base64 = base64_encode(Storage::disk('public')->get($path));

                $pipeline = $this->executeMultiEnginePipeline($base64, $mimeType, $ext, Storage::disk('public')->path($path));
                $extracted = $pipeline['extracted'];

                if (!empty($extracted) && !empty($extracted['merchant_name'])) {
                    if (!empty($extracted['merchant_name'])) $receipt->vendor_name = trim($extracted['merchant_name']);
                    if (!empty($extracted['supplier_tin']))  $receipt->vendor_tin = preg_replace('/[^0-9]/', '', $extracted['supplier_tin']);
                    if (!empty($extracted['fs_no']))         $receipt->fs_no = strtoupper(preg_replace('/\s+/', '', $extracted['fs_no']));
                    if (!empty($extracted['machine_no']))    $receipt->mrc_no = strtoupper(preg_replace('/\s+/', '', $extracted['machine_no']));
                    if (!empty($extracted['receipt_date']))  $receipt->receipt_date = $this->parseDateToYmd($extracted['receipt_date']);
                    if (!empty($extracted['subtotal']))      $receipt->subtotal = (float)$extracted['subtotal'];
                    if (!empty($extracted['vat_amount']))    $receipt->vat_amount = (float)$extracted['vat_amount'];
                    if (!empty($extracted['total_amount']))  $receipt->total_amount = (float)$extracted['total_amount'];
                    $receipt->ocr_engine       = $pipeline['engine_slug'];
                    $receipt->confidence       = $pipeline['confidence'];
                    $receipt->confidence_score = $pipeline['confidence_score'];
                    $receipt->qc_notes         = $pipeline['qc_notes'];
                    $receipt->needs_review     = ($pipeline['confidence_score'] < 75);
                    $receipt->save();

                    $itemsData = $extracted['items'] ?? [];
                    if (!empty($itemsData)) {
                        $first = $itemsData[0];
                        $item->item_description = $first['item_description'] ?? ($receipt->vendor_name . ' Material');
                        $item->qty = (float)($first['qty'] ?? 1);
                        $item->unit_price = (float)($first['unit_price'] ?? ($item->qty > 0 ? round($receipt->subtotal / $item->qty, 2) : $receipt->subtotal));
                        $item->total_value = (float)($first['total_value'] ?? $receipt->subtotal);
                        $item->vat_amount = (float)($first['vat'] ?? $receipt->vat_amount);
                        $item->value_after_vat = (float)($first['value_after_vat'] ?? $receipt->total_amount);
                    } else {
                        $item->item_description = $extracted['description'] ?? ($receipt->vendor_name . ' Supplies');
                        $item->total_value = (float)$receipt->subtotal;
                        $item->vat_amount = (float)$receipt->vat_amount;
                        $item->value_after_vat = (float)$receipt->total_amount;
                    }
                    $item->is_flagged = false;
                    $item->save();
                    $updatedCount++;
                } else {
                    $failedCount++;
                }
            } catch (\Throwable $e) {
                $failedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Re-scanned {$updatedCount} receipts with AI" . ($failedCount > 0 ? " ({$failedCount} failed or had no image file)." : "."),
        ]);
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

        // Filter: Valid only
        if ($request->boolean('valid_only')) {
            $query->whereHas('receipt', function($rq) {
                $rq->where('tin_valid', true)
                   ->where('fs_no_valid', true);
            });
        }

        $items = $query->get();
        $template = $request->input('template', 'etax');
        $items = $query->get();
        $tempPath = storage_path('app/temp_' . uniqid() . '.xlsx');

        if ($template === 'erca_15') {
            $filename = 'VAT_RECEIPT_REPORT_' . now()->format('Y_m_d_His') . '.xlsx';
        } else {
            $filename = 'Purchase_Declaration_' . now()->format('Y_m_d_His') . '.xlsx';
        }

        try {
            $writer = new XlsxWriter();
            $writer->openToFile($tempPath);

            if ($template === 'erca_15') {
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
                    $rawTin = $r ? (string)$r->vendor_tin : '';
                    $rawFs  = $r ? (string)$r->fs_no : '';
                    $seller = $r ? (string)$r->vendor_name : '';
                    $mrc    = $r ? (string)$r->mrc_no : '';

                    $tinDigits = preg_replace('/[^0-9]/', '', $rawTin);
                    $tinText = (strlen($tinDigits) === 10) ? $tinDigits : $rawTin;

                    $fsDigits = preg_replace('/[^0-9]/', '', $rawFs);
                    $fsText = (strlen($fsDigits) === 8) ? $fsDigits : $rawFs;

                    $rowCells = [
                        Cell::fromValue((string)($item->vat_category ?: 'G')),
                        Cell::fromValue((string)($item->calendar_type ?: 'G')),
                        Cell::fromValue((int)($item->purchase_type ?: 3)),
                        Cell::fromValue((string)$tinText),
                        Cell::fromValue($seller),
                        Cell::fromValue($dateFormatted),
                        Cell::fromValue($mrc),
                        Cell::fromValue((string)$fsText),
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
            } else {
                // e-Tax Purchase Declaration Form (Exact 8 Columns requested by User)
                // A: Purchaser TIN | B: Seller TIN | C: Amount | D: Receipt No | E: Receipt Date | F: Encoding Date | G: MRC | H: PurcType
                $headerCells = [
                    Cell::fromValue('Purchaser TIN'),
                    Cell::fromValue('Seller TIN'),
                    Cell::fromValue('Amount'),
                    Cell::fromValue('Receipt No'),
                    Cell::fromValue('Receipt Date'),
                    Cell::fromValue('Encoding Date'),
                    Cell::fromValue('MRC'),
                    Cell::fromValue('PurcType'),
                ];
                $writer->addRow(new Row($headerCells));

                foreach ($items as $item) {
                    $r = $item->receipt;

                    // Col A: Purchaser TIN (e.g. 38480010)
                    $buyerTin = $r ? ($r->buyer_tin ?: SystemSetting::get('purchaser_tin', SystemSetting::get('company_tin', '0038480010'))) : '0038480010';
                    $buyerDigits = preg_replace('/[^0-9]/', '', (string)$buyerTin);
                    $purchaserTinVal = ($buyerDigits !== '') ? (int)$buyerDigits : (is_numeric($buyerTin) ? (int)$buyerTin : $buyerTin);

                    // Col B: Seller TIN (e.g. 5201, 6870105, 60440)
                    $rawTin = $r ? (string)$r->vendor_tin : '';
                    $sellerDigits = preg_replace('/[^0-9]/', '', $rawTin);
                    $sellerTinVal = ($sellerDigits !== '') ? (int)$sellerDigits : '';

                    // Col C: Amount (Receipt gross total / value after vat)
                    $amountVal = (float)($item->value_after_vat > 0 ? $item->value_after_vat : ($item->total_value > 0 ? $item->total_value : ($r ? $r->total_amount : 0)));
                    $amountVal = round($amountVal, 2);

                    // Col D: Receipt No (e.g. FS00003898, DHN638Y2E0, 539)
                    $receiptNo = '';
                    if ($r) {
                        if (!empty($r->fs_no_raw)) {
                            $receiptNo = trim($r->fs_no_raw);
                        } elseif (!empty($r->fs_no)) {
                            $cleanFs = trim($r->fs_no);
                            if (preg_match('/^\d{8}$/', $cleanFs)) {
                                $receiptNo = 'FS' . $cleanFs;
                            } else {
                                $receiptNo = $cleanFs;
                            }
                        } else {
                            $receiptNo = trim((string)$r->receipt_number);
                        }
                    }

                    // Col E: Receipt Date (Format: DD-MM-YYYY, e.g. 15-08-2026)
                    $receiptDate = $r && $r->receipt_date ? $r->receipt_date->format('d-m-Y') : now()->format('d-m-Y');

                    // Col F: Encoding Date (Format: DD-MM-YYYY, e.g. 26-08-2026)
                    $encodingDate = ($r && $r->created_at) ? $r->created_at->format('d-m-Y') : now()->format('d-m-Y');

                    // Col G: MRC (Machine Registration Code, e.g. DDB0000032 or blank)
                    $mrc = $r && !empty($r->mrc_no) ? trim((string)$r->mrc_no) : '';

                    // Col H: PurcType (PUR_GD = Goods, PUR_SER = Services)
                    $isService = ($item->vat_category === 'S') ||
                                 (strtolower((string)($r->category ?? '')) === 'service') ||
                                 (stripos((string)($item->item_description ?? ''), 'service') !== false);
                    $purcType = $isService ? 'PUR_SER' : 'PUR_GD';

                    $rowCells = [
                        Cell::fromValue($purchaserTinVal),
                        Cell::fromValue($sellerTinVal),
                        Cell::fromValue($amountVal),
                        Cell::fromValue($receiptNo),
                        Cell::fromValue($receiptDate),
                        Cell::fromValue($encodingDate),
                        Cell::fromValue($mrc),
                        Cell::fromValue($purcType),
                    ];
                    $writer->addRow(new Row($rowCells));
                }
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
     * Export table into CSV.
     * Defaults to e-Tax Purchase Declaration (8 columns), supports template=erca_15 for 15 columns.
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

        // Filter: Valid only
        if ($request->boolean('valid_only')) {
            $query->whereHas('receipt', function($rq) {
                $rq->where('tin_valid', true)
                   ->where('fs_no_valid', true);
            });
        }

        $template = $request->input('template', 'etax');
        $items = $query->get();

        if ($template === 'erca_15') {
            $filename = 'VAT_REPORT_' . now()->format('Y_m_d_His') . '.csv';
        } else {
            $filename = 'Purchase_Declaration_' . now()->format('Y_m_d_His') . '.csv';
        }

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($items, $template) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            if ($template === 'erca_15') {
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
                    $rawTin = $r ? (string)$r->vendor_tin : '';
                    $rawFs  = $r ? (string)$r->fs_no : '';
                    $seller = $r ? (string)$r->vendor_name : '';
                    $mrc    = $r ? (string)$r->mrc_no : '';

                    $tinDigits = preg_replace('/[^0-9]/', '', $rawTin);
                    $tinText = (strlen($tinDigits) === 10) ? $tinDigits : $rawTin;

                    $fsDigits = preg_replace('/[^0-9]/', '', $rawFs);
                    $fsText = (strlen($fsDigits) === 8) ? $fsDigits : $rawFs;

                    fputcsv($out, [
                        $item->vat_category ?: 'G',
                        $item->calendar_type ?: 'G',
                        $item->purchase_type ?: 3,
                        (string)$tinText,
                        $seller,
                        $dateFormatted,
                        $mrc,
                        (string)$fsText,
                        $item->item_description ?: 'Material',
                        $item->uom ?: '9',
                        number_format((float)$item->qty, 2, '.', ''),
                        number_format((float)$item->unit_price, 2, '.', ''),
                        number_format((float)$item->total_value, 2, '.', ''),
                        number_format((float)$item->vat_amount, 2, '.', ''),
                        number_format((float)$item->value_after_vat, 2, '.', ''),
                    ]);
                }
            } else {
                // e-Tax 8-column format: Purchaser TIN, Seller TIN, Amount, Receipt No, Receipt Date, Encoding Date, MRC, PurcType
                fputcsv($out, [
                    'Purchaser TIN',
                    'Seller TIN',
                    'Amount',
                    'Receipt No',
                    'Receipt Date',
                    'Encoding Date',
                    'MRC',
                    'PurcType',
                ]);

                foreach ($items as $item) {
                    $r = $item->receipt;

                    $buyerTin = $r ? ($r->buyer_tin ?: SystemSetting::get('purchaser_tin', SystemSetting::get('company_tin', '0038480010'))) : '0038480010';
                    $buyerDigits = preg_replace('/[^0-9]/', '', (string)$buyerTin);
                    $purchaserTin = ($buyerDigits !== '') ? $buyerDigits : '38480010';

                    $rawTin = $r ? (string)$r->vendor_tin : '';
                    $sellerDigits = preg_replace('/[^0-9]/', '', $rawTin);
                    $sellerTin = ($sellerDigits !== '') ? $sellerDigits : '';

                    $amount = (float)($item->value_after_vat > 0 ? $item->value_after_vat : ($item->total_value > 0 ? $item->total_value : ($r ? $r->total_amount : 0)));

                    $receiptNo = '';
                    if ($r) {
                        if (!empty($r->fs_no_raw)) {
                            $receiptNo = trim($r->fs_no_raw);
                        } elseif (!empty($r->fs_no)) {
                            $cleanFs = trim($r->fs_no);
                            if (preg_match('/^\d{8}$/', $cleanFs)) {
                                $receiptNo = 'FS' . $cleanFs;
                            } else {
                                $receiptNo = $cleanFs;
                            }
                        } else {
                            $receiptNo = trim((string)$r->receipt_number);
                        }
                    }

                    $receiptDate = $r && $r->receipt_date ? $r->receipt_date->format('d-m-Y') : now()->format('d-m-Y');
                    $encodingDate = ($r && $r->created_at) ? $r->created_at->format('d-m-Y') : now()->format('d-m-Y');
                    $mrc = $r && !empty($r->mrc_no) ? trim((string)$r->mrc_no) : '';

                    $isService = ($item->vat_category === 'S') ||
                                 (strtolower((string)($r->category ?? '')) === 'service') ||
                                 (stripos((string)($item->item_description ?? ''), 'service') !== false);
                    $purcType = $isService ? 'PUR_SER' : 'PUR_GD';

                    fputcsv($out, [
                        $purchaserTin,
                        $sellerTin,
                        number_format($amount, 2, '.', ''),
                        $receiptNo,
                        $receiptDate,
                        $encodingDate,
                        $mrc,
                        $purcType,
                    ]);
                }
            }

            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }


    /**
     * AI Chat Agent for Receipt OCR Studio.
     * Answers queries like "show receipts with invalid TIN or FS number".
     */
    public function chatAgent(Request $request)
    {
        $this->ensureAuthorized();
        $this->ensureSchema();

        $queryStr = trim((string)$request->input('message', $request->input('query', '')));
        if (empty($queryStr)) {
            return response()->json([
                'success' => false,
                'message' => 'Please ask a question, e.g. "show receipts with invalid TIN or FS number".',
            ], 422);
        }

        $lower = strtolower($queryStr);

        $isInvalidTinFs = (str_contains($lower, 'invalid tin') && str_contains($lower, 'fs'))
            || str_contains($lower, 'invalid identifier')
            || str_contains($lower, 'invalid tin or fs')
            || (str_contains($lower, 'invalid') && (str_contains($lower, 'tin') || str_contains($lower, 'fs')));

        $isInvalidTinOnly = str_contains($lower, 'invalid tin') || (str_contains($lower, 'tin') && (str_contains($lower, 'wrong') || str_contains($lower, 'error')));
        $isInvalidFsOnly  = str_contains($lower, 'invalid fs') || (str_contains($lower, 'fs') && (str_contains($lower, 'wrong') || str_contains($lower, 'error')));
        $isNeedsReview    = str_contains($lower, 'review') || str_contains($lower, 'flagged');

        $totalReceipts    = Receipt::count();
        $invalidTinCount  = Receipt::where('tin_valid', false)->count();
        $invalidFsCount   = Receipt::where('fs_no_valid', false)->count();
        $bothInvalidCount = Receipt::where(function($q) {
            $q->where('tin_valid', false)->orWhere('fs_no_valid', false);
        })->count();
        $needsReviewCount = Receipt::where('needs_review', true)->count();

        if ($isInvalidTinFs) {
            $reply = "I found **{$bothInvalidCount} receipt(s)** with an invalid TIN or FS number out of {$totalReceipts} total receipts.\n\n"
                   . "- **Invalid TINs (must be exactly 10 digits):** {$invalidTinCount}\n"
                   . "- **Invalid FS Numbers (must be exactly 8 digits):** {$invalidFsCount}\n\n"
                   . "You can review and correct them directly in the table.";

            return response()->json([
                'success'       => true,
                'reply'         => $reply,
                'filter_search' => 'show receipts with invalid TIN or FS number',
                'count'         => $bothInvalidCount,
                'action_label'  => 'Filter Invalid TIN & FS in Table',
            ]);
        }

        if ($isInvalidTinOnly) {
            $reply = "There are **{$invalidTinCount} receipt(s)** where the Supplier TIN is invalid (must be exactly 10 digits).";
            return response()->json([
                'success'       => true,
                'reply'         => $reply,
                'filter_search' => 'invalid tin',
                'count'         => $invalidTinCount,
                'action_label'  => 'Show Receipts with Invalid TIN',
            ]);
        }

        if ($isInvalidFsOnly) {
            $reply = "There are **{$invalidFsCount} receipt(s)** where the FS Number is invalid (must be exactly 8 digits).";
            return response()->json([
                'success'       => true,
                'reply'         => $reply,
                'filter_search' => 'invalid fs',
                'count'         => $invalidFsCount,
                'action_label'  => 'Show Receipts with Invalid FS No',
            ]);
        }

        if ($isNeedsReview) {
            $reply = "There are **{$needsReviewCount} receipt(s)** currently flagged as 'Needs Review' requiring manual attention.";
            return response()->json([
                'success'       => true,
                'reply'         => $reply,
                'filter_search' => 'needs review',
                'count'         => $needsReviewCount,
                'action_label'  => 'Show All Needs Review Receipts',
            ]);
        }

        $reply = "Receipt OCR Studio status:\n"
               . "- Total receipts: {$totalReceipts}\n"
               . "- Needs Review: {$needsReviewCount}\n"
               . "- Invalid TINs: {$invalidTinCount}\n"
               . "- Invalid FS Numbers: {$invalidFsCount}\n\n"
               . "Try asking: *\"show receipts with invalid TIN or FS number\"* or *\"show receipts needing review\"*.";

        return response()->json([
            'success' => true,
            'reply'   => $reply,
        ]);
    }

    /**
     * Legacy alias for export-vat route.
     */
    public function exportVatReport(Request $request)
    {
        return $this->exportExcel($request);
    }

    /**
     * Get current OCR and multi-engine configuration status.
     */
    public function getSettings()
    {
        $this->ensureAuthorized();

        $geminiKey     = SystemSetting::get('gemini_api_key', env('GEMINI_API_KEY'));
        $ocrSpaceKey   = SystemSetting::get('ocr_space_api_key', env('OCR_SPACE_API_KEY', 'helloworld'));
        $nvidiaKey     = SystemSetting::get('nvidia_api_key', env('NVIDIA_API_KEY'));
        $azureKey      = SystemSetting::get('azure_vision_key', env('AZURE_VISION_KEY'));
        $azureEndpoint = SystemSetting::get('azure_vision_endpoint', env('AZURE_VISION_ENDPOINT'));
        $fsNoLength    = (int)SystemSetting::get('fs_no_length', env('FS_NO_LENGTH', 8));
        $fsNoAllowPrefix = (bool)SystemSetting::get('fs_no_allow_prefix', env('FS_NO_ALLOW_PREFIX', true));

        return response()->json([
            'success' => true,
            'settings' => [
                'gemini_configured'     => !empty($geminiKey),
                'ocr_space_configured'  => !empty($ocrSpaceKey),
                'nvidia_configured'     => !empty($nvidiaKey),
                'azure_configured'      => !empty($azureKey) && !empty($azureEndpoint),
                'masked_gemini'         => $this->maskKey($geminiKey),
                'masked_ocr_space'      => $this->maskKey($ocrSpaceKey),
                'masked_nvidia'         => $this->maskKey($nvidiaKey),
                'masked_azure'          => $this->maskKey($azureKey),
                'azure_endpoint'        => $azureEndpoint ?: '',
                'fs_no_length'          => $fsNoLength > 0 ? $fsNoLength : 8,
                'fs_no_allow_prefix'    => $fsNoAllowPrefix,
            ]
        ]);
    }

    /**
     * Save AI and OCR multi-engine settings securely.
     */
    public function saveSettings(Request $request)
    {
        $this->ensureAuthorized();

        $request->validate([
            'gemini_api_key'        => 'nullable|string',
            'ocr_space_api_key'     => 'nullable|string',
            'nvidia_api_key'        => 'nullable|string',
            'azure_vision_key'      => 'nullable|string',
            'azure_vision_endpoint' => 'nullable|string',
            'fs_no_length'          => 'nullable|integer|min:1|max:20',
            'fs_no_allow_prefix'    => 'nullable|boolean',
        ]);

        if ($request->has('gemini_api_key') && !str_contains($request->gemini_api_key, '...')) {
            SystemSetting::set('gemini_api_key', trim($request->gemini_api_key), 'string', 'ocr', 'Google Gemini AI Key');
        }

        if ($request->has('ocr_space_api_key') && !str_contains($request->ocr_space_api_key, '...')) {
            SystemSetting::set('ocr_space_api_key', trim($request->ocr_space_api_key), 'string', 'ocr', 'OCR.Space API Key');
        }

        if ($request->has('nvidia_api_key') && !str_contains($request->nvidia_api_key, '...')) {
            SystemSetting::set('nvidia_api_key', trim($request->nvidia_api_key), 'string', 'ocr', 'NVIDIA Vision NIM Key');
        }

        if ($request->has('azure_vision_key') && !str_contains($request->azure_vision_key, '...')) {
            SystemSetting::set('azure_vision_key', trim($request->azure_vision_key), 'string', 'ocr', 'Azure Computer Vision Key');
        }

        if ($request->has('azure_vision_endpoint')) {
            $endpoint = rtrim(trim((string)$request->azure_vision_endpoint), '/');
            SystemSetting::set('azure_vision_endpoint', $endpoint, 'string', 'ocr', 'Azure Computer Vision Endpoint');
        }

        if ($request->has('fs_no_length')) {
            $fsLen = max(1, min(20, (int)$request->fs_no_length));
            SystemSetting::set('fs_no_length', $fsLen, 'integer', 'ocr', 'Fiscal Sales (FS) number digit length');
        }

        if ($request->has('fs_no_allow_prefix')) {
            $allowPrefix = $request->boolean('fs_no_allow_prefix');
            SystemSetting::set('fs_no_allow_prefix', $allowPrefix, 'boolean', 'ocr', 'Allow and strip FS prefix during normalization');
        }

        return response()->json([
            'success' => true,
            'message' => 'OCR & Multi-Engine settings saved securely.',
        ]);
    }

    /**
     * Test API Key connectivity for Gemini, OCR.Space, NVIDIA, or Azure.
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

            $models = ['gemini-flash-latest', 'gemini-3.1-flash-lite', 'gemini-3.5-flash'];
            $lastErr = '';

            foreach ($models as $model) {
                try {
                    $response = Http::timeout(10)->post(
                        "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey,
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
                            'message' => "Gemini Multimodal AI ({$model}) is connected and working perfectly!",
                        ]);
                    }

                    $lastErr = $response->json('error.message', 'HTTP ' . $response->status());
                } catch (\Throwable $e) {
                    $lastErr = $e->getMessage();
                }
            }

            return response()->json(['success' => false, 'message' => 'Gemini test failed: ' . $lastErr], 400);
        }

        if ($engine === 'nvidia') {
            $apiKey = (!empty($key) && !str_contains($key, '...'))
                ? trim($key)
                : SystemSetting::get('nvidia_api_key', env('NVIDIA_API_KEY'));

            if (empty($apiKey)) {
                return response()->json(['success' => false, 'message' => 'No NVIDIA Vision key provided to test.'], 422);
            }

            try {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ])->timeout(12)->post('https://integrate.api.nvidia.com/v1/chat/completions', [
                    'model' => 'meta/llama-3.2-11b-vision-instruct',
                    'messages' => [
                        ['role' => 'user', 'content' => 'Ping. Respond with {"status":"ok"}.']
                    ],
                    'max_tokens' => 20,
                    'temperature' => 0.1,
                ]);

                if ($response->successful()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'NVIDIA Vision NIM API (Llama 3.2 Vision) is connected and operational!',
                    ]);
                }

                $err = $response->json('error.message', 'HTTP ' . $response->status());
                return response()->json(['success' => false, 'message' => 'NVIDIA API test failed: ' . $err], 400);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => 'NVIDIA connection error: ' . $e->getMessage()], 500);
            }
        }

        if ($engine === 'azure') {
            $apiKey = (!empty($key) && !str_contains($key, '...'))
                ? trim($key)
                : SystemSetting::get('azure_vision_key', env('AZURE_VISION_KEY'));
            $endpoint = $request->input('endpoint');
            $endpoint = (!empty($endpoint))
                ? rtrim(trim($endpoint), '/')
                : rtrim((string)SystemSetting::get('azure_vision_endpoint', env('AZURE_VISION_ENDPOINT')), '/');

            if (empty($apiKey)) {
                return response()->json(['success' => false, 'message' => 'No Azure Vision key provided to test.'], 422);
            }
            if (empty($endpoint)) {
                return response()->json(['success' => false, 'message' => 'No Azure Vision endpoint provided.'], 422);
            }

            try {
                $testPixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
                $response = Http::withHeaders([
                    'Ocp-Apim-Subscription-Key' => $apiKey,
                    'Content-Type'              => 'application/octet-stream',
                ])->timeout(12)->withBody($testPixel, 'application/octet-stream')
                  ->post("{$endpoint}/computervision/imageanalysis:analyze?api-version=2024-02-01&features=read");

                if ($response->successful() || $response->status() === 200 || $response->status() === 202) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Azure Computer Vision Read API is connected and ready!',
                    ]);
                }

                $errMsg = $response->json('error.message', 'HTTP ' . $response->status());
                return response()->json(['success' => false, 'message' => 'Azure Vision returned: ' . $errMsg], 400);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => 'Azure connection error: ' . $e->getMessage()], 500);
            }
        }

        // Test OCR.Space
        $ocrKey = (!empty($key) && !str_contains($key, '...'))
            ? trim($key)
            : SystemSetting::get('ocr_space_api_key', env('OCR_SPACE_API_KEY', 'helloworld'));

        try {
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
   - Every single line item (e.g. "H07V-U 1X2.5mm2", "RG 6 DISH", "9uroro conduit 20(100m)", "CEMENT OPC 42.5", "REBAR 16MM", "TIMBER", "FUEL DIESEL", etc.) must be extracted with its full printed description in "item_description".
   - NEVER use generic placeholders like "Purchased Material", "Goods", or "Item" when actual material/product names are printed on the receipt!
   - If multiple items are printed, create an object in the "items" array for EACH individual item with its own qty, unit_price, total_value, vat, and value_after_vat!
   - For "description": return a clean comma-separated list of ALL bought materials extracted from the receipt (e.g. "H07V-U 1X2.5mm2, RG 6 DISH, 9uroro conduit 20(100m)").
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
                            // Strict normalization with QcService - never guess or pad
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
                    $tinNorm = QcService::normalizeTin($t);
                    $supplierTin = $tinNorm['value'];
                }
            }
        }

        // 2. Find FS Number (normalize without guessing or auto-padding)
        if (preg_match('/(?:FS|F\/S|FISCAL\s*NO|FISCAL\s*RECEIPT\s*NO|FS\s*NO|FS\s*\#)[\s\:\.\#\-\_]*([0-9]{1,12})/i', $text, $m)) {
            $fsNorm = QcService::normalizeFsNo($m[1]);
            $fsNo = $fsNorm['value'];
        } elseif (preg_match('/\bFS\s*([0-9]{3,12})\b/i', $text, $m)) {
            $fsNorm = QcService::normalizeFsNo($m[1]);
            $fsNo = $fsNorm['value'];
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
        $vendorNameCandidates = [];
        foreach ($cleanLines as $line) {
            $upper = strtoupper($line);
            if (str_contains($upper, 'RECEIPT') || str_contains($upper, 'ERCA') || str_contains($upper, 'TIN') || str_contains($upper, 'TEL') || str_contains($upper, 'DATE') || str_contains($upper, 'MRC') || str_contains($upper, 'FS') || is_numeric($line)) {
                continue;
            }
            if (strlen($line) >= 3 && preg_match('/[A-Za-z]/', $line)) {
                $vendorNameCandidates[] = $line;
                if (count($vendorNameCandidates) >= 2) break;
            }
        }
        if (!empty($vendorNameCandidates)) {
            if (count($vendorNameCandidates) > 1 && strlen($vendorNameCandidates[0]) < 12) {
                $vendorName = trim($vendorNameCandidates[0] . ' ' . $vendorNameCandidates[1]);
            } else {
                $vendorName = $vendorNameCandidates[0];
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

        // 7. Extract actual items / materials from slip lines
        $detectedItems = [];
        $skipKeywords = ['RECEIPT', 'ERCA', 'TIN', 'TEL', 'DATE', 'MRC', 'FS', 'TOTAL', 'CASH', 'SUBTOTAL', 'TAXBL', 'TAX1', 'VAT', 'CHANGE', 'THANK', 'CLIENT', 'BUYER'];

        foreach ($cleanLines as $line) {
            $upper = strtoupper($line);
            $shouldSkip = false;
            foreach ($skipKeywords as $sk) {
                if (str_contains($upper, $sk)) {
                    $shouldSkip = true;
                    break;
                }
            }
            if ($shouldSkip || strlen($line) < 3 || is_numeric($line)) {
                continue;
            }

            // Line with description, qty, unit price, total
            if (preg_match('/^([A-Za-z0-9\s\/\-\_\.\#]+?)\s+([0-9]+\.?[0-9]*)\s+([0-9]+\.[0-9]{2})\s+([0-9]+\.[0-9]{2})$/', $line, $m)) {
                $desc = trim($m[1]);
                $q = (float)$m[2];
                $p = (float)$m[3];
                $t = (float)$m[4];
                $v = round($t * 0.15, 2);
                $detectedItems[] = [
                    'item_description' => $desc,
                    'uom'              => '9',
                    'qty'              => $q,
                    'unit_price'       => $p,
                    'total_value'      => $t,
                    'vat'              => $v,
                    'value_after_vat'  => round($t + $v, 2),
                ];
            } elseif (preg_match('/^([A-Za-z0-9\s\/\-\_\.\#]+?)\s+([0-9,]+\.[0-9]{2})$/', $line, $m)) {
                $desc = trim($m[1]);
                $t = (float)str_replace(',', '', $m[2]);
                if ($t > 0 && strlen($desc) >= 3 && !preg_match('/^(?:SUBTOTAL|TAXBL|TOTAL|VAT|CASH)$/i', $desc)) {
                    $v = round($t * 0.15, 2);
                    $detectedItems[] = [
                        'item_description' => $desc,
                        'uom'              => '9',
                        'qty'              => 1.00,
                        'unit_price'       => $t,
                        'total_value'      => $t,
                        'vat'              => $v,
                        'value_after_vat'  => round($t + $v, 2),
                    ];
                }
            }
        }

        if (!empty($detectedItems)) {
            $items = $detectedItems;
        } else {
            // Find first plausible material description from non-header lines
            $firstDesc = '';
            foreach ($cleanLines as $line) {
                $upper = strtoupper($line);
                $isMeta = false;
                foreach ($skipKeywords as $sk) {
                    if (str_contains($upper, $sk)) { $isMeta = true; break; }
                }
                if (!$isMeta && strlen($line) >= 4 && preg_match('/[A-Za-z]/', $line) && !in_array($line, $vendorNameCandidates)) {
                    $firstDesc = $line;
                    break;
                }
            }

            $items = [
                [
                    'item_description' => $firstDesc ?: (!empty($vendorName) ? ($vendorName . ' Supplies') : 'Construction Material'),
                    'uom'              => '9',
                    'qty'              => 1.00,
                    'unit_price'       => $subtotalVal,
                    'total_value'      => $subtotalVal,
                    'vat'              => $vatVal,
                    'value_after_vat'  => $totalVal,
                ]
            ];
        }

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

    /**
     * Engine 3: NVIDIA Vision NIM API (Llama 3.2 Vision Instruct).
     */
    private function scanWithNvidia(string $apiKey, string $base64, string $mimeType): ?array
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
                            // Strict normalization with QcService - never guess or pad
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
     * Engine 4: Azure Computer Vision / Read API.
     */
    private function scanWithAzure(string $apiKey, string $endpoint, string $base64, string $mimeType): ?array
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
     * Quality Control AI Agent.
     * Evaluates extractions from all active OCR engines, reconciles conflicts,
     * verifies ERCA fiscal rules & 15% VAT arithmetic, and computes confidence score (0-100).
     */
    private function callQcAgent(string $geminiKey, array $engineResults): array
    {
        // If only 1 engine returned data, run deterministic QC on it
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

CRITICAL IDENTIFIER RULES (STRICT - NEVER GUESS OR FORCE DIGIT COUNT):
1. SUPPLIER TIN:
   - Must be EXACTLY 10 digits, numeric only.
   - You may resolve common OCR character confusions (O/o->0, I/l/|->1, S/s->5, B->8) ONLY IF the resulting value becomes exactly 10 digits.
   - You must NEVER add, remove, guess, or pad digits to force validity.
   - If still not exactly 10 digits, output the raw extracted digits and state it is invalid in qc_notes.
2. FS NUMBER:
   - Must be EXACTLY 8 digits, numeric only (strip "FS" prefix).
   - You may resolve common OCR character confusions (O/o->0, I/l->1, S/s->5, B->8) ONLY IF the resulting value becomes exactly 8 digits.
   - You must NEVER add, remove, guess, or pad digits to force validity (e.g. 7 digits like 0002674 must NOT be padded to 8 digits).
   - If fewer or more than 8 digits, output the raw value without padding and flag as invalid in qc_notes.
3. If ANY candidate value fails the digit-length rule, it can NEVER win consensus.
4. BUYER TIN: 10 digits (e.g. 0038480010 if company purchaser or printed).
5. MACHINE NO / MRC: Pick valid fiscal register serial (e.g. MOR..., DATECS..., DAISY...).
6. RECEIPT DATE: Format strictly as DD/MM/YYYY.
7. ARITHMETIC VERIFICATION:
   - Check Subtotal + VAT (15%) = Total Amount within 0.05 tolerance.
   - If engines disagree on numbers, choose the mathematically sound set that sums correctly.
8. ITEMS BREAKDOWN:
   - Preserve detailed material descriptions (e.g. "REBAR 16MM", "CEMENT OPC", "SAND"). Avoid generic labels.
   - Ensure for every item: Qty x Unit Price = Total Value, and Total Value + VAT = Value After VAT.
9. CONFIDENCE SCORE (0-100):
   - 95-100: All engines agreed on 10-digit TIN, 8-digit FS#, and arithmetic is 100% exact.
   - 80-94: Minor discrepancy resolved, but math, 10-digit TIN and 8-digit FS# are verified.
   - 60-79: Discrepancy required significant reconciliation or low-quality receipt.
   - <60: Unresolved discrepancy or invalid TIN / FS#.
10. PROVENANCE & QC NOTES:
   - State clearly which engine outputs were adopted, what was reconciled, and whether TIN / FS# are valid.

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
                            // Run strict deterministic QC normalization on QC Agent's output
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

        // Fallback to deterministic reconciliation if Gemini QC call failed
        return $this->reconcileWithDeterministicQc($engineResults);
    }

    /**
     * Deterministic pure-PHP Quality Control & reconciliation.
     * Computes arithmetic accuracy, validates 10-digit TINs & FS numbers,
     * and calculates confidence score (0-100).
     */
    private function reconcileWithDeterministicQc(array $engineResults, ?string $singleEngine = null): array
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

        // Cross-check supplier TIN: compare only AFTER normalization
        // A value that fails the digit-length rule can NEVER win the consensus vote!
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
            // No engine produced a valid 10-digit TIN: candidate can never win consensus
            arsort($rawTins);
            $base['supplier_tin'] = array_key_first($rawTins) ?: '';
            $base['tin_valid']    = false;
        }

        // Cross-check FS No: compare only AFTER normalization
        // A value that fails the digit-length rule can NEVER win the consensus vote!
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
            // No engine produced a valid 8-digit FS No: candidate can never win consensus
            arsort($rawFsMap);
            $base['fs_no']       = array_key_first($rawFsMap) ?: '';
            $base['fs_no_raw']   = $base['fs_no'];
            $base['fs_no_valid'] = false;
        }

        // Arithmetic cross-check
        $subtotal = (float)($base['subtotal'] ?? 0);
        $vat = (float)($base['vat_amount'] ?? 0);
        $total = (float)($base['total_amount'] ?? 0);

        $mathExact = false;
        if ($total > 0 && abs(($subtotal + $vat) - $total) <= 0.05) {
            $mathExact = true;
        } elseif ($total > 0 && $subtotal == 0) {
            $subtotal = round($total / 1.15, 2);
            $vat = round($total - $subtotal, 2);
            $base['subtotal'] = $subtotal;
            $base['vat_amount'] = $vat;
            $mathExact = true;
        }

        // Calculate confidence score (0-100)
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
            $score += 10; // Bonus for multi-engine consensus
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
     * Parallel Multi-Engine Extraction Pipeline with QC AI Agent.
     * Fans out across all configured engines (Gemini, NVIDIA, OCR.Space, Azure).
     * Runs QC reconciliation and assigns numeric confidence score (0-100).
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
}
