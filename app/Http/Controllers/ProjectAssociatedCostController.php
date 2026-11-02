<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectAssociatedCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectAssociatedCostController extends Controller
{
    protected ProjectAssociatedCostService $service;

    public function __construct(ProjectAssociatedCostService $service)
    {
        $this->middleware('auth');
        $this->service = $service;
    }

    /**
     * Display or return associated unbudgeted costs for a project.
     */
    public function index(Request $request, Project $project)
    {
        Gate::authorize('view', $project);

        $filters = [
            'category'    => $request->input('category'),
            'source_type' => $request->input('source_type'),
            'date_from'   => $request->input('date_from'),
            'date_to'     => $request->input('date_to'),
            'search'      => $request->input('search'),
        ];

        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        $paginated = $this->service->getPaginated($project, $filters, $perPage, $page);
        $summary = $this->service->getSummary($project);

        if ($request->wantsJson() || $request->ajax()) {
            if ($request->boolean('partial', false)) {
                return view('projects.partials.associated_costs_table', [
                    'project'   => $project,
                    'costs'     => $paginated,
                    'summary'   => $summary,
                    'filters'   => $filters,
                ])->render();
            }

            return response()->json([
                'success' => true,
                'data'    => $paginated->items(),
                'summary' => $summary,
                'meta'    => [
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                ],
            ]);
        }

        return view('projects.associated_costs', compact('project', 'paginated', 'summary', 'filters'));
    }
}
