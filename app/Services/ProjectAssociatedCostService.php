<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProjectAssociatedCostService
{
    /**
     * Get a paginated list of associated (unbudgeted) costs for a project.
     */
    public function getPaginated(Project $project, array $filters = [], int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $query = $this->buildBaseQuery($project, $filters);

        $totalCount = DB::query()->fromSub($query, 'sub_total')->count();

        $items = (clone $query)
            ->orderBy('cost_date', 'desc')
            ->orderBy('source_id', 'desc')
            ->forPage($page, $perPage)
            ->get();

        return new LengthAwarePaginator(
            $items,
            $totalCount,
            $perPage,
            $page,
            [
                'path'  => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * Get summary KPI totals for associated (unbudgeted) costs.
     */
    public function getSummary(Project $project): array
    {
        $baseQuery = $this->buildBaseQuery($project, []);

        $stats = DB::query()->fromSub($baseQuery, 'sub')
            ->selectRaw('
                COUNT(*) as total_count,
                COALESCE(SUM(amount), 0) as total_associated_cost,
                COALESCE(SUM(CASE WHEN category = "Material" THEN amount ELSE 0 END), 0) as total_material_cost,
                COALESCE(SUM(CASE WHEN category = "Cash" THEN amount ELSE 0 END), 0) as total_cash_cost
            ')
            ->first();

        $budgetAllocated = (float) $project->budget_allocated;
        $budgetConsumed = (float) $project->budget_consumed;
        $associatedCost = (float) ($stats->total_associated_cost ?? 0);
        $totalRealCost = $budgetConsumed + $associatedCost;

        return [
            'total_count'           => (int) ($stats->total_count ?? 0),
            'total_associated_cost' => $associatedCost,
            'total_material_cost'   => (float) ($stats->total_material_cost ?? 0),
            'total_cash_cost'       => (float) ($stats->total_cash_cost ?? 0),
            'budget_allocated'      => $budgetAllocated,
            'budget_consumed'       => $budgetConsumed,
            'total_real_cost'       => $totalRealCost,
        ];
    }

    /**
     * Construct the unified SQL query across the 5 approved sources.
     */
    protected function buildBaseQuery(Project $project, array $filters = [])
    {
        $projectId = (int) $project->id;

        // 1. Procurement Payments (Cash-disbursed vendor payments, excluding credit-settled PRs)
        $procurementSql = DB::table('procurement_payments as pp')
            ->join('purchase_requests as pr', 'pr.id', '=', 'pp.purchase_request_id')
            ->where('pr.project_id', $projectId)
            ->where('pp.status', 'paid')
            ->where(function ($q) {
                $q->whereNull('pp.method')->orWhere('pp.method', '!=', 'credit');
            })
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('credit_store_ledgers as csl')
                    ->whereColumn('csl.purchase_request_id', 'pr.id');
            })
            ->select([
                DB::raw("'procurement' AS source_type"),
                'pp.id AS source_id',
                DB::raw("COALESCE(pp.paid_at, pp.created_at) AS cost_date"),
                DB::raw("COALESCE(pr.pr_no, CONCAT('PR-', pr.id)) AS reference_no"),
                DB::raw("COALESCE(pr.justification, pp.notes, 'Material Purchase via Procurement') AS description"),
                DB::raw("'Material' AS category"),
                'pp.amount AS amount',
                'pp.status AS status',
                DB::raw("CONCAT('/purchase-requests/', pr.id) AS view_url"),
            ]);

        // 2. Petty Cash Material Purchases (Site store purchases: completed & non-deleted)
        $pettyCashSql = DB::table('petty_cash_material_purchases as pcmp')
            ->join('stores as s', 's.id', '=', 'pcmp.store_id')
            ->where('s.project_id', $projectId)
            ->where('pcmp.status', 'completed')
            ->whereNull('pcmp.deleted_at')
            ->where('pcmp.total_amount', '>', 0)
            ->select([
                DB::raw("'petty_cash' AS source_type"),
                'pcmp.id AS source_id',
                DB::raw("COALESCE(pcmp.purchase_date, pcmp.created_at) AS cost_date"),
                DB::raw("COALESCE(pcmp.purchase_no, CONCAT('PC-', pcmp.id)) AS reference_no"),
                DB::raw("COALESCE(pcmp.notes, CONCAT('Site Petty Cash Purchase - ', COALESCE(pcmp.supplier_name, 'Supplier'))) AS description"),
                DB::raw("'Material' AS category"),
                'pcmp.total_amount AS amount',
                'pcmp.status AS status',
                DB::raw("CONCAT('/petty-cash-material-purchases/', pcmp.id) AS view_url"),
            ]);

        // 3. Credit Store Ledger (Materials delivered on credit; strictly gated by actual physical goods receipt)
        $creditStoreSql = DB::table('credit_store_ledgers as csl')
            ->leftJoin('purchase_requests as pr', 'pr.id', '=', 'csl.purchase_request_id')
            ->where('csl.project_id', $projectId)
            ->where('csl.credit_amount', '>', 0)
            ->whereIn('csl.status', ['outstanding', 'partially_paid', 'fully_paid'])
            ->where(function ($q) {
                // Direct ledger entry: must be authorized
                $q->where(function ($directQ) {
                    $directQ->whereNull('csl.purchase_request_id')
                            ->whereNotNull('csl.authorized_at');
                // Linked to PR: physical goods MUST be received on site first
                })->orWhere(function ($prQ) {
                    $prQ->whereNotNull('csl.purchase_request_id')
                        ->where(function ($subPr) {
                            $subPr->whereIn('pr.status', ['intake_complete', 'completed', 'delivered'])
                                  ->orWhereExists(function ($drSub) {
                                      $drSub->select(DB::raw(1))
                                            ->from('delivery_receipts as dr')
                                            ->whereColumn('dr.purchase_request_id', 'csl.purchase_request_id')
                                            ->where('dr.status', 'verified');
                                  });
                        });
                });
            })
            ->select([
                DB::raw("'credit_store' AS source_type"),
                'csl.id AS source_id',
                DB::raw("COALESCE(csl.authorized_at, csl.created_at) AS cost_date"),
                DB::raw("COALESCE(csl.pr_no, CONCAT('CR-', csl.id)) AS reference_no"),
                DB::raw("CONCAT('Credit Purchase from ', COALESCE(csl.supplier_name, 'Supplier')) AS description"),
                DB::raw("'Material' AS category"),
                'csl.credit_amount AS amount',
                'csl.status AS status',
                DB::raw("CONCAT('/finance/credit-store/', csl.id) AS view_url"),
            ]);

        // 4. Expense Requests (Operational Cash Vouchers; excluding credit settlements & petty cash float top-ups)
        $expenseRequestSql = DB::table('expense_requests as er')
            ->where('er.project_id', $projectId)
            ->where('er.status', 'Paid')
            ->whereNull('er.deleted_at')
            ->where('er.amount', '>', 0)
            // Primary exclusion: indexed foreign keys to other modules
            ->whereNull('er.credit_store_ledger_id')
            ->whereNull('er.purchase_request_id')
            ->whereNull('er.petty_cash_owner_id')
            // Secondary exclusion: defined constants where FK might be unlinked historically
            ->where(function ($q) {
                $q->whereNull('er.other_reason')
                  ->orWhereNotIn('er.other_reason', [
                      \App\Models\ExpenseRequest::REASON_CREDIT_SETTLEMENT,
                      \App\Models\ExpenseRequest::REASON_PETTY_REPLENISHMENT,
                  ]);
            })
            ->where(function ($q) {
                $q->whereNull('er.category')
                  ->orWhere('er.category', '!=', 'Credit Settlement');
            })
            ->select([
                DB::raw("'expense_request' AS source_type"),
                'er.id AS source_id',
                DB::raw("COALESCE(er.paid_at, er.created_at) AS cost_date"),
                'er.request_number AS reference_no',
                DB::raw("COALESCE(er.description, er.category) AS description"),
                DB::raw("'Cash' AS category"),
                'er.amount AS amount',
                'er.status AS status',
                DB::raw("CONCAT('/expense-requests/', er.id) AS view_url"),
            ]);

        // 5. Standalone Expenses Table (Direct approved expenses NOT consumed into budget_consumed)
        $expensesSql = DB::table('expenses as e')
            ->where('e.project_id', $projectId)
            ->where('e.status', 'approved')
            ->where('e.amount', '>', 0)
            // Save-time fact: only expenses that were NOT consumed into the budget meter
            ->whereNull('e.budget_consumed_at')
            ->where(function ($q) {
                $q->whereNull('e.budget_status')
                  ->orWhere('e.budget_status', 'unguarded');
            })
            // Exclude mirror/duplicate records of PRs or Credit Store
            ->where(function ($q) {
                $q->whereNull('e.description')
                  ->orWhere(function ($sub) {
                      $sub->where('e.description', 'not like', '%' . \App\Models\Expense::EXCLUDED_PR_MARKER . '%')
                          ->where('e.description', 'not like', '%' . \App\Models\Expense::EXCLUDED_CREDIT_MARKER . '%')
                          ->where('e.description', 'not like', '%' . \App\Models\Expense::EXCLUDED_CREDIT_PAYMENT_MARKER . '%')
                          ->where('e.description', 'not like', '%PR #%');
                  });
            })
            ->select([
                DB::raw("'expense' AS source_type"),
                'e.id AS source_id',
                DB::raw("COALESCE(e.expense_date, e.created_at) AS cost_date"),
                DB::raw("CONCAT('EXP-', e.id) AS reference_no"),
                DB::raw("COALESCE(e.description, e.category) AS description"),
                DB::raw("CASE WHEN e.category = 'material' THEN 'Material' ELSE 'Cash' END AS category"),
                'e.amount AS amount',
                'e.status AS status',
                DB::raw("CONCAT('/expenses/', e.id) AS view_url"),
            ]);

        // Combine all 5 queries via SQL UNION ALL
        $unionQuery = $procurementSql
            ->unionAll($pettyCashSql)
            ->unionAll($creditStoreSql)
            ->unionAll($expenseRequestSql)
            ->unionAll($expensesSql);

        $wrapper = DB::query()->fromSub($unionQuery, 'unbudgeted_costs');

        // Apply filters in SQL
        if (!empty($filters['category']) && in_array(strtolower($filters['category']), ['material', 'cash'])) {
            $wrapper->where('category', ucfirst(strtolower($filters['category'])));
        }

        if (!empty($filters['source_type'])) {
            $wrapper->where('source_type', $filters['source_type']);
        }

        if (!empty($filters['date_from'])) {
            $wrapper->whereDate('cost_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $wrapper->whereDate('cost_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim($filters['search']) . '%';
            $wrapper->where(function ($q) use ($term) {
                $q->where('reference_no', 'like', $term)
                  ->orWhere('description', 'like', $term);
            });
        }

        return $wrapper;
    }
}
