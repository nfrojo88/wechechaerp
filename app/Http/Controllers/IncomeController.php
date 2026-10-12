<?php

namespace App\Http\Controllers;

use App\Models\IncomeRecord;
use App\Models\Project;
use App\Models\BankAccount;
use App\Models\SystemSetting;
use App\Services\ReceiptOcrService;
use App\Services\QcService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell;

class IncomeController extends Controller
{
    protected ReceiptOcrService $ocrService;

    public function __construct(?ReceiptOcrService $ocrService = null)
    {
        $this->middleware('auth');
        $this->ocrService = $ocrService ?? app(ReceiptOcrService::class);
    }

    /**
     * Self-healing runtime schema check.
     * Ensures income_records has all receipt and eTax columns.
     */
    private function ensureSchema(): void
    {
        try {
            if (Schema::hasTable('income_records')) {
                // If category was an enum in MySQL, widen it to VARCHAR(50) so divisions don't fail
                if (DB::getDriverName() === 'mysql') {
                    try {
                        DB::statement("ALTER TABLE income_records MODIFY category VARCHAR(50) NOT NULL DEFAULT 'other'");
                    } catch (\Throwable $e) {}
                }

                Schema::table('income_records', function (Blueprint $table) {
                    if (!Schema::hasColumn('income_records', 'seller_tin')) {
                        $table->string('seller_tin', 50)->nullable()->default('38480010')->after('category');
                    }
                    if (!Schema::hasColumn('income_records', 'buyer_tin')) {
                        $table->string('buyer_tin', 50)->nullable()->index()->after('seller_tin');
                    }
                    if (!Schema::hasColumn('income_records', 'buyer_name')) {
                        $table->string('buyer_name', 255)->nullable()->after('buyer_tin');
                    }
                    if (!Schema::hasColumn('income_records', 'transaction_type')) {
                        $table->string('transaction_type', 100)->nullable()->default('Cash Sales')->after('buyer_name');
                    }
                    if (!Schema::hasColumn('income_records', 'receipt_category')) {
                        $table->string('receipt_category', 100)->nullable()->default('Cash Sales Fiscal Receipt')->after('transaction_type');
                    }
                    if (!Schema::hasColumn('income_records', 'receipt_used')) {
                        $table->string('receipt_used', 100)->nullable()->default('Fiscal Cash Register')->after('receipt_category');
                    }
                    if (!Schema::hasColumn('income_records', 'mrc_no')) {
                        $table->string('mrc_no', 100)->nullable()->after('receipt_used');
                    }
                    if (!Schema::hasColumn('income_records', 'receipt_no')) {
                        $table->string('receipt_no', 100)->nullable()->index()->after('mrc_no');
                    }
                    if (!Schema::hasColumn('income_records', 'commodity_type')) {
                        $table->string('commodity_type', 100)->nullable()->default('Services')->after('receipt_no');
                    }
                    if (!Schema::hasColumn('income_records', 'quantity')) {
                        $table->decimal('quantity', 12, 2)->default(1.00)->after('commodity_type');
                    }
                    if (!Schema::hasColumn('income_records', 'unit_price')) {
                        $table->decimal('unit_price', 18, 2)->nullable()->after('quantity');
                    }
                    if (!Schema::hasColumn('income_records', 'receipt_date')) {
                        $table->date('receipt_date')->nullable()->after('unit_price');
                    }
                    if (!Schema::hasColumn('income_records', 'encoded_by')) {
                        $table->string('encoded_by', 100)->nullable()->default('wechacha')->after('receipt_date');
                    }
                    if (!Schema::hasColumn('income_records', 'receipt_file_path')) {
                        $table->string('receipt_file_path', 255)->nullable()->after('encoded_by');
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::warning('IncomeController ensureSchema error: ' . $e->getMessage());
        }
    }

    /**
     * Build filtered query for income records.
     */
    private function buildQuery(Request $request)
    {
        $this->ensureSchema();
        $query = IncomeRecord::with(['project', 'createdBy', 'bankAccount'])->latest('income_date')->latest('id');

        // Division / Category filter
        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        // Project filter
        if ($request->filled('project_id') && $request->project_id !== 'all') {
            $query->where('project_id', $request->project_id);
        }

        // Date range filtering
        $df = $request->input('from_date') ?: ($request->input('date_from') ?: $request->input('start_date'));
        $dt = $request->input('to_date') ?: ($request->input('date_to') ?: $request->input('end_date'));
        $singleDate = $request->input('date');

        if (!empty($singleDate)) {
            $df = $singleDate;
            $dt = $singleDate;
        }

        if (!empty($df)) {
            $query->whereDate('income_date', '>=', $df);
        }
        if (!empty($dt)) {
            $query->whereDate('income_date', '<=', $dt);
        }

        // Free search
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function($q) use ($s) {
                $q->where('income_no', 'like', "%{$s}%")
                  ->orWhere('receipt_no', 'like', "%{$s}%")
                  ->orWhere('mrc_no', 'like', "%{$s}%")
                  ->orWhere('buyer_name', 'like', "%{$s}%")
                  ->orWhere('buyer_tin', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%")
                  ->orWhere('notes', 'like', "%{$s}%");
            });
        }

        return $query;
    }

    public function index(Request $request)
    {
        $this->ensureSchema();
        $query = $this->buildQuery($request);

        $incomes = $query->paginate(20)->withQueryString();

        $totalRevenue = IncomeRecord::where('status', 'confirmed')->sum('amount');
        $totalBalance = BankAccount::where('is_active', true)->sum('current_balance') ?? 0;

        $transportationTotal = IncomeRecord::where('category', 'transportation')->where('status', 'confirmed')->sum('amount');
        $rentalTotal         = IncomeRecord::where('category', 'rental')->where('status', 'confirmed')->sum('amount');
        $constructionTotal   = IncomeRecord::where('category', 'construction')->where('status', 'confirmed')->sum('amount');

        $projects     = Project::where('status', 'active')->get();
        $bankAccounts = BankAccount::where('is_active', true)->get();

        return view('finance.income.index', compact(
            'incomes',
            'totalRevenue',
            'totalBalance',
            'transportationTotal',
            'rentalTotal',
            'constructionTotal',
            'projects',
            'bankAccounts'
        ));
    }

    public function create()
    {
        $this->ensureSchema();
        $projects     = Project::where('status', 'active')->get();
        $bankAccounts = BankAccount::where('is_active', true)->get();
        return view('finance.income.create', compact('projects', 'bankAccounts'));
    }

    public function store(Request $request)
    {
        $this->ensureSchema();

        $data = $request->validate([
            'project_id'       => 'nullable|exists:projects,id',
            'category'         => 'required|string|max:50',
            'income_date'      => 'required|date',
            'amount'           => 'required|numeric|min:0.01',
            'payment_method'   => 'required|string|max:50',
            'bank_account_id'  => 'nullable|exists:bank_accounts,id',
            'description'      => 'nullable|string',
            'notes'            => 'nullable|string',
            // Receipt & eTax fields
            'seller_tin'       => 'nullable|string|max:50',
            'buyer_tin'        => 'nullable|string|max:50',
            'buyer_name'       => 'nullable|string|max:255',
            'transaction_type' => 'nullable|string|max:100',
            'receipt_category' => 'nullable|string|max:100',
            'receipt_used'     => 'nullable|string|max:100',
            'mrc_no'           => 'nullable|string|max:100',
            'receipt_no'       => 'nullable|string|max:100',
            'commodity_type'   => 'nullable|string|max:100',
            'quantity'         => 'nullable|numeric|min:0.01',
            'unit_price'       => 'nullable|numeric|min:0',
            'receipt_date'     => 'nullable|date',
            'encoded_by'       => 'nullable|string|max:100',
            'receipt_file'     => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:25600',
            'receipt_file_path'=> 'nullable|string|max:255',
        ]);

        // Handle receipt file upload if present
        if ($request->hasFile('receipt_file')) {
            $file = $request->file('receipt_file');
            $ext  = strtolower($file->getClientOriginalExtension());
            $filename = 'income_rcpt_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
            $path = $file->storeAs('income_receipts', $filename, 'public');
            $data['receipt_file_path'] = $path;
        }

        // Generate income tracking code
        $no = 'INC-' . date('Ymd') . '-' . str_pad(IncomeRecord::count() + 1, 4, '0', STR_PAD_LEFT);
        $data['income_no']  = $no;
        $data['created_by'] = Auth::id();
        $data['status']     = 'draft';

        // Normalized Defaults for ERCA eTax Sales compliance
        $data['seller_tin']       = $request->input('seller_tin') ?: SystemSetting::get('purchaser_tin', SystemSetting::get('company_tin', '38480010'));
        $data['transaction_type'] = $request->input('transaction_type') ?: 'Cash Sales';
        $data['receipt_category'] = $request->input('receipt_category') ?: 'Cash Sales Fiscal Receipt';
        $data['receipt_used']     = $request->input('receipt_used') ?: 'Fiscal Cash Register';
        
        if (empty($data['commodity_type'])) {
            $catLower = strtolower($data['category']);
            $data['commodity_type'] = in_array($catLower, ['construction', 'goods']) ? 'Goods' : 'Services';
        }

        $data['quantity']   = (float)($request->input('quantity') ?: 1.00);
        $data['unit_price'] = (float)($request->input('unit_price') ?: $data['amount']);
        $data['receipt_date'] = $request->input('receipt_date') ?: $data['income_date'];

        $defaultEncodedBy = SystemSetting::get('etax_encoded_by', 'wechacha');
        $user = Auth::user();
        if ($user && !in_array(strtolower($user->name), ['admin', 'global admin', 'system'])) {
            $defaultEncodedBy = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $user->name)) ?: $defaultEncodedBy;
        }
        $data['encoded_by'] = $request->input('encoded_by') ?: $defaultEncodedBy;

        // Auto-populate buyer name from project if blank
        if (empty($data['buyer_name']) && !empty($data['project_id'])) {
            $proj = Project::find($data['project_id']);
            if ($proj) {
                $data['buyer_name'] = $proj->client_name ?: $proj->name;
            }
        }

        // Clean receipt number
        if (!empty($data['receipt_no'])) {
            $cleanFs = trim($data['receipt_no']);
            if (preg_match('/^fs/i', $cleanFs)) {
                $data['receipt_no'] = strtoupper($cleanFs);
            } elseif (preg_match('/^\d+$/', $cleanFs)) {
                $data['receipt_no'] = 'FS' . str_pad($cleanFs, 8, '0', STR_PAD_LEFT);
            }
        }

        IncomeRecord::create($data);
        return redirect()->route('income.index')->with('success', "Income record #{$no} with receipt details saved successfully.");
    }

    public function show(IncomeRecord $income)
    {
        $this->ensureSchema();
        $income->load(['project', 'createdBy', 'bankAccount', 'journalEntry']);
        return view('finance.income.show', compact('income'));
    }

    public function confirm(IncomeRecord $income)
    {
        $income->update(['status' => 'confirmed']);
        return back()->with('success', 'Income record confirmed.');
    }

    /**
     * AJAX OCR Receipt Extraction for Income Recording.
     * Takes an uploaded receipt image or PDF and extracts buyer, FS#, MRC#, amount, and date.
     */
    public function scanReceipt(Request $request)
    {
        $this->ensureSchema();

        $request->validate([
            'receipt_file' => 'required|file|mimes:jpeg,jpg,png,webp,pdf|max:25600',
        ]);

        try {
            $file     = $request->file('receipt_file');
            $ext      = strtolower($file->getClientOriginalExtension());
            $filename = 'income_rcpt_' . now()->format('Ymd_His') . '_' . uniqid() . '.' . $ext;
            $path     = $file->storeAs('income_receipts', $filename, 'public');
            $fileUrl  = asset('storage/' . $path);

            $scan = $this->ocrService->scan($file->getRealPath());

            // Extract fields
            $rawFs = trim((string)($scan['fs_no'] ?? ''));
            $fsNorm = QcService::normalizeFsNo($rawFs);
            $fsNo = $fsNorm['value'] ?: $rawFs;
            if (empty($fsNo) && !empty($scan['extracted']['receipt_no'])) {
                $fsNo = $scan['extracted']['receipt_no'];
            }

            $rawTin = trim((string)($scan['extracted']['supplier_tin'] ?? ($scan['extracted']['merchant_tin'] ?? '')));
            $tinNorm = QcService::normalizeTin($rawTin);
            $buyerTin = $tinNorm['value'] ?: $rawTin;

            $buyerName = trim((string)($scan['extracted']['merchant_name'] ?? ''));
            $mrcNo     = trim((string)($scan['extracted']['machine_no'] ?? ($scan['extracted']['mrc_no'] ?? '')));
            $amount    = (float)($scan['extracted']['total_amount'] ?? 0);
            
            $rawDate = $scan['extracted']['receipt_date'] ?? null;
            $receiptDate = date('Y-m-d');
            if (!empty($rawDate)) {
                try {
                    $receiptDate = \Carbon\Carbon::parse($rawDate)->format('Y-m-d');
                } catch (\Throwable $e) {}
            }

            $sellerTin = SystemSetting::get('purchaser_tin', SystemSetting::get('company_tin', '38480010'));
            $sellerDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$sellerTin));

            return response()->json([
                'success'      => true,
                'file_path'    => $path,
                'file_url'     => $fileUrl,
                'seller_tin'   => $sellerDigits ?: '38480010',
                'buyer_tin'    => $buyerTin,
                'buyer_name'   => $buyerName,
                'receipt_no'   => $fsNo,
                'mrc_no'       => $mrcNo,
                'amount'       => $amount,
                'receipt_date' => $receiptDate,
                'confidence'   => $scan['confidence'] ?? 'medium',
                'engine'       => $scan['engine_label'] ?? 'AI Vision OCR',
            ]);
        } catch (\Throwable $e) {
            Log::error('IncomeController scanReceipt failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'OCR processing failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Export Company Income & Sales Receipts to Excel (.xlsx).
     * Exact 13 ERCA eTax Sales Format:
     * Seller TIN | Buyer TIN | Buyer Name | Transaction | Receipt Category | Receipt Used | MRC | Receipt No | Commodity Type | Quantity | Price | Receipt Date | Encoded By
     */
    public function exportExcel(Request $request)
    {
        $this->ensureSchema();
        $query = $this->buildQuery($request);
        $items = $query->get();

        $filename = 'Company_Income_Sales_Report_' . now()->format('Y_m_d_His') . '.xlsx';
        $tempPath = storage_path('app/income_export_' . uniqid() . '.xlsx');

        try {
            $writer = new XlsxWriter();
            $writer->openToFile($tempPath);

            // Exact 13 Columns Header as specified by user
            $headerCells = [
                Cell::fromValue('Seller TIN'),
                Cell::fromValue('Buyer TIN'),
                Cell::fromValue('Buyer Name'),
                Cell::fromValue('Transaction'),
                Cell::fromValue('Receipt Category'),
                Cell::fromValue('Receipt Used'),
                Cell::fromValue('MRC'),
                Cell::fromValue('Receipt No'),
                Cell::fromValue('Commodity Type'),
                Cell::fromValue('Quantity'),
                Cell::fromValue('Price'),
                Cell::fromValue('Receipt Date'),
                Cell::fromValue('Encoded By'),
            ];
            $writer->addRow(new Row($headerCells));

            foreach ($items as $inc) {
                // Col A: Seller TIN (Wechecha Construction TIN: 38480010 without leading zeros)
                $sellerTinVal = (int)$inc->formatted_seller_tin;

                // Col B: Buyer TIN (Customer/Client TIN without leading zeros)
                $buyerDigits = preg_replace('/^0+/', '', preg_replace('/[^0-9]/', '', (string)$inc->buyer_tin));
                $buyerTinVal = ($buyerDigits !== '') ? (int)$buyerDigits : (trim((string)$inc->buyer_tin));

                // Col C: Buyer Name
                $buyerName = trim((string)$inc->buyer_name);
                if (empty($buyerName) && $inc->project) {
                    $buyerName = trim((string)($inc->project->client_name ?: $inc->project->name));
                }
                if (empty($buyerName)) {
                    $buyerName = 'Walk-in Customer';
                }

                // Col D: Transaction
                $transaction = trim((string)($inc->transaction_type ?: 'Cash Sales'));

                // Col E: Receipt Category
                $receiptCategory = trim((string)($inc->receipt_category ?: 'Cash Sales Fiscal Receipt'));

                // Col F: Receipt Used
                $receiptUsed = trim((string)($inc->receipt_used ?: 'Fiscal Cash Register'));

                // Col G: MRC
                $mrc = trim((string)($inc->mrc_no ?? ''));

                // Col H: Receipt No (e.g. FS00003898)
                $receiptNo = $inc->formatted_receipt_no ?: trim((string)($inc->receipt_no ?: $inc->income_no));

                // Col I: Commodity Type (Services or Goods)
                $commodityType = trim((string)($inc->commodity_type ?? ''));
                if (empty($commodityType)) {
                    $cat = strtolower((string)$inc->category);
                    $commodityType = in_array($cat, ['construction', 'goods']) ? 'Goods' : 'Services';
                }

                // Col J: Quantity
                $qtyVal = (float)($inc->quantity > 0 ? $inc->quantity : 1.00);

                // Col K: Price (numeric 2 decimals)
                $priceVal = (float)($inc->unit_price > 0 ? $inc->unit_price : $inc->amount);
                $priceVal = round($priceVal, 2);

                // Col L: Receipt Date (Format: DD-MM-YYYY, e.g. 15-08-2026)
                $rDate = $inc->receipt_date ?: $inc->income_date;
                $receiptDateFormatted = $rDate ? $rDate->format('d-m-Y') : now()->format('d-m-Y');

                // Col M: Encoded By (e.g. wechacha)
                $encodedBy = trim((string)($inc->encoded_by ?: ($request->input('encoded_by') ?: (
                    SystemSetting::get('etax_encoded_by') ?: (
                        $inc->createdBy && !in_array(strtolower($inc->createdBy->name), ['admin', 'global admin', 'system'])
                            ? strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $inc->createdBy->name))
                            : 'wechacha'
                    )
                ))));
                if (empty($encodedBy)) {
                    $encodedBy = 'wechacha';
                }

                $rowCells = [
                    Cell::fromValue($sellerTinVal),
                    Cell::fromValue($buyerTinVal),
                    Cell::fromValue($buyerName),
                    Cell::fromValue($transaction),
                    Cell::fromValue($receiptCategory),
                    Cell::fromValue($receiptUsed),
                    Cell::fromValue($mrc),
                    Cell::fromValue($receiptNo),
                    Cell::fromValue($commodityType),
                    Cell::fromValue($qtyVal),
                    Cell::fromValue($priceVal),
                    Cell::fromValue($receiptDateFormatted),
                    Cell::fromValue($encodedBy),
                ];
                $writer->addRow(new Row($rowCells));
            }

            $writer->close();

            return response()->download($tempPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            Log::error('IncomeController exportExcel failed: ' . $e->getMessage());
            return $this->exportCsv($request);
        }
    }

    /**
     * Export Company Income & Sales Receipts to CSV (UTF-8 with BOM).
     * Exact 13 Columns matching eTax Sales template.
     */
    public function exportCsv(Request $request)
    {
        $this->ensureSchema();
        $query = $this->buildQuery($request);
        $items = $query->get();

        $filename = 'Company_Income_Sales_Report_' . now()->format('Y_m_d_His') . '.csv';

        return response()->streamDownload(function() use ($items, $request) {
            $out = fopen('php://output', 'w');
            // Write UTF-8 BOM
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, [
                'Seller TIN',
                'Buyer TIN',
                'Buyer Name',
                'Transaction',
                'Receipt Category',
                'Receipt Used',
                'MRC',
                'Receipt No',
                'Commodity Type',
                'Quantity',
                'Price',
                'Receipt Date',
                'Encoded By',
            ]);

            foreach ($items as $inc) {
                $sellerTin = $inc->formatted_seller_tin;
                $buyerTin  = $inc->formatted_buyer_tin;

                $buyerName = trim((string)$inc->buyer_name);
                if (empty($buyerName) && $inc->project) {
                    $buyerName = trim((string)($inc->project->client_name ?: $inc->project->name));
                }
                if (empty($buyerName)) {
                    $buyerName = 'Walk-in Customer';
                }

                $transaction     = trim((string)($inc->transaction_type ?: 'Cash Sales'));
                $receiptCategory = trim((string)($inc->receipt_category ?: 'Cash Sales Fiscal Receipt'));
                $receiptUsed     = trim((string)($inc->receipt_used ?: 'Fiscal Cash Register'));
                $mrc             = trim((string)($inc->mrc_no ?? ''));
                $receiptNo       = $inc->formatted_receipt_no ?: trim((string)($inc->receipt_no ?: $inc->income_no));

                $commodityType = trim((string)($inc->commodity_type ?? ''));
                if (empty($commodityType)) {
                    $cat = strtolower((string)$inc->category);
                    $commodityType = in_array($cat, ['construction', 'goods']) ? 'Goods' : 'Services';
                }

                $qty   = (float)($inc->quantity > 0 ? $inc->quantity : 1.00);
                $price = (float)($inc->unit_price > 0 ? $inc->unit_price : $inc->amount);

                $rDate = $inc->receipt_date ?: $inc->income_date;
                $receiptDateFormatted = $rDate ? $rDate->format('d-m-Y') : now()->format('d-m-Y');

                $encodedBy = trim((string)($inc->encoded_by ?: ($request->input('encoded_by') ?: (
                    SystemSetting::get('etax_encoded_by') ?: (
                        $inc->createdBy && !in_array(strtolower($inc->createdBy->name), ['admin', 'global admin', 'system'])
                            ? strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $inc->createdBy->name))
                            : 'wechacha'
                    )
                ))));
                if (empty($encodedBy)) {
                    $encodedBy = 'wechacha';
                }

                fputcsv($out, [
                    $sellerTin,
                    $buyerTin,
                    $buyerName,
                    $transaction,
                    $receiptCategory,
                    $receiptUsed,
                    $mrc,
                    $receiptNo,
                    $commodityType,
                    number_format($qty, 2, '.', ''),
                    number_format($price, 2, '.', ''),
                    $receiptDateFormatted,
                    $encodedBy,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
