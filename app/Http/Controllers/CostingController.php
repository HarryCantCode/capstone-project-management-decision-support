<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * CostingController
 *
 * Handles the display of project cost and budget tracking for Admin and Manager roles.
 */
class CostingController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            'auth',
            'two-factor',
            'role:Admin|Manager',
        ];
    }

    /**
     * Display a listing of project budgets and expenditures using real database records.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $projects = Project::query()
            ->with('creator')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('project_code', 'like', "%{$search}%")
                      ->orWhere('client_name', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $financials = Project::query()
            ->selectRaw('COALESCE(SUM(contract_price), 0) as total_allocated, COALESCE(SUM(actual_spend), 0) as total_spent')
            ->first();

        $totalAllocated = (float) ($financials?->total_allocated ?? 0);
        $totalSpent = (float) ($financials?->total_spent ?? 0);
        $totalRemaining = $totalAllocated - $totalSpent;

        return view('costing.index', compact('projects', 'totalAllocated', 'totalSpent', 'totalRemaining', 'search', 'status'));
    }

    /**
     * Display a comprehensive, filterable breakdown of all costs/expenses for a specific project.
     *
     * @return \Illuminate\View\View
     */
    public function show(Request $request, Project $project): View
    {
        $project->load(['creator', 'updater']);

        $search = $request->query('search');
        $category = $request->query('category');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $minAmount = $request->query('min_amount');
        $maxAmount = $request->query('max_amount');

        $costsQuery = \App\Models\ProjectCost::query()
            ->where('project_id', $project->id)
            ->with('creator');

        if (!empty($search)) {
            $costsQuery->where('description', 'like', "%{$search}%");
        }

        if (!empty($category)) {
            $costsQuery->where('cost_type', $category);
        }

        if (!empty($startDate)) {
            $costsQuery->whereDate('incurred_date', '>=', $startDate);
        }

        if (!empty($endDate)) {
            $costsQuery->whereDate('incurred_date', '<=', $endDate);
        }

        if ($minAmount !== null && $minAmount !== '') {
            $costsQuery->where('amount', '>=', (float) $minAmount);
        }

        if ($maxAmount !== null && $maxAmount !== '') {
            $costsQuery->where('amount', '<=', (float) $maxAmount);
        }

        $costs = $costsQuery
            ->orderByDesc('incurred_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $categoryTotals = \App\Models\ProjectCost::query()
            ->where('project_id', $project->id)
            ->selectRaw('cost_type, sum(amount) as total, count(*) as count')
            ->groupBy('cost_type')
            ->get()
            ->keyBy('cost_type');

        return view('costing.show', compact(
            'project',
            'costs',
            'categoryTotals',
            'search',
            'category',
            'startDate',
            'endDate',
            'minAmount',
            'maxAmount',
        ));
    }
}
