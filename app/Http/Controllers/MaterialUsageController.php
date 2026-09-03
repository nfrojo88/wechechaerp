<?php

namespace App\Http\Controllers;

use App\Models\MaterialUsage;
use App\Models\MaterialUsageItem;
use App\Models\Store;
use App\Models\Project;
use App\Models\Product;
use App\Models\Inventory;
use App\Services\OperationalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

class MaterialUsageController extends Controller
{
    protected OperationalService $operationalService;

    public function __construct(OperationalService $operationalService)
    {
        $this->operationalService = $operationalService;
    }

    /**
     * Auto-heal database schema for material_usages and material_usage_items.
     */
    protected function ensureSchemaExists(): void
    {
        try {
            if (Schema::hasTable('material_usages')) {
                Schema::table('material_usages', function (Blueprint $table) {
                    if (!Schema::hasColumn('material_usages', 'consumed_by_name')) {
                        $table->string('consumed_by_name', 150)->nullable()->after('description');
                    }
                    if (!Schema::hasColumn('material_usages', 'activity_type')) {
                        $table->string('activity_type', 100)->nullable()->after('consumed_by_name');
                    }
                    if (!Schema::hasColumn('material_usages', 'slip_number')) {
                        $table->string('slip_number', 50)->nullable()->after('usage_no');
                    }
                    if (!Schema::hasColumn('material_usages', 'verified_by_id')) {
                        $table->unsignedBigInteger('verified_by_id')->nullable()->after('created_by');
                    }
                    if (!Schema::hasColumn('material_usages', 'confirmed_at')) {
                        $table->timestamp('confirmed_at')->nullable()->after('status');
                    }
                });
            }

            if (Schema::hasTable('material_usage_items')) {
                Schema::table('material_usage_items', function (Blueprint $table) {
                    if (!Schema::hasColumn('material_usage_items', 'quantity')) {
                        $table->decimal('quantity', 15, 3)->nullable()->after('product_id');
                    }
                    if (!Schema::hasColumn('material_usage_items', 'unit_cost')) {
                        $table->decimal('unit_cost', 15, 2)->default(0)->after('unit');
                    }
                    if (!Schema::hasColumn('material_usage_items', 'total_cost')) {
                        $table->decimal('total_cost', 15, 2)->default(0)->after('unit_cost');
                    }
                    if (!Schema::hasColumn('material_usage_items', 'remarks')) {
                        $table->text('remarks')->nullable()->after('notes');
                    }
                });
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('MaterialUsage schema auto-heal: ' . $e->getMessage());
        }
    }

    /**
     * Display listing of Daily Material Consumptions with KPI stats and filters.
     */
    public function index(Request $request)
    {
        $this->ensureSchemaExists();

        $user = auth()->user();
        $userStoreId = $user->store_id ?? Store::where('manager_id', $user->id)->value('id');

        $userRoles = $user->getRoleNames()->map(fn($r) => strtolower(str_replace([' ', '-'], '_', trim($r))))->toArray();
        $isStoreKeeper = in_array('store_keeper', $userRoles);
        $isStoreManager = in_array('store_manager', $userRoles);
        $isAdmin = $user->hasAnyRole(['admin', 'global_admin']);

        // Base query with relations
        $query = MaterialUsage::with(['project', 'store', 'createdBy', 'verifiedBy', 'items.product'])->latest('usage_date')->latest('id');

        // Scope to store keeper's assigned store if not an admin/manager with filter
        if ($isStoreKeeper && $userStoreId && !$request->filled('store_id')) {
            $query->where('store_id', $userStoreId);
        }

        // Apply Filters
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('usage_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('usage_date', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('usage_no', 'like', "%{$search}%")
                  ->orWhere('slip_number', 'like', "%{$search}%")
                  ->orWhere('consumed_by_name', 'like', "%{$search}%")
                  ->orWhere('activity_type', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('items.product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                         ->orWhere('item_code', 'like', "%{$search}%");
                  });
            });
        }

        // Calculate KPI summaries
        $today = now()->toDateString();
        $startOfMonth = now()->startOfMonth()->toDateString();

        $kpiQuery = MaterialUsage::query();
        if ($isStoreKeeper && $userStoreId && !$request->filled('store_id')) {
            $kpiQuery->where('store_id', $userStoreId);
        }

        $kpi = [
            'today_count' => (clone $kpiQuery)->whereDate('usage_date', $today)->count(),
            'month_count' => (clone $kpiQuery)->whereDate('usage_date', '>=', $startOfMonth)->count(),
            'confirmed_count' => (clone $kpiQuery)->where('status', 'confirmed')->count(),
            'draft_count' => (clone $kpiQuery)->where('status', 'draft')->count(),
        ];

        $usages = $query->paginate(15)->withQueryString();

        $stores = Store::where('status', 'active')->orderBy('name')->get();
        $projects = Project::where('status', 'active')->orderBy('name')->get();

        return view('operational.material-usages.index', compact(
            'usages',
            'kpi',
            'stores',
            'projects',
            'userStoreId',
            'isStoreKeeper'
        ));
    }

    /**
     * Show form for logging a new Daily Material Consumption.
     */
    public function create(Request $request)
    {
        $this->ensureSchemaExists();

        $user = auth()->user();
        $userStoreId = $user->store_id ?? Store::where('manager_id', $user->id)->value('id');

        $stores = Store::where('status', 'active')->orderBy('name')->get();
        $projects = Project::where('status', 'active')->orderBy('name')->get();

        // Suggested Consumption No: DC-YYYYMMDD-XXXX
        $suggestedUsageNo = 'DC-' . date('Ymd') . '-' . strtoupper(Str::random(4));

        // Pre-select store if store keeper
        $selectedStoreId = $request->query('store_id', $userStoreId ?? ($stores->first()->id ?? null));

        // Get initial product inventory for the selected store
        $storeProducts = [];
        if ($selectedStoreId) {
            $storeProducts = Inventory::with('product')
                ->where('store_id', $selectedStoreId)
                ->where('quantity_on_hand', '>', 0)
                ->get()
                ->map(fn($inv) => [
                    'id' => $inv->product_id,
                    'name' => $inv->product->name ?? 'Unknown',
                    'item_code' => $inv->product->item_code ?? '',
                    'unit' => $inv->product->unit ?? 'pcs',
                    'unit_cost' => (float) ($inv->unit_cost ?? $inv->product->unit_cost ?? 0),
                    'stock_on_hand' => (float) $inv->quantity_on_hand,
                ])->values()->all();
        }

        // Fallback to all products if no store inventory yet
        $allProducts = Product::where('status', 'active')->orderBy('name')->get();

        return view('operational.material-usages.create', compact(
            'stores',
            'projects',
            'suggestedUsageNo',
            'selectedStoreId',
            'storeProducts',
            'allProducts',
            'userStoreId'
        ));
    }

    /**
     * AJAX endpoint to fetch products available at a specific store with live stock.
     */
    public function getStoreProducts(Store $store)
    {
        $inventories = Inventory::with('product')
            ->where('store_id', $store->id)
            ->get()
            ->map(fn($inv) => [
                'id' => $inv->product_id,
                'name' => $inv->product->name ?? 'Item',
                'item_code' => $inv->product->item_code ?? '',
                'unit' => $inv->product->unit ?? 'pcs',
                'unit_cost' => (float) ($inv->unit_cost ?? $inv->product->unit_cost ?? 0),
                'stock_on_hand' => (float) $inv->quantity_on_hand,
            ]);

        return response()->json([
            'store_id' => $store->id,
            'store_name' => $store->name,
            'products' => $inventories,
        ]);
    }

    /**
     * Store daily material consumption log and automatically deduct stock if confirmed.
     */
    public function store(Request $request)
    {
        $this->ensureSchemaExists();

        $data = $request->validate([
            'usage_no' => 'required|string|max:50|unique:material_usages,usage_no',
            'slip_number' => 'nullable|string|max:50',
            'project_id' => 'required|exists:projects,id',
            'store_id' => 'required|exists:stores,id',
            'usage_date' => 'required|date',
            'consumed_by_name' => 'nullable|string|max:150',
            'activity_type' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'auto_confirm' => 'nullable|boolean',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.remarks' => 'nullable|string|max:255',
        ]);

        $storeId = (int) $data['store_id'];
        $autoConfirm = $request->boolean('auto_confirm', true);

        // Pre-validate stock availability for all items before initiating transaction
        $insufficientStockItems = [];
        foreach ($data['items'] as $item) {
            $inv = Inventory::where('store_id', $storeId)->where('product_id', $item['product_id'])->first();
            $available = $inv ? (float) $inv->quantity_on_hand : 0;
            $requested = (float) $item['quantity'];

            if ($autoConfirm && $requested > $available) {
                $prod = Product::find($item['product_id']);
                $insufficientStockItems[] = ($prod ? $prod->name : 'Item') . " (Requested: {$requested}, Available in Store: {$available})";
            }
        }

        if (!empty($insufficientStockItems)) {
            return back()->withInput()->with('error', 'Cannot record consumption due to insufficient stock in the selected store: ' . implode('; ', $insufficientStockItems));
        }

        $usage = null;

        DB::transaction(function () use ($data, $autoConfirm, $storeId, &$usage) {
            $usageData = [
                'usage_no' => $data['usage_no'],
                'slip_number' => $data['slip_number'] ?? null,
                'project_id' => $data['project_id'],
                'store_id' => $storeId,
                'usage_date' => $data['usage_date'],
                'consumed_by_name' => $data['consumed_by_name'] ?? null,
                'activity_type' => $data['activity_type'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $autoConfirm ? 'confirmed' : 'draft',
                'confirmed_at' => $autoConfirm ? now() : null,
                'created_by' => auth()->id(),
            ];

            $usage = MaterialUsage::create($usageData);

            foreach ($data['items'] as $itemData) {
                $product = Product::find($itemData['product_id']);
                $qty = (float) $itemData['quantity'];
                $unitCost = (float) ($product->unit_cost ?? 0);
                $totalCost = round($qty * $unitCost, 2);

                $usageItem = $usage->items()->create([
                    'product_id' => $itemData['product_id'],
                    'used_quantity' => $qty,
                    'quantity' => $qty,
                    'unit' => $product->unit ?? 'pcs',
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'notes' => $itemData['remarks'] ?? null,
                    'remarks' => $itemData['remarks'] ?? null,
                ]);
            }

            // If auto-confirm is enabled, deduct stock in real-time via OperationalService
            if ($autoConfirm) {
                $this->operationalService->recordMaterialUsage($usage);
            }
        });

        $msg = $autoConfirm
            ? "Daily Consumption #{$usage->usage_no} recorded and store inventory stock successfully deducted!"
            : "Daily Consumption #{$usage->usage_no} saved as draft.";

        return redirect()->route('material-usages.show', $usage)->with('success', $msg);
    }

    /**
     * Show detailed view of a daily material consumption record.
     */
    public function show(MaterialUsage $materialUsage)
    {
        $this->ensureSchemaExists();

        $materialUsage->load(['items.product', 'project', 'store', 'task', 'createdBy', 'verifiedBy']);
        return view('operational.material-usages.show', compact('materialUsage'));
    }

    /**
     * Printable Daily Material Consumption Slip / SIV Voucher.
     */
    public function printSlip(MaterialUsage $materialUsage)
    {
        $this->ensureSchemaExists();

        $materialUsage->load(['items.product', 'project', 'store', 'task', 'createdBy', 'verifiedBy']);
        return view('operational.material-usages.print', compact('materialUsage'));
    }

    /**
     * Confirm a draft consumption log and execute stock deduction.
     */
    public function confirm(MaterialUsage $materialUsage)
    {
        $this->ensureSchemaExists();

        if ($materialUsage->status === 'draft') {
            try {
                $this->operationalService->recordMaterialUsage($materialUsage);
                $materialUsage->update([
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                    'verified_by_id' => auth()->id(),
                ]);
                return redirect()->back()->with('success', "Usage #{$materialUsage->usage_no} confirmed and inventory stock deducted successfully.");
            } catch (\Throwable $e) {
                return redirect()->back()->with('error', 'Failed to deduct stock: ' . $e->getMessage());
            }
        }

        return redirect()->back()->with('info', 'This consumption log is already confirmed.');
    }
}
