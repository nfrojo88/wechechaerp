<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Receipt;
use App\Services\ReceiptParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ReceiptController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ── Index ─────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $this->authorizeReceipts();

        $query = Receipt::with(['uploader', 'project', 'approver'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('receipt_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('receipt_date', '<=', $request->date_to);
        }
        if ($request->filled('parse_status')) {
            $query->where('parse_status', $request->parse_status);
        }

        $receipts   = $query->paginate(20)->withQueryString();
        $projects   = Project::orderBy('name')->get();
        $categories = Receipt::CATEGORIES;

        // Summary stats
        $stats = [
            'total'    => Receipt::count(),
            'pending'  => Receipt::where('status', 'pending')->count(),
            'approved' => Receipt::where('status', 'approved')->count(),
            'value'    => Receipt::where('status', 'approved')->sum('total_amount'),
        ];

        return view('finance.receipts.index', compact('receipts', 'projects', 'categories', 'stats'));
    }

    // ── Create ────────────────────────────────────────────────────────────
    public function create()
    {
        $this->authorizeManage();

        $projects   = Project::where('status', '!=', 'cancelled')->orderBy('name')->get();
        $categories = Receipt::CATEGORIES;

        return view('finance.receipts.create', compact('projects', 'categories'));
    }

    // ── Store (upload + OCR parse) ────────────────────────────────────────
    public function store(Request $request, ReceiptParserService $parser)
    {
        $this->authorizeManage();

        $request->validate([
            'receipt_file' => 'required|file|mimes:jpeg,jpg,png,webp,pdf|max:10240',
            'project_id'   => 'nullable|exists:projects,id',
            'notes'        => 'nullable|string|max:1000',
            'category'     => 'nullable|in:' . implode(',', array_keys(Receipt::CATEGORIES)),
        ]);

        $file     = $request->file('receipt_file');
        $mimeType = $file->getMimeType();
        $ext      = $file->getClientOriginalExtension();
        $stored   = $file->store('receipts', 'public');
        $absPath  = Storage::disk('public')->path($stored);

        // Default parse result (in case OCR fails)
        $parsed = [
            'vendor_name'  => null,
            'vendor_tin'   => null,
            'receipt_date' => null,
            'subtotal'     => 0,
            'vat_amount'   => 0,
            'total_amount' => 0,
            'category'     => $request->category ?? 'other',
            'description'  => null,
            'raw_text'     => '',
        ];
        $parseStatus = 'failed';
        $parseError  = null;

        try {
            $parsed      = $parser->parse($absPath, $mimeType);
            $parseStatus = 'parsed';
        } catch (\Throwable $e) {
            $parseError = $e->getMessage();
        }

        $receipt = Receipt::create([
            'uploaded_by'  => Auth::id(),
            'project_id'   => $request->project_id,
            'vendor_name'  => $parsed['vendor_name'],
            'vendor_tin'   => $parsed['vendor_tin'],
            'receipt_date' => $parsed['receipt_date'],
            'subtotal'     => $parsed['subtotal'],
            'vat_amount'   => $parsed['vat_amount'],
            'total_amount' => $parsed['total_amount'],
            'currency'     => 'ETB',
            'category'     => $request->category ?? $parsed['category'],
            'description'  => $parsed['description'],
            'file_path'    => $stored,
            'file_type'    => str_contains($mimeType, 'pdf') ? 'pdf' : 'image',
            'ocr_raw_text' => $parsed['raw_text'],
            'parsed_data'  => $parsed,
            'parse_status' => $parseStatus,
            'parse_error'  => $parseError,
            'status'       => 'pending',
            'notes'        => $request->notes,
        ]);

        $msg = $parseStatus === 'parsed'
            ? 'Receipt uploaded and parsed successfully.'
            : 'Receipt uploaded but OCR parsing failed — please fill in the fields manually. Error: ' . $parseError;

        return redirect()->route('receipts.show', $receipt)
            ->with($parseStatus === 'parsed' ? 'success' : 'warning', $msg);
    }

    // ── Show ──────────────────────────────────────────────────────────────
    public function show(Receipt $receipt)
    {
        $this->authorizeReceipts();
        $receipt->load(['uploader', 'project', 'approver']);
        $categories = Receipt::CATEGORIES;
        return view('finance.receipts.show', compact('receipt', 'categories'));
    }

    // ── Update (manual field correction) ─────────────────────────────────
    public function update(Request $request, Receipt $receipt)
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'vendor_name'  => 'nullable|string|max:255',
            'vendor_tin'   => 'nullable|string|max:50',
            'receipt_date' => 'nullable|date',
            'subtotal'     => 'nullable|numeric|min:0',
            'vat_amount'   => 'nullable|numeric|min:0',
            'total_amount' => 'nullable|numeric|min:0',
            'category'     => 'nullable|in:' . implode(',', array_keys(Receipt::CATEGORIES)),
            'description'  => 'nullable|string|max:1000',
            'notes'        => 'nullable|string|max:1000',
        ]);

        $receipt->update($validated);

        return back()->with('success', 'Receipt details updated.');
    }

    // ── Approve ───────────────────────────────────────────────────────────
    public function approve(Receipt $receipt)
    {
        $this->authorizeManage();

        if ($receipt->status !== 'pending') {
            return back()->with('warning', 'Receipt is already ' . $receipt->status . '.');
        }

        $receipt->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Receipt approved successfully.');
    }

    // ── Reject ────────────────────────────────────────────────────────────
    public function reject(Request $request, Receipt $receipt)
    {
        $this->authorizeManage();

        $receipt->update([
            'status' => 'rejected',
            'notes'  => $request->rejection_reason ?? $receipt->notes,
        ]);

        return back()->with('success', 'Receipt rejected.');
    }

    // ── Destroy ───────────────────────────────────────────────────────────
    public function destroy(Receipt $receipt)
    {
        $this->authorizeAdmin();

        Storage::disk('public')->delete($receipt->file_path);

        // Also clean up temp PDF-to-PNG file if it exists
        if ($receipt->file_type === 'pdf') {
            Storage::disk('public')->delete($receipt->file_path . '_page0.png');
        }

        $receipt->delete();

        return redirect()->route('receipts.index')->with('success', 'Receipt deleted.');
    }

    // ── Authorization helpers ─────────────────────────────────────────────
    private function authorizeReceipts()
    {
        abort_unless(
            Auth::user()->hasAnyPermission(['manage-receipts', 'view-receipts']),
            403,
            'You do not have permission to view receipts.'
        );
    }

    private function authorizeManage()
    {
        abort_unless(
            Auth::user()->hasPermissionTo('manage-receipts'),
            403,
            'You do not have permission to manage receipts.'
        );
    }

    private function authorizeAdmin()
    {
        abort_unless(
            Auth::user()->hasAnyRole(['admin', 'global_admin']),
            403,
            'Only administrators can delete receipts.'
        );
    }
}
