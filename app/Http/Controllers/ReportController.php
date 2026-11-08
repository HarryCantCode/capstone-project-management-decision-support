<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportManpowerReportRequest;
use App\Http\Requests\ExportMaterialsReportRequest;
use App\Http\Requests\ExportPortfolioReportRequest;
use App\Http\Requests\ExportProjectReportRequest;
use App\Http\Requests\ExportRevenueReportRequest;
use App\Models\Personnel;
use App\Models\Project;
use App\Models\Resource;
use App\Services\ReportService;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ReportController — thin HTTP orchestrator for the Reports & Insights module.
 *
 * All data aggregation, filtering, and PDF/CSV generation live in ReportService.
 * Restricted to Admin and Manager roles via middleware and Policies.
 */
class ReportController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {
    }

    /**
     * Assign route middleware to restrict Reports to Admin and Manager roles.
     *
     * @return list<string>
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
     * Display the main Reports & Insights hub with interactive Project, Materials, and Manpower export tabs.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Project::class);

        $metrics = $this->reportService->getModuleMetrics();
        $projects = $this->reportService->getProjectsCatalog();
        $materials = $this->reportService->getMaterialsCatalog();
        $manpower = $this->reportService->getManpowerCatalog();
        $expertiseOptions = Personnel::EXPERTISE_OPTIONS;

        $recentProjectsMap = [];
        foreach ($manpower as $person) {
            $recentProjectsMap[$person->id] = $this->reportService->resolvePersonnelRecentProjects($person, 5);
        }

        return view('reports.index', compact(
            'metrics',
            'projects',
            'materials',
            'manpower',
            'expertiseOptions',
            'recentProjectsMap',
        ));
    }

    /**
     * Export a single project into a downloadable (or streamable) PDF dossier.
     */
    public function exportProjectPdf(ExportProjectReportRequest $request, int|string $project): Response
    {
        $projectModel = Project::withTrashed()->findOrFail($project);
        $this->authorize('view', $projectModel);

        return $this->reportService->generateProjectPdf($projectModel, $request->validated());
    }

    /**
     * Export a multi-project executive portfolio summary PDF.
     */
    public function exportPortfolio(ExportPortfolioReportRequest $request): Response
    {
        $this->authorize('viewAny', Project::class);

        return $this->reportService->generatePortfolioPdf($request->validated());
    }

    /**
     * Export Revenue & Financial Performance data (Without Taxes) as PDF or CSV.
     */
    public function exportRevenue(ExportRevenueReportRequest $request): Response|StreamedResponse
    {
        $this->authorize('viewAny', Project::class);

        $validated = $request->validated();

        if (($validated['format'] ?? 'pdf') === 'csv') {
            return $this->reportService->generateRevenueCsv($validated);
        }

        return $this->reportService->generateRevenuePdf($validated);
    }

    /**
     * Export selected or filtered Materials Catalog items (Fast-Moving / Slow-Moving) as PDF or CSV.
     */
    public function exportMaterials(ExportMaterialsReportRequest $request): Response|StreamedResponse
    {
        $this->authorize('viewAny', Project::class);
        $this->authorize('viewAny', Resource::class);

        $validated = $request->validated();

        if (($validated['format'] ?? 'pdf') === 'csv') {
            return $this->reportService->generateMaterialsCsv($validated);
        }

        return $this->reportService->generateMaterialsPdf($validated);
    }

    /**
     * Export selected or filtered Manpower Roster (by expertise, with optional recent projects) as PDF or CSV.
     */
    public function exportManpower(ExportManpowerReportRequest $request): Response|StreamedResponse
    {
        $this->authorize('viewAny', Personnel::class);

        $validated = $request->validated();

        if (($validated['format'] ?? 'pdf') === 'csv') {
            return $this->reportService->generateManpowerCsv($validated);
        }

        return $this->reportService->generateManpowerPdf($validated);
    }
}
