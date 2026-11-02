<?php

namespace App\Http\Controllers;

use App\Exceptions\BudgetExceededException;
use App\Models\Expense;
use App\Models\Project;
use App\Models\ChartOfAccount;
use App\Services\BudgetGuardService;
use App\Services\Finance\JournalEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ExpenseController extends Controller
{
    protected JournalEngine $journalEngine;
    protected BudgetGuardService $budgetGuard;

    public function __construct(JournalEngine $journalEngine, BudgetGuardService $budgetGuard)
    {
        $this->journalEngine = $journalEngine;
        $this->budgetGuard   = $budgetGuard;
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Expense::class);

        $query = Expense::with(['project', 'creator'])->latest('expense_date');

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $expenses   = $query->paginate(20);
        $projects   = Project::all();
        $categories = Expense::CATEGORIES;

        return view('finance.expenses.index', compact('expenses', 'projects', 'categories'));
    }

    public function create()
    {
        Gate::authorize('create', Expense::class);
        $projects   = Project::where('status', '!=', 'cancelled')->get();
        $categories = Expense::CATEGORIES;
        return view('finance.expenses.create', compact('projects', 'categories'));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Expense::class);

        $validated = $request->validate([
            'project_id'   => 'required|exists:projects,id',
            'category'     => 'required|in:' . implode(',', array_keys(Expense::CATEGORIES)),
            'description'  => 'required|string|max:500',
            'amount'       => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'notes'        => 'nullable|string',
        ]);

        $project = Project::findOrFail($validated['project_id']);

        // ── Budget Guard ──────────────────────────────────────────────────
        $budgetCheck = $this->budgetGuard->check($project, (float) $validated['amount']);

        if ($budgetCheck['status'] === 'blocked') {
            return back()
                ->withInput()
                ->withErrors(['amount' => $budgetCheck['message']]);
        }

        $validated['created_by'] = auth()->id();

        // Atomically consume budget if guarded (record save-time fact)
        if ($budgetCheck['guarded']) {
            $this->budgetGuard->consume($project, (float) $validated['amount']);
            $validated['budget_status'] = $budgetCheck['status']; // 'safe' | 'at_risk'
            $validated['budget_consumed_at'] = now();
        } else {
            $validated['budget_status'] = 'unguarded';
            $validated['budget_consumed_at'] = null;
        }

        $expense = Expense::create($validated);

        $warningMsg = $budgetCheck['status'] === 'at_risk'
            ? ' ⚠️ Warning: ' . $budgetCheck['message']
            : '';

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense recorded.' . $warningMsg);
    }

    public function show(Expense $expense)
    {
        Gate::authorize('view', $expense);
        $expense->load(['project', 'creator', 'approver']);
        return view('finance.expenses.show', compact('expense'));
    }

    public function approve(Expense $expense)
    {
        Gate::authorize('approve', $expense);

        DB::transaction(function () use ($expense) {
            $expense->update([
                'status'      => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $categoryCodeMap = [
                'labour'        => '5200',
                'material'      => '5100',
                'equipment'     => '5300',
                'overhead'      => '6100',
                'subcontractor' => '5400',
                'other'         => '6100',
            ];
            $code = $categoryCodeMap[$expense->category] ?? '6100';
            $expenseAccount         = ChartOfAccount::where('code', $code)->first() ?? ChartOfAccount::where('type', 'expense')->first();
            $liabilityOrAssetAccount = ChartOfAccount::where('code', '2100')->first() ?? ChartOfAccount::where('code', '1120')->first();

            if ($expenseAccount && $liabilityOrAssetAccount) {
                $this->journalEngine->createEntry(
                    'Expense',
                    $expense->id,
                    'Expense Approved: ' . $expense->description,
                    [
                        ['account_id' => $expenseAccount->id,          'side' => 'debit',  'amount' => $expense->amount, 'description' => 'Expense incurred'],
                        ['account_id' => $liabilityOrAssetAccount->id, 'side' => 'credit', 'amount' => $expense->amount, 'description' => 'Liability/Cash credited'],
                    ],
                    auth()->id(),
                    auth()->id()
                );
            }
        });

        return back()->with('success', 'Expense approved and journal entry created.');
    }

    public function reject(Expense $expense)
    {
        Gate::authorize('approve', $expense);

        $expense->update(['status' => 'rejected']);

        // Release the consumed budget back to the project if consumed at save-time
        if ($expense->project_id && ($expense->budget_consumed_at !== null || in_array($expense->budget_status, ['safe', 'at_risk']))) {
            app(BudgetGuardService::class)->release($expense->project, (float) $expense->amount);
            $expense->update(['budget_consumed_at' => null]);
        }

        return back()->with('success', 'Expense rejected and budget released.');
    }
}
