<?php

namespace App\Http\Controllers;

use App\Models\SubconAgreement;
use Illuminate\Http\Request;

class SubcontractorController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route('subcon-agreements.index');
    }

    public function create()
    {
        return redirect()->route('subcon-agreements.create');
    }

    public function store(Request $request)
    {
        return app(SubconAgreementController::class)->store($request);
    }

    public function show(SubconAgreement $subcontractor)
    {
        return redirect()->route('subcon-agreements.show', $subcontractor);
    }
}
