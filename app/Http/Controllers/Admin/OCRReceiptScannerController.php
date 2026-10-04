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
     * Check if user is Global Admin.
     */
    private function ensureGlobalAdmin()
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('global_admin')) {
            abort(403, 'Unauthorized access. The OCR Receipt Scanner is reserved for Global Admin only.');
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
                'vendor'      => $request->vendor_name,
                'proprietor'  => $request->proprietor_name,
                'tin'         => $request->vendor_tin,
                'buyer_tin'   => $request->buyer_tin,
                'fs_no'       => $request->fs_no,
                'machine_no'  => $request->machine_no,
                'address'     => $request->vendor_address,
                'phone'       => $request->vendor_phone,
                'date'        => $request->receipt_date,
                'subtotal'    => $subtotal,
                'vat'         => $vat,
                'total'       => $total,
                'category'    => $request->category,
                'items'       => $request->input('line_items', []),
                'scanned_by'  => Auth::user()->name,
                'scanned_at'  => now()->toIso8601String(),
            ],
            'parse_status' => 'parsed',
            'status'       => 'approved', // Auto-approved when scanned and confirmed by Global Admin
            'approved_by'  => Auth::id(),
            'approved_at'  => now(),
            'notes'        => 'Scanned and verified via Global Admin OCR Scanner Studio.' . ($request->fs_no ? " FS: {$request->fs_no}" : ""),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Receipt {$receipt->receipt_number} saved and recorded successfully!",
            'receipt' => $receipt,
        ]);
    }

    /**
     * View details of a specific scanned receipt.
     */
    public function show(Receipt $receipt)
    {
        $this->ensureGlobalAdmin();
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
        $this->ensureGlobalAdmin();

        if ($receipt->file_path && Storage::disk('public')->exists($receipt->file_path)) {
            Storage::disk('public')->delete($receipt->file_path);
        }

        $receiptNo = $receipt->receipt_number;
        $receipt->delete();

        return redirect()->route('admin.ocr.index')->with('success', "Receipt {$receiptNo} deleted successfully.");
    }
}
