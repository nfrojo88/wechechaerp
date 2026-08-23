<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\MaterialPrice;
use App\Models\ErpPlanHeader;
use App\Models\ErpPlanTaskResource;
use App\Models\PurchaseOrderItem;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MarketingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // Safe query executor to avoid crashes if table not yet migrated
    private function safe(callable $fn, $default = null)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return $default ?? collect();
        }
    }

    /**
     * Marketing Dashboard Overview
     */
    public function dashboard(Request $request)
    {
        // Products – avoid filtering on 'is_active' if column doesn't exist
        $products = $this->safe(fn() => Product::orderBy('name')->get(), collect());

        // Prepare 6-month label array regardless
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = Carbon::now()->subMonths($i)->format('Y-m');
        }

        $materialSummaries = [];
        $totalTracked      = 0;
        $materialsIncreased = 0;
        $totalInflationSum  = 0;
        $inflationCount     = 0;

        foreach ($products as $product) {
            $latestPriceRecord = $this->safe(
                fn() => MaterialPrice::where('product_id', $product->id)
                    ->orderBy('effective_date', 'desc')
                    ->first()
            );

            if (!$latestPriceRecord) {
                $latestPrice     = (float) ($product->unit_price ?? 0);
                $prevPriceRecord = null;
            } else {
                $latestPrice     = (float) $latestPriceRecord->price;
                $prevPriceRecord = $this->safe(
                    fn() => MaterialPrice::where('product_id', $product->id)
                        ->where('effective_date', '<', $latestPriceRecord->effective_date)
                        ->orderBy('effective_date', 'desc')
                        ->first()
                );
            }

            $prevPrice  = $prevPriceRecord ? (float) $prevPriceRecord->price : $latestPrice;
            $diffAmount = $latestPrice - $prevPrice;
            $pctChange  = $prevPrice > 0 ? (($diffAmount / $prevPrice) * 100) : 0;

            if ($latestPriceRecord)              { $totalTracked++;    }
            if ($diffAmount > 0)                 { $materialsIncreased++; }
            if ($prevPriceRecord && $prevPrice > 0) {
                $totalInflationSum += $pctChange;
                $inflationCount++;
            }

            $indicator = 'no_change';
            if ($pctChange > 0.01)       { $indicator = 'increase'; }
            elseif ($pctChange < -0.01)  { $indicator = 'decrease'; }

            $materialSummaries[] = [
                'product_id'   => $product->id,
                'name'         => $product->name,
                'sku'          => $product->sku ?? '',
                'unit'         => $product->unit ?? '',
                'category'     => $product->category ?? '',
                'latest_price' => $latestPrice,
                'prev_price'   => $prevPrice,
                'diff_amount'  => $diffAmount,
                'pct_change'   => round($pctChange, 2),
                'indicator'    => $indicator,
                'last_updated' => $latestPriceRecord
                    ? $latestPriceRecord->effective_date->format('Y-m-d')
                    : 'Not yet tracked',
            ];
        }

        $avgInflation = $inflationCount > 0 ? round($totalInflationSum / $inflationCount, 2) : 0;

        $topIncreases = collect($materialSummaries)
            ->filter(fn($m) => $m['pct_change'] > 0)
            ->sortByDesc('pct_change')
            ->take(5)
            ->values();

        // Chart datasets (top 4 materials by price change)
        $chartDatasets = [];
        $topProducts   = collect($materialSummaries)->sortByDesc('pct_change')->take(4);

        foreach ($topProducts as $tp) {
            $dataPoints = [];
            foreach ($months as $m) {
                $dateObj  = Carbon::parse($m . '-01')->endOfMonth();
                $priceRec = $this->safe(
                    fn() => MaterialPrice::where('product_id', $tp['product_id'])
                        ->where('effective_date', '<=', $dateObj)
                        ->orderBy('effective_date', 'desc')
                        ->first()
                );
                $dataPoints[] = $priceRec ? (float) $priceRec->price : $tp['latest_price'];
            }
            $chartDatasets[] = [
                'label' => $tp['name'],
                'data'  => $dataPoints,
            ];
        }

        return view('marketing.dashboard', compact(
            'materialSummaries',
            'totalTracked',
            'materialsIncreased',
            'avgInflation',
            'topIncreases',
            'months',
            'chartDatasets'
        ));
    }

    /**
     * Monthly Price Update Form
     */
    public function createPrice()
    {
        $products  = $this->safe(fn() => Product::where('category', '!=', 'Fixed Asset')->orderBy('name')->get(), collect());
        $roles     = $this->safe(fn() => \App\Models\Designation::orderBy('title')->get(), collect());
        $equipment = $this->safe(fn() => Product::where('category', 'Fixed Asset')->orderBy('name')->get(), collect());
        
        $priceHistory = $this->safe(fn() => MaterialPrice::with('product', 'creator')
            ->orderBy('effective_date', 'desc')
            ->orderBy('id', 'desc')
            ->take(15)
            ->get(), collect());

        return view('marketing.prices.create', compact('products', 'roles', 'equipment', 'priceHistory'));
    }

    /**
     * Store Monthly Price Update (Prevents duplicate entries in the same month)
     */
    public function storePrice(Request $request)
    {
        $request->validate([
            'resource_type'  => 'required|in:material,manpower,equipment',
            'product_id'     => 'required_if:resource_type,material|nullable',
            'role_id'        => 'required_if:resource_type,manpower|nullable',
            'equipment_id'   => 'required_if:resource_type,equipment|nullable',
            'price'          => 'required|numeric|min:0',
            'effective_date' => 'required|date',
            'notes'          => 'nullable|string|max:500',
        ]);

        $type = $request->resource_type;
        $date = Carbon::parse($request->effective_date);
        $startOfMonth = $date->copy()->startOfMonth()->toDateString();
        $endOfMonth   = $date->copy()->endOfMonth()->toDateString();

        $msg = 'Price record updated successfully.';

        if ($type === 'material') {
            $existing = MaterialPrice::where('product_id', $request->product_id)
                ->whereBetween('effective_date', [$startOfMonth, $endOfMonth])
                ->first();

            if ($existing) {
                $existing->update([
                    'price'          => $request->price,
                    'effective_date' => $request->effective_date,
                    'notes'          => $request->notes,
                    'created_by'     => auth()->id(),
                ]);
            } else {
                MaterialPrice::create([
                    'product_id'     => $request->product_id,
                    'price'          => $request->price,
                    'currency'       => 'ETB',
                    'effective_date' => $request->effective_date,
                    'source'         => 'market',
                    'notes'          => $request->notes,
                    'created_by'     => auth()->id(),
                ]);
            }
            Product::where('id', $request->product_id)->update(['unit_price' => $request->price]);
            \App\Models\Inventory::where('product_id', $request->product_id)->update(['unit_cost' => $request->price]);
            $msg = 'Material market price recorded and synced to inventory unit cost for ' . $date->format('F Y') . '.';

        } elseif ($type === 'manpower') {
            $role = \App\Models\Designation::find($request->role_id);
            if ($role) {
                // Monthly rate converted to min_salary estimate or updated
                $role->update(['min_salary' => round($request->price * 26, 2)]);
            }
            $msg = 'Manpower daily rate updated to ETB ' . number_format($request->price, 2) . '/day.';

        } elseif ($type === 'equipment') {
            $eq = Product::find($request->equipment_id);
            if ($eq) {
                $eq->update(['unit_price' => $request->price, 'selling_price' => $request->price]);
            }
            $msg = 'Equipment rate updated to ETB ' . number_format($request->price, 2) . '/day.';
        }

        return redirect()->route('marketing.prices.history')
            ->with('success', $msg);
    }

    /**
     * Price History & Trends Log
     */
    public function priceHistory(Request $request)
    {
        try {
            $query = MaterialPrice::with(['product', 'creator'])->orderBy('effective_date', 'desc');

            if ($request->filled('product_id')) {
                $query->where('product_id', $request->product_id);
            }
            if ($request->filled('category')) {
                $query->whereHas('product', fn($q) => $q->where('category', $request->category));
            }

            $prices = $query->paginate(20);
        } catch (\Throwable $e) {
            $prices = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }

        $products   = $this->safe(fn() => Product::orderBy('name')->get(), collect());
        $categories = $this->safe(fn() => Product::distinct()->pluck('category')->filter()->values(), collect());

        return view('marketing.prices.history', compact('prices', 'products', 'categories'));
    }

    /**
     * Inflation Report
     */
    public function inflationReport(Request $request)
    {
        $products   = $this->safe(fn() => Product::orderBy('name')->get(), collect());
        $reportData = [];

        foreach ($products as $product) {
            $priceHistory = $this->safe(
                fn() => MaterialPrice::where('product_id', $product->id)
                    ->orderBy('effective_date', 'asc')
                    ->get(),
                collect()
            );

            if ($priceHistory->count() >= 2) {
                $firstPrice  = (float) $priceHistory->first()->price;
                $latestPrice = (float) $priceHistory->last()->price;
                $pctIncrease = $firstPrice > 0 ? ((($latestPrice - $firstPrice) / $firstPrice) * 100) : 0;

                $reportData[] = [
                    'product_name'  => $product->name,
                    'category'      => $product->category ?? '',
                    'unit'          => $product->unit ?? '',
                    'initial_price' => $firstPrice,
                    'latest_price'  => $latestPrice,
                    'diff_amount'   => $latestPrice - $firstPrice,
                    'pct_increase'  => round($pctIncrease, 2),
                    'records_count' => $priceHistory->count(),
                ];
            }
        }

        $avgInflationRate = count($reportData) > 0 ? round(collect($reportData)->avg('pct_increase'), 2) : 0;

        return view('marketing.reports.inflation', compact('reportData', 'avgInflationRate', 'products'));
    }

    /**
     * Planning vs Actual Cost Comparison Report (Two Costing Modes)
     */
    public function planningVsActual(Request $request)
    {
        $plans = ErpPlanHeader::with(['project', 'tasks.resources'])->get();

        $comparisonData = [];

        foreach ($plans as $plan) {
            $plannedMaterialCost = 0;
            $actualMaterialCost  = 0;

            foreach ($plan->tasks as $task) {
                foreach ($task->resources as $res) {
                    if (strtolower($res->resource_type) === 'material') {
                        $product = Product::where('name', $res->resource_name)->first();
                        $marketPrice = $product ? ($product->latestMarketPrice?->price ?? $product->unit_price) : $res->rate;

                        // Planning Cost (Uses latest market price)
                        $plannedMaterialCost += round($res->quantity * $marketPrice, 2);

                        // Actual Cost (Uses real paid purchase rate or store issue weighted average cost)
                        // If issued from store inventory, use stored cost rate
                        $actualRate = $res->rate; // Default to task resource rate
                        if ($product) {
                            $lastPurchase = PurchaseOrderItem::where('product_id', $product->id)->latest()->first();
                            if ($lastPurchase && $lastPurchase->unit_price > 0) {
                                $actualRate = (float) $lastPurchase->unit_price;
                            }
                        }
                        $actualMaterialCost += round($res->quantity * $actualRate, 2);
                    }
                }
            }

            $variance = $actualMaterialCost - $plannedMaterialCost;
            $variancePct = $plannedMaterialCost > 0 ? (($variance / $plannedMaterialCost) * 100) : 0;

            $comparisonData[] = [
                'plan_id'               => $plan->id,
                'plan_name'             => $plan->name,
                'project_name'          => $plan->project?->name ?? 'N/A',
                'planned_material_cost' => $plannedMaterialCost,
                'actual_material_cost'  => $actualMaterialCost,
                'variance'              => $variance,
                'variance_pct'          => round($variancePct, 2),
            ];
        }

        return view('marketing.reports.planning_vs_actual', compact('plans', 'comparisonData'));
    }
    /**
     * Quick-Add Equipment (Fixed Asset) from price-update page modal
     */
    public function storeEquipment(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:100',
            'category'    => 'nullable|string|max:100',
            'hourly_rate' => 'nullable|numeric|min:0',
            'daily_rate'  => 'nullable|numeric|min:0',
        ]);

        // Map to Product::$fillable columns only
        // 'code' -> 'sku', daily_rate is stored as unit_price/selling_price
        Product::create([
            'name'          => $request->name,
            'sku'           => $request->code,
            'category'      => $request->category ?? 'Fixed Asset',
            'unit'          => 'day',
            'unit_price'    => $request->daily_rate  ?? 0,
            'selling_price' => $request->daily_rate  ?? 0,
        ]);

        return redirect()->route('marketing.prices.create')
            ->with('success', 'Equipment "' . $request->name . '" added successfully.');
    }
}
