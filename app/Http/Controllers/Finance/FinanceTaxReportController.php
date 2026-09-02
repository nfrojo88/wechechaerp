<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ExpenseRequest;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceTaxReportController extends Controller
{
    /**
     * Display VAT and Withholding Tax deduction ledger and analytics.
     */
    public function index(Request $request)
    {
        $tab = $request->input('tab', 'all');
        $search = $request->input('search');
        $category = $request->input('category');
        $vatType = $request->input('vat_type');
        $hasWithholding = $request->input('has_withholding');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $accountId = $request->input('account_id');

        // Base Query: All records that have VAT, Withholding Tax, or an uploaded Withholding slip
        $query = ExpenseRequest::with(['user', 'employee', 'paidBy', 'bankAccount', 'chartOfAccount', 'letter', 'purchaseRequest'])
            ->where(function ($q) {
                $q->where('has_withholding', true)
                  ->orWhere('withholding_amount', '>', 0)
                  ->orWhere('vat_amount', '>', 0)
                  ->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b'])
                  ->orWhere(function ($sq) {
                      $sq->whereNotNull('withholding_receipt')->where('withholding_receipt', '!=', '');
                  });
            });

        // Tab Filtering
        if ($tab === 'withholding') {
            $query->where(function ($q) {
                $q->where('has_withholding', true)
                  ->orWhere('withholding_amount', '>', 0);
            });
        } elseif ($tab === 'vat') {
            $query->where(function ($q) {
                $q->where('vat_amount', '>', 0)
                  ->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b']);
            });
        } elseif ($tab === 'paid') {
            $query->where('status', ExpenseRequest::STATUS_PAID);
        }


        // Search Filter
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('payment_reference', 'like', "%{$search}%")
                  ->orWhere('withholding_receipt_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Category Filter
        if (!empty($category)) {
            $query->where('category', $category);
        }

        // VAT Type Filter
        if (!empty($vatType)) {
            $query->where('vat_type', $vatType);
        }

        // Withholding Filter
        if ($hasWithholding !== null && $hasWithholding !== '') {
            $query->where('has_withholding', (bool)$hasWithholding);
        }

        // Date Range Filter
        if (!empty($fromDate)) {
            $query->whereDate('created_at', '>=', Carbon::parse($fromDate));
        }
        if (!empty($toDate)) {
            $query->whereDate('created_at', '<=', Carbon::parse($toDate));
        }

        // Funding Account Filter
        if (!empty($accountId)) {
            $query->where(function ($q) use ($accountId) {
                $q->where('bank_account_id', $accountId)
                  ->orWhere('coa_id', $accountId)
                  ->orWhere('chart_of_account_id', $accountId);
            });
        }

        // Summary Aggregates
        $summaryQuery = clone $query;
        $allTaxItems = $summaryQuery->get();

        $totalRecords = $allTaxItems->count();
        $totalGrossBase = $allTaxItems->sum(function ($item) {
            return (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
        });
        $totalVatAmount = $allTaxItems->sum(function ($item) {
            if ((float)$item->vat_amount > 0) return (float)$item->vat_amount;
            $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
            $vatType = $item->vat_type ?? 'none';
            $vatRate = (float)($item->vat_rate ?? 15.00);
            if (in_array($vatType, ['inclusive', 'vat_b'])) {
                $base = round($gross / (1 + ($vatRate / 100)), 2);
                return round($gross - $base, 2);
            } elseif ($vatType === 'exclusive') {
                return round($gross * ($vatRate / 100), 2);
            }
            return 0.0;
        });
        $totalWithholdingAmount = $allTaxItems->sum(function ($item) {
            return (float)$item->calculated_withholding_amount;
        });
        $totalNetDisbursed = $allTaxItems->sum(function ($item) {
            return (float)$item->effective_payable_amount;
        });

        $totalWhtTransactions = $allTaxItems->filter(fn($item) => $item->has_withholding || (float)$item->withholding_amount > 0)->count();
        $slipsAttachedCount = $allTaxItems->filter(fn($item) => ($item->has_withholding || (float)$item->withholding_amount > 0) && !empty($item->withholding_receipt))->count();
        $missingSlipsCount = $totalWhtTransactions - $slipsAttachedCount;

        // Paginated records
        $records = $query->latest()->paginate(20)->withQueryString();

        // Support data for filter dropdowns
        $bankAccounts = BankAccount::orderBy('bank_name')->get();
        $chartOfAccounts = ChartOfAccount::orderBy('code')->get();

        return view('finance.tax-deductions.index', compact(
            'records',
            'tab',
            'totalRecords',
            'totalGrossBase',
            'totalVatAmount',
            'totalWithholdingAmount',
            'totalNetDisbursed',
            'totalWhtTransactions',
            'slipsAttachedCount',
            'missingSlipsCount',
            'bankAccounts',
            'chartOfAccounts'
        ));
    }

    /**
     * Export VAT and Withholding Tax deductions ledger to CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $tab = $request->input('tab', 'all');
        $search = $request->input('search');
        $category = $request->input('category');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        // Base Query: All records that have VAT, Withholding Tax, or an uploaded Withholding slip
        $query = ExpenseRequest::with(['user', 'employee', 'paidBy', 'bankAccount', 'chartOfAccount', 'letter', 'purchaseRequest'])
            ->where(function ($q) {
                $q->where('has_withholding', true)
                  ->orWhere('withholding_amount', '>', 0)
                  ->orWhere('vat_amount', '>', 0)
                  ->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b'])
                  ->orWhere(function ($sq) {
                      $sq->whereNotNull('withholding_receipt')->where('withholding_receipt', '!=', '');
                  });
            });

        if ($tab === 'withholding') {
            $query->where(function ($q) {
                $q->where('has_withholding', true)
                  ->orWhere('withholding_amount', '>', 0);
            });
        } elseif ($tab === 'vat') {
            $query->where(function ($q) {
                $q->where('vat_amount', '>', 0)
                  ->orWhereIn('vat_type', ['exclusive', 'inclusive', 'vat_b']);
            });
        } elseif ($tab === 'paid') {
            $query->where('status', ExpenseRequest::STATUS_PAID);
        }


        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('payment_reference', 'like', "%{$search}%")
                  ->orWhere('withholding_receipt_number', 'like', "%{$search}%");
            });
        }

        if (!empty($category)) {
            $query->where('category', $category);
        }

        if (!empty($fromDate)) {
            $query->whereDate('created_at', '>=', Carbon::parse($fromDate));
        }
        if (!empty($toDate)) {
            $query->whereDate('created_at', '<=', Carbon::parse($toDate));
        }

        $records = $query->latest()->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="tax_deductions_report_' . date('Ymd_His') . '.csv"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($records) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for proper Excel rendering
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Request #',
                'Date',
                'Beneficiary / Requester',
                'Category',
                'Description',
                'Gross / Base Amount (ETB)',
                'VAT Type',
                'VAT Rate (%)',
                'VAT Amount (ETB)',
                'Withholding Tax (3% WHT ETB)',
                'Net Disbursed / Paid (ETB)',
                'Payment Reference',
                'Paid At',
                'Paying Account',
                'WHT Receipt Serial #',
                'WHT Receipt Attached',
                'Status',
            ]);

            foreach ($records as $item) {
                $gross = (float)($item->gross_amount > 0 ? $item->gross_amount : $item->amount);
                $wht = (float)$item->calculated_withholding_amount;
                $net = (float)$item->effective_payable_amount;
                $vatLabel = match ($item->vat_type) {
                    'exclusive' => '15% VAT Added',
                    'inclusive', 'vat_b' => '15% VAT B Included',
                    default => 'No VAT (0%)',
                };

                fputcsv($handle, [
                    $item->request_number,
                    optional($item->created_at)->format('Y-m-d H:i'),
                    $item->user->name ?? 'N/A',
                    $item->category,
                    $item->description,
                    number_format($gross, 2, '.', ''),
                    $vatLabel,
                    number_format((float)($item->vat_rate ?? 15.00), 2, '.', ''),
                    number_format((float)($item->vat_amount ?? 0), 2, '.', ''),
                    number_format($wht, 2, '.', ''),
                    number_format($net, 2, '.', ''),
                    $item->payment_reference ?? 'N/A',
                    optional($item->paid_at)->format('Y-m-d H:i') ?? 'Pending',
                    $item->chartOfAccount->name ?? ($item->bankAccount->bank_name ?? 'Default Petty Cash'),
                    $item->withholding_receipt_number ?? 'N/A',
                    !empty($item->withholding_receipt) ? 'YES' : 'NO',
                    $item->status,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
