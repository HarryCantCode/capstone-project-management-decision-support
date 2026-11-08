<?php

namespace App\Services\DecisionSupport;

use App\Models\Personnel;
use App\Models\Project;
use App\Models\Resource;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * ScenarioSimulatorService
 *
 * Pure in-memory What-If Scenario Simulator for the Decision Support System (DSS).
 * Never writes to the database. Supports:
 * 1. Project Feasibility ("Can we take this project?")
 * 2. Delay Propagation ("What if an active project slips by X days?")
 * 3. Budget / Price Adjustment ("What if material or labor/service costs change?")
 */
class ScenarioSimulatorService
{
    /**
     * Simulate Project Feasibility ("Can we take this project?").
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
        $safetyBufferRatio = (float) config('decision_support.inventory.safety_buffer_ratio', 0.10);
        $materialWarnPct = (float) config('decision_support.budget.material_share_warning_percent', 70.0);

        $contractBudget = max(0.0, (float) ($input['contract_budget'] ?? 0));
        $startDate = ! empty($input['start_date']) ? Carbon::parse($input['start_date'])->startOfDay() : Carbon::today();
        $endDate = ! empty($input['target_completion_date'])
            ? Carbon::parse($input['target_completion_date'])->startOfDay()
            : $startDate->copy()->addDays(60);

        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy()->addDays(30);
        }

        $plannedDays = max(1, (int) $startDate->diffInDays($endDate));

        // 1. Manpower Feasibility & Gap Table
        /** @var array<int, array<string, mixed>> $requiredRoles */
        $requiredRoles = $input['required_roles'] ?? [];
        $roleGaps = [];
        $totalReqHeadcount = 0;
        $totalManpowerDeficit = 0;
        $totalReleasableUsed = 0;
        $suggestedCrew = [];

        $unassignedPool = $allPersonnel->filter(fn (Personnel $p) => $p->project_id === null && ! $p->trashed());
        $releasablePool = $allPersonnel->filter(function (Personnel $p) use ($startDate) {
            if ($p->project_id === null || $p->trashed() || ! $p->project) {
                return false;
            }
            if ($p->project->status === 'completed') {
                return true;
            }
            if ($p->project->target_completion_date) {
                return Carbon::parse($p->project->target_completion_date)->startOfDay()->lt($startDate);
            }

            return false;
        });

        foreach ($requiredRoles as $roleReq) {
            $expertise = (string) ($roleReq['expertise'] ?? '');
            $needed = max(0, (int) ($roleReq['count'] ?? 0));
            if ($expertise === '' || $needed <= 0) {
                continue;
            }

            $totalReqHeadcount += $needed;

            $availableNow = $unassignedPool->where('expertise', $expertise)->count();
            $releasableByStart = $releasablePool->where('expertise', $expertise)->count();
            $effectiveAvailable = $availableNow + $releasableByStart;
            $deficit = max(0, $needed - $effectiveAvailable);
            $totalManpowerDeficit += $deficit;

            if ($availableNow < $needed && $effectiveAvailable >= $needed) {
                $totalReleasableUsed += ($needed - $availableNow);
            }

            $rowStatus = 'ok';
            if ($deficit > 0) {
                $rowStatus = 'critical';
            } elseif ($availableNow < $needed) {
                $rowStatus = 'warning';
            }

            $roleGaps[] = [
                'expertise' => $expertise,
                'required' => $needed,
                'available_now' => $availableNow,
                'free_by_start' => $releasableByStart,
                'deficit' => $deficit,
                'status' => $rowStatus,
                'formula' => sprintf('Gap = max(0, %d Required − (%d Available + %d Free by Start)) = %d', $needed, $availableNow, $releasableByStart, $deficit),
            ];

            // Pick top suggested personnel for this role
            $candidates = $unassignedPool
                ->where('expertise', $expertise)
                ->sortBy([
                    fn (Personnel $a, Personnel $b) => ($a->assignment_history_count ?? 0) <=> ($b->assignment_history_count ?? 0),
                    fn (Personnel $a, Personnel $b) => strcmp($a->employee_id, $b->employee_id),
                ])
                ->take($needed);

            foreach ($candidates as $cand) {
                $suggestedCrew[] = [
                    'employee_id' => $cand->employee_id,
                    'full_name' => $cand->full_name,
                    'expertise' => $cand->expertise,
                    'historical_load' => (int) ($cand->assignment_history_count ?? 0),
                    'availability' => 'Available Now',
                ];
            }
        }

        // 2. Materials Stock & Cost Gap Table
        /** @var array<int, array<string, mixed>> $requiredMaterials */
        $requiredMaterials = $input['required_materials'] ?? [];
        $materialGaps = [];
        $totalMaterialCost = 0.0;
        $totalMaterialDeficitItems = 0;

        foreach ($requiredMaterials as $matReq) {
            $resId = (int) ($matReq['resource_id'] ?? 0);
            $qty = max(0, (int) ($matReq['quantity'] ?? 0));
            if ($resId <= 0 || $qty <= 0) {
                continue;
            }

            /** @var Resource|null $res */
            $res = $allResources->firstWhere('id', $resId);
            if (! $res) {
                continue;
            }

            $avail = (int) $res->quantity_available;
            $deficit = max(0, $qty - $avail);
            $reorderQty = $deficit > 0 ? (int) ceil($deficit * (1 + $safetyBufferRatio)) : 0;
            $unitPrice = (float) $res->price;
            $lineCost = round($qty * $unitPrice, 2);
            $totalMaterialCost += $lineCost;

            if ($deficit > 0) {
                $totalMaterialDeficitItems++;
            }

            $rowStatus = $deficit > 0 ? 'critical' : (($avail - $qty) < 5 ? 'warning' : 'ok');

            $materialGaps[] = [
                'resource_id' => $res->id,
                'resource_code' => $res->resource_code,
                'name' => $res->name,
                'required_qty' => $qty,
                'available_stock' => $avail,
                'remaining_after' => $avail - $qty,
                'deficit' => $deficit,
                'reorder_qty' => $reorderQty,
                'unit_price' => $unitPrice,
                'line_cost' => $lineCost,
                'status' => $rowStatus,
                'formula' => $deficit > 0
                    ? sprintf('Deficit = %d − %d = %d → Reorder = ⌈%d × 1.10⌉ = %d units', $qty, $avail, $deficit, $deficit, $reorderQty)
                    : sprintf('Sufficient Stock (%d avail − %d req = %d remaining)', $avail, $qty, $avail - $qty),
            ];
        }

        // 3. Budget & Margin Evaluation
        $remainingBudgetAfterMaterials = round($contractBudget - $totalMaterialCost, 2);
        $materialBudgetSharePct = $contractBudget > 0
            ? round(($totalMaterialCost / $contractBudget) * 100, 1)
            : ($totalMaterialCost > 0 ? 100.0 : 0.0);

        // Overall Rating: Green (ok), Yellow (warning), Red (critical)
        $status = 'ok';
        $ratingColor = 'Green';
        $ratingLabel = 'Feasible — Ready to Accept';

        if ($totalManpowerDeficit > 0 || $totalMaterialDeficitItems > 0 || ($contractBudget > 0 && $totalMaterialCost > $contractBudget)) {
            $status = 'critical';
            $ratingColor = 'Red';
            $ratingLabel = 'Not Feasible — Critical Resource or Budget Gaps';
        } elseif ($totalReleasableUsed > 0 || $materialBudgetSharePct >= $materialWarnPct) {
            $status = 'warning';
            $ratingColor = 'Yellow';
            $ratingLabel = 'Conditionally Feasible — Requires Reallocation / Tight Margin';
        }

        return [
            'status' => $status,
            'rating_color' => $ratingColor,
            'rating_label' => $ratingLabel,
            'start_date' => $startDate->format('M j, Y'),
            'target_completion_date' => $endDate->format('M j, Y'),
            'planned_days' => $plannedDays,
            'contract_budget' => $contractBudget,
            'total_material_cost' => $totalMaterialCost,
            'remaining_budget_margin' => $remainingBudgetAfterMaterials,
            'material_budget_share_pct' => $materialBudgetSharePct,
            'total_required_headcount' => $totalReqHeadcount,
            'total_manpower_deficit' => $totalManpowerDeficit,
            'total_material_deficit_items' => $totalMaterialDeficitItems,
            'role_gaps' => $roleGaps,
            'material_gaps' => $materialGaps,
            'suggested_crew' => $suggestedCrew,
        ];
    }

    /**
     * Simulate Delay Propagation ("What if an active project slips by X days?").
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
        $slipDays = max(0, min(365, $slipDays));
        $project->loadMissing(['personnel', 'tasks.subtasks']);

        $origTarget = $project->target_completion_date
            ? Carbon::parse($project->target_completion_date)->startOfDay()
            : Carbon::today()->addDays(30);
        $newTarget = $origTarget->copy()->addDays($slipDays);

        $assignedCrew = $project->personnel->where('project_id', $project->id)->values();
        $lockedRoles = $assignedCrew->pluck('expertise')->unique()->values()->all();

        $affectedPersonnel = $assignedCrew->map(fn (Personnel $p) => [
            'employee_id' => $p->employee_id,
            'full_name' => $p->full_name,
            'expertise' => $p->expertise,
            'original_release' => $origTarget->format('M j, Y'),
            'delayed_release' => $newTarget->format('M j, Y'),
            'slip_days' => $slipDays,
        ])->all();

        // Identify downstream / concurrent active or pending projects whose timeline overlaps
        // with the slip window [origTarget, newTarget] or starts before newTarget
        $downstreamImpacts = [];
        foreach ($allProjects as $other) {
            if ($other->id === $project->id || $other->status === 'completed' || $other->trashed()) {
                continue;
            }

            $otherStart = $other->start_date ? Carbon::parse($other->start_date)->startOfDay() : Carbon::today();
            $otherEnd = $other->target_completion_date ? Carbon::parse($other->target_completion_date)->startOfDay() : $otherStart->copy()->addDays(60);

            // Check if the other project overlaps with the extended window [origTarget, newTarget]
            $overlapsWindow = $otherStart->lte($newTarget) && $otherEnd->gte($origTarget);
            if (! $overlapsWindow) {
                continue;
            }

            $overlapDays = max(1, (int) max($otherStart, $origTarget)->diffInDays(min($otherEnd, $newTarget)) + 1);

            $downstreamImpacts[] = [
                'project_code' => $other->project_code,
                'name' => $other->name,
                'status' => $other->status,
                'start_date' => $otherStart->format('M j, Y'),
                'target_date' => $otherEnd->format('M j, Y'),
                'overlap_days' => min($slipDays, $overlapDays),
                'competing_roles' => ! empty($lockedRoles) ? implode(', ', array_slice($lockedRoles, 0, 3)) : 'General Crew',
                'impact_level' => $other->status === 'pending' ? 'warning' : 'critical',
            ];
        }

        $status = $slipDays === 0
            ? 'ok'
            : (($slipDays >= 14 || count($downstreamImpacts) > 0) ? 'critical' : 'warning');

        return [
            'project_code' => $project->project_code,
            'project_name' => $project->name,
            'slip_days' => $slipDays,
            'original_target_date' => $origTarget->format('M j, Y'),
            'new_target_date' => $newTarget->format('M j, Y'),
            'affected_personnel_count' => count($affectedPersonnel),
            'affected_personnel' => $affectedPersonnel,
            'downstream_projects_count' => count($downstreamImpacts),
            'downstream_projects' => $downstreamImpacts,
            'status' => $status,
            'formula' => sprintf(
                'New Target Date = %s + %d slip day(s) = %s · %d crew member(s) held',
                $origTarget->format('M j, Y'),
                $slipDays,
                $newTarget->format('M j, Y'),
                count($affectedPersonnel),
            ),
        ];
    }

    /**
     * Simulate Supplier Procurement & Lead-Time Trade-off ("Compare suppliers for best cost & delivery schedule").
     * Pure in-memory multi-criteria decision analysis (MCDA).
     *
     * @param Project|null $project
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function simulateSupplierComparison(?Project $project, array $input): array
    {
        $materialName = trim((string) ($input['material_name'] ?? 'Elevator Component'));
        $requiredQty = max(1, (int) ($input['required_quantity'] ?? 1));
        $orderDate = ! empty($input['order_date'])
            ? Carbon::parse($input['order_date'])->startOfDay()
            : Carbon::today();
        $targetArrivalDate = ! empty($input['required_by_date'])
            ? Carbon::parse($input['required_by_date'])->startOfDay()
            : ($project && $project->target_completion_date
                ? Carbon::parse($project->target_completion_date)->startOfDay()->subDays(14)
                : $orderDate->copy()->addDays(21));
        $windowDays = (int) $orderDate->diffInDays($targetArrivalDate, false);

        $costWeight = max(0.0, (float) ($input['cost_weight'] ?? 0.55));
        $leadTimeWeight = max(0.0, (float) ($input['lead_time_weight'] ?? 0.45));
        $totalWeight = $costWeight + $leadTimeWeight;
        if ($totalWeight <= 0) {
            $costWeight = 0.55;
            $leadTimeWeight = 0.45;
            $totalWeight = 1.0;
        }
        $wCost = $costWeight / $totalWeight;
        $wLead = $leadTimeWeight / $totalWeight;

        /** @var array<int, array<string, mixed>> $rawSuppliers */
        $rawSuppliers = $input['suppliers'] ?? [];
        if (empty($rawSuppliers)) {
            $rawSuppliers = [
                [
                    'name' => 'Fuji Elevator Components Corp.',
                    'unit_price' => 185000,
                    'shipping_fee' => 6500,
                    'lead_time_days' => 10,
                    'payment_terms' => '30 Days Net',
                ],
                [
                    'name' => 'Orient Hoist & Industrial Direct',
                    'unit_price' => 142000,
                    'shipping_fee' => 28000,
                    'lead_time_days' => 35,
                    'payment_terms' => '50% DP / 50% Delivery',
                ],
            ];
        }

        $minTotalCost = null;
        $maxTotalCost = null;
        $minLeadDays = null;
        $maxLeadDays = null;

        $parsed = [];
        foreach ($rawSuppliers as $idx => $s) {
            $name = trim((string) ($s['name'] ?? 'Supplier ' . chr(65 + $idx)));
            $unitPrice = max(0.0, (float) ($s['unit_price'] ?? 0));
            $shippingFee = max(0.0, (float) ($s['shipping_fee'] ?? 0));
            $leadDays = max(1, (int) ($s['lead_time_days'] ?? 1));
            $terms = trim((string) ($s['payment_terms'] ?? 'Standard'));

            $totalCost = ($unitPrice * $requiredQty) + $shippingFee;
            $arrivalDate = $orderDate->copy()->addDays($leadDays);
            $latestOrderDate = $targetArrivalDate->copy()->subDays($leadDays);
            $daysDiffToNeed = (int) $arrivalDate->diffInDays($targetArrivalDate, false);
            $isLate = $arrivalDate->gt($targetArrivalDate);
            $delayDays = $isLate ? (int) $targetArrivalDate->diffInDays($arrivalDate) : 0;

            if ($minTotalCost === null || $totalCost < $minTotalCost) {
                $minTotalCost = $totalCost;
            }
            if ($maxTotalCost === null || $totalCost > $maxTotalCost) {
                $maxTotalCost = $totalCost;
            }
            if ($minLeadDays === null || $leadDays < $minLeadDays) {
                $minLeadDays = $leadDays;
            }
            if ($maxLeadDays === null || $leadDays > $maxLeadDays) {
                $maxLeadDays = $leadDays;
            }

            $parsed[] = [
                'id' => $idx + 1,
                'name' => $name,
                'unit_price' => $unitPrice,
                'shipping_fee' => $shippingFee,
                'total_cost' => round($totalCost, 2),
                'lead_time_days' => $leadDays,
                'order_date' => $orderDate->format('M j, Y'),
                'arrival_date' => $arrivalDate->format('M j, Y'),
                'latest_order_date' => $latestOrderDate->format('M j, Y'),
                'payment_terms' => $terms,
                'is_late' => $isLate,
                'delay_days' => $delayDays,
                'days_buffer' => $daysDiffToNeed,
            ];
        }

        $minTotalCost = $minTotalCost ?? 1.0;
        $maxTotalCost = $maxTotalCost ?? 1.0;
        $minLeadDays = $minLeadDays ?? 1;
        $maxLeadDays = $maxLeadDays ?? 1;

        $costRange = max(1.0, $maxTotalCost - $minTotalCost);
        $leadRange = max(1, $maxLeadDays - $minLeadDays);
        $hasAnyOnTime = collect($parsed)->contains(fn (array $p) => ! $p['is_late']);

        $evaluated = [];
        $bestScore = -1.0;
        $bestId = null;

        foreach ($parsed as $item) {
            $costScore = $costRange > 0
                ? round(100 - ((($item['total_cost'] - $minTotalCost) / $costRange) * 100), 1)
                : 100.0;

            $speedScore = $leadRange > 0
                ? round(100 - ((($item['lead_time_days'] - $minLeadDays) / $leadRange) * 100), 1)
                : 100.0;

            if (! $item['is_late']) {
                $effWindow = max(1, $windowDays);
                $fitScore = min(100.0, 70.0 + round(($item['days_buffer'] / $effWindow) * 30.0, 1));
                $leadScore = min(100.0, round(($speedScore * 0.45) + ($fitScore * 0.55), 1));
            } else {
                $leadScore = max(0.0, round(($speedScore * 0.3) - ($item['delay_days'] * 6), 1));
            }

            $rawComposite = ($costScore * $wCost) + ($leadScore * $wLead);
            $compositeScore = $item['is_late']
                ? max(5.0, round($rawComposite - 35.0 - ($item['delay_days'] * 1.5), 1))
                : round($rawComposite, 1);

            $badges = [];
            if ($item['total_cost'] == $minTotalCost) {
                $badges[] = 'Lowest Price';
            }
            if ($item['lead_time_days'] == $minLeadDays) {
                $badges[] = 'Fastest Delivery';
            }

            $status = $item['is_late'] ? 'critical' : ($item['days_buffer'] <= 3 ? 'warning' : 'ok');

            $evaluated[] = array_merge($item, [
                'cost_score' => $costScore,
                'lead_score' => $leadScore,
                'composite_score' => $compositeScore,
                'badges' => $badges,
                'status' => $status,
            ]);

            $isEligibleForBest = $hasAnyOnTime ? ! $item['is_late'] : true;
            if ($isEligibleForBest && $compositeScore > $bestScore) {
                $bestScore = $compositeScore;
                $bestId = $item['id'];
            }
        }

        foreach ($evaluated as &$ev) {
            $ev['is_best_overall'] = ($ev['id'] === $bestId);
        }
        unset($ev);

        return [
            'material_name' => $materialName,
            'required_quantity' => $requiredQty,
            'order_date' => $orderDate->format('M j, Y'),
            'required_by_date' => $targetArrivalDate->format('M j, Y'),
            'procurement_window_days' => $windowDays,
            'suppliers' => $evaluated,
            'summary' => [
                'total_compared' => count($evaluated),
                'best_supplier' => ! empty($evaluated[0]) ? $evaluated[0]['name'] : null,
                'best_score' => ! empty($evaluated[0]) ? $evaluated[0]['composite_score'] : null,
                'price_difference' => ($maxTotalCost - $minTotalCost),
                'days_difference' => ($maxLeadDays - $minLeadDays),
            ],
        ];
    }
}
