<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\DeliveryReceipt;
use App\Models\DeliveryReceiptItem;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PettyCashMaterialPurchase;
use App\Models\PettyCashMaterialPurchaseItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SlipSequence;
use App\Models\Store;
use App\Models\User;
use App\Services\FileUploadService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PettyCashMaterialPurchaseController extends Controller
{
    /**
     * Self-healing schema check to ensure tables exist without manual intervention
     */
    protected static function ensureSchema(): void
    {
        try {
            if (!Schema::hasTable('petty_cash_material_purchases')) {
                Schema::create('petty_cash_material_purchases', function (Blueprint $table) {
                    $table->id();
                    $table->string('purchase_no', 50)->unique();
                    $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
                    $table->foreignId('chart_of_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                    $table->foreignId('purchased_by')->constrained('users')->cascadeOnDelete();
                    $table->date('purchase_date');
                    $table->string('supplier_name', 255)->nullable();
                    $table->string('receipt_no', 100)->nullable();
                    $table->decimal('total_amount', 18, 2)->default(0);
                    $table->text('notes')->nullable();
                    $table->string('attachment_path')->nullable();
                    $table->foreignId('delivery_receipt_id')->nullable()->constrained('delivery_receipts')->nullOnDelete();
                    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                    $table->string('status', 30)->default('completed')->index();
                    $table->timestamps();
                    $table->softDeletes();

                    $table->index(['store_id', 'purchase_date']);
                    $table->index(['purchased_by', 'status']);
                });
            }

            if (!Schema::hasTable('petty_cash_material_purchase_items')) {
                Schema::create('petty_cash_material_purchase_items', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('purchase_id')->constrained('petty_cash_material_purchases')->cascadeOnDelete();
                    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                    $table->decimal('quantity', 14, 3);
                    $table->string('unit', 30)->default('pcs');
                    $table->decimal('unit_price', 18, 2)->default(0);
                    $table->decimal('total_price', 18, 2)->default(0);
                    $table->string('remarks', 255)->nullable();
                    $table->timestamps();

                    $table->index(['purchase_id', 'product_id'], 'pcmp_items_purchase_prod_idx');
                });
            }

            if (Schema::hasTable('stores') && !Schema::hasColumn('stores', 'petty_cash_account_id')) {
                Schema::table('stores', function (Blueprint $table) {
                    $table->foreignId('petty_cash_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                });
            }
        } catch (\Throwable $e) {
            // Silently continue if schema cannot be created here
        }
    }

    /**
     * Resolve the assigned store for a user
     */
    protected function resolveAssignedStore(?User $user): ?Store
    {
        if (!$user) {
            return null;
        }

        if ($user->store) {
            return $user->store;
        }

        if ($user->store_id) {
            $s = Store::find($user->store_id);
            if ($s) return $s;
        }

        $managed = Store::where('manager_id', $user->id)->first();
        if ($managed) {
            return $managed;
        }

        $linked = Store::whereHas('users', fn($q) => $q->where('users.id', $user->id))->first();
        if ($linked) {
            return $linked;
        }

        return null;
    }

    /**
     * Resolve or automatically create a dedicated separate Site Petty Cash account for the Store.
     * Strictly avoids linking to corporate '1010' Petty Cash account.
     */
    public function resolveStoreSitePettyCash(Store $store, ?User $user = null): ChartOfAccount
    {
        // 1. Direct link on store
        if (Schema::hasColumn('stores', 'petty_cash_account_id') && !empty($store->petty_cash_account_id)) {
            $existing = ChartOfAccount::find($store->petty_cash_account_id);
            if ($existing && $existing->code !== '1010') {
                return $existing;
            }
        }

        // 2. Find by store pattern: [STORE:{id}] or code 1010-{id} or 'Site Petty Cash - {store->name}'
        $patternAccount = ChartOfAccount::where('type', 'asset')
            ->where('code', '!=', '1010')
            ->where(function ($q) use ($store) {
                $q->where('code', '1010-' . str_pad($store->id, 3, '0', STR_PAD_LEFT))
                  ->orWhere('code', 'PC-' . $store->id)
                  ->orWhere('name', 'Site Petty Cash - ' . $store->name)
                  ->orWhere('description', 'like', '%[STORE:' . $store->id . ']%');
            })
            ->first();

        if ($patternAccount) {
            if (Schema::hasColumn('stores', 'petty_cash_account_id') && $store->petty_cash_account_id !== $patternAccount->id) {
                try {
                    $store->update(['petty_cash_account_id' => $patternAccount->id]);
                } catch (\Throwable $e) {}
            }
            return $patternAccount;
        }

        // 3. If user has an assigned custodian account that is NOT 1010
        if ($user) {
            $userAssigned = ChartOfAccount::where('assigned_to', $user->id)
                ->where('code', '!=', '1010')
                ->first();
            if ($userAssigned) {
                if (Schema::hasColumn('stores', 'petty_cash_account_id')) {
                    try {
                        $store->update(['petty_cash_account_id' => $userAssigned->id]);
                    } catch (\Throwable $e) {}
                }
                return $userAssigned;
            }
        }

        // 4. Automatically create dedicated Site Petty Cash account for this store
        $baseCode = '1010-' . str_pad($store->id, 3, '0', STR_PAD_LEFT);
        $code = $baseCode;
        $counter = 1;
        while (ChartOfAccount::where('code', $code)->exists()) {
            $code = $baseCode . '-' . $counter;
            $counter++;
        }

        $siteAccount = ChartOfAccount::create([
            'code'            => $code,
            'name'            => 'Site Petty Cash - ' . $store->name,
            'type'            => 'asset',
            'subtype'         => 'cash',
            'is_active'       => true,
            'is_system'       => false,
            'opening_balance' => 0.00,
            'current_balance' => 0.00,
            'description'     => "Dedicated Site Petty Cash fund for Store: {$store->name} ({$store->code}) [STORE:{$store->id}]",
            'assigned_to'     => $store->manager_id ?? $user?->id ?? Auth::id(),
            'sort_order'      => 10,
        ]);

        if (Schema::hasColumn('stores', 'petty_cash_account_id')) {
            try {
                $store->update(['petty_cash_account_id' => $siteAccount->id]);
            } catch (\Throwable $e) {}
        }

        return $siteAccount;
    }

    /**
     * List Petty Cash Material Purchases
     */
    public function index(Request $request)
    {
        self::ensureSchema();

        /** @var User $user */
        $user = Auth::user();
        $assignedStore = $this->resolveAssignedStore($user);
        $isStoreKeeper = $user && $user->hasRole('store_keeper') && !$user->hasAnyRole(['admin', 'global_admin', 'store_manager', 'finance_head', 'finance']);

        $query = PettyCashMaterialPurchase::with(['store', 'purchaser', 'chartOfAccount', 'items.product', 'deliveryReceipt'])
            ->latest('purchase_date')
            ->latest('id');

        // Scoping for Store Keeper
        if ($isStoreKeeper) {
            if ($assignedStore) {
                $query->where(function ($q) use ($user, $assignedStore) {
                    $q->where('store_id', $assignedStore->id)
                      ->orWhere('purchased_by', $user->id);
                });
            } else {
                $query->where('purchased_by', $user->id);
            }
        } elseif ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('purchase_no', 'like', "%{$s}%")
                  ->orWhere('supplier_name', 'like', "%{$s}%")
                  ->orWhere('receipt_no', 'like', "%{$s}%");
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('purchase_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('purchase_date', '<=', $request->to_date);
        }

        $purchases = $query->paginate(15)->withQueryString();

        // Calculate summary statistics
        $statsQuery = PettyCashMaterialPurchase::query();
        if ($isStoreKeeper) {
            if ($assignedStore) {
                $statsQuery->where(function ($q) use ($user, $assignedStore) {
                    $q->where('store_id', $assignedStore->id)
                      ->orWhere('purchased_by', $user->id);
                });
            } else {
                $statsQuery->where('purchased_by', $user->id);
            }
        }
        $totalSpent = (float) $statsQuery->sum('total_amount');
        $totalPurchases = $statsQuery->count();

        $stores = Store::where('is_active', true)->orderBy('name')->get();

        $activeStore = $assignedStore;
        if (!$activeStore && $request->filled('store_id')) {
            $activeStore = Store::find($request->store_id);
        }
        if (!$activeStore) {
            $activeStore = $stores->first();
        }

        $pettyCashAccount = $activeStore ? $this->resolveStoreSitePettyCash($activeStore, $user) : null;

        return view('store-keeper.petty-cash-purchases.index', compact(
            'purchases',
            'assignedStore',
            'isStoreKeeper',
            'stores',
            'totalSpent',
            'totalPurchases',
            'pettyCashAccount'
        ));
    }

    /**
     * Create Petty Cash Material Purchase Form
     */
    public function create()
    {
        self::ensureSchema();

        /** @var User $user */
        $user = Auth::user();
        $assignedStore = $this->resolveAssignedStore($user);
        $isStoreKeeper = $user && $user->hasRole('store_keeper') && !$user->hasAnyRole(['admin', 'global_admin', 'store_manager']);

        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        $targetStore = $assignedStore ?? $stores->first();
        $pettyCashAccount = $targetStore ? $this->resolveStoreSitePettyCash($targetStore, $user) : null;

        // Map every store to its dedicated separate Site Petty Cash account
        $storesData = $stores->mapWithKeys(function ($st) use ($user) {
            $acc = $this->resolveStoreSitePettyCash($st, $user);
            return [$st->id => [
                'store_id'     => $st->id,
                'store_name'   => $st->name,
                'store_code'   => $st->code,
                'account_id'   => $acc->id,
                'account_code' => $acc->code,
                'account_name' => $acc->name,
                'balance'      => (float) $acc->current_balance,
            ]];
        });

        return view('store-keeper.petty-cash-purchases.create', compact(
            'assignedStore',
            'isStoreKeeper',
            'stores',
            'products',
            'pettyCashAccount',
            'storesData'
        ));
    }

    /**
     * Store Petty Cash Material Purchase
     * Increments inventory on hand, creates Receive Slip (GRN), and records Petty Cash Journal Entry
     */
    public function store(Request $request)
    {
        self::ensureSchema();

        /** @var User $user */
        $user = Auth::user();
        $assignedStore = $this->resolveAssignedStore($user);
        $isStoreKeeper = $user && $user->hasRole('store_keeper') && !$user->hasAnyRole(['admin', 'global_admin', 'store_manager']);

        // Force store_id for store keeper to assigned store if present
        if ($isStoreKeeper && $assignedStore) {
            $request->merge(['store_id' => $assignedStore->id]);
        }

        $validated = $request->validate([
            'store_id'            => 'required|exists:stores,id',
            'chart_of_account_id' => 'nullable|exists:chart_of_accounts,id',
            'purchase_date'       => 'required|date',
            'supplier_name'       => 'required|string|max:255',
            'receipt_no'          => 'required|string|max:100',
            'notes'               => 'nullable|string|max:1000',
            'attachment'          => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.quantity'    => 'required|numeric|min:0.001',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.unit'        => 'nullable|string|max:30',
            'items.*.remarks'     => 'nullable|string|max:255',
        ]);

        $store = Store::findOrFail($validated['store_id']);

        // Determine dedicated separate Site Petty Cash account (NEVER 1010)
        $pettyCashAccount = $this->resolveStoreSitePettyCash($store, $user);
        $coaId = $pettyCashAccount->id;

        // Handle attachment upload
        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = FileUploadService::upload($request->file('attachment'), 'petty_cash_purchases');
        }

        $purchase = DB::transaction(function () use ($validated, $store, $pettyCashAccount, $attachmentPath, $user) {
            $today = date('Ymd');
            $countToday = PettyCashMaterialPurchase::whereDate('created_at', now())->count() + 1;
            $purchaseNo = 'PCMP-' . $today . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

            // Compute total amount
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += (float)$item['quantity'] * (float)$item['unit_price'];
            }

            // 1. Create Purchase Record
            $purchase = PettyCashMaterialPurchase::create([
                'purchase_no'         => $purchaseNo,
                'store_id'            => $store->id,
                'chart_of_account_id' => $pettyCashAccount?->id,
                'purchased_by'        => $user->id,
                'purchase_date'       => $validated['purchase_date'],
                'supplier_name'       => $validated['supplier_name'],
                'receipt_no'          => $validated['receipt_no'],
                'total_amount'        => $totalAmount,
                'notes'               => $validated['notes'] ?? null,
                'attachment_path'     => $attachmentPath,
                'status'              => 'completed',
            ]);

            // 2. Create Dummy PO for DeliveryReceipt (required by schema)
            $refNumber = 'PETTY-CASH-' . time();
            $dummyPo = PurchaseOrder::firstOrCreate(
                ['supplier_name' => $validated['supplier_name']],
                [
                    'reference_number' => $refNumber,
                    'status'           => 'delivered',
                    'total_amount'     => $totalAmount,
                    'issued_date'      => $validated['purchase_date'],
                    'created_by'       => $user->id,
                ]
            );

            // 3. Resolve Slip Sequence for Receive Slip
            $slipSeq = SlipSequence::where('store_id', $store->id)
                ->where('slip_type', 'receive')
                ->where('status', 'active')
                ->first();

            $drNo = $slipSeq ? $slipSeq->generateSlipNumber() : ('GRN-PC-' . $today . '-' . str_pad($purchase->id, 4, '0', STR_PAD_LEFT));

            // 4. Create Receive Slip (DeliveryReceipt)
            $deliveryReceipt = DeliveryReceipt::create([
                'dr_no'             => $drNo,
                'slip_type'         => 'receive',
                'store_id'          => $store->id,
                'received_date'     => $validated['purchase_date'],
                'receipt_date'      => $validated['purchase_date'],
                'supplier_name'     => $validated['supplier_name'],
                'reference_no'      => $validated['receipt_no'],
                'purchase_order_id' => $dummyPo->id,
                'received_by'       => $user->id,
                'created_by'        => $user->id,
                'status'            => 'received',
                'is_void'           => false,
                'sequence_status'   => 'valid',
                'notes'             => "Petty Cash Purchase {$purchaseNo} - {$validated['supplier_name']}",
            ]);

            // 5. Process Items, Increment Inventory, and Create Movement
            foreach ($validated['items'] as $itemData) {
                $qty = (float) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];
                $lineTotal = round($qty * $unitPrice, 2);

                $product = Product::find($itemData['product_id']);
                $unit = $itemData['unit'] ?? ($product?->unit_of_measure ?? $product?->unit ?? 'pcs');

                // Record purchase item
                $purchase->items()->create([
                    'product_id'  => $itemData['product_id'],
                    'quantity'    => $qty,
                    'unit'        => $unit,
                    'unit_price'  => $unitPrice,
                    'total_price' => $lineTotal,
                    'remarks'     => $itemData['remarks'] ?? null,
                ]);

                // Create DeliveryReceiptItem
                $deliveryReceipt->items()->create([
                    'product_id'        => $itemData['product_id'],
                    'quantity_received' => $qty,
                    'accepted_quantity' => $qty,
                    'rejected_quantity' => 0,
                    'unit'              => $unit,
                ]);

                // Update / Increment Store Inventory
                $inventory = Inventory::firstOrCreate(
                    [
                        'store_id'   => $store->id,
                        'product_id' => $itemData['product_id'],
                    ],
                    [
                        'quantity_on_hand'  => 0,
                        'quantity_reserved' => 0,
                        'unit_cost'         => $unitPrice,
                        'min_stock'         => 0,
                    ]
                );

                $inventory->increment('quantity_on_hand', $qty);
                $inventory->update([
                    'last_movement_at' => now(),
                    'unit_cost'        => $unitPrice > 0 ? $unitPrice : $inventory->unit_cost,
                ]);

                // Log Inventory Movement
                InventoryMovement::create([
                    'inventory_id'   => $inventory->id,
                    'type'           => 'purchase',
                    'quantity'       => $qty,
                    'reference_type' => PettyCashMaterialPurchase::class,
                    'reference_id'   => $purchase->id,
                    'performed_by'   => $user->id,
                    'remarks'        => "Petty Cash Buy ({$purchaseNo}): +{$qty} {$unit} from {$validated['supplier_name']} [Rcpt: {$validated['receipt_no']}]",
                ]);
            }

            $purchase->delivery_receipt_id = $deliveryReceipt->id;

            // 6. Petty Cash Deduction & Journal Entry
            if ($pettyCashAccount && $totalAmount > 0) {
                // Decrement petty cash account balance
                $pettyCashAccount->decrement('current_balance', $totalAmount);

                // Find expense/inventory account (Cost of Material: 5100, or Supply Expense: 6901)
                $expenseAccount = ChartOfAccount::where('code', '5100')->first()
                    ?? ChartOfAccount::where('code', '6901')->first()
                    ?? ChartOfAccount::where('type', 'expense')->first();

                if ($expenseAccount) {
                    $entryCount = JournalEntry::count() + 1;
                    $entryNo = 'JE-' . date('Ymd') . '-' . str_pad($entryCount, 4, '0', STR_PAD_LEFT);

                    $journalEntry = JournalEntry::create([
                        'entry_no'       => $entryNo,
                        'entry_date'     => $validated['purchase_date'],
                        'reference_type' => 'petty_cash_material_purchase',
                        'reference_id'   => $purchase->id,
                        'description'    => "Petty Cash Material Purchase #{$purchaseNo} - {$validated['supplier_name']} (Rcpt: {$validated['receipt_no']})",
                        'status'         => 'posted',
                        'created_by'     => $user->id,
                        'posted_at'      => now(),
                    ]);

                    // Debit: Cost of Material / Inventory Expense
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id'       => $expenseAccount->id,
                        'description'      => "Material purchase: {$validated['supplier_name']} (#{$purchaseNo})",
                        'side'             => 'debit',
                        'amount'           => $totalAmount,
                    ]);

                    // Credit: Petty Cash
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id'       => $pettyCashAccount->id,
                        'description'      => "Paid from Petty Cash [{$pettyCashAccount->code}] {$pettyCashAccount->name}",
                        'side'             => 'credit',
                        'amount'           => $totalAmount,
                    ]);

                    $purchase->journal_entry_id = $journalEntry->id;
                }
            }

            $purchase->save();

            return $purchase;
        });

        return redirect()->route('store-keeper.petty-cash-purchases.show', $purchase)
            ->with('success', "Material purchase #{$purchase->purchase_no} recorded successfully! Stock has been added to {$store->name} inventory, GRN slip generated, and Petty Cash ledger updated.");
    }

    /**
     * Show Purchase Details & Printable Voucher
     */
    public function show(PettyCashMaterialPurchase $purchase)
    {
        self::ensureSchema();

        $purchase->load(['store', 'purchaser', 'chartOfAccount', 'items.product', 'deliveryReceipt', 'journalEntry.lines.account']);

        return view('store-keeper.petty-cash-purchases.show', compact('purchase'));
    }
}
