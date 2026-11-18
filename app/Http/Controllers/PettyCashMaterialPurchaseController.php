<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\DeliveryReceipt;
use App\Models\DeliveryReceiptItem;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PettyCashMaterialPurchase;
use App\Models\PettyCashMaterialPurchaseItem;
use App\Models\PettyCashReplenishment;
use App\Models\PettyCashReplenishmentItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SlipSequence;
use App\Models\Store;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\SmsEthiopiaService;
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

            if (Schema::hasTable('petty_cash_replenishments')) {
                if (!Schema::hasColumn('petty_cash_replenishments', 'store_id')) {
                    Schema::table('petty_cash_replenishments', function (Blueprint $table) {
                        $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
                    });
                }
                if (!Schema::hasColumn('petty_cash_replenishments', 'payment_method')) {
                    Schema::table('petty_cash_replenishments', function (Blueprint $table) {
                        $table->string('payment_method', 100)->nullable();
                    });
                }
                if (!Schema::hasColumn('petty_cash_replenishments', 'recipient_phone')) {
                    Schema::table('petty_cash_replenishments', function (Blueprint $table) {
                        $table->string('recipient_phone', 50)->nullable();
                    });
                }
                if (!Schema::hasColumn('petty_cash_replenishments', 'recipient_name')) {
                    Schema::table('petty_cash_replenishments', function (Blueprint $table) {
                        $table->string('recipient_name', 150)->nullable();
                    });
                }
            }

            if (Schema::hasTable('petty_cash_material_purchases')) {
                if (!Schema::hasColumn('petty_cash_material_purchases', 'supplier_tin')) {
                    Schema::table('petty_cash_material_purchases', function (Blueprint $table) {
                        $table->string('supplier_tin', 20)->nullable()->after('supplier_name');
                    });
                }
                if (!Schema::hasColumn('petty_cash_material_purchases', 'fs_no')) {
                    Schema::table('petty_cash_material_purchases', function (Blueprint $table) {
                        $table->string('fs_no', 20)->nullable()->after('supplier_tin');
                    });
                }
                if (!Schema::hasColumn('petty_cash_material_purchases', 'material_request_id')) {
                    Schema::table('petty_cash_material_purchases', function (Blueprint $table) {
                        $table->foreignId('material_request_id')->nullable()->after('delivery_receipt_id')->constrained('material_requests')->nullOnDelete();
                    });
                }
                if (!Schema::hasColumn('petty_cash_material_purchases', 'material_request_item_id')) {
                    Schema::table('petty_cash_material_purchases', function (Blueprint $table) {
                        $table->foreignId('material_request_item_id')->nullable()->after('material_request_id')->constrained('material_request_items')->nullOnDelete();
                    });
                }
                if (!Schema::hasColumn('petty_cash_material_purchases', 'decided_by')) {
                    Schema::table('petty_cash_material_purchases', function (Blueprint $table) {
                        $table->foreignId('decided_by')->nullable()->after('purchased_by')->constrained('users')->nullOnDelete();
                    });
                }
                if (!Schema::hasColumn('petty_cash_material_purchases', 'decided_at')) {
                    Schema::table('petty_cash_material_purchases', function (Blueprint $table) {
                        $table->timestamp('decided_at')->nullable()->after('decided_by');
                    });
                }
                if (!Schema::hasColumn('petty_cash_material_purchases', 'is_physical_store_only')) {
                    Schema::table('petty_cash_material_purchases', function (Blueprint $table) {
                        $table->boolean('is_physical_store_only')->default(false)->after('attachment_path');
                    });
                }
            }

            if (Schema::hasTable('material_request_items')) {
                if (!Schema::hasColumn('material_request_items', 'status')) {
                    Schema::table('material_request_items', function (Blueprint $table) {
                        $table->string('status', 30)->default('pending')->after('quantity_fulfilled')->index();
                    });
                }
                if (!Schema::hasColumn('material_request_items', 'rejection_reason')) {
                    Schema::table('material_request_items', function (Blueprint $table) {
                        $table->text('rejection_reason')->nullable()->after('notes');
                    });
                }
            }

            if (Schema::hasTable('material_requests') && !Schema::hasColumn('material_requests', 'purchase_method')) {
                Schema::table('material_requests', function (Blueprint $table) {
                    $table->string('purchase_method', 50)->nullable()->after('status');
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
        return $store->autoCreateSitePettyCash($user);
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

        $replenishedPurchaseNos = PettyCashReplenishmentItem::pluck('reference')->filter()->toArray();
        $unreplenishedPurchases = $activeStore && $pettyCashAccount ? PettyCashMaterialPurchase::where('store_id', $activeStore->id)
            ->where('chart_of_account_id', $pettyCashAccount->id)
            ->whereNotIn('purchase_no', $replenishedPurchaseNos)
            ->latest('purchase_date')
            ->get() : collect();
        $unreplenishedTotal = (float) $unreplenishedPurchases->sum('total_amount');

        $pendingReplenishment = $pettyCashAccount ? PettyCashReplenishment::where('chart_of_account_id', $pettyCashAccount->id)
            ->whereIn('status', [PettyCashReplenishment::STATUS_PENDING, PettyCashReplenishment::STATUS_UNDER_AUDIT])
            ->latest()
            ->first() : null;

        // Active Bank & Cash accounts for replacement money disbursement
        $sourceAccounts = ChartOfAccount::where('is_active', true)
            ->where('code', '!=', '1010')
            ->where(function ($q) {
                $q->where('type', 'asset')
                  ->where(function ($sub) {
                      $sub->whereIn('subtype', ['cash_and_bank', 'bank', 'cash', 'current_asset'])
                          ->orWhere('code', 'like', '1000%')
                          ->orWhere('code', 'like', '1020%')
                          ->orWhere('name', 'like', '%bank%')
                          ->orWhere('name', 'like', '%cash%');
                  });
            })
            ->orderBy('name')
            ->get();

        $storeKeepers = User::where('is_active', true)->orderBy('name')->get();

        // Replenishment & Replacement money history
        $replenishmentsQuery = PettyCashReplenishment::with(['chartOfAccount', 'sourceCoa', 'requester', 'financeHead', 'store'])
            ->latest('fulfilled_at')
            ->latest('id');

        if ($isStoreKeeper && $assignedStore) {
            $replenishmentsQuery->where(function ($q) use ($assignedStore, $user, $pettyCashAccount) {
                $q->where('store_id', $assignedStore->id)
                  ->orWhere('requested_by', $user->id);
                if ($pettyCashAccount) {
                    $q->orWhere('chart_of_account_id', $pettyCashAccount->id);
                }
            });
        }
        $replenishments = $replenishmentsQuery->paginate(15, ['*'], 'rep_page')->withQueryString();

        // ── Planning-Approved Petty Cash Material Lines Awaiting Storekeeper Purchase ──
        $pendingPettyCashItemsQuery = \App\Models\MaterialRequestItem::with([
            'materialRequest.project',
            'materialRequest.destinationStore',
            'materialRequest.planningApprover',
            'materialRequest.creator',
            'materialRequest.requestedBy',
            'product'
        ])
        ->whereHas('materialRequest', function ($q) use ($isStoreKeeper, $assignedStore, $request) {
            $q->whereNotIn('status', ['rejected', 'cancelled', 'draft', 'pending_approval']);
            if ($isStoreKeeper && $assignedStore) {
                $q->where(function ($sq) use ($assignedStore) {
                    $sq->where('destination_store_id', $assignedStore->id)
                       ->orWhere('project_id', $assignedStore->project_id);
                });
            } elseif ($request->filled('store_id')) {
                $st = Store::find($request->store_id);
                $q->where(function ($sq) use ($request, $st) {
                    $sq->where('destination_store_id', $request->store_id)
                       ->when($st && $st->project_id, fn($ssq) => $ssq->orWhere('project_id', $st->project_id));
                });
            }
        })
        ->where(function ($q) {
            $q->where('status', 'petty_cash')
              ->orWhere(function ($sub) {
                  $sub->whereNotIn('status', ['approved', 'rejected', 'purchased'])
                      ->whereHas('materialRequest', function ($mq) {
                          $mq->where(function ($inner) {
                              $inner->where('status', 'approved_for_petty_cash')
                                    ->orWhere('purchase_method', 'petty_cash');
                          })->where(function ($inner2) {
                              $inner2->where('planning_approval_status', 'approved')
                                     ->orWhereNotNull('planning_approved_by');
                          });
                      });
              });
        })
        ->where('status', '!=', 'purchased')
        ->where('status', '!=', 'rejected')
        ->latest('id');

        $pendingPettyCashItems = $pendingPettyCashItemsQuery->get();

        // Emergency Material Requests approved for Site Petty Cash purchase
        $pendingEmergencyMrsQuery = \App\Models\MaterialRequest::with(['project', 'store', 'creator', 'requestedBy', 'items.product', 'planningApprover'])
            ->where(function($q) {
                $q->where('status', 'approved_for_petty_cash')
                  ->orWhere(fn($sq) => $sq->where('purchase_method', 'petty_cash')->whereNotIn('status', ['completed', 'fulfilled', 'rejected', 'cancelled']));
            })
            ->latest('planning_approved_at')
            ->latest('id');

        if ($isStoreKeeper && $assignedStore) {
            $pendingEmergencyMrsQuery->where(function ($q) use ($assignedStore) {
                $q->where('destination_store_id', $assignedStore->id)
                  ->orWhere('project_id', $assignedStore->project_id);
            });
        } elseif ($request->filled('store_id')) {
            $pendingEmergencyMrsQuery->where(function ($q) use ($request) {
                $st = Store::find($request->store_id);
                $q->where('destination_store_id', $request->store_id)
                  ->when($st && $st->project_id, fn($sq) => $sq->orWhere('project_id', $st->project_id));
            });
        }
        $pendingEmergencyMrs = $pendingEmergencyMrsQuery->get();

        return view('store-keeper.petty-cash-purchases.index', compact(
            'purchases',
            'assignedStore',
            'isStoreKeeper',
            'stores',
            'totalSpent',
            'totalPurchases',
            'pettyCashAccount',
            'sourceAccounts',
            'storeKeepers',
            'replenishments',
            'pendingEmergencyMrs',
            'pendingPettyCashItems',
            'unreplenishedPurchases',
            'unreplenishedTotal',
            'pendingReplenishment'
        ));
    }

    /**
     * Complete Petty Cash Material Purchase Form
     * Strictly requires a Planning Manager approved material line.
     * Manual creation without a Planning Manager decision is prohibited.
     */
    public function create(Request $request)
    {
        self::ensureSchema();

        /** @var User $user */
        $user = Auth::user();
        $assignedStore = $this->resolveAssignedStore($user);
        $isStoreKeeper = $user && $user->hasRole('store_keeper') && !$user->hasAnyRole(['admin', 'global_admin', 'store_manager']);

        // Check for material request item id
        $itemId = $request->input('material_request_item_id') ?: $request->input('item_id');

        // If parent emergency MR was provided without item, select its first active petty cash line
        if (!$itemId && $request->filled('material_request_id')) {
            $parentMr = \App\Models\MaterialRequest::with('items')->find($request->material_request_id);
            if ($parentMr) {
                $firstItem = $parentMr->items()->whereNotIn('status', ['purchased', 'rejected'])->first();
                if ($firstItem) {
                    $itemId = $firstItem->id;
                }
            }
        }

        // Rule: Direct manual creation without a linked Planning decision is prohibited
        if (!$itemId) {
            return redirect()->route('store-keeper.petty-cash-purchases.index')
                ->with('info', 'Direct manual creation of petty cash purchases is disabled. Please select an approved material line from the queue below to complete purchase.');
        }

        $mrItem = \App\Models\MaterialRequestItem::with([
            'materialRequest.project',
            'materialRequest.destinationStore',
            'materialRequest.planningApprover',
            'materialRequest.creator',
            'materialRequest.requestedBy',
            'product'
        ])->findOrFail($itemId);

        $mr = $mrItem->materialRequest;
        if (!$mr) {
            abort(403, 'Unauthorized: Invalid Material Request.');
        }

        // Verify Planning Manager / Coordinator decision
        $isPlanningApproved = ($mr->planning_approval_status === 'approved' || !empty($mr->planning_approved_by));
        $isPettyCashItem = ($mrItem->status === 'petty_cash') || 
            ((in_array($mr->status, ['approved_for_petty_cash']) || $mr->purchase_method === 'petty_cash') && !in_array($mrItem->status, ['approved', 'rejected']));

        if (!$isPlanningApproved || !$isPettyCashItem || in_array($mrItem->status, ['rejected'])) {
            abort(403, 'Unauthorized: This material item has not been approved for Petty Cash purchase by the Planning Manager.');
        }

        // Verify not already purchased
        if ($mrItem->status === 'purchased') {
            return redirect()->route('store-keeper.petty-cash-purchases.index')
                ->with('warning', "Material '{$mrItem->product?->name}' from Request #{$mr->reference_number} has already been purchased.");
        }

        // Storekeeper site authorization check: only own site allowed
        if ($isStoreKeeper && $assignedStore) {
            $matchesStore = ($mr->destination_store_id && $mr->destination_store_id == $assignedStore->id);
            $matchesProject = ($mr->project_id && $assignedStore->project_id && $mr->project_id == $assignedStore->project_id);
            if (!$matchesStore && !$matchesProject) {
                abort(403, 'Unauthorized: You can only complete purchases for materials assigned to your site/store.');
            }
        }

        $targetStore = $assignedStore ?? $mr->destinationStore ?? ($mr->project_id ? Store::where('project_id', $mr->project_id)->first() : null) ?? Store::first();
        $pettyCashAccount = $targetStore ? $this->resolveStoreSitePettyCash($targetStore, $user) : null;

        $slipService = app(\App\Services\SlipSequenceService::class);
        $nextReceivingSlip = $targetStore ? $slipService->getNextExpected((int)$targetStore->id, 'receive') : null;

        $replenishedPurchaseNos = PettyCashReplenishmentItem::pluck('reference')->filter()->toArray();
        $unreplenishedPurchases = $targetStore && $pettyCashAccount ? PettyCashMaterialPurchase::where('store_id', $targetStore->id)
            ->where('chart_of_account_id', $pettyCashAccount->id)
            ->whereNotIn('purchase_no', $replenishedPurchaseNos)
            ->latest('purchase_date')
            ->get() : collect();
        $unreplenishedTotal = (float) $unreplenishedPurchases->sum('total_amount');

        $pendingReplenishment = $pettyCashAccount ? PettyCashReplenishment::where('chart_of_account_id', $pettyCashAccount->id)
            ->whereIn('status', [PettyCashReplenishment::STATUS_PENDING, PettyCashReplenishment::STATUS_UNDER_AUDIT])
            ->latest()
            ->first() : null;

        $stores = Store::where('is_active', true)->orderBy('name')->get();
        $sourceAccounts = ChartOfAccount::where('is_active', true)
            ->where('code', '!=', '1010')
            ->where(function ($q) {
                $q->where('type', 'asset')
                  ->where(function ($sub) {
                      $sub->whereIn('subtype', ['cash_and_bank', 'bank', 'cash', 'current_asset'])
                          ->orWhere('code', 'like', '1000%')
                          ->orWhere('code', 'like', '1020%')
                          ->orWhere('name', 'like', '%bank%')
                          ->orWhere('name', 'like', '%cash%');
                  });
            })
            ->orderBy('name')
            ->get();
        $storeKeepers = User::where('is_active', true)->orderBy('name')->get();

        return view('store-keeper.petty-cash-purchases.create', compact(
            'mrItem',
            'mr',
            'assignedStore',
            'targetStore',
            'isStoreKeeper',
            'stores',
            'pettyCashAccount',
            'nextReceivingSlip',
            'unreplenishedPurchases',
            'unreplenishedTotal',
            'pendingReplenishment',
            'sourceAccounts',
            'storeKeepers'
        ));
    }

    /**
     * Store Petty Cash Material Purchase
     * Validates Planning decision link, checks site petty cash balance, verifies unique receipt #,
     * deducts petty cash, increments store stock on hand, generates Model 19 (GRN), marks item purchased,
     * and logs audit entry.
     */
    public function store(Request $request)
    {
        self::ensureSchema();

        /** @var User $user */
        $user = Auth::user();
        $assignedStore = $this->resolveAssignedStore($user);
        $isStoreKeeper = $user && $user->hasRole('store_keeper') && !$user->hasAnyRole(['admin', 'global_admin', 'store_manager']);

        // 1. Enforce linked Planning Manager decision (Reject direct unapproved calls with HTTP 403)
        $itemId = $request->input('material_request_item_id') ?: $request->input('item_id');
        if (!$itemId) {
            abort(403, 'Unauthorized: Petty cash purchases must be linked to an approved Planning Manager decision. Manual creation is prohibited.');
        }

        $mrItem = \App\Models\MaterialRequestItem::with([
            'materialRequest.project',
            'materialRequest.destinationStore',
            'materialRequest.planningApprover',
            'product'
        ])->findOrFail($itemId);

        $mr = $mrItem->materialRequest;
        if (!$mr) {
            abort(403, 'Unauthorized: Invalid Material Request.');
        }

        // Verify Planning Manager / Coordinator decision
        $isPlanningApproved = ($mr->planning_approval_status === 'approved' || !empty($mr->planning_approved_by));
        $isPettyCashItem = ($mrItem->status === 'petty_cash') || 
            ((in_array($mr->status, ['approved_for_petty_cash']) || $mr->purchase_method === 'petty_cash') && !in_array($mrItem->status, ['approved', 'rejected']));

        if (!$isPlanningApproved || !$isPettyCashItem || in_array($mrItem->status, ['rejected'])) {
            abort(403, 'Unauthorized: This material item has not been approved for Petty Cash purchase by the Planning Manager.');
        }

        // Verify not already purchased
        if ($mrItem->status === 'purchased') {
            return back()->withInput()->with('error', 'This material item has already been marked as purchased.');
        }

        // Storekeeper site authorization check: only own site allowed
        if ($isStoreKeeper && $assignedStore) {
            $matchesStore = ($mr->destination_store_id && $mr->destination_store_id == $assignedStore->id);
            $matchesProject = ($mr->project_id && $assignedStore->project_id && $mr->project_id == $assignedStore->project_id);
            if (!$matchesStore && !$matchesProject) {
                abort(403, 'Unauthorized: You can only complete petty cash purchases for your assigned site/store.');
            }
        }

        // 2. Validate input fields
        $validated = $request->validate([
            'material_request_item_id' => 'required|exists:material_request_items,id',
            'total_amount'             => 'required|numeric|min:0.01',
            'supplier_name'            => 'required|string|max:255',
            'supplier_tin'             => 'nullable|string|max:20',
            'fs_no'                    => 'nullable|string|max:20',
            'receipt_no'               => 'required|string|max:100|unique:petty_cash_material_purchases,receipt_no',
            'purchase_date'            => 'required|date',
            'attachment'               => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'slip_no'                  => 'nullable|string|max:100',
            'notes'                    => 'nullable|string|max:1000',
            'is_physical_store_only'   => 'nullable|boolean',
        ], [
            'receipt_no.unique'        => 'This Cash Receipt / FS number has already been recorded. Receipt number must be unique.',
            'receipt_no.required'      => 'Receipt / FS number is required.',
            'attachment.required'      => 'A photo or scan of the cash sales receipt is required.',
            'total_amount.required'    => 'The amount of money spent (ETB) is required.',
            'total_amount.min'         => 'The amount spent must be greater than zero.',
            'supplier_name.required'   => 'The supplier / vendor name is required.',
            'purchase_date.required'   => 'The purchase date is required.',
        ]);

        // 3. Determine store & verify site petty cash balance
        $store = $assignedStore ?? $mr->destinationStore ?? ($mr->project_id ? Store::where('project_id', $mr->project_id)->first() : null) ?? Store::firstOrFail();
        $pettyCashAccount = $this->resolveStoreSitePettyCash($store, $user);
        $availableBalance = (float)($pettyCashAccount->current_balance ?? 0);
        $totalAmount = (float)$validated['total_amount'];

        if ($totalAmount > $availableBalance) {
            return back()->withInput()->withErrors([
                'total_amount' => "Purchase amount (ETB " . number_format($totalAmount, 2) . ") exceeds the available Site Petty Cash balance of ETB " . number_format($availableBalance, 2) . ". Please request fund replenishment first."
            ])->with('error', "Purchase amount exceeds available Site Petty Cash balance (ETB " . number_format($availableBalance, 2) . ").");
        }

        // 4. Handle attachment upload
        $attachmentPath = FileUploadService::upload($request->file('attachment'), 'petty_cash_purchases');
        $isPhysicalStoreOnly = $request->boolean('is_physical_store_only');

        try {
            $purchase = DB::transaction(function () use ($validated, $store, $pettyCashAccount, $attachmentPath, $user, $request, $isPhysicalStoreOnly, $mrItem, $mr, $totalAmount) {
                $today = date('Ymd');
                $countToday = PettyCashMaterialPurchase::whereDate('created_at', now())->count() + 1;
                $purchaseNo = 'PCMP-' . $today . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

                $quantity = (float)$mrItem->quantity;
                $unitPrice = $quantity > 0 ? round($totalAmount / $quantity, 4) : $totalAmount;
                $product = $mrItem->product;
                $unit = $product?->unit_of_measure ?? $product?->unit ?? 'pcs';

                // 4a. Create PettyCashMaterialPurchase record
                $purchase = PettyCashMaterialPurchase::create([
                    'purchase_no'              => $purchaseNo,
                    'store_id'                 => $store->id,
                    'chart_of_account_id'      => $pettyCashAccount?->id,
                    'purchased_by'             => $user->id,
                    'purchase_date'            => $validated['purchase_date'],
                    'supplier_name'            => $validated['supplier_name'],
                    'supplier_tin'             => $validated['supplier_tin'] ?? null,
                    'fs_no'                    => $validated['fs_no'] ?? null,
                    'receipt_no'               => $validated['receipt_no'],
                    'total_amount'             => $totalAmount,
                    'notes'                    => $validated['notes'] ?? null,
                    'attachment_path'          => $attachmentPath,
                    'material_request_id'      => $mr->id,
                    'material_request_item_id' => $mrItem->id,
                    'decided_by'               => $mr->planning_approved_by,
                    'decided_at'               => $mr->planning_approved_at,
                    'is_physical_store_only'   => $isPhysicalStoreOnly,
                    'status'                   => 'completed',
                ]);

                // 4b. Create single PettyCashMaterialPurchaseItem matching MR line
                $purchase->items()->create([
                    'product_id'  => $mrItem->product_id,
                    'quantity'    => $quantity,
                    'unit'        => $unit,
                    'unit_price'  => $unitPrice,
                    'total_price' => $totalAmount,
                    'remarks'     => $validated['notes'] ?? null,
                ]);

                // 4c. Create Dummy PO for DeliveryReceipt (required by schema)
                $dummyPo = PurchaseOrder::firstOrCreate(
                    ['supplier_name' => $validated['supplier_name']],
                    [
                        'reference_number' => 'PETTY-CASH-' . time(),
                        'status'           => 'delivered',
                        'total_amount'     => $totalAmount,
                        'issued_date'      => $validated['purchase_date'],
                        'created_by'       => $user->id,
                    ]
                );

                // 4d. Consume next slip from store's receiving sequence (Model 19 GRN)
                $slipService = app(\App\Services\SlipSequenceService::class);
                $slipResult = $slipService->consumeNext(
                    storeId: $store->id,
                    slipType: 'receive',
                    inputSlipNo: $request->input('slip_no') ?: null,
                    sourceType: 'petty_cash',
                    metadata: ['petty_cash_material_purchase_id' => $purchase->id]
                );
                $drNo = $slipResult['slip_no'];

                // 4e. Create Receive Slip (DeliveryReceipt)
                $deliveryReceipt = DeliveryReceipt::create([
                    'dr_no'                  => $drNo,
                    'slip_no'                => $drNo,
                    'slip_type'              => 'receive',
                    'source_type'            => 'petty_cash',
                    'store_id'               => $store->id,
                    'received_date'          => $validated['purchase_date'],
                    'receipt_date'           => $validated['purchase_date'],
                    'supplier_name'          => $validated['supplier_name'],
                    'reference_no'           => $validated['receipt_no'],
                    'purchase_order_id'      => $dummyPo->id,
                    'received_by'            => $user->id,
                    'created_by'             => $user->id,
                    'status'                 => 'received',
                    'is_void'                => false,
                    'sequence_status'        => 'valid',
                    'notes'                  => "Petty Cash Purchase {$purchaseNo} - {$validated['supplier_name']} for MR #{$mr->reference_number}",
                    'slip_receipt_path'      => $attachmentPath,
                    'is_physical_store_only' => $isPhysicalStoreOnly,
                    'physical_slip_notes'    => $isPhysicalStoreOnly ? "Internal physical store slip (Excluded from VAT)" : null,
                ]);

                $deliveryReceipt->items()->create([
                    'product_id'        => $mrItem->product_id,
                    'quantity_received' => $quantity,
                    'accepted_quantity' => $quantity,
                    'rejected_quantity' => 0,
                    'unit'              => $unit,
                ]);

                $purchase->delivery_receipt_id = $deliveryReceipt->id;

                // 4f. Increment Store Stock on Hand & Log Inventory Movement
                $inventory = Inventory::firstOrCreate(
                    [
                        'store_id'   => $store->id,
                        'product_id' => $mrItem->product_id,
                    ],
                    [
                        'quantity_on_hand'  => 0,
                        'quantity_reserved' => 0,
                        'unit_cost'         => $unitPrice,
                        'min_stock'         => 0,
                    ]
                );

                $inventory->increment('quantity_on_hand', $quantity);
                $inventory->update([
                    'last_movement_at' => now(),
                    'unit_cost'        => $unitPrice > 0 ? $unitPrice : $inventory->unit_cost,
                ]);

                InventoryMovement::create([
                    'inventory_id'   => $inventory->id,
                    'type'           => 'purchase',
                    'quantity'       => $quantity,
                    'reference_type' => PettyCashMaterialPurchase::class,
                    'reference_id'   => $purchase->id,
                    'performed_by'   => $user->id,
                    'remarks'        => "Petty Cash Buy ({$purchaseNo}): +{$quantity} {$unit} {$product?->name} [Rcpt: {$validated['receipt_no']}]",
                ]);

                // 4g. Deduct Petty Cash Balance via Journal Entry
                if ($pettyCashAccount && $totalAmount > 0) {
                    $expenseAccount = ChartOfAccount::where('code', '5100')->first()
                        ?? ChartOfAccount::where('code', '6901')->first()
                        ?? ChartOfAccount::where('type', 'expense')->first();

                    if ($expenseAccount) {
                        $journalEngine = app(\App\Services\Finance\JournalEngine::class);
                        $lines = [
                            [
                                'account_id'  => $expenseAccount->id,
                                'description' => "Material purchase: {$product?->name} ({$purchaseNo}) from {$validated['supplier_name']}",
                                'side'        => 'debit',
                                'amount'      => $totalAmount,
                            ],
                            [
                                'account_id'  => $pettyCashAccount->id,
                                'description' => "Paid from Petty Cash [{$pettyCashAccount->code}] {$pettyCashAccount->name}",
                                'side'        => 'credit',
                                'amount'      => $totalAmount,
                            ],
                        ];

                        $journalEntry = $journalEngine->createEntry(
                            'petty_cash_material_purchase',
                            $purchase->id,
                            "Petty Cash Material Purchase #{$purchaseNo} - {$validated['supplier_name']} (Rcpt: {$validated['receipt_no']})",
                            $lines,
                            $user->id,
                            $user->id,
                            $validated['purchase_date']
                        );

                        $purchase->journal_entry_id = $journalEntry->id;
                    }
                }

                $purchase->save();

                // 4h. Mark request line as "Purchased"
                $mrItem->update([
                    'status'             => 'purchased',
                    'quantity_fulfilled' => $quantity,
                ]);

                // Check if all lines of the MR are now purchased/fulfilled
                $hasUnfinished = \App\Models\MaterialRequestItem::where('material_request_id', $mr->id)
                    ->whereNotIn('status', ['purchased', 'rejected'])
                    ->exists();

                if (!$hasUnfinished) {
                    $mr->update([
                        'status' => 'completed',
                        'notes'  => ($mr->notes ? $mr->notes . "\n" : '') . "All items fulfilled via Site Petty Cash (latest: Purchase #{$purchaseNo}) on " . date('Y-m-d') . " by {$user->name}.",
                    ]);

                    // Also complete companion PRs
                    $mr->loadMissing('purchaseRequests');
                    foreach ($mr->purchaseRequests as $pr) {
                        $pr->update(['status' => \App\Models\PurchaseRequest::STATUS_COMPLETED]);
                    }
                }

                // 4i. Audit Log: who decided, who purchased, when, and amount
                $decider = $mr->planningApprover ?? User::find($mr->planning_approved_by);
                $deciderName = $decider?->name ?? 'Planning Manager';
                $decidedAtFormatted = optional($mr->planning_approved_at)->format('Y-m-d H:i') ?? 'N/A';

                ActivityLog::log(
                    'petty_cash_material_purchased',
                    "Petty Cash Purchase #{$purchaseNo} of ETB " . number_format($totalAmount, 2) . " for Material Request #{$mr->reference_number} ({$product?->name}) completed by {$user->name}. Decision by {$deciderName} on {$decidedAtFormatted}.",
                    'petty_cash',
                    $purchase,
                    [
                        'purchase_no'              => $purchaseNo,
                        'material_request_id'      => $mr->id,
                        'material_request_ref'     => $mr->reference_number,
                        'material_request_item_id' => $mrItem->id,
                        'product_id'               => $mrItem->product_id,
                        'product_name'             => $product?->name,
                        'quantity'                 => $quantity,
                        'unit'                     => $unit,
                        'total_amount'             => $totalAmount,
                        'receipt_no'               => $validated['receipt_no'],
                        'supplier_name'            => $validated['supplier_name'],
                        'decided_by_id'            => $mr->planning_approved_by,
                        'decided_by_name'          => $deciderName,
                        'decided_at'               => $mr->planning_approved_at?->toIso8601String(),
                        'purchased_by_id'          => $user->id,
                        'purchased_by_name'        => $user->name,
                        'purchased_at'             => now()->toIso8601String(),
                    ]
                );

                // Auto-link to central Receipt table for fiscal and tax declarations (skip if physical store only)
                if (!$isPhysicalStoreOnly && ($attachmentPath || !empty($validated['fs_no']))) {
                    try {
                        $ocrService = app(\App\Services\ReceiptOcrService::class);
                        $ocrItems = [
                            [
                                'item_description' => ($product?->name ?? 'Material') . (!empty($validated['notes']) ? ' - ' . $validated['notes'] : ''),
                                'uom'              => '9',
                                'qty'              => $quantity,
                                'unit_price'       => $unitPrice,
                                'total_value'      => $totalAmount,
                                'vat'              => round($totalAmount * 0.15, 2),
                                'value_after_vat'  => round($totalAmount * 1.15, 2),
                            ]
                        ];

                        $scanData = [
                            'vendor_name'       => $validated['supplier_name'],
                            'supplier_tin'      => $validated['supplier_tin'] ?? '',
                            'tin_valid'         => !empty($validated['supplier_tin']) && strlen(trim($validated['supplier_tin'])) === 10,
                            'buyer_tin'         => '0038480010',
                            'fs_no'             => $validated['fs_no'] ?? $validated['receipt_no'],
                            'fs_no_raw'         => $validated['fs_no'] ?? $validated['receipt_no'],
                            'fs_no_valid'       => !empty($validated['fs_no']) && strlen(trim($validated['fs_no'])) === 8,
                            'receipt_date_ymd'  => $validated['purchase_date'],
                            'subtotal'          => $totalAmount,
                            'vat_amount'        => round($totalAmount * 0.15, 2),
                            'total_amount'      => round($totalAmount * 1.15, 2),
                            'currency'          => 'ETB',
                            'description'       => "Petty Cash Purchase: " . $purchaseNo,
                            'engine'            => 'Receipt OCR / Form',
                            'engine_slug'       => 'petty_cash',
                            'confidence'        => 'high',
                            'confidence_score'  => 95,
                            'qc_notes'          => "Site Petty Cash receipt #{$purchaseNo}",
                            'items'             => $ocrItems,
                        ];

                        $ocrService->saveReceipt(
                            $scanData,
                            $attachmentPath ?: 'receipts/manual_petty_cash.pdf',
                            $purchase,
                            $user->id,
                            $store->project_id ?? null,
                            'material'
                        );
                    } catch (\Throwable $ocrEx) {
                        \Illuminate\Support\Facades\Log::warning("Could not auto-create linked Receipt for Petty Cash Purchase #{$purchaseNo}: " . $ocrEx->getMessage());
                    }
                }

                return $purchase;
            });
        } catch (\App\Exceptions\SlipSequenceGapException $e) {
            return back()->withInput()->withErrors(['slip_no' => $e->getMessage()])
                ->with('error', $e->getMessage())
                ->with('slip_gap_info', [
                    'store_id'          => $e->getStoreId(),
                    'slip_type'         => $e->getSlipType(),
                    'expected_number'   => $e->getExpectedNumber(),
                    'given_number'      => $e->getGivenNumber(),
                    'missing_numbers'   => $e->getMissingNumbers(),
                    'missing_formatted' => $e->getMissingFormatted(),
                ]);
        } catch (\App\Exceptions\SlipSequenceException $e) {
            return back()->withInput()->withErrors(['slip_no' => $e->getMessage()])->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Failed to record purchase: ' . $e->getMessage());
        }

        return redirect()->route('store-keeper.petty-cash-purchases.show', $purchase)
            ->with('success', "Material '{$mrItem->product?->name}' purchased successfully! Stock added to {$store->name}, Model 19 GRN slip generated, and Site Petty Cash updated.");
    }

    /**
     * Show Purchase Details & Printable Voucher
     */
    public function show(PettyCashMaterialPurchase $purchase)
    {
        self::ensureSchema();

        $purchase->load([
            'store',
            'purchaser',
            'chartOfAccount',
            'items.product',
            'deliveryReceipt',
            'journalEntry.lines.account',
            'decider',
            'materialRequestItem.product',
            'materialRequest.project',
            'materialRequest.destinationStore',
            'materialRequest.planningApprover'
        ]);

        return view('store-keeper.petty-cash-purchases.show', compact('purchase'));
    }

    /**
     * Send Replacement Money to Store Keeper with all information
     * Direct disbursement from Bank/Cash into dedicated Site Petty Cash account
     */
    public function sendReplacementMoney(Request $request)
    {
        self::ensureSchema();

        /** @var User $authUser */
        $authUser = Auth::user();

        $validated = $request->validate([
            'store_id'         => 'required|exists:stores,id',
            'source_coa_id'    => 'required|exists:chart_of_accounts,id',
            'recipient_id'     => 'nullable|exists:users,id',
            'amount'           => 'required|numeric|min:1',
            'payment_method'   => 'required|string|max:100',
            'reference_no'     => 'required|string|max:100',
            'transfer_date'    => 'required|date',
            'notes'            => 'nullable|string|max:1000',
            'attachment'       => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'send_sms'         => 'nullable',
        ]);

        $store = Store::findOrFail($validated['store_id']);
        $recipient = null;
        if (!empty($validated['recipient_id'])) {
            $recipient = User::find($validated['recipient_id']);
        }
        if (!$recipient) {
            $recipient = $store->manager ?? $store->users()->first() ?? $authUser;
        }

        $sourceAccount = ChartOfAccount::findOrFail($validated['source_coa_id']);
        $siteAccount = $this->resolveStoreSitePettyCash($store, $recipient);

        $amount = (float) $validated['amount'];

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = FileUploadService::upload($request->file('attachment'), 'petty_cash_replenishments');
        }

        $replenishment = DB::transaction(function () use (
            $store,
            $recipient,
            $sourceAccount,
            $siteAccount,
            $amount,
            $validated,
            $attachmentPath,
            $authUser
        ) {
            // 1. Balances derive strictly from posted journal lines (no direct static balance mutation)

            // 2. Generate Request/Voucher Number
            $today = date('Ymd');
            $countToday = PettyCashReplenishment::whereDate('created_at', now())->count() + 1;
            $requestNo = 'REP-' . $today . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

            // 3. Create Journal Entry via JournalEngine
            $journalEngine = app(\App\Services\Finance\JournalEngine::class);
            $lines = [
                [
                    'account_id'  => $siteAccount->id,
                    'description' => "Petty cash replacement received for {$store->name} [{$siteAccount->code}]",
                    'side'        => 'debit',
                    'amount'      => $amount,
                ],
                [
                    'account_id'  => $sourceAccount->id,
                    'description' => "Disbursed to {$store->name} Store Keeper {$recipient->name} [Ref: {$validated['reference_no']}]",
                    'side'        => 'credit',
                    'amount'      => $amount,
                ],
            ];

            $journalEntry = $journalEngine->createEntry(
                'petty_cash_replacement',
                null,
                "Replacement money to Store Keeper {$recipient->name} for {$store->name} via {$validated['payment_method']} [Ref: {$validated['reference_no']}]",
                $lines,
                $authUser->id,
                $authUser->id,
                $validated['transfer_date']
            );

            // 4. Create Replenishment record
            $recipientPhone = $recipient->phone ?? $recipient->employee?->phone ?? $recipient->employee?->mobile_phone ?? null;

            $replenishment = PettyCashReplenishment::create([
                'request_no'                 => $requestNo,
                'chart_of_account_id'        => $siteAccount->id,
                'source_coa_id'              => $sourceAccount->id,
                'requested_by'               => $recipient->id,
                'finance_head_id'            => $authUser->id,
                'requested_amount'           => $amount,
                'fulfilled_amount'           => $amount,
                'current_balance_at_request' => $siteAccount->ledger_balance - $amount,
                'total_expenses_amount'      => 0,
                'period_start_date'          => now(),
                'period_end_date'            => now(),
                'status'                     => PettyCashReplenishment::STATUS_FULFILLED,
                'notes'                      => $validated['notes'] ?? 'Site petty cash replacement disbursement',
                'finance_notes'              => "Disbursed via {$validated['payment_method']} (Ref: {$validated['reference_no']})",
                'fulfillment_reference'      => $validated['reference_no'],
                'fulfilled_at'               => now(),
                'journal_entry_id'           => $journalEntry->id,
                'attachment_path'            => $attachmentPath,
                'store_id'                   => $store->id,
                'payment_method'             => $validated['payment_method'],
                'recipient_phone'            => $recipientPhone,
                'recipient_name'             => $recipient->name,
            ]);

            $journalEntry->update(['reference_id' => $replenishment->id]);

            // 5. Activity Log
            ActivityLog::log(
                'disbursed',
                "Disbursed ETB " . number_format($amount, 2) . " replacement petty cash to Store Keeper {$recipient->name} for store '{$store->name}' [Voucher #{$requestNo}].",
                'Petty Cash Replacement',
                $replenishment,
                [
                    'store'          => $store->name,
                    'amount'         => $amount,
                    'source_account' => "[{$sourceAccount->code}] {$sourceAccount->name}",
                    'site_account'   => "[{$siteAccount->code}] {$siteAccount->name}",
                    'voucher_no'     => $requestNo,
                    'reference'      => $validated['reference_no'],
                ]
            );

            // 6. Send SMS Notification to Store Keeper if requested
            if (!empty($validated['send_sms']) && $recipientPhone) {
                try {
                    $smsService = app(SmsEthiopiaService::class);
                    $formattedAmt = number_format($amount, 2);
                    $newBal = number_format($siteAccount->ledger_balance, 2);
                    $msg = "ConstructPro ERP: ETB {$formattedAmt} replacement petty cash has been credited for store {$store->name} via {$validated['payment_method']} (Ref: {$validated['reference_no']}). New available balance: ETB {$newBal}.";
                    $smsService->sendMessage($recipientPhone, $msg);
                } catch (\Throwable $e) {
                    \Log::warning("Could not send SMS to store keeper: " . $e->getMessage());
                }
            }

            return $replenishment;
        });

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'           => true,
                'message'           => "ETB " . number_format($amount, 2) . " replacement money successfully sent to Store Keeper {$recipient->name}!",
                'new_balance'       => (float) $siteAccount->ledger_balance,
                'formatted_balance' => 'ETB ' . number_format($siteAccount->ledger_balance, 2),
                'account_id'        => $siteAccount->id,
                'voucher_no'        => $replenishment->request_no,
                'voucher_url'       => route('store-keeper.petty-cash-purchases.replacement-voucher', $replenishment->id),
            ]);
        }

        return redirect()->back()->with('success', "ETB " . number_format($amount, 2) . " replacement money sent successfully to Store Keeper {$recipient->name}! Voucher: {$replenishment->request_no}. Available Petty Cash updated to ETB " . number_format($siteAccount->ledger_balance, 2));
    }

    /**
     * Store Keeper / User asks for Replacement Money directly routed to Internal Audit
     * Uses company standard petty cash replenishment & audit workflow
     */
    public function requestReplacementMoney(Request $request)
    {
        self::ensureSchema();

        /** @var User $authUser */
        $authUser = Auth::user();

        $validated = $request->validate([
            'store_id'         => 'required|exists:stores,id',
            'requested_amount' => 'required|numeric|min:1',
            'urgency'          => 'nullable|string|max:50',
            'notes'            => 'nullable|string|max:1000',
            'attachment'       => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);

        $store = Store::findOrFail($validated['store_id']);
        $siteAccount = $this->resolveStoreSitePettyCash($store, $authUser);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = FileUploadService::upload($request->file('attachment'), 'petty_cash_replenishments');
        }

        $today = date('Ymd');
        $countToday = PettyCashReplenishment::whereDate('created_at', now())->count() + 1;
        $requestNo = 'PCR-' . $today . '-' . str_pad($countToday, 4, '0', STR_PAD_LEFT);

        $recipientPhone = $authUser->phone ?? $authUser->employee?->phone ?? $authUser->employee?->mobile_phone ?? null;

        // Collect all unreplenished spot material purchases for this store & site petty cash fund
        $replenishedPurchaseNos = PettyCashReplenishmentItem::pluck('reference')->filter()->toArray();
        $unreplenishedPurchases = PettyCashMaterialPurchase::where('store_id', $store->id)
            ->where('chart_of_account_id', $siteAccount->id)
            ->whereNotIn('purchase_no', $replenishedPurchaseNos)
            ->orderBy('purchase_date', 'asc')
            ->get();

        $totalExpenses = (float) $unreplenishedPurchases->sum('total_amount');

        $replenishment = DB::transaction(function () use (
            $requestNo, $siteAccount, $authUser, $validated, $totalExpenses, 
            $unreplenishedPurchases, $attachmentPath, $store, $recipientPhone
        ) {
            // 1. Create standard PettyCashReplenishment with status STATUS_UNDER_AUDIT
            $replenishment = PettyCashReplenishment::create([
                'request_no'                 => $requestNo,
                'chart_of_account_id'        => $siteAccount->id,
                'requested_by'               => $authUser->id,
                'requested_amount'           => $validated['requested_amount'],
                'current_balance_at_request' => $siteAccount->ledger_balance,
                'total_expenses_amount'      => $totalExpenses > 0 ? $totalExpenses : $validated['requested_amount'],
                'period_start_date'          => $unreplenishedPurchases->min('purchase_date') ?? now(),
                'period_end_date'            => $unreplenishedPurchases->max('purchase_date') ?? now(),
                'status'                     => PettyCashReplenishment::STATUS_UNDER_AUDIT, // directly in Internal Audit queue
                'notes'                      => ($validated['urgency'] ? "[Urgency: {$validated['urgency']}] " : '') . ($validated['notes'] ?? 'Site Petty Cash replenishment for spot material purchases'),
                'attachment_path'            => $attachmentPath,
                'store_id'                   => $store->id,
                'recipient_name'             => $authUser->name,
                'recipient_phone'            => $recipientPhone,
                'reviewed_by'                => $authUser->id,
                'reviewed_at'                => now(),
            ]);

            // 2. Attach each unreplenished spot purchase voucher as a PettyCashReplenishmentItem
            foreach ($unreplenishedPurchases as $purchase) {
                PettyCashReplenishmentItem::create([
                    'petty_cash_replenishment_id' => $replenishment->id,
                    'entry_date'                  => $purchase->purchase_date,
                    'reference'                   => $purchase->purchase_no,
                    'description'                 => "Spot Material Purchase [Receipt #{$purchase->receipt_no}] - Supplier: {$purchase->supplier_name}",
                    'target_account_name'         => "Site Inventory / Materials",
                    'amount'                      => $purchase->total_amount,
                    'side'                        => 'debit',
                    'status'                      => PettyCashReplenishmentItem::STATUS_PENDING,
                ]);
            }

            // 3. Log into Audit Trail
            ActivityLog::log(
                'sent_to_audit',
                "Store Keeper {$authUser->name} submitted replenishment request #{$requestNo} (ETB " . number_format($validated['requested_amount'], 2) . ") with " . count($unreplenishedPurchases) . " spot purchase vouchers directly to Internal Audit for store '{$store->name}' [{$siteAccount->code}].",
                'Finance & Petty Cash Audit',
                $replenishment,
                [
                    'request_no'          => $requestNo,
                    'requested_amount'    => (float) $validated['requested_amount'],
                    'expenses_total'      => $totalExpenses,
                    'account'             => "[{$siteAccount->code}] {$siteAccount->name}",
                    'store'               => $store->name,
                    'vouchers_count'      => count($unreplenishedPurchases),
                ]
            );

            return $replenishment;
        });

        $auditUrl = route('finance.replenishments.index', ['tab' => 'under_audit']);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'        => true,
                'message'        => "Replacement request #{$requestNo} submitted directly to Internal Audit! Internal Audit will inspect purchase vouchers, clear approval, and Finance will disburse the replacement money.",
                'request_no'     => $requestNo,
                'amount'         => (float) $validated['requested_amount'],
                'vouchers_count' => count($unreplenishedPurchases),
                'audit_url'      => $auditUrl,
            ]);
        }

        return redirect()->back()->with('success', "Replacement request #{$requestNo} submitted directly to Internal Audit! Audit team will review vouchers and grant clearance.");
    }

    /**
     * Printable Official Replacement / Disbursement Voucher
     */
    public function showReplacementVoucher($id)
    {
        self::ensureSchema();

        $replenishment = PettyCashReplenishment::with([
            'chartOfAccount',
            'sourceCoa',
            'requester',
            'financeHead',
            'store',
            'journalEntry.lines.account'
        ])->findOrFail($id);

        return view('store-keeper.petty-cash-purchases.replacement-voucher', compact('replenishment'));
    }
}
