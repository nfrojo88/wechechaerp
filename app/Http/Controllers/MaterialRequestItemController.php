<?php

namespace App\Http\Controllers;

use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MaterialRequestItemController extends Controller
{
    public function store(Request $request, MaterialRequest $materialRequest)
    {
        $user = auth()->user();
        $refNo = $materialRequest->reference_number ?? ('MR #' . $materialRequest->id);

        // Strict Lock Rule: Only Global Admin can add new materials to an existing record
        if (!$user || !$user->isGlobalAdmin()) {
            $actorRole = $user?->roles?->first()?->name ?? 'user';
            $userName = $user?->name ?? 'Unknown User';

            try {
                \App\Models\ActivityLog::log(
                    'unauthorized_add_mr_material_blocked',
                    "Security violation: User '{$userName}' ({$actorRole}) attempted to add material to locked Material Request {$refNo} without Global Admin authorization.",
                    'Procurement',
                    $materialRequest,
                    ['user_id' => $user?->id, 'product_id' => $request->product_id]
                );
            } catch (\Throwable $e) {}

            return back()->with('error', 'Access Denied: Records are locked after creation. No new materials can be added to an existing entry. Only Global Admin is authorized to add materials.');
        }

        Gate::authorize('update', $materialRequest);

        $validated = $request->validate([
            'product_id'         => 'required|exists:products,id',
            'quantity_requested' => 'required|numeric|min:0.001',
            'notes'              => 'nullable|string|max:500',
        ]);

        $validated['material_request_id'] = $materialRequest->id;
        $validated['quantity_fulfilled']  = 0;

        MaterialRequestItem::create($validated);

        try {
            \App\Models\ActivityLog::log(
                'admin_added_mr_material',
                "Global Admin '{$user->name}' added material to Material Request {$refNo}.",
                'Procurement',
                $materialRequest,
                ['user_id' => $user->id, 'product_id' => $request->product_id]
            );
        } catch (\Throwable $e) {}

        return back()->with('success', 'Item added to request by Global Admin.');
    }

    public function destroy(MaterialRequestItem $item)
    {
        $user = auth()->user();
        $materialRequest = $item->materialRequest;
        $refNo = $materialRequest?->reference_number ?? ('MR #' . ($materialRequest?->id ?? ''));

        // Strict Lock Rule: Only Global Admin can remove materials from an existing record
        if (!$user || !$user->isGlobalAdmin()) {
            $actorRole = $user?->roles?->first()?->name ?? 'user';
            $userName = $user?->name ?? 'Unknown User';

            try {
                \App\Models\ActivityLog::log(
                    'unauthorized_remove_mr_material_blocked',
                    "Security violation: User '{$userName}' ({$actorRole}) attempted to delete item #{$item->id} from locked Material Request {$refNo} without Global Admin authorization.",
                    'Procurement',
                    $materialRequest,
                    ['user_id' => $user?->id, 'item_id' => $item->id]
                );
            } catch (\Throwable $e) {}

            return back()->with('error', 'Access Denied: Records are locked after creation. Existing materials cannot be deleted. Only Global Admin is authorized to remove materials.');
        }

        Gate::authorize('update', $item->materialRequest);

        $item->delete();

        try {
            \App\Models\ActivityLog::log(
                'admin_removed_mr_material',
                "Global Admin '{$user->name}' removed material from Material Request {$refNo}.",
                'Procurement',
                $materialRequest,
                ['user_id' => $user->id, 'item_id' => $item->id]
            );
        } catch (\Throwable $e) {}

        return back()->with('success', 'Item removed by Global Admin.');
    }
}
