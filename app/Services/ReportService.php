<?php

namespace App\Services;

use App\Models\Personnel;
use App\Models\Project;
use App\Models\Resource;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ReportService
 *
 * Domain service responsible for aggregating organizational insights,
 * preparing structured report datasets, and generating downloadable
 * PDF and CSV documents for Projects, Materials Catalog, and Manpower Roster.
 */
class ReportService
{
    /**
     * Expertise hierarchy order for sorting project manpower by responsibility level.
     *
     * @var array<string, int>
     */
    private const EXPERTISE_PRIORITY = [
        Personnel::EXPERTISE_PROJECT_MANAGER => 1,
        Personnel::EXPERTISE_SITE_ENGINEER => 2,
        Personnel::EXPERTISE_FOREMAN => 3,
        Personnel::EXPERTISE_SAFETY_OFFICER => 4,
        Personnel::EXPERTISE_QA_INSPECTOR => 5,
        Personnel::EXPERTISE_ELEVATOR_CONTROL => 6,
        Personnel::EXPERTISE_TECHNICIAN => 7,
        Personnel::EXPERTISE_WORKER => 8,
    ];

    /**
     * Expense category labels in standard financial reporting order.
     *
     * @var array<string, string>
     */
    public const COST_CATEGORIES = [
        'materials' => 'Materials & Components',
        'equipment' => 'Equipment Rental',
        'labor' => 'Labor / Overtime',
        'subcontractor' => 'Subcontractor Services',
        'other' => 'Other / Miscellaneous',
    ];

    /**
     * Retrieve real-time organizational metrics for the Reports & Insights hub.
     *
     * @return array<string, mixed>
     */
    public function getModuleMetrics(): array
    {
        $projectAgg = Project::query()
            ->selectRaw("
                COUNT(*) as total_projects,
                SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as active_projects,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_projects,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_projects,
                SUM(CASE WHEN status = 'delayed' THEN 1 ELSE 0 END) as delayed_projects,
                COALESCE(SUM(contract_price), 0) as total_contract_value,
                COALESCE(SUM(actual_spend), 0) as total_actual_spend
            ")
            ->first();

        $totalContract = (float) ($projectAgg?->total_contract_value ?? 0.0);
        $totalSpend = (float) ($projectAgg?->total_actual_spend ?? 0.0);
        $remainingBudget = $totalContract - $totalSpend;
        $spendRate = $totalContract > 0 ? round(($totalSpend / $totalContract) * 100, 1) : 0.0;

        $materialsAgg = Resource::query()
            ->selectRaw("
                COUNT(*) as total_materials,
                SUM(CASE WHEN demand_status = 'fast-moving' THEN 1 ELSE 0 END) as fast_moving_count,
                SUM(CASE WHEN demand_status = 'slow-moving' THEN 1 ELSE 0 END) as slow_moving_count,
                SUM(CASE WHEN quantity_available < 5 THEN 1 ELSE 0 END) as low_stock_count,
                COALESCE(SUM(quantity_available), 0) as total_units,
                COALESCE(SUM(quantity_available * price), 0) as total_retail_value,
                COALESCE(SUM(quantity_available * unit_cost), 0) as total_cost_value
            ")
            ->first();

        $totalPersonnel = Personnel::count();
        $assignedPersonnel = Personnel::whereHas('project')->count();
        $availablePersonnel = max(0, $totalPersonnel - $assignedPersonnel);
        $utilizationRate = $totalPersonnel > 0 ? (int) round(($assignedPersonnel / $totalPersonnel) * 100) : 0;

        return [
            'total_projects' => (int) ($projectAgg?->total_projects ?? 0),
            'active_projects' => (int) ($projectAgg?->active_projects ?? 0),
            'completed_projects' => (int) ($projectAgg?->completed_projects ?? 0),
            'pending_projects' => (int) ($projectAgg?->pending_projects ?? 0),
            'delayed_projects' => (int) ($projectAgg?->delayed_projects ?? 0),
            'archived_projects' => Project::onlyTrashed()->count(),
            'total_revenue' => $totalContract,
            'total_expenses' => $totalSpend,
            'remaining_budget' => $remainingBudget,
            'budget_utilization' => $spendRate,
            'total_materials' => (int) ($materialsAgg?->total_materials ?? 0),
            'fast_moving_materials' => (int) ($materialsAgg?->fast_moving_count ?? 0),
            'slow_moving_materials' => (int) ($materialsAgg?->slow_moving_count ?? 0),
            'low_stock_materials' => (int) ($materialsAgg?->low_stock_count ?? 0),
            'total_material_units' => (int) ($materialsAgg?->total_units ?? 0),
            'materials_retail_value' => (float) ($materialsAgg?->total_retail_value ?? 0.0),
            'materials_cost_value' => (float) ($materialsAgg?->total_cost_value ?? 0.0),
            'active_personnel' => $totalPersonnel,
            'assigned_personnel' => $assignedPersonnel,
            'available_personnel' => $availablePersonnel,
            'archived_personnel' => Personnel::onlyTrashed()->count(),
            'manpower_utilization' => $utilizationRate,
        ];
    }

    /**
     * Retrieve all projects with summary counts for the Reports interactive hub.
     *
     * @return Collection<int, Project>
     */
    public function getProjectsCatalog(): Collection
    {
        return Project::withTrashed()
            ->with([
                'creator:id,name',
                'personnel:id,project_id,employee_id,first_name,last_name,expertise',
                'tasks.subtasks',
            ])
            ->withCount(['costs', 'personnel', 'personnelHistory'])
            ->orderByRaw("CASE status
                WHEN 'ongoing' THEN 1
                WHEN 'delayed' THEN 2
                WHEN 'pending' THEN 3
                WHEN 'completed' THEN 4
                ELSE 5 END")
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Retrieve all catalog materials for the Reports interactive hub.
     *
     * @return Collection<int, Resource>
     */
    public function getMaterialsCatalog(): Collection
    {
        return Resource::query()
            ->orderBy('resource_code')
            ->orderBy('id')
            ->get();
    }

    /**
     * Retrieve all personnel with current project and recent project history for the Reports hub.
     *
     * @return Collection<int, Personnel>
     */
    public function getManpowerCatalog(): Collection
    {
        return Personnel::withTrashed()
            ->with([
                'project:id,name,project_code,client_name,status',
                'assignmentHistory.project:id,name,project_code,client_name,status,deleted_at',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    /**
     * Format recent projects for a given personnel record (deduplicating current + history).
     *
     * @return list<array<string, mixed>>
     */
    public function resolvePersonnelRecentProjects(Personnel $person, int $limit = 5): array
    {
        $projects = [];
        $seenKeys = [];

        // 1. Current active assignment first (if present)
        if ($person->project_id && $person->project) {
            $proj = $person->project;
            $key = 'active-' . $proj->id;
            $seenKeys[$proj->id] = true;

            $projects[] = [
                'project_id' => $proj->id,
                'project_code' => $proj->project_code,
                'project_name' => $proj->name,
                'client_name' => $proj->client_name,
                'project_status' => ucfirst($proj->status),
                'assigned_at' => $person->date_assigned ? $person->date_assigned->format('M j, Y') : 'Active',
                'released_at' => 'Currently Assigned',
                'is_current' => true,
            ];
        }

        // 2. Historical assignments ordered by most recentassigned_at
        if ($person->relationLoaded('assignmentHistory')) {
            foreach ($person->assignmentHistory as $history) {
                if (! $history->project) {
                    continue;
                }

                $proj = $history->project;

                // Skip duplicate active row if already added above
                if (isset($seenKeys[$proj->id]) && $history->released_at === null) {
                    // Update assigned_at if history has a more specific date and person->date_assigned was null
                    if (! empty($projects[0]) && $projects[0]['project_id'] === $proj->id && $history->assigned_at) {
                        $projects[0]['assigned_at'] = $history->assigned_at->format('M j, Y');
                    }
                    continue;
                }

                $releaseLabel = 'Currently Assigned';
                if ($history->released_at) {
                    $reason = match ($history->release_reason) {
                        'project_completed' => 'Completed',
                        'reassigned' => 'Reassigned',
                        'unassigned' => 'Unassigned',
                        'archived', 'project_archived' => 'Archived',
                        default => 'Released',
                    };
                    $releaseLabel = $history->released_at->format('M j, Y') . " ({$reason})";
                }

                $projects[] = [
                    'project_id' => $proj->id,
                    'project_code' => $proj->project_code,
                    'project_name' => $proj->name,
                    'client_name' => $proj->client_name,
                    'project_status' => ucfirst($proj->status),
                    'assigned_at' => $history->assigned_at ? $history->assigned_at->format('M j, Y') : '—',
                    'released_at' => $releaseLabel,
                    'is_current' => $history->released_at === null,
                ];

                if (count($projects) >= $limit) {
                    break;
                }
            }
        }

        return array_slice($projects, 0, $limit);
    }

    /**
     * Build complete structured data for a single Project PDF export.
     *
     * Hierarchy & Chronological Order (by executive/engineering importance):
     *   1. Project Overview (Identification, Client, Location, Assigned Manager, Creator, Schedule, Progress, Warranty)
     *   2. Elevator Technical Specifications (Capacity, Speed, Stops, Cage, Door, Power, Finishes)
     *   3. Financials & Budget Summary (Contract Value Without Taxes, Spend, Remaining, Utilization, Category Breakdown)
     *   4. Expenditures Ledger (Chronological itemized expenses with category & logged-by audit)
     *   5. Assigned Manpower / Personnel (Currently assigned personnel sorted by role hierarchy & assignment date)
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function buildProjectReportData(Project $project, array $options = []): array
    {
        $project->load([
            'creator',
            'updater',
            'personnel',
            'costs' => fn ($q) => $q->with(['creator', 'resource'])->orderBy('incurred_date', 'desc')->orderBy('id', 'desc'),
            'tasks.subtasks',
        ]);

        $includeOverview = filter_var($options['include_overview'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $includeSpecs = filter_var($options['include_specs'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $includeFinancials = filter_var($options['include_financials'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $includeExpenditures = filter_var($options['include_expenditures'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $includeManpower = filter_var($options['include_manpower'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $includeTimeline = filter_var($options['include_timeline'] ?? true, FILTER_VALIDATE_BOOLEAN);

        // If all sections were unchecked, default to including the 5 core required sections
        if (! $includeOverview && ! $includeSpecs && ! $includeFinancials && ! $includeExpenditures && ! $includeManpower) {
            $includeOverview = $includeSpecs = $includeFinancials = $includeExpenditures = $includeManpower = true;
        }

        // Timeline overall progress calculation
        $tasks = $project->tasks;
        $totalSubtasks = $tasks->sum(fn ($t) => $t->subtasks->count());
        $completedSubtasks = $tasks->sum(fn ($t) => $t->subtasks->where('is_completed', true)->count());
        $overallProgress = $totalSubtasks > 0
            ? (int) round(($completedSubtasks / $totalSubtasks) * 100)
            : ($project->status === 'completed' ? 100 : 0);

        // Financial category breakdown
        $costs = $project->costs;
        $actualSpend = (float) $project->actual_spend;
        $contractPrice = (float) $project->contract_price;
        $remainingBudget = $project->remaining_budget;
        $spendPercentage = $project->spend_percentage;

        $categoryBreakdown = [];
        foreach (self::COST_CATEGORIES as $key => $label) {
            $items = $costs->where('cost_type', $key);
            $catTotal = (float) $items->sum('amount');
            $catCount = $items->count();
            $catPct = $actualSpend > 0 ? round(($catTotal / $actualSpend) * 100, 1) : 0.0;

            $categoryBreakdown[$key] = [
                'label' => $label,
                'count' => $catCount,
                'total' => $catTotal,
                'percentage' => $catPct,
            ];
        }

        // Resolve Project Lead from the currently assigned Project Manager(s) on this project
        $assignedManagers = $project->personnel
            ->where('project_id', $project->id)
            ->where('expertise', Personnel::EXPERTISE_PROJECT_MANAGER)
            ->pluck('full_name')
            ->filter()
            ->values();

        $projectLead = $assignedManagers->isNotEmpty()
            ? $assignedManagers->implode(', ')
            : 'None';

        // Compile Currently Assigned Manpower ordered by role hierarchy then chronological assignment date
        $manpowerRecords = $this->compileProjectManpower($project);

        return [
            'logoBase64' => $this->getLogoBase64(),
            'referenceNo' => sprintf('RPT-PRJ-%s-%s', $project->project_code, now()->format('Ymd')),
            'generatedAt' => now()->format('F j, Y · g:i A'),
            'generatedBy' => Auth::user()?->name ?? 'System Administrator',
            'generatedByRole' => Auth::user()?->roles?->first()?->name ?? 'Authorized User',
            'project' => $project,
            'projectLead' => $projectLead,
            'projectCreator' => $project->creator?->name ?? 'System',
            'overallProgress' => $overallProgress,
            'totalSubtasks' => $totalSubtasks,
            'completedSubtasks' => $completedSubtasks,
            'financials' => [
                'contract_price' => $contractPrice,
                'actual_spend' => $actualSpend,
                'remaining_budget' => $remainingBudget,
                'spend_percentage' => $spendPercentage,
                'costing_status' => $project->costing_status,
                'payment_status' => $project->payment_status ?: 'Partial',
                'categories' => $categoryBreakdown,
            ],
            'specifications' => [
                ['label' => 'Rated Capacity', 'value' => $project->rated_capacity ?: 'Not specified', 'mono' => true],
                ['label' => 'Lifting Speed', 'value' => $project->lifting_speed ?: 'Not specified', 'mono' => true],
                ['label' => 'Number of Stops', 'value' => $project->no_of_stops ? $project->no_of_stops . ' Stops' : 'Not specified', 'mono' => true],
                ['label' => 'Cage Dimension (W × D × H)', 'value' => $project->cage_dimension ?: 'Not specified', 'mono' => true],
                ['label' => 'Door Operation', 'value' => $project->door_operation ?: 'Not specified', 'mono' => false],
                ['label' => 'Power Source', 'value' => $project->power_source ?: 'Not specified', 'mono' => true],
                ['label' => 'Control / Operation', 'value' => $project->operation ?: 'Not specified', 'mono' => false],
                ['label' => 'Wall & Ceiling Finish', 'value' => $project->wall_and_ceiling ?: 'Not specified', 'mono' => false],
                ['label' => 'Car Flooring', 'value' => $project->flooring ?: 'Not specified', 'mono' => false],
            ],
            'costs' => $costs,
            'manpower' => $manpowerRecords,
            'tasks' => $tasks,
            'sections' => [
                'overview' => $includeOverview,
                'specs' => $includeSpecs,
                'financials' => $includeFinancials,
                'expenditures' => $includeExpenditures,
                'manpower' => $includeManpower,
                'timeline' => $includeTimeline,
            ],
        ];
    }

    /**
     * Compile and rank currently assigned project manpower by role importance level and assignment chronology.
     * Strictly uses personnel currently assigned to the project ($project->personnel).
     *
     * @return list<array<string, mixed>>
     */
    private function compileProjectManpower(Project $project): array
    {
        $records = [];

        // Strictly include only personnel currently assigned to this specific project
        $assignedPersonnel = $project->personnel
            ->where('project_id', $project->id);

        foreach ($assignedPersonnel as $person) {
            $records[] = [
                'employee_id' => $person->employee_id,
                'full_name' => $person->full_name,
                'expertise' => $person->expertise,
                'priority' => self::EXPERTISE_PRIORITY[$person->expertise] ?? 99,
                'assigned_at' => $person->date_assigned ? $person->date_assigned->format('M j, Y') : ($project->start_date ? $project->start_date->format('M j, Y') : '—'),
                'assigned_raw' => $person->date_assigned ? $person->date_assigned->toDateString() : '0000-00-00',
            ];
        }

        // Sort by importance hierarchy first (Project Manager -> Site Engineer -> Foreman -> ...), then chronological date
        usort($records, function (array $a, array $b): int {
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] <=> $b['priority'];
            }

            return strcmp($a['assigned_raw'], $b['assigned_raw']);
        });

        return $records;
    }

    /**
     * Generate and return the PDF response for a single project.
     *
     * @param array<string, mixed> $options
     */
    public function generateProjectPdf(Project $project, array $options = []): Response
    {
        $data = $this->buildProjectReportData($project, $options);

        $pdf = Pdf::loadView('reports.pdf.project', $data)
            ->setPaper('a4', 'portrait');

        $filename = sprintf(
            'DEX-Project-Report-%s-%s.pdf',
            $project->project_code,
            now()->format('Ymd'),
        );

        if (($options['disposition'] ?? 'download') === 'stream') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    /**
     * Build structured data for the Materials Catalog export (selected items or filtered by fast/slow moving).
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildMaterialsReportData(array $filters = []): array
    {
        $exportMode = $filters['export_mode'] ?? 'filtered';
        $selectedIds = array_values(array_filter(array_map('intval', $filters['material_ids'] ?? [])));
        $demandStatus = $filters['demand_status'] ?? null;
        $stockFilter = $filters['stock_filter'] ?? 'all';
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Resource::query();

        if ($exportMode === 'selected' && ! empty($selectedIds)) {
            $query->whereIn('id', $selectedIds);
        } else {
            if (! empty($demandStatus)) {
                $query->where('demand_status', $demandStatus);
            }

            if ($stockFilter === 'low_stock') {
                $query->where('quantity_available', '>', 0)->where('quantity_available', '<', 5);
            } elseif ($stockFilter === 'out_of_stock') {
                $query->where('quantity_available', '<=', 0);
            } elseif ($stockFilter === 'in_stock') {
                $query->where('quantity_available', '>', 0);
            }

            if ($search !== '') {
                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('resource_code', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }
        }

        $materials = $query
            ->orderByRaw("CASE demand_status WHEN 'fast-moving' THEN 1 WHEN 'slow-moving' THEN 2 ELSE 3 END")
            ->orderBy('resource_code')
            ->get();

        $totalItems = $materials->count();
        $totalUnits = (int) $materials->sum('quantity_available');
        $fastMovingCount = $materials->where('demand_status', 'fast-moving')->count();
        $slowMovingCount = $materials->where('demand_status', 'slow-moving')->count();
        $lowStockCount = $materials->where('quantity_available', '<', 5)->count();
        $totalRetailValue = (float) $materials->sum(fn (Resource $r) => (int) $r->quantity_available * (float) $r->price);
        $totalCostValue = (float) $materials->sum(fn (Resource $r) => (int) $r->quantity_available * (float) $r->unit_cost);

        // Determine readable filter description for report header
        $filterSummary = [];
        if ($exportMode === 'selected' && ! empty($selectedIds)) {
            $filterSummary[] = 'Scope: Selected Materials (' . count($selectedIds) . ' chosen)';
        } else {
            $filterSummary[] = 'Scope: Filtered Catalog List';
            if ($demandStatus === 'fast-moving') {
                $filterSummary[] = 'Demand Movement: Fast-Moving Only';
            } elseif ($demandStatus === 'slow-moving') {
                $filterSummary[] = 'Demand Movement: Slow-Moving Only';
            } else {
                $filterSummary[] = 'Demand Movement: All (Fast & Slow Moving)';
            }

            if ($stockFilter && $stockFilter !== 'all') {
                $filterSummary[] = 'Stock Level: ' . ucwords(str_replace('_', ' ', $stockFilter));
            }
            if ($search !== '') {
                $filterSummary[] = "Search Term: \"{$search}\"";
            }
        }

        return [
            'logoBase64' => $this->getLogoBase64(),
            'referenceNo' => 'RPT-MAT-' . now()->format('Ymd-His'),
            'generatedAt' => now()->format('F j, Y · g:i A'),
            'generatedBy' => Auth::user()?->name ?? 'System Administrator',
            'generatedByRole' => Auth::user()?->roles?->first()?->name ?? 'Authorized User',
            'materials' => $materials,
            'filterSummary' => implode('  |  ', $filterSummary),
            'demandStatusFilter' => $demandStatus,
            'exportMode' => $exportMode,
            'summary' => [
                'total_items' => $totalItems,
                'total_units' => $totalUnits,
                'fast_moving_count' => $fastMovingCount,
                'slow_moving_count' => $slowMovingCount,
                'low_stock_count' => $lowStockCount,
                'total_retail_value' => $totalRetailValue,
                'total_cost_value' => $totalCostValue,
            ],
        ];
    }

    /**
     * Generate PDF response for Materials Catalog report.
     *
     * @param array<string, mixed> $filters
     */
    public function generateMaterialsPdf(array $filters = []): Response
    {
        $data = $this->buildMaterialsReportData($filters);

        $pdf = Pdf::loadView('reports.pdf.materials', $data)
            ->setPaper('a4', 'landscape');

        $suffix = ! empty($filters['demand_status']) ? '-' . $filters['demand_status'] : '';
        $filename = sprintf('DEX-Materials-Report%s-%s.pdf', $suffix, now()->format('Ymd'));

        if (($filters['disposition'] ?? 'download') === 'stream') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    /**
     * Generate CSV stream response for Materials Catalog report.
     *
     * @param array<string, mixed> $filters
     */
    public function generateMaterialsCsv(array $filters = []): StreamedResponse
    {
        $data = $this->buildMaterialsReportData($filters);
        $materials = $data['materials'];
        $filename = sprintf('DEX-Materials-Report-%s.csv', now()->format('Ymd'));

        return response()->streamDownload(function () use ($materials) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Item Code',
                'Material Name',
                'Model / Specifications',
                'Description',
                'Demand Status',
                'Available Qty',
                'Unit Price (PHP)',
                'Unit Cost (PHP)',
                'Total Retail Valuation (PHP)',
            ]);

            foreach ($materials as $item) {
                $totalValuation = (int) $item->quantity_available * (float) $item->price;
                fputcsv($handle, [
                    $item->resource_code,
                    $item->name,
                    $item->model ?? '—',
                    $item->description ?? '—',
                    ucfirst($item->demand_status ?? 'fast-moving'),
                    $item->quantity_available,
                    number_format((float) $item->price, 2, '.', ''),
                    number_format((float) $item->unit_cost, 2, '.', ''),
                    number_format($totalValuation, 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Build structured data for the Manpower Roster export
     * (selected personnel or filtered by expertise, with optional recent projects).
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildManpowerReportData(array $filters = []): array
    {
        $exportMode = $filters['export_mode'] ?? 'filtered';
        $selectedIds = array_values(array_filter(array_map('intval', $filters['personnel_ids'] ?? [])));
        $expertise = $filters['expertise'] ?? null;
        $status = $filters['status'] ?? 'all';
        $search = trim((string) ($filters['search'] ?? ''));
        $includeRecentProjects = filter_var($filters['include_recent_projects'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = Personnel::query()->with([
            'project:id,name,project_code,client_name,status',
            'assignmentHistory.project:id,name,project_code,client_name,status,deleted_at',
        ]);

        if ($exportMode === 'selected' && ! empty($selectedIds)) {
            $query->withTrashed()->whereIn('id', $selectedIds);
        } else {
            if ($status === 'archived') {
                $query->onlyTrashed();
            } elseif ($status === 'all') {
                $query->withTrashed();
            } else {
                $query->whereNull('deleted_at');
            }

            if ($status === 'assigned') {
                $query->whereHas('project');
            } elseif ($status === 'not_assigned') {
                $query->whereDoesntHave('project');
            }

            if (! empty($expertise)) {
                $query->where('expertise', $expertise);
            }

            if ($search !== '') {
                $query->where(function ($sub) use ($search) {
                    $sub->where('employee_id', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('expertise', 'like', "%{$search}%")
                        ->orWhereHas('project', function ($pq) use ($search) {
                            $pq->where('name', 'like', "%{$search}%")
                                ->orWhere('project_code', 'like', "%{$search}%");
                        });
                });
            }
        }

        $personnelCollection = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        // Enrich each personnel with resolved recent projects and role priority
        $enrichedPersonnel = $personnelCollection->map(function (Personnel $person) use ($includeRecentProjects) {
            $recentProjects = $includeRecentProjects
                ? $this->resolvePersonnelRecentProjects($person, 5)
                : [];

            return [
                'model' => $person,
                'employee_id' => $person->employee_id,
                'full_name' => $person->full_name,
                'expertise' => $person->expertise,
                'priority' => self::EXPERTISE_PRIORITY[$person->expertise] ?? 99,
                'is_archived' => $person->trashed(),
                'status' => $person->trashed() ? 'Archived' : $person->status,
                'current_project' => $person->project,
                'date_assigned' => $person->date_assigned ? $person->date_assigned->format('M j, Y') : '—',
                'location' => $person->city ? ($person->city . ($person->province ? ', ' . $person->province : '')) : ($person->full_address ?: '—'),
                'recent_projects' => $recentProjects,
            ];
        })->sortBy([
            ['priority', 'asc'],
            ['full_name', 'asc'],
        ])->values();

        $totalCount = $enrichedPersonnel->count();
        $assignedCount = $enrichedPersonnel->where('status', 'Assigned')->count();
        $availableCount = $enrichedPersonnel->where('status', 'Not Assigned')->count();
        $archivedCount = $enrichedPersonnel->where('status', 'Archived')->count();

        $expertiseBreakdown = $enrichedPersonnel
            ->groupBy('expertise')
            ->map(fn ($group) => $group->count())
            ->toArray();

        $filterSummary = [];
        if ($exportMode === 'selected' && ! empty($selectedIds)) {
            $filterSummary[] = 'Scope: Selected Personnel (' . count($selectedIds) . ' chosen)';
        } else {
            $filterSummary[] = 'Scope: Filtered Manpower List';
            $filterSummary[] = 'Expertise: ' . ($expertise ?: 'All Expertise Roles');
            if ($status && $status !== 'all') {
                $filterSummary[] = 'Status: ' . ucwords(str_replace('_', ' ', $status));
            }
            if ($search !== '') {
                $filterSummary[] = "Search Term: \"{$search}\"";
            }
        }
        $filterSummary[] = 'Recent Projects History: ' . ($includeRecentProjects ? 'Included' : 'Excluded');

        return [
            'logoBase64' => $this->getLogoBase64(),
            'referenceNo' => 'RPT-MNP-' . now()->format('Ymd-His'),
            'generatedAt' => now()->format('F j, Y · g:i A'),
            'generatedBy' => Auth::user()?->name ?? 'System Administrator',
            'generatedByRole' => Auth::user()?->roles?->first()?->name ?? 'Authorized User',
            'personnel' => $enrichedPersonnel,
            'includeRecentProjects' => $includeRecentProjects,
            'expertiseFilter' => $expertise,
            'filterSummary' => implode('  |  ', $filterSummary),
            'summary' => [
                'total_count' => $totalCount,
                'assigned_count' => $assignedCount,
                'available_count' => $availableCount,
                'archived_count' => $archivedCount,
                'expertise_breakdown' => $expertiseBreakdown,
            ],
        ];
    }

    /**
     * Generate PDF response for Manpower Roster report.
     *
     * @param array<string, mixed> $filters
     */
    public function generateManpowerPdf(array $filters = []): Response
    {
        $data = $this->buildManpowerReportData($filters);

        $orientation = $data['includeRecentProjects'] ? 'landscape' : 'portrait';

        $pdf = Pdf::loadView('reports.pdf.manpower', $data)
            ->setPaper('a4', $orientation);

        $filename = sprintf('DEX-Manpower-Report-%s.pdf', now()->format('Ymd'));

        if (($filters['disposition'] ?? 'download') === 'stream') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    /**
     * Generate CSV stream response for Manpower Roster report.
     *
     * @param array<string, mixed> $filters
     */
    public function generateManpowerCsv(array $filters = []): StreamedResponse
    {
        $data = $this->buildManpowerReportData($filters);
        $personnelList = $data['personnel'];
        $includeRecent = $data['includeRecentProjects'];
        $filename = sprintf('DEX-Manpower-Report-%s.csv', now()->format('Ymd'));

        return response()->streamDownload(function () use ($personnelList, $includeRecent) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            $headers = [
                'Employee ID',
                'Full Name',
                'Expertise / Role',
                'Deployment Status',
                'Current Assigned Project',
                'Date Assigned',
            ];

            if ($includeRecent) {
                $headers[] = 'Recent Projects History';
            }

            fputcsv($handle, $headers);

            foreach ($personnelList as $person) {
                $currentProjectText = $person['current_project']
                    ? "{$person['current_project']->project_code} — {$person['current_project']->name}"
                    : 'Unassigned';

                $row = [
                    $person['employee_id'],
                    $person['full_name'],
                    $person['expertise'],
                    $person['status'],
                    $currentProjectText,
                    $person['date_assigned'],
                ];

                if ($includeRecent) {
                    $recentText = collect($person['recent_projects'])
                        ->map(fn (array $rp) => "{$rp['project_code']} {$rp['project_name']} ({$rp['assigned_at']} -> {$rp['released_at']})")
                        ->implode('; ');
                    $row[] = $recentText ?: 'No project assignments recorded';
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Build structured data for the Revenue & Financial Performance Report (Without Taxes),
     * aggregating contract price (revenue without taxes), actual spends, and total remaining budget
     * across monthly or yearly comparison periods.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildRevenueReportData(array $filters = []): array
    {
        $comparisonMode = ($filters['comparison_mode'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        $yearFilter = $filters['year'] ?? (string) now()->year;
        $statusFilter = $filters['status'] ?? null;
        $scopeFilter = $filters['scope'] ?? 'active';

        $query = Project::query()->with(['creator:id,name']);

        if ($scopeFilter === 'archived') {
            $query->onlyTrashed();
        } elseif ($scopeFilter === 'all') {
            $query->withTrashed();
        }

        if (! empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }

        $allScopedProjects = $query->orderByDesc('start_date')->orderByDesc('created_at')->get();

        // Filter projects for the selected year when in monthly mode (unless 'all' is selected)
        $filteredProjects = $allScopedProjects->filter(function (Project $p) use ($comparisonMode, $yearFilter) {
            if ($comparisonMode === 'monthly' && $yearFilter !== '' && $yearFilter !== 'all') {
                $effDate = $p->start_date ?? $p->created_at;

                return (int) $effDate->format('Y') === (int) $yearFilter;
            }

            return true;
        })->values();

        $totalContractWithoutTaxes = (float) $filteredProjects->sum('contract_price');
        $totalActualSpends = (float) $filteredProjects->sum('actual_spend');
        $totalRemainingBudget = $totalContractWithoutTaxes - $totalActualSpends;
        $utilizationRate = $totalContractWithoutTaxes > 0
            ? round(($totalActualSpends / $totalContractWithoutTaxes) * 100, 1)
            : 0.0;

        // Build Monthly or Yearly Period Breakdown
        $periodRows = [];
        if ($comparisonMode === 'yearly') {
            $currentYear = (int) now()->year;
            $projectYears = $allScopedProjects
                ->map(fn (Project $p) => (int) ($p->start_date ?? $p->created_at)->format('Y'))
                ->unique()
                ->toArray();

            $years = array_unique(array_merge(range($currentYear - 4, $currentYear), $projectYears));
            sort($years);

            $prevContract = null;
            foreach ($years as $yr) {
                $yrProjects = $allScopedProjects->filter(fn (Project $p) => (int) ($p->start_date ?? $p->created_at)->format('Y') === (int) $yr);
                $contract = (float) $yrProjects->sum('contract_price');
                $spend = (float) $yrProjects->sum('actual_spend');
                $remaining = $contract - $spend;
                $util = $contract > 0 ? round(($spend / $contract) * 100, 1) : 0.0;

                $growthPct = null;
                if ($prevContract !== null && $prevContract > 0) {
                    $growthPct = round((($contract - $prevContract) / $prevContract) * 100, 1);
                }
                $prevContract = $contract;

                $periodRows[] = [
                    'period_label' => (string) $yr,
                    'projects_count' => $yrProjects->count(),
                    'contract_price' => $contract,
                    'actual_spend' => $spend,
                    'remaining_budget' => $remaining,
                    'utilization' => $util,
                    'growth_pct' => $growthPct,
                ];
            }
        } else {
            $months = [
                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
            ];

            $prevContract = null;
            foreach ($months as $mNum => $mName) {
                $mProjects = $filteredProjects->filter(fn (Project $p) => (int) ($p->start_date ?? $p->created_at)->format('n') === $mNum);
                $contract = (float) $mProjects->sum('contract_price');
                $spend = (float) $mProjects->sum('actual_spend');
                $remaining = $contract - $spend;
                $util = $contract > 0 ? round(($spend / $contract) * 100, 1) : 0.0;

                $growthPct = null;
                if ($prevContract !== null && $prevContract > 0) {
                    $growthPct = round((($contract - $prevContract) / $prevContract) * 100, 1);
                }
                $prevContract = $contract;

                $periodRows[] = [
                    'period_label' => $mName . ($yearFilter !== 'all' ? " {$yearFilter}" : ''),
                    'projects_count' => $mProjects->count(),
                    'contract_price' => $contract,
                    'actual_spend' => $spend,
                    'remaining_budget' => $remaining,
                    'utilization' => $util,
                    'growth_pct' => $growthPct,
                ];
            }
        }

        $maxPeriodValue = max(1.0, (float) collect($periodRows)->max('contract_price'));

        $filterSummaryParts = [
            'Tax Basis: Revenue Displayed Without Taxes',
            'Comparison Mode: ' . ($comparisonMode === 'yearly' ? 'Yearly Revenue Comparison' : 'Monthly Revenue Comparison (' . ($yearFilter === 'all' ? 'All Years' : $yearFilter) . ')'),
            'Project Status: ' . ($statusFilter ? ucfirst($statusFilter) : 'All Statuses'),
            'Repository Scope: ' . ucfirst($scopeFilter) . ' Projects',
        ];

        return [
            'logoBase64' => $this->getLogoBase64(),
            'referenceNo' => 'RPT-REV-' . now()->format('Ymd-His'),
            'generatedAt' => now()->format('F j, Y · g:i A'),
            'generatedBy' => Auth::user()?->name ?? 'System Administrator',
            'generatedByRole' => Auth::user()?->roles?->first()?->name ?? 'Authorized User',
            'comparisonMode' => $comparisonMode,
            'yearFilter' => $yearFilter,
            'statusFilter' => $statusFilter,
            'scopeFilter' => $scopeFilter,
            'filterSummary' => implode('  |  ', $filterSummaryParts),
            'projects' => $filteredProjects,
            'periodRows' => $periodRows,
            'maxPeriodValue' => $maxPeriodValue,
            'summary' => [
                'projects_count' => $filteredProjects->count(),
                'total_contract_without_taxes' => $totalContractWithoutTaxes,
                'total_actual_spends' => $totalActualSpends,
                'total_remaining_budget' => $totalRemainingBudget,
                'utilization_rate' => $utilizationRate,
            ],
        ];
    }

    /**
     * Generate PDF response for the Revenue & Financial Performance Report (Without Taxes).
     *
     * @param array<string, mixed> $filters
     */
    public function generateRevenuePdf(array $filters = []): Response
    {
        $data = $this->buildRevenueReportData($filters);

        $pdf = Pdf::loadView('reports.pdf.revenue', $data)
            ->setPaper('a4', 'landscape');

        $modeSuffix = $data['comparisonMode'] === 'yearly' ? 'Yearly' : ('Monthly-' . $data['yearFilter']);
        $filename = sprintf('DEX-Revenue-Report-Without-Taxes-%s-%s.pdf', $modeSuffix, now()->format('Ymd'));

        if (($filters['disposition'] ?? 'download') === 'stream') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    /**
     * Generate CSV stream response for the Revenue & Financial Performance Report (Without Taxes).
     *
     * @param array<string, mixed> $filters
     */
    public function generateRevenueCsv(array $filters = []): StreamedResponse
    {
        $data = $this->buildRevenueReportData($filters);
        $periodRows = $data['periodRows'];
        $projects = $data['projects'];
        $summary = $data['summary'];
        $filename = sprintf('DEX-Revenue-Report-Without-Taxes-%s.csv', now()->format('Ymd'));

        return response()->streamDownload(function () use ($periodRows, $projects, $summary, $data) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            // Section 1: Period Comparison Summary
            fputcsv($handle, ['DEX INTERNATIONAL CO. — REVENUE & FINANCIAL PERFORMANCE REPORT (WITHOUT TAXES)']);
            fputcsv($handle, ['Filter Configuration', $data['filterSummary']]);
            fputcsv($handle, []);
            fputcsv($handle, [
                'Period (' . ucfirst($data['comparisonMode']) . ')',
                'Projects Count',
                'Total Contract Value (PHP)',
                'Actual Spends (PHP)',
                'Total revenue / remaining budget (without tax) (PHP)',
                'Budget Utilization (%)',
            ]);

            foreach ($periodRows as $row) {
                fputcsv($handle, [
                    $row['period_label'],
                    $row['projects_count'],
                    number_format((float) $row['contract_price'], 2, '.', ''),
                    number_format((float) $row['actual_spend'], 2, '.', ''),
                    number_format((float) $row['remaining_budget'], 2, '.', ''),
                    number_format((float) $row['utilization'], 1, '.', '') . '%',
                ]);
            }

            fputcsv($handle, [
                'TOTAL',
                $summary['projects_count'],
                number_format((float) $summary['total_contract_without_taxes'], 2, '.', ''),
                number_format((float) $summary['total_actual_spends'], 2, '.', ''),
                number_format((float) $summary['total_remaining_budget'], 2, '.', ''),
                number_format((float) $summary['utilization_rate'], 1, '.', '') . '%',
            ]);

            fputcsv($handle, []);
            fputcsv($handle, ['PROJECT-BY-PROJECT REVENUE BREAKDOWN (WITHOUT TAXES)']);
            fputcsv($handle, [
                'Project Code',
                'Project Name',
                'Client Name',
                'Status',
                'Project Date',
                'Total Contract Value (PHP)',
                'Actual Spends (PHP)',
                'Total revenue / remaining budget (without tax) (PHP)',
                'Spend Utilization (%)',
            ]);

            foreach ($projects as $project) {
                $effDate = ($project->start_date ?? $project->created_at)?->format('Y-m-d') ?? '—';
                fputcsv($handle, [
                    $project->project_code,
                    $project->name,
                    $project->client_name,
                    ucfirst($project->status),
                    $effDate,
                    number_format((float) $project->contract_price, 2, '.', ''),
                    number_format((float) $project->actual_spend, 2, '.', ''),
                    number_format((float) $project->remaining_budget, 2, '.', ''),
                    number_format((float) $project->spend_percentage, 1, '.', '') . '%',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Build structured data for a multi-project Executive Portfolio Summary PDF.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildPortfolioReportData(array $filters = []): array
    {
        $selectedIds = array_values(array_filter(array_map('intval', $filters['project_ids'] ?? [])));
        $status = $filters['status'] ?? null;
        $scope = $filters['scope'] ?? 'active';
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Project::query()->with(['creator:id,name', 'tasks.subtasks'])->withCount(['costs', 'personnel']);

        if (! empty($selectedIds)) {
            $query->withTrashed()->whereIn('id', $selectedIds);
        } else {
            if ($scope === 'archived') {
                $query->onlyTrashed();
            } elseif ($scope === 'all') {
                $query->withTrashed();
            }

            if (! empty($status)) {
                $query->where('status', $status);
            }

            if ($search !== '') {
                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('project_code', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%");
                });
            }
        }

        $projects = $query->orderByDesc('created_at')->get();

        $totalAllocated = (float) $projects->sum('contract_price');
        $totalSpent = (float) $projects->sum('actual_spend');
        $totalRemaining = $totalAllocated - $totalSpent;
        $utilization = $totalAllocated > 0 ? round(($totalSpent / $totalAllocated) * 100, 1) : 0.0;

        return [
            'logoBase64' => $this->getLogoBase64(),
            'referenceNo' => 'RPT-PRT-' . now()->format('Ymd-His'),
            'generatedAt' => now()->format('F j, Y · g:i A'),
            'generatedBy' => Auth::user()?->name ?? 'System Administrator',
            'generatedByRole' => Auth::user()?->roles?->first()?->name ?? 'Authorized User',
            'projects' => $projects,
            'summary' => [
                'total_projects' => $projects->count(),
                'ongoing_count' => $projects->where('status', 'ongoing')->count(),
                'completed_count' => $projects->where('status', 'completed')->count(),
                'pending_count' => $projects->where('status', 'pending')->count(),
                'delayed_count' => $projects->where('status', 'delayed')->count(),
                'total_allocated' => $totalAllocated,
                'total_spent' => $totalSpent,
                'total_remaining' => $totalRemaining,
                'utilization' => $utilization,
            ],
        ];
    }

    /**
     * Generate PDF response for Executive Portfolio Summary.
     *
     * @param array<string, mixed> $filters
     */
    public function generatePortfolioPdf(array $filters = []): Response
    {
        $data = $this->buildPortfolioReportData($filters);

        $pdf = Pdf::loadView('reports.pdf.portfolio', $data)
            ->setPaper('a4', 'landscape');

        $filename = sprintf('DEX-Portfolio-Summary-%s.pdf', now()->format('Ymd'));

        if (($filters['disposition'] ?? 'download') === 'stream') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }

    /**
     * Encode the official DEX International Co. logo as a base64 data URI
     * so DomPDF renders it reliably across all OS and container environments.
     */
    public function getLogoBase64(): ?string
    {
        $logoPath = public_path('images/dex-logo.png');

        if (! is_file($logoPath) || ! is_readable($logoPath)) {
            return null;
        }

        $contents = file_get_contents($logoPath);
        if ($contents === false) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($contents);
    }
}
