<?php

namespace App\Services\DecisionSupport;

use App\Models\Personnel;
use App\Models\Project;
use App\Models\Resource;
use App\Services\DecisionSupport\DTOs\DecisionSupportResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * RuleEngineService
 *
 * Deterministic, rule-based engine for the Decision Support System (DSS).
 * Evaluates projects and catalog resources against configurable thresholds to produce
 * explainable `ok` / `warning` / `critical` flags, formulas, and recommendations.
 */
class RuleEngineService
{
    /**
     * Evaluate a single project across all 4 DSS domains (Budget, Schedule, Manpower, Inventory)
     * and return a structured DecisionSupportResult DTO.
     *
     * @param Project $project
     * @param Collection<int, Personnel>|null $allPersonnel Preloaded active personnel (optional)
     * @param Collection<int, Resource>|null $allResources Preloaded catalog resources (optional)
     */
    public function evaluateProject(
        Project $project,
        ?Collection $allPersonnel = null,
        ?Collection $allResources = null,
    ): DecisionSupportResult {
        $project->loadMissing([
            'personnel',
            'costs.resource',
            'tasks.subtasks',
            'personnelHistory.project',
        ]);

        $allPersonnel ??= Personnel::query()
            ->with(['project', 'assignmentHistory.project'])
            ->withCount('assignmentHistory')
            ->orderBy('employee_id')
            ->get();

        $allResources ??= Resource::query()
            ->orderBy('resource_code')
            ->get();

        $budgetEval = $this->evaluateBudgetRule($project);
        $scheduleEval = $this->evaluateScheduleRule($project);
        $manpowerEval = $this->evaluateManpowerRule($project, $allPersonnel);
        $inventoryEval = $this->evaluateInventoryRule($project, $allResources);

        // Combine resource health from Manpower + Inventory
        $resourceStatus = $this->worstStatus($manpowerEval['status'], $inventoryEval['status']);
        $overallStatus = $this->worstStatus(
            $resourceStatus,
            $this->worstStatus($budgetEval['status'], $scheduleEval['status']),
        );

        $readinessLabel = match ($overallStatus) {
            'critical' => 'Critical Attention Required',
            'warning' => 'At Risk — Action Recommended',
            default => 'Optimal / On Track',
        };

        $badges = [
            'resource' => [
                'domain' => 'Resource & Crew',
                'status' => $resourceStatus,
                'label' => $this->statusLabel($resourceStatus),
                'summary' => sprintf(
                    '%d assigned · %d missing core role(s) · %d stock alert(s)',
                    $manpowerEval['assigned_count'],
                    count($manpowerEval['missing_roles']),
                    $inventoryEval['stock_alerts_count'],
                ),
                'formula' => 'Roles Covered = Assigned Core Roles / 5 Core Roles; Deficit = max(0, Target Reserve − Available Stock)',
                'numbers' => sprintf(
                    'Core roles: %d/5 covered · Collisions: %d · Material share: %s%% of budget',
                    5 - count($manpowerEval['missing_roles']),
                    count($manpowerEval['collisions']),
                    number_format($inventoryEval['material_budget_share_pct'], 1),
                ),
            ],
            'timeline' => [
                'domain' => 'Timeline & Schedule',
                'status' => $scheduleEval['status'],
                'label' => $this->statusLabel($scheduleEval['status']),
                'summary' => $scheduleEval['overdue_tasks_count'] > 0
                    ? sprintf('%d overdue item(s) · +%d day(s) slip', $scheduleEval['overdue_tasks_count'], $scheduleEval['max_overdue_days'])
                    : sprintf('%d%% task progress · On schedule', $scheduleEval['task_progress_pct']),
                'formula' => 'Cascading End Date = Target Completion Date + max(Overdue Task Days)',
                'numbers' => sprintf(
                    'Elapsed: %s%% (%d/%d days) · Progress: %d%% · Overdue: %d item(s) (%d days)',
                    number_format($scheduleEval['elapsed_time_pct'], 1),
                    $scheduleEval['elapsed_days'],
                    $scheduleEval['total_planned_days'],
                    $scheduleEval['task_progress_pct'],
                    $scheduleEval['overdue_tasks_count'],
                    $scheduleEval['max_overdue_days'],
                ),
            ],
            'budget' => [
                'domain' => 'Budget & Burn Rate',
                'status' => $budgetEval['status'],
                'label' => $this->statusLabel($budgetEval['status']),
                'summary' => sprintf(
                    '%s%% utilized (₱%s rem.)',
                    number_format($budgetEval['burn_ratio_pct'], 1),
                    number_format($budgetEval['remaining_budget'], 2),
                ),
                'formula' => 'Burn Ratio (Actual ÷ Budget) ≤ Elapsed Time Ratio + ' . number_format($budgetEval['buffer_pct'], 0) . '%',
                'numbers' => sprintf(
                    'Burn: %s%% (₱%s / ₱%s) vs. Allowed: %s%% (Elapsed %s%% + %s%%)',
                    number_format($budgetEval['burn_ratio_pct'], 1),
                    number_format($budgetEval['actual_spend'], 2),
                    number_format($budgetEval['contract_price'], 2),
                    number_format($budgetEval['allowed_burn_pct'], 1),
                    number_format($budgetEval['elapsed_time_pct'], 1),
                    number_format($budgetEval['buffer_pct'], 0),
                ),
            ],
        ];

        $recommendations = array_values(array_filter([
            $budgetEval['recommendation_item'],
            $scheduleEval['recommendation_item'],
            $manpowerEval['recommendation_item'],
            $inventoryEval['recommendation_item'],
        ]));

        return new DecisionSupportResult(
            manpowerSuggestion: $manpowerEval,
            materialSuggestion: $inventoryEval,
            timelineSuggestion: $scheduleEval,
            operationalReadiness: $readinessLabel,
            status: $overallStatus,
            budgetSuggestion: $budgetEval,
            badges: $badges,
            recommendations: $recommendations,
        );
    }

    /**
     * Evaluate all active projects and build the Project Health Matrix rows + portfolio KPIs.
     *
     * @param Collection<int, Project> $projects
     * @param Collection<int, Personnel> $allPersonnel
     * @param Collection<int, Resource> $allResources
     * @return array{
     *     matrix: list<array<string, mixed>>,
     *     kpis: array<string, mixed>,
     *     catalog_alerts: list<array<string, mixed>>
     * }
     */
    public function evaluatePortfolioHealthMatrix(
        Collection $projects,
        Collection $allPersonnel,
        Collection $allResources,
    ): array {
        $matrix = [];
        $criticalProjects = 0;
        $warningProjects = 0;
        $healthyProjects = 0;
        $totalOverdueTasks = 0;

        foreach ($projects as $project) {
            $dto = $this->evaluateProject($project, $allPersonnel, $allResources);

            if ($dto->status === 'critical') {
                $criticalProjects++;
            } elseif ($dto->status === 'warning') {
                $warningProjects++;
            } else {
                $healthyProjects++;
            }

            $totalOverdueTasks += (int) ($dto->timelineSuggestion['overdue_tasks_count'] ?? 0);

            $matrix[] = [
                'id' => $project->id,
                'project_code' => $project->project_code,
                'name' => $project->name,
                'client_name' => $project->client_name,
                'project_type' => $project->project_type ?: 'General',
                'project_status' => $project->status,
                'overall_status' => $dto->status,
                'overall_label' => $this->statusLabel($dto->status),
                'operational_readiness' => $dto->operationalReadiness,
                'contract_price' => (float) $project->contract_price,
                'actual_spend' => (float) $project->actual_spend,
                'remaining_budget' => (float) $project->remaining_budget,
                'spend_percentage' => (float) $project->spend_percentage,
                'start_date' => $project->start_date ? $project->start_date->format('M j, Y') : 'Not set',
                'target_date' => $project->target_completion_date ? $project->target_completion_date->format('M j, Y') : 'Not set',
                'specs' => [
                    'project_type' => $project->project_type ?: 'Passenger Elevator',
                    'rated_capacity' => $project->rated_capacity ?: '1000 kg',
                    'lifting_speed' => $project->lifting_speed ?: '1.0 m/s',
                    'no_of_stops' => (int) ($project->no_of_stops ?: 4),
                    'door_operation' => $project->door_operation ?: '2-Panel Center Opening',
                    'power_source' => $project->power_source ?: '380V / 3-Phase / 60Hz',
                    'operation' => $project->operation ?: 'Simplex Selective Collective',
                    'cage_dimension' => $project->cage_dimension ?: 'Standard',
                ],
                'show_url' => route('projects.show', $project->id),
                'badges' => $dto->badges,
                'budget' => $dto->budgetSuggestion,
                'schedule' => $dto->timelineSuggestion,
                'manpower' => $dto->manpowerSuggestion,
                'inventory' => $dto->materialSuggestion,
                'recommendations' => $dto->recommendations,
            ];
        }

        $catalogAlerts = $this->getCatalogInventoryAlerts($allResources);
        $availablePersonnelCount = $allPersonnel->whereNull('project_id')->count();
        $assignedPersonnelCount = $allPersonnel->whereNotNull('project_id')->count();

        return [
            'matrix' => $matrix,
            'kpis' => [
                'total_projects' => $projects->count(),
                'healthy_projects' => $healthyProjects,
                'warning_projects' => $warningProjects,
                'critical_projects' => $criticalProjects,
                'total_overdue_tasks' => $totalOverdueTasks,
                'available_personnel' => $availablePersonnelCount,
                'assigned_personnel' => $assignedPersonnelCount,
                'total_personnel' => $allPersonnel->count(),
                'catalog_alerts_count' => count($catalogAlerts),
                'stockout_count' => collect($catalogAlerts)->where('status', 'critical')->count(),
                'low_stock_count' => collect($catalogAlerts)->where('status', 'warning')->count(),
            ],
            'catalog_alerts' => $catalogAlerts,
        ];
    }

    /**
     * Rule 1 (Budget): Burn ratio (actual cost ÷ budget) vs. elapsed-time ratio + 15% buffer.
     *
     * @return array<string, mixed>
     */
    public function evaluateBudgetRule(Project $project): array
    {
        $bufferPct = (float) config('decision_support.budget.burn_buffer_percent', 15.0);
        $atRiskPct = (float) config('decision_support.budget.at_risk_utilization_percent', 85.0);
        $criticalPct = (float) config('decision_support.budget.critical_utilization_percent', 100.0);

        $contractPrice = max(0.0, (float) $project->contract_price);
        $actualSpend = max(0.0, (float) $project->actual_spend);
        $remainingBudget = $contractPrice - $actualSpend;

        $burnRatioPct = $contractPrice > 0
            ? round(($actualSpend / $contractPrice) * 100, 1)
            : ($actualSpend > 0 ? 100.0 : 0.0);

        [$elapsedRatioPct, $elapsedDays, $totalDays] = $this->calculateElapsedTimeMetrics($project);
        $allowedBurnPct = min(100.0, round($elapsedRatioPct + $bufferPct, 1));
        $burnVariancePct = round($burnRatioPct - $elapsedRatioPct, 1);

        // Projected total spend & overrun based on current burn trajectory
        $effectiveProgressRatio = max($elapsedRatioPct / 100.0, 0.15);
        $projectedFinalSpend = ($project->status !== 'completed' && $burnVariancePct > $bufferPct)
            ? round($actualSpend / $effectiveProgressRatio, 2)
            : $actualSpend;
        $projectedOverrun = max(0.0, round($projectedFinalSpend - $contractPrice, 2));

        $status = 'ok';
        $headline = 'Budget burn rate is aligned with project timeline.';
        $recommendation = 'Continue standard expenditure monitoring; budget utilization is within safe operational thresholds.';

        if ($burnRatioPct > $criticalPct) {
            $status = 'critical';
            $overrunAmt = abs($remainingBudget);
            $headline = sprintf('Budget Overrun: Actual spend exceeds contract budget by ₱%s (%s%% utilized).', number_format($overrunAmt, 2), number_format($burnRatioPct, 1));
            $recommendation = sprintf('Immediate cost control required: freeze non-essential expenditures and review ₱%s budget deficit.', number_format($overrunAmt, 2));
        } elseif ($project->status !== 'completed' && $burnRatioPct >= $atRiskPct) {
            $status = 'warning';
            $headline = sprintf('At Risk: %s%% of contract budget consumed with ₱%s remaining.', number_format($burnRatioPct, 1), number_format($remainingBudget, 2));
            $recommendation = sprintf('Require manager sign-off on remaining ₱%s balance to prevent a contract overrun.', number_format($remainingBudget, 2));
        } elseif ($project->status !== 'completed' && $burnRatioPct > $allowedBurnPct) {
            $status = 'warning';
            $headline = sprintf(
                'Accelerated Burn Trend: Burn ratio (%s%%) exceeds elapsed time (%s%%) + %s%% buffer.',
                number_format($burnRatioPct, 1),
                number_format($elapsedRatioPct, 1),
                number_format($bufferPct, 0),
            );
            $recommendation = sprintf(
                'Audit recent cost entries; at the current burn pace, projected spend reaches ₱%s (projected overrun: ₱%s).',
                number_format($projectedFinalSpend, 2),
                number_format($projectedOverrun, 2),
            );
        }

        return [
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'contract_price' => $contractPrice,
            'actual_spend' => $actualSpend,
            'remaining_budget' => $remainingBudget,
            'burn_ratio_pct' => $burnRatioPct,
            'elapsed_time_pct' => $elapsedRatioPct,
            'buffer_pct' => $bufferPct,
            'allowed_burn_pct' => $allowedBurnPct,
            'burn_variance_pct' => $burnVariancePct,
            'projected_final_spend' => $projectedFinalSpend,
            'projected_overrun' => $projectedOverrun,
            'elapsed_days' => $elapsedDays,
            'total_planned_days' => $totalDays,
            'formula' => sprintf('Burn Ratio = (₱%s ÷ ₱%s) = %s%% vs. Threshold (%s%% Elapsed + %s%% Buffer = %s%%)', number_format($actualSpend, 2), number_format($contractPrice, 2), number_format($burnRatioPct, 1), number_format($elapsedRatioPct, 1), number_format($bufferPct, 0), number_format($allowedBurnPct, 1)),
            'headline' => $headline,
            'recommendation' => $recommendation,
            'recommendation_item' => [
                'area' => 'Budget & Burn Rate',
                'status' => $status,
                'headline' => $headline,
                'action' => $recommendation,
                'formula' => sprintf('Burn Ratio (%s%%) vs. Elapsed (%s%%) + %s%% Buffer', number_format($burnRatioPct, 1), number_format($elapsedRatioPct, 1), number_format($bufferPct, 0)),
            ],
        ];
    }

    /**
     * Rule 2 (Schedule): Task past due and not completed -> Days overdue and cascading delay to the end date.
     *
     * @return array<string, mixed>
     */
    public function evaluateScheduleRule(Project $project): array
    {
        $criticalOverdueDays = (int) config('decision_support.schedule.critical_overdue_days', 7);
        $approachingDays = (int) config('decision_support.schedule.approaching_deadline_days', 14);
        $today = Carbon::today();

        [$elapsedRatioPct, $elapsedDays, $totalDays] = $this->calculateElapsedTimeMetrics($project);

        $tasks = $project->tasks ?? collect();
        $totalSubtasks = $tasks->sum(fn ($t) => $t->subtasks->count());
        $completedSubtasks = $tasks->sum(fn ($t) => $t->subtasks->where('is_completed', true)->count());
        $taskProgressPct = $totalSubtasks > 0
            ? (int) round(($completedSubtasks / $totalSubtasks) * 100)
            : ($project->status === 'completed' ? 100 : 0);

        $overdueItems = [];
        $maxOverdueDays = 0;

        if ($project->status !== 'completed') {
            foreach ($tasks as $task) {
                $isTaskCompleted = $task->computed_status === 'completed';
                if (! $isTaskCompleted && $task->end_date && Carbon::parse($task->end_date)->startOfDay()->lt($today)) {
                    $daysLate = (int) Carbon::parse($task->end_date)->startOfDay()->diffInDays($today);
                    $maxOverdueDays = max($maxOverdueDays, $daysLate);
                    $overdueItems[] = [
                        'type' => 'Mother Task',
                        'title' => $task->task_name,
                        'due_date' => Carbon::parse($task->end_date)->format('M j, Y'),
                        'days_overdue' => $daysLate,
                        'progress_pct' => $task->progress_percentage,
                    ];
                }

                foreach ($task->subtasks as $subtask) {
                    if (! $subtask->is_completed && $subtask->due_date && Carbon::parse($subtask->due_date)->startOfDay()->lt($today)) {
                        $daysLate = (int) Carbon::parse($subtask->due_date)->startOfDay()->diffInDays($today);
                        $maxOverdueDays = max($maxOverdueDays, $daysLate);
                        $overdueItems[] = [
                            'type' => 'Subtask',
                            'title' => $subtask->title . ' (' . $task->task_name . ')',
                            'due_date' => Carbon::parse($subtask->due_date)->format('M j, Y'),
                            'days_overdue' => $daysLate,
                            'progress_pct' => 0,
                        ];
                    }
                }
            }

            // Also check if the project target_completion_date itself is past due
            if ($project->target_completion_date && Carbon::parse($project->target_completion_date)->startOfDay()->lt($today)) {
                $projDaysLate = (int) Carbon::parse($project->target_completion_date)->startOfDay()->diffInDays($today);
                $maxOverdueDays = max($maxOverdueDays, $projDaysLate);
            }
        }

        $targetDateObj = $project->target_completion_date ? Carbon::parse($project->target_completion_date)->startOfDay() : null;
        $projectedCompletionDate = $targetDateObj
            ? $targetDateObj->copy()->addDays($maxOverdueDays)->format('M j, Y')
            : 'Not set';

        $daysUntilTarget = ($targetDateObj && $targetDateObj->gte($today))
            ? (int) $today->diffInDays($targetDateObj)
            : 0;

        $status = 'ok';
        $headline = 'Project schedule and task milestones are on track.';
        $recommendation = 'Maintain current task execution cadence across scheduled installation phases.';

        if ($project->status !== 'completed') {
            if ($project->status === 'delayed' || $maxOverdueDays >= $criticalOverdueDays || ($targetDateObj && $targetDateObj->lt($today))) {
                $status = 'critical';
                $headline = sprintf(
                    'Schedule Delay: %d overdue item(s) · Max %d day(s) past due (Cascading end date: %s).',
                    count($overdueItems),
                    $maxOverdueDays,
                    $projectedCompletionDate,
                );
                $recommendation = sprintf(
                    'Prioritize the %d overdue task(s) or reallocate field technicians to recover the +%d day slip.',
                    max(1, count($overdueItems)),
                    $maxOverdueDays,
                );
            } elseif ($maxOverdueDays > 0 || count($overdueItems) > 0) {
                $status = 'warning';
                $headline = sprintf(
                    'Minor Task Slip: %d task/subtask(s) overdue by up to %d day(s) (Projected end: %s).',
                    count($overdueItems),
                    $maxOverdueDays,
                    $projectedCompletionDate,
                );
                $recommendation = 'Fast-track overdue subtasks this week to prevent cascading delay to the final handover date.';
            } elseif ($targetDateObj && $daysUntilTarget <= $approachingDays && $taskProgressPct < 70) {
                $status = 'warning';
                $headline = sprintf(
                    'Approaching Deadline: %d day(s) remaining until %s with %d%% task completion.',
                    $daysUntilTarget,
                    $targetDateObj->format('M j, Y'),
                    $taskProgressPct,
                );
                $recommendation = 'Increase daily task throughput or assign additional installation crew before target completion date.';
            }
        }

        return [
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'elapsed_time_pct' => $elapsedRatioPct,
            'elapsed_days' => $elapsedDays,
            'total_planned_days' => $totalDays,
            'task_progress_pct' => $taskProgressPct,
            'total_subtasks' => $totalSubtasks,
            'completed_subtasks' => $completedSubtasks,
            'overdue_tasks_count' => count($overdueItems),
            'overdue_items' => array_slice($overdueItems, 0, 8),
            'max_overdue_days' => $maxOverdueDays,
            'target_completion_date' => $targetDateObj ? $targetDateObj->format('M j, Y') : 'Not set',
            'projected_completion_date' => $projectedCompletionDate,
            'formula' => sprintf(
                'Cascading End Date = Target Date (%s) + Max Overdue (%d days) = %s',
                $targetDateObj ? $targetDateObj->format('M j, Y') : 'N/A',
                $maxOverdueDays,
                $projectedCompletionDate,
            ),
            'headline' => $headline,
            'recommendation' => $recommendation,
            'recommendation_item' => [
                'area' => 'Schedule & Milestones',
                'status' => $status,
                'headline' => $headline,
                'action' => $recommendation,
                'formula' => sprintf('Max Overdue = %d days · Progress = %d%% (%d/%d subtasks)', $maxOverdueDays, $taskProgressPct, $completedSubtasks, $totalSubtasks),
            ],
        ];
    }

    /**
     * Rule 3 (Manpower): Check role coverage & overlapping assignment collisions, and rank available staff
     * by (1) Role match for missing core roles, (2) Lowest historical project load, (3) Employee ID.
     *
     * @param Collection<int, Personnel> $allPersonnel
     * @return array<string, mixed>
     */
    public function evaluateManpowerRule(Project $project, Collection $allPersonnel): array
    {
        /** @var list<string> $coreRoles */
        $coreRoles = config('decision_support.manpower.core_project_roles', [
            Personnel::EXPERTISE_PROJECT_MANAGER,
            Personnel::EXPERTISE_SITE_ENGINEER,
            Personnel::EXPERTISE_FOREMAN,
            Personnel::EXPERTISE_SAFETY_OFFICER,
            Personnel::EXPERTISE_TECHNICIAN,
        ]);
        $maxSuggested = (int) config('decision_support.manpower.max_suggested_crew', 8);

        $assignedPersonnel = $project->personnel->where('project_id', $project->id)->values();
        $assignedRoles = $assignedPersonnel->pluck('expertise')->unique()->values()->all();
        $missingRoles = array_values(array_diff($coreRoles, $assignedRoles));

        // Check for overlapping active assignments / schedule contention across projects
        $collisions = [];
        foreach ($assignedPersonnel as $person) {
            $fullPerson = $allPersonnel->firstWhere('id', $person->id) ?? $person;
            $activeHistories = ($fullPerson->assignmentHistory ?? collect())
                ->whereNull('released_at')
                ->where('project_id', '!=', $project->id);

            foreach ($activeHistories as $hist) {
                if ($hist->project && ! $hist->project->trashed() && $hist->project->status !== 'completed') {
                    $collisions[] = [
                        'employee_id' => $person->employee_id,
                        'full_name' => $person->full_name,
                        'expertise' => $person->expertise,
                        'conflicting_project' => $hist->project->project_code . ' — ' . $hist->project->name,
                    ];
                }
            }
        }

        // Rank available (unassigned) staff by:
        // 1) Role match (fills a missing core role on this project)
        // 2) Lowest historical assignment load (assignment_history_count ASC)
        // 3) Employee ID (employee_id ASC)
        $availableStaff = $allPersonnel
            ->filter(fn (Personnel $p) => $p->project_id === null && ! $p->trashed())
            ->map(function (Personnel $p) use ($missingRoles) {
                $isMissingRoleMatch = in_array($p->expertise, $missingRoles, true);
                $loadCount = (int) ($p->assignment_history_count ?? $p->assignmentHistory->count());

                return [
                    'id' => $p->id,
                    'employee_id' => $p->employee_id,
                    'full_name' => $p->full_name,
                    'expertise' => $p->expertise,
                    'role_match' => $isMissingRoleMatch,
                    'historical_load' => $loadCount,
                    'rank_reason' => $isMissingRoleMatch
                        ? sprintf('Fills missing %s role · %d prior project(s)', $p->expertise, $loadCount)
                        : sprintf('Available %s · %d prior project(s)', $p->expertise, $loadCount),
                ];
            })
            ->sort(function (array $a, array $b): int {
                if ($a['role_match'] !== $b['role_match']) {
                    return $b['role_match'] <=> $a['role_match'];
                }
                if ($a['historical_load'] !== $b['historical_load']) {
                    return $a['historical_load'] <=> $b['historical_load'];
                }

                return strcmp($a['employee_id'], $b['employee_id']);
            })
            ->values()
            ->take($maxSuggested)
            ->all();

        $status = 'ok';
        $headline = sprintf('%d personnel assigned · Core engineering roles covered.', $assignedPersonnel->count());
        $recommendation = 'Crew allocation is balanced; no immediate manpower reassignment required.';

        if ($project->status !== 'completed') {
            if (! empty($collisions)) {
                $status = 'critical';
                $headline = sprintf('Workforce Collision: %d assigned personnel have overlapping active project records.', count($collisions));
                $recommendation = 'Resolve overlapping project assignments in the Manpower module to prevent scheduling conflicts.';
            } elseif ($assignedPersonnel->isEmpty()) {
                $status = 'critical';
                $headline = 'Unstaffed Active Project: 0 field personnel currently assigned.';
                $recommendation = 'Assign a Project Manager, Site Engineer, and installation crew from the Suggested Crew List below.';
            } elseif (! empty($missingRoles)) {
                $status = 'warning';
                $headline = sprintf(
                    'Role Gap: %d core role(s) unassigned (%s).',
                    count($missingRoles),
                    implode(', ', array_slice($missingRoles, 0, 3)),
                );
                $recommendation = sprintf(
                    'Consider assigning available %s personnel from the ranked suggestions.',
                    implode(', ', array_slice($missingRoles, 0, 2)),
                );
            }
        }

        return [
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'assigned_count' => $assignedPersonnel->count(),
            'assigned_crew' => $assignedPersonnel->map(fn (Personnel $p) => [
                'employee_id' => $p->employee_id,
                'full_name' => $p->full_name,
                'expertise' => $p->expertise,
                'date_assigned' => $p->date_assigned ? $p->date_assigned->format('M j, Y') : '—',
            ])->values()->all(),
            'core_roles' => $coreRoles,
            'covered_roles' => array_values(array_intersect($coreRoles, $assignedRoles)),
            'missing_roles' => $missingRoles,
            'collisions' => $collisions,
            'suggested_crew' => $availableStaff,
            'formula' => 'Rank = 1. Role Match (Missing Core Role) → 2. Lowest Historical Load (ASC) → 3. Employee ID (ASC)',
            'headline' => $headline,
            'recommendation' => $recommendation,
            'recommendation_item' => [
                'area' => 'Manpower & Crew Allocation',
                'status' => $status,
                'headline' => $headline,
                'action' => $recommendation,
                'formula' => sprintf('Assigned: %d · Core Roles Covered: %d/%d', $assignedPersonnel->count(), count($coreRoles) - count($missingRoles), count($coreRoles)),
            ],
        ];
    }

    /**
     * Rule 4 (Inventory & Materials):
     * - Deficit = Target Reserve / Planned Allocation − Available Stock -> Reorder Qty = ceil(Deficit * (1 + 10% buffer))
     * - Material Cost Share vs. Contract Budget check.
     *
     * @param Collection<int, Resource> $allResources
     * @return array<string, mixed>
     */
    public function evaluateInventoryRule(Project $project, Collection $allResources): array
    {
        $lowStockThreshold = (int) config('decision_support.inventory.low_stock_threshold', 5);
        $safetyBufferRatio = (float) config('decision_support.inventory.safety_buffer_ratio', 0.10);
        $materialShareWarningPct = (float) config('decision_support.budget.material_share_warning_percent', 70.0);

        $costs = $project->costs ?? collect();
        $materialCosts = $costs->where('cost_type', 'materials');
        $totalMaterialSpend = (float) $materialCosts->sum('amount');
        $contractPrice = max(0.0, (float) $project->contract_price);
        $materialSharePct = $contractPrice > 0
            ? round(($totalMaterialSpend / $contractPrice) * 100, 1)
            : 0.0;

        // Inspect materials linked to this project via cost entries
        $projectResourceIds = $materialCosts->pluck('resource_id')->filter()->unique()->values()->all();
        $projectMaterialItems = [];
        $stockAlertsCount = 0;
        $hasCriticalStockout = false;

        foreach ($projectResourceIds as $resId) {
            /** @var Resource|null $res */
            $res = $allResources->firstWhere('id', $resId);
            if (! $res) {
                continue;
            }

            $usedQty = (int) $materialCosts->where('resource_id', $resId)->sum('quantity');
            $availQty = (int) $res->quantity_available;
            $targetReserve = $res->demand_status === 'fast-moving'
                ? (int) config('decision_support.inventory.fast_moving_target_stock', 10)
                : (int) config('decision_support.inventory.slow_moving_target_stock', 5);

            $deficit = max(0, $targetReserve - $availQty);
            $reorderQty = $deficit > 0 ? (int) ceil($deficit * (1 + $safetyBufferRatio)) : 0;

            $itemStatus = 'ok';
            if ($availQty <= 0) {
                $itemStatus = 'critical';
                $hasCriticalStockout = true;
                $stockAlertsCount++;
            } elseif ($availQty < $lowStockThreshold) {
                $itemStatus = 'warning';
                $stockAlertsCount++;
            }

            $projectMaterialItems[] = [
                'resource_code' => $res->resource_code,
                'name' => $res->name,
                'demand_status' => $res->demand_status ?: 'fast-moving',
                'used_on_project' => $usedQty,
                'available_stock' => $availQty,
                'target_reserve' => $targetReserve,
                'deficit' => $deficit,
                'reorder_qty' => $reorderQty,
                'unit_price' => (float) $res->price,
                'status' => $itemStatus,
            ];
        }

        $status = 'ok';
        $headline = sprintf('Material spend is %s%% of contract budget (₱%s).', number_format($materialSharePct, 1), number_format($totalMaterialSpend, 2));
        $recommendation = 'Material inventory levels and project material cost share are within normal bounds.';

        if ($totalMaterialSpend > $contractPrice && $contractPrice > 0) {
            $status = 'critical';
            $overrunPct = round($materialSharePct - 100.0, 1);
            $headline = sprintf('Material Cost Breach: Material expenses alone exceed contract price by +%s%%.', number_format($overrunPct, 1));
            $recommendation = 'Audit material requisitions and supplier unit pricing immediately.';
        } elseif ($hasCriticalStockout && $project->status !== 'completed') {
            $status = 'critical';
            $headline = sprintf('Stockout Alert: %d project material(s) have 0 remaining catalog stock.', $stockAlertsCount);
            $recommendation = 'Replenish depleted catalog items using the recommended reorder quantities (Deficit + 10% safety buffer).';
        } elseif ($materialSharePct >= $materialShareWarningPct && $project->status !== 'completed') {
            $status = 'warning';
            $headline = sprintf('High Material Cost Share: Materials consume %s%% of total contract budget.', number_format($materialSharePct, 1));
            $recommendation = 'Monitor remaining labor and subcontractor allocations closely; material costs exceed the 70% guideline.';
        } elseif ($stockAlertsCount > 0 && $project->status !== 'completed') {
            $status = 'warning';
            $headline = sprintf('Low Catalog Stock: %d material(s) used on this project are below %d units.', $stockAlertsCount, $lowStockThreshold);
            $recommendation = 'Place advance restock orders for low-stock components in case additional units are required.';
        }

        return [
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'total_material_spend' => $totalMaterialSpend,
            'material_budget_share_pct' => $materialSharePct,
            'stock_alerts_count' => $stockAlertsCount,
            'project_materials' => $projectMaterialItems,
            'safety_buffer_pct' => round($safetyBufferRatio * 100, 0),
            'formula' => 'Deficit = max(0, Target Reserve − Available Stock); Reorder Qty = ⌈Deficit × 1.10⌉',
            'headline' => $headline,
            'recommendation' => $recommendation,
            'recommendation_item' => [
                'area' => 'Materials & Inventory',
                'status' => $status,
                'headline' => $headline,
                'action' => $recommendation,
                'formula' => sprintf('Material Spend Share = ₱%s / ₱%s (%s%%)', number_format($totalMaterialSpend, 2), number_format($contractPrice, 2), number_format($materialSharePct, 1)),
            ],
        ];
    }

    /**
     * Evaluate catalog-wide low stock and stockout items with deterministic reorder quantities.
     *
     * @param Collection<int, Resource> $allResources
     * @return list<array<string, mixed>>
     */
    public function getCatalogInventoryAlerts(Collection $allResources): array
    {
        $lowStockThreshold = (int) config('decision_support.inventory.low_stock_threshold', 5);
        $safetyBufferRatio = (float) config('decision_support.inventory.safety_buffer_ratio', 0.10);

        $alerts = [];

        foreach ($allResources as $res) {
            $avail = (int) $res->quantity_available;
            if ($avail >= $lowStockThreshold) {
                continue;
            }

            $targetReserve = ($res->demand_status === 'fast-moving')
                ? (int) config('decision_support.inventory.fast_moving_target_stock', 10)
                : (int) config('decision_support.inventory.slow_moving_target_stock', 5);

            $deficit = max(1, $targetReserve - $avail);
            $reorderQty = (int) ceil($deficit * (1 + $safetyBufferRatio));
            $estimatedReorderCost = round($reorderQty * (float) ($res->unit_cost > 0 ? $res->unit_cost : $res->price), 2);

            $alerts[] = [
                'id' => $res->id,
                'resource_code' => $res->resource_code,
                'name' => $res->name,
                'model' => $res->model ?: 'Standard',
                'demand_status' => $res->demand_status ?: 'fast-moving',
                'available_stock' => $avail,
                'target_reserve' => $targetReserve,
                'deficit' => $deficit,
                'reorder_qty' => $reorderQty,
                'unit_price' => (float) $res->price,
                'estimated_reorder_cost' => $estimatedReorderCost,
                'status' => $avail <= 0 ? 'critical' : 'warning',
                'status_label' => $avail <= 0 ? 'Stockout (0)' : 'Low Stock',
                'formula' => sprintf('Deficit = %d − %d = %d → Reorder = ⌈%d × 1.10⌉ = %d units', $targetReserve, $avail, $deficit, $deficit, $reorderQty),
                'show_url' => route('resources.show', $res->id),
            ];
        }

        usort($alerts, fn (array $a, array $b) => $a['available_stock'] <=> $b['available_stock']);

        return $alerts;
    }

    /**
     * Calculate elapsed time percentage, elapsed days, and total planned days for a project.
     *
     * @return array{0: float, 1: int, 2: int}
     */
    private function calculateElapsedTimeMetrics(Project $project): array
    {
        if ($project->status === 'completed') {
            return [100.0, 100, 100];
        }

        $today = Carbon::today();
        $start = $project->start_date ? Carbon::parse($project->start_date)->startOfDay() : ($project->created_at ? Carbon::parse($project->created_at)->startOfDay() : $today);
        $end = $project->target_completion_date ? Carbon::parse($project->target_completion_date)->startOfDay() : $start->copy()->addDays(90);

        $totalDays = max(1, (int) $start->diffInDays($end));
        if ($today->lt($start)) {
            $elapsedDays = 0;
        } else {
            $elapsedDays = (int) $start->diffInDays($today);
        }

        $ratioPct = min(100.0, max(0.0, round(($elapsedDays / $totalDays) * 100, 1)));

        return [$ratioPct, $elapsedDays, $totalDays];
    }

    /**
     * Return the more severe status between two statuses (`ok` < `warning` < `critical`).
     */
    private function worstStatus(string $a, string $b): string
    {
        $rank = ['ok' => 1, 'warning' => 2, 'critical' => 3];

        return ($rank[$a] ?? 1) >= ($rank[$b] ?? 1) ? $a : $b;
    }

    /**
     * Human-readable status badge label.
     */
    private function statusLabel(string $status): string
    {
        return match ($status) {
            'critical' => 'Critical',
            'warning' => 'Warning',
            default => 'Healthy',
        };
    }
}
