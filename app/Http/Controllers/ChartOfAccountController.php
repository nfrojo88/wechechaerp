<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use Illuminate\Http\Request;

class ChartOfAccountController extends Controller
{
    public function index()
    {
        // Auto-purge account 1003 and any associated data if still present
        if (ChartOfAccount::where('code', '1003')->exists()) {
            ChartOfAccount::deleteAccountAndRelated('1003', true);
        }

        $accounts = ChartOfAccount::with(['parent', 'manager'])->orderBy('code')->paginate(50);
        return view('finance.coa.index', compact('accounts'));
    }

    public function create()
    {
        $parents = ChartOfAccount::whereNull('parent_id')->orderBy('code')->get();
        $users = \App\Models\User::orderBy('name')->get();
        return view('finance.coa.create', compact('parents', 'users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'            => 'required|string|max:20|unique:chart_of_accounts',
            'name'            => 'required|string|max:255',
            'parent_id'       => 'nullable|exists:chart_of_accounts,id',
            'type'            => 'required|in:asset,liability,equity,revenue,expense',
            'subtype'         => 'nullable|string|max:50',
            'opening_balance' => 'nullable|numeric',
            'description'     => 'nullable|string',
            'sort_order'      => 'nullable|integer',
            'assigned_to'     => 'nullable|exists:users,id',
        ]);

        ChartOfAccount::create($data);
        return redirect()->route('coa.index')->with('success', 'Account created.');
    }

    public function edit(ChartOfAccount $coa)
    {
        $parents = ChartOfAccount::whereNull('parent_id')->where('id', '!=', $coa->id)->orderBy('code')->get();
        $users = \App\Models\User::orderBy('name')->get();
        return view('finance.coa.edit', compact('coa', 'parents', 'users'));
    }

    public function update(Request $request, ChartOfAccount $coa)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:20|unique:chart_of_accounts,code,' . $coa->id,
            'name'        => 'required|string|max:255',
            'parent_id'   => 'nullable|exists:chart_of_accounts,id',
            'type'        => 'required|in:asset,liability,equity,revenue,expense',
            'subtype'     => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'boolean',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $coa->update($data);
        return redirect()->route('coa.index')->with('success', 'Account updated.');
    }

    public function destroy(ChartOfAccount $coa)
    {
        $code = $coa->code;
        $name = $coa->name;
        ChartOfAccount::deleteAccountAndRelated($coa, true);

        return redirect()->route('coa.index')->with('success', "Account {$code} ({$name}) and all associated history have been permanently deleted.");
    }
}
