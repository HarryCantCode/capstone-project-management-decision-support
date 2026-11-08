<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\Project;
use App\Models\Resource;
use App\Services\DecisionSupport\DecisionSupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * DecisionSupportController
 *
 * Handles requests for the Rule-Based Decision Support System (DSS) module
 * under the Insights section, accessible to Admin and Manager roles.
 * Provides:
 * 1. Project Overview & Health Matrix (Resource, Timeline, and Budget risk badges + detailed health checks)
 * 2. In-Memory What-If Scenario Simulator (Project Feasibility, Delay Propagation, Budget/Price Adjustment)
 * 3. Generate Resource Allocation (Spec-based & custom templates for automatic manpower & material assignment)
 */
class DecisionSupportController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            'auth',
            'two-factor',
            'role:Admin|Manager|Staff',
        ];
    }

    /**
     * Display the Decision Support module (Project Overview, What-If Simulator & Generate Resource Allocation).
     */
    public function index(Request $request, DecisionSupportService $service): View
    {
        $projects = Project::query()
            ->with([
                'personnel',
                'costs.resource',
                'tasks.subtasks',
                'personnelHistory.project',
            ])
            ->orderByRaw("CASE status
                WHEN 'delayed' THEN 1
                WHEN 'ongoing' THEN 2
                WHEN 'pending' THEN 3
                WHEN 'completed' THEN 4
                ELSE 5 END")
            ->orderByDesc('created_at')
            ->get();

        $allPersonnel = Personnel::query()
            ->with(['project', 'assignmentHistory.project'])
            ->withCount('assignmentHistory')
            ->orderBy('employee_id')
            ->get();

        $allResources = Resource::query()
            ->orderBy('resource_code')
            ->orderBy('id')
            ->get();

        $overview = $service->buildPortfolioOverview($projects, $allPersonnel, $allResources);

        $selectedProject = null;
        $result = null;

        if ($request->filled('project_id')) {
            $selectedProject = $projects->firstWhere('id', (int) $request->input('project_id'))
                ?? Project::with(['personnel', 'costs.resource', 'tasks.subtasks', 'personnelHistory.project'])
                    ->findOrFail((int) $request->input('project_id'));
            $result = $service->generateRecommendation($selectedProject, $allPersonnel, $allResources);
        } elseif ($projects->isNotEmpty()) {
            $selectedProject = $projects->first();
            $result = $service->generateRecommendation($selectedProject, $allPersonnel, $allResources);
        }

        $personnelForSim = $allPersonnel->map(function (Personnel $p) {
            return [
                'id' => $p->id,
                'employee_id' => $p->employee_id,
                'full_name' => $p->full_name,
                'expertise' => $p->expertise,
                'project_id' => $p->project_id,
                'project_code' => $p->project?->project_code,
                'project_name' => $p->project?->name,
                'project_status' => $p->project?->status,
                'project_target_date' => $p->project?->target_completion_date?->format('Y-m-d'),
                'historical_load' => (int) ($p->assignment_history_count ?? 0),
            ];
        })->values();

        $resourcesForSim = $allResources->map(function (Resource $r) {
            return [
                'id' => $r->id,
                'resource_code' => $r->resource_code,
                'name' => $r->name,
                'model' => $r->model ?: 'Standard',
                'quantity_available' => (int) $r->quantity_available,
                'price' => (float) $r->price,
                'unit_cost' => (float) $r->unit_cost,
                'demand_status' => $r->demand_status ?: 'fast-moving',
            ];
        })->values();

        $expertiseOptions = Personnel::EXPERTISE_OPTIONS;
        $initialTab = in_array($request->query('tab'), ['simulator', 'allocation'], true)
            ? $request->query('tab')
            : 'simulator';

        return view('decision-support.index', [
            'projects' => $projects,
            'selectedProject' => $selectedProject,
            'result' => $result,
            'matrix' => $overview['matrix'],
            'kpis' => $overview['kpis'],
            'catalogAlerts' => $overview['catalog_alerts'],
            'personnelForSim' => $personnelForSim,
            'resourcesForSim' => $resourcesForSim,
            'expertiseOptions' => $expertiseOptions,
            'initialTab' => $initialTab,
        ]);
    }

    /**
     * Execute an in-memory What-If Scenario Simulation (Feasibility, Delay Propagation, or Budget Adjustment)
     * without writing to the database.
     */
    public function simulate(Request $request, DecisionSupportService $service): JsonResponse
    {
        $validated = $request->validate([
            'scenario_type' => ['required', 'string', 'in:feasibility,delay,budget'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'slip_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'material_price_delta_pct' => ['nullable', 'numeric', 'min:-90', 'max:500'],
            'other_cost_delta_pct' => ['nullable', 'numeric', 'min:-90', 'max:500'],
            'additional_contingency' => ['nullable', 'numeric', 'min:0'],
            'contract_budget' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'target_completion_date' => ['nullable', 'date'],
            'required_roles' => ['nullable', 'array'],
            'required_roles.*.expertise' => ['required_with:required_roles', 'string'],
            'required_roles.*.count' => ['required_with:required_roles', 'integer', 'min:1'],
            'required_materials' => ['nullable', 'array'],
            'required_materials.*.resource_id' => ['required_with:required_materials', 'integer'],
            'required_materials.*.quantity' => ['required_with:required_materials', 'integer', 'min:1'],
        ]);

        $type = $validated['scenario_type'];

        if ($type === 'feasibility') {
            $allPersonnel = Personnel::query()
                ->with(['project'])
                ->withCount('assignmentHistory')
                ->orderBy('employee_id')
                ->get();
            $allResources = Resource::query()->orderBy('resource_code')->get();

            return response()->json([
                'scenario_type' => 'feasibility',
                'simulation' => $service->simulateFeasibility($validated, $allPersonnel, $allResources),
            ]);
        }

        $project = Project::with(['personnel', 'costs', 'tasks.subtasks'])
            ->findOrFail((int) ($validated['project_id'] ?? Project::query()->value('id')));

        if ($type === 'delay') {
            $allProjects = Project::query()->whereNull('deleted_at')->get();

            return response()->json([
                'scenario_type' => 'delay',
                'simulation' => $service->simulateDelayPropagation(
                    $project,
                    (int) ($validated['slip_days'] ?? 7),
                    $allProjects,
                ),
            ]);
        }

        return response()->json([
            'scenario_type' => 'budget',
            'simulation' => $service->simulateBudgetAdjustment(
                $project,
                (float) ($validated['material_price_delta_pct'] ?? 0.0),
                (float) ($validated['other_cost_delta_pct'] ?? 0.0),
                (float) ($validated['additional_contingency'] ?? 0.0),
            ),
        ]);
    }

    /**
     * Validate and execute automatic manpower & material resource allocation to a project.
     */
    public function allocate(Request $request, DecisionSupportService $service): RedirectResponse|JsonResponse
    {
        abort_if(Auth::user()->hasRole('Staff'), 403, 'Staff users cannot allocate resources.');

        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'template_name' => ['nullable', 'string', 'max:120'],
            'materials' => ['nullable', 'array'],
            'materials.*.resource_id' => ['required_with:materials', 'integer', 'exists:resources,id'],
            'materials.*.quantity' => ['required_with:materials', 'integer', 'min:1', 'max:9999'],
            'roles' => ['nullable', 'array'],
            'roles.*.expertise' => ['required_with:roles', 'string'],
            'roles.*.count' => ['required_with:roles', 'integer', 'min:1', 'max:50'],
            'personnel_ids' => ['nullable', 'array'],
            'personnel_ids.*' => ['required', 'integer', 'exists:personnel,id'],
        ]);

        /** @var Project $project */
        $project = Project::findOrFail((int) $validated['project_id']);
        $this->authorize('update', $project);

        try {
            $summary = $service->allocateResourcesToProject($project, $validated);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                throw $e;
            }

            $firstError = collect($e->errors())->flatten()->first() ?? 'Resource allocation validation failed.';

            return redirect()
                ->route('decision-support.index', [
                    'tab' => 'allocation',
                    'project_id' => $project->id,
                ])
                ->withErrors($e->errors())
                ->with('error', $firstError);
        }

        $msg = sprintf(
            'Successfully allocated %d manpower and %d material item(s) (₱%s) to project %s — %s.',
            $summary['assigned_personnel_count'],
            $summary['allocated_materials_count'],
            number_format($summary['total_material_cost'], 2),
            $project->project_code,
            $project->name,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $msg,
                'summary' => $summary,
            ]);
        }

        return redirect()
            ->route('decision-support.index', [
                'tab' => 'allocation',
                'project_id' => $project->id,
            ])
            ->with('status', $msg);
    }
}
