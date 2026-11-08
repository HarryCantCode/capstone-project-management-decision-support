<?php

namespace App\Services\DecisionSupport;

use App\Models\Personnel;
use App\Models\Project;
use App\Models\ProjectPersonnelHistory;
use App\Models\ProjectStatusHistory;
use App\Models\Resource;
use App\Services\DecisionSupport\DTOs\DecisionSupportResult;
use App\Services\NotificationService;
use App\Services\ProjectService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * DecisionSupportService
 *
 * Orchestrates the Rule-Based Engine (`RuleEngineService`),
 * the In-Memory What-If Scenario Simulator (`ScenarioSimulatorService`),
 * and the Automated Resource Allocation Generator (`allocateResourcesToProject`)
 * for the Decision Support module under Insights.
 */
class DecisionSupportService
{
    public function __construct(
        private readonly RuleEngineService $ruleEngine,
        private readonly ScenarioSimulatorService $scenarioSimulator,
        private readonly ProjectService $projectService,
        private readonly NotificationService $notificationService,
    ) {
    }

    /**
     * Generate a deterministic, explainable decision support analysis for a given project.
     *
     * @param Project $project
     * @param Collection<int, Personnel>|null $allPersonnel
     * @param Collection<int, Resource>|null $allResources
     */
    public function generateRecommendation(
        Project $project,
        ?Collection $allPersonnel = null,
        ?Collection $allResources = null,
    ): DecisionSupportResult {
        return $this->ruleEngine->evaluateProject($project, $allPersonnel, $allResources);
    }

    /**
     * Build the full Portfolio Health Matrix, KPI metrics, and Catalog Inventory Alerts.
     *
     * @param Collection<int, Project> $projects
     * @param Collection<int, Personnel> $allPersonnel
     * @param Collection<int, Resource> $allResources
     * @return array<string, mixed>
     */
    public function buildPortfolioOverview(
        Collection $projects,
        Collection $allPersonnel,
        Collection $allResources,
    ): array {
        return $this->ruleEngine->evaluatePortfolioHealthMatrix($projects, $allPersonnel, $allResources);
    }

    /**
     * Run the in-memory Project Feasibility simulator ("Can we take this project?").
     *
     * @param array<string, mixed> $input
     * @param Collection<int, Personnel> $allPersonnel
     * @param Collection<int, Resource> $allResources
     * @return array<string, mixed>
     */
    public function simulateFeasibility(
        array $input,
        Collection $allPersonnel,
        Collection $allResources,
    ): array {
        return $this->scenarioSimulator->simulateFeasibility($input, $allPersonnel, $allResources);
    }

    /**
     * Run the in-memory Delay Propagation simulator ("What if an active project slips by X days?").
     *
     * @param Project $project
     * @param int $slipDays
     * @param Collection<int, Project> $allProjects
     * @return array<string, mixed>
     */
    public function simulateDelayPropagation(
        Project $project,
        int $slipDays,
        Collection $allProjects,
    ): array {
        return $this->scenarioSimulator->simulateDelayPropagation($project, $slipDays, $allProjects);
    }

    /**
     * Run the in-memory Supplier Procurement & Lead-Time Trade-off simulator.
     *
     * @param Project|null $project
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function simulateSupplierComparison(?Project $project, array $input): array
    {
        return $this->scenarioSimulator->simulateSupplierComparison($project, $input);
    }

    /**
     * Atomically validate and allocate manpower and/or material template items to a target project.
     * Enforces strict conditions:
     * 1. Project must be active and not completed/archived.
     * 2. Sufficient available manpower for every requested role or personnel ID.
     * 3. Sufficient catalog stock for every requested material item.
     * 4. Total material allocation cost must not exceed the project's remaining contract budget.
     *
     * @param Project $project
     * @param array<string, mixed> $payload
     * @return array{assigned_personnel_count: int, allocated_materials_count: int, total_material_cost: float}
     *
     * @throws ValidationException
     */
    public function allocateResourcesToProject(Project $project, array $payload): array
    {
        if ($project->trashed() || $project->status === 'completed') {
            throw ValidationException::withMessages([
                'project_id' => 'Cannot allocate resources to a completed or archived project.',
            ]);
        }

        $templateName = trim((string) ($payload['template_name'] ?? 'DSS Spec-Based Template'));

        // Consolidate requested materials: resource_id => total_quantity
        $materialMap = [];
        foreach (($payload['materials'] ?? []) as $matRow) {
            $resId = (int) ($matRow['resource_id'] ?? 0);
            $qty = (int) ($matRow['quantity'] ?? 0);
            if ($resId > 0 && $qty > 0) {
                $materialMap[$resId] = ($materialMap[$resId] ?? 0) + $qty;
            }
        }

        // Consolidate requested manpower roles: expertise => count
        $roleMap = [];
        foreach (($payload['roles'] ?? []) as $roleRow) {
            $exp = trim((string) ($roleRow['expertise'] ?? ''));
            $cnt = (int) ($roleRow['count'] ?? 0);
            if ($exp !== '' && $cnt > 0) {
                $roleMap[$exp] = ($roleMap[$exp] ?? 0) + $cnt;
            }
        }

        // Explicit personnel IDs (optional)
        $explicitPersonnelIds = array_values(array_unique(array_filter(array_map(
            'intval',
            $payload['personnel_ids'] ?? [],
        ))));

        if (empty($materialMap) && empty($roleMap) && empty($explicitPersonnelIds)) {
            throw ValidationException::withMessages([
                'allocation' => 'Please include at least one material item or manpower role in the allocation template.',
            ]);
        }

        $userId = Auth::id();

        return DB::transaction(function () use ($project, $materialMap, $roleMap, $explicitPersonnelIds, $templateName, $userId): array {
            /** @var Project $lockedProject */
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);

            if ($lockedProject->status === 'completed') {
                throw ValidationException::withMessages([
                    'project_id' => 'Cannot allocate resources to a completed project.',
                ]);
            }

            // ── 1. Validate Materials Stock & Budget Sufficiency ──
            $lockedResources = collect();
            $totalMaterialCost = 0.0;

            if (! empty($materialMap)) {
                $lockedResources = Resource::query()
                    ->whereIn('id', array_keys($materialMap))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($materialMap as $resId => $reqQty) {
                    /** @var Resource|null $res */
                    $res = $lockedResources->get($resId);
                    if (! $res) {
                        throw ValidationException::withMessages([
                            'materials' => "Selected material ID #{$resId} does not exist in the catalog.",
                        ]);
                    }

                    if ($res->quantity_available < $reqQty) {
                        throw ValidationException::withMessages([
                            'materials' => sprintf(
                                'Not enough materials in stock for %s (%s): requested %d unit(s), but only %d unit(s) available in catalog.',
                                $res->resource_code,
                                $res->name,
                                $reqQty,
                                $res->quantity_available,
                            ),
                        ]);
                    }

                    $lineCost = round($reqQty * (float) $res->price, 2);
                    if ($lineCost < 100.0) {
                        $lineCost = 100.0;
                    }
                    $totalMaterialCost += $lineCost;
                }

                // Strict Budget Remaining Check
                $remainingBudget = (float) $lockedProject->contract_price - (float) $lockedProject->actual_spend;
                if ((float) $lockedProject->contract_price > 0 && $totalMaterialCost > $remainingBudget) {
                    throw ValidationException::withMessages([
                        'budget' => sprintf(
                            'Not enough remaining project budget: template material cost is ₱%s, which exceeds the remaining budget of ₱%s.',
                            number_format($totalMaterialCost, 2),
                            number_format(max(0.0, $remainingBudget), 2),
                        ),
                    ]);
                }
            }

            // ── 2. Validate & Select Manpower (Explicit IDs + Role-Based Auto-Ranking) ──
            /** @var Collection<int, Personnel> $personnelToAssign */
            $personnelToAssign = collect();

            if (! empty($explicitPersonnelIds)) {
                $lockedExplicit = Personnel::query()
                    ->whereIn('id', $explicitPersonnelIds)
                    ->lockForUpdate()
                    ->get();

                if ($lockedExplicit->count() !== count($explicitPersonnelIds)) {
                    throw ValidationException::withMessages([
                        'manpower' => 'One or more selected personnel records could not be found.',
                    ]);
                }

                foreach ($lockedExplicit as $person) {
                    if ($person->project_id !== null && (int) $person->project_id !== (int) $lockedProject->id) {
                        throw ValidationException::withMessages([
                            'manpower' => sprintf(
                                'Personnel %s (%s) is already assigned to another active project.',
                                $person->employee_id,
                                $person->full_name,
                            ),
                        ]);
                    }
                    if ($person->project_id === null) {
                        $personnelToAssign->push($person);
                    }
                }
            }

            if (! empty($roleMap)) {
                $availablePool = Personnel::query()
                    ->whereNull('project_id')
                    ->whereNull('deleted_at')
                    ->whereNotIn('id', $personnelToAssign->pluck('id')->all())
                    ->withCount('assignmentHistory')
                    ->lockForUpdate()
                    ->get();

                foreach ($roleMap as $expertise => $neededCount) {
                    $candidates = $availablePool
                        ->where('expertise', $expertise)
                        ->sort(function (Personnel $a, Personnel $b): int {
                            $loadCmp = ($a->assignment_history_count ?? 0) <=> ($b->assignment_history_count ?? 0);
                            if ($loadCmp !== 0) {
                                return $loadCmp;
                            }

                            return strcmp($a->employee_id, $b->employee_id);
                        })
                        ->values();

                    if ($candidates->count() < $neededCount) {
                        throw ValidationException::withMessages([
                            'manpower' => sprintf(
                                'Not enough available manpower for role "%s": template requires %d personnel, but only %d unassigned personnel are available.',
                                $expertise,
                                $neededCount,
                                $candidates->count(),
                            ),
                        ]);
                    }

                    foreach ($candidates->take($neededCount) as $chosen) {
                        $personnelToAssign->push($chosen);
                    }
                }
            }

            // ── 3. Execute Manpower Assignments to Project ──
            $nowDate = now()->toDateString();
            $assignedPersonnelCount = 0;

            if ($personnelToAssign->isNotEmpty()) {
                foreach ($personnelToAssign as $person) {
                    $person->update([
                        'project_id' => $lockedProject->id,
                        'date_assigned' => $nowDate,
                        'updated_by' => $userId,
                    ]);

                    ProjectPersonnelHistory::create([
                        'project_id' => $lockedProject->id,
                        'personnel_id' => $person->id,
                        'assigned_at' => $nowDate,
                        'assigned_by' => $userId,
                    ]);

                    $assignedPersonnelCount++;
                }

                $manpowerSummary = $personnelToAssign
                    ->map(fn (Personnel $p) => "{$p->employee_id} ({$p->full_name} - {$p->expertise})")
                    ->implode(', ');

                ProjectStatusHistory::create([
                    'project_id' => $lockedProject->id,
                    'from_status' => $lockedProject->status,
                    'to_status' => $lockedProject->status,
                    'notes' => "DSS Resource Allocation ({$templateName}) — Assigned {$assignedPersonnelCount} manpower: {$manpowerSummary}",
                    'changed_by' => $userId,
                    'changed_at' => now(),
                ]);

                $this->notificationService->notifyManpowerAssigned($lockedProject, $personnelToAssign);
            }

            // ── 4. Execute Material Allocations & Cost Entries on Project ──
            $allocatedMaterialsCount = 0;
            foreach ($materialMap as $resId => $reqQty) {
                /** @var Resource $res */
                $res = $lockedResources->get($resId);
                $lineAmount = max(100.0, round($reqQty * (float) $res->price, 2));

                $this->projectService->addCost($lockedProject, [
                    'resource_id' => $res->id,
                    'description' => "{$res->name} ({$res->resource_code}) — DSS Allocation [{$templateName}]",
                    'cost_type' => 'materials',
                    'amount' => $lineAmount,
                    'quantity' => $reqQty,
                    'incurred_date' => $nowDate,
                ]);

                $allocatedMaterialsCount++;
            }

            return [
                'assigned_personnel_count' => $assignedPersonnelCount,
                'allocated_materials_count' => $allocatedMaterialsCount,
                'total_material_cost' => $totalMaterialCost,
            ];
        });
    }
}
