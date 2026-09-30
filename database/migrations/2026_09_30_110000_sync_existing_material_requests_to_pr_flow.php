<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Automatically sync all existing Material Requests into the complete Purchase Request Procurement Lifecycle.
     */
    public function up(): void
    {
        if (!Schema::hasTable('material_requests') || !Schema::hasTable('purchase_requests')) {
            return;
        }

        $unlinkedMrs = DB::table('material_requests')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('purchase_requests')
                    ->whereColumn('purchase_requests.material_request_id', 'material_requests.id');
            })
            ->get();

        if ($unlinkedMrs->isEmpty()) {
            return;
        }

        $now = now();
        $datePrefix = 'PR-' . date('Ymd') . '-';
        $seq = (int) DB::table('purchase_requests')->count() + 1;

        foreach ($unlinkedMrs as $mr) {
            $prNo = $datePrefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
            while (DB::table('purchase_requests')->where('pr_no', $prNo)->exists()) {
                $seq++;
                $prNo = $datePrefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
            }
            $seq++;

            // Determine initial status & owner based on MR status
            $initialStatus = 'pending_store_review';
            $initialOwner = 'store_manager';

            if (in_array($mr->status, ['pending_planning', 'submitted', 'pending']) && ($mr->planning_approval_status ?? '') !== 'approved') {
                $initialStatus = 'pending_planning_approval';
                $initialOwner = 'planning';
            } elseif ($mr->status === 'planning_approved') {
                $initialStatus = 'pending_hr_approval';
                $initialOwner = 'coordinator';
            } elseif (in_array($mr->status, ['needs_purchase', 'sent_to_pr'])) {
                $initialStatus = 'pending_marketing_review';
                $initialOwner = 'purchase_manager';
            }

            $creatorId = $mr->created_by ?: 1;

            $prId = DB::table('purchase_requests')->insertGetId([
                'pr_no'               => $prNo,
                'project_id'          => $mr->project_id,
                'store_id'            => $mr->destination_store_id,
                'requested_by'        => $creatorId,
                'material_request_id' => $mr->id,
                'priority'            => 'normal',
                'type'                => (($mr->source ?? '') === 'Emergency' ? 'emergency' : 'normal'),
                'is_office_request'   => false,
                'required_date'       => $mr->required_date,
                'justification'       => "Material Requisition #{$mr->reference_number}" . ($mr->notes ? ": {$mr->notes}" : ''),
                'status'              => $initialStatus,
                'current_owner_role'  => $initialOwner,
                'created_at'          => $mr->created_at ?: $now,
                'updated_at'          => $now,
            ]);

            // Copy items
            if (Schema::hasTable('material_request_items') && Schema::hasTable('purchase_request_items')) {
                $items = DB::table('material_request_items')
                    ->where('material_request_id', $mr->id)
                    ->get();

                foreach ($items as $item) {
                    $prod = DB::table('products')->where('id', $item->product_id)->first();
                    $unit = $prod->unit ?? 'pcs';
                    $estPrice = (float)($prod->unit_price ?? $prod->selling_price ?? 0);

                    if (Schema::hasTable('material_prices')) {
                        $latestMp = DB::table('material_prices')
                            ->where('product_id', $item->product_id)
                            ->orderBy('effective_date', 'desc')
                            ->first();
                        if ($latestMp) {
                            $estPrice = (float)$latestMp->price;
                        }
                    }

                    $qty = (float)($item->quantity_requested ?? $item->quantity ?? 1);

                    DB::table('purchase_request_items')->insert([
                        'purchase_request_id' => $prId,
                        'product_id'          => $item->product_id,
                        'quantity'            => $qty,
                        'unit'                => $unit,
                        'specifications'      => $item->notes ?? null,
                        'estimated_unit_cost' => $estPrice,
                        'created_at'          => $now,
                        'updated_at'          => $now,
                    ]);
                }
            }

            // Update MR status
            if (!in_array($mr->status, ['sent_to_pr', 'issued', 'processed'])) {
                DB::table('material_requests')
                    ->where('id', $mr->id)
                    ->update([
                        'status'     => 'sent_to_pr',
                        'updated_at' => $now,
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep PR records created from MRs intact for audit trail.
    }
};
