<?php

namespace App\Services;

use App\Models\ProjectCost;
use App\Models\Resource;
use App\Models\ResourceAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResourceCatalogService
{
    /**
     * Calculate 3-month rolling project usage statistics for one or all resources.
     *
     * A material is classified as 'fast-moving' when it is frequently used across
     * projects within the last 3 months (>= 2 usage events, >= 2 distinct projects,
     * or >= 3 units used in the 3-month window); otherwise 'slow-moving'.
     *
     * @return array<int, array{usage_count: int, projects_count: int, total_quantity: int, computed_status: string}>
     */
    public function getThreeMonthUsageStats(?int $resourceId = null): array
    {
        $cutoffDateTime = now()->subMonths(3)->startOfDay();
        $cutoffDate = $cutoffDateTime->toDateString();

        $resourceIds = $resourceId !== null
            ? [$resourceId]
            : Resource::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $stats = [];
        $projectSets = [];

        foreach ($resourceIds as $id) {
            $stats[$id] = [
                'usage_count' => 0,
                'projects_count' => 0,
                'total_quantity' => 0,
                'computed_status' => 'slow-moving',
            ];
            $projectSets[$id] = [];
        }

        if (empty($resourceIds)) {
            return $stats;
        }

        // 1. ProjectCost records linked to catalog materials in the last 3 months
        $costsQuery = ProjectCost::query()
            ->whereNotNull('resource_id')
            ->whereIn('resource_id', $resourceIds)
            ->where(function ($q) use ($cutoffDate, $cutoffDateTime) {
                $q->whereDate('incurred_date', '>=', $cutoffDate)
                    ->orWhere(function ($sub) use ($cutoffDateTime) {
                        $sub->whereNull('incurred_date')
                            ->where('created_at', '>=', $cutoffDateTime);
                    });
            });

        foreach ($costsQuery->get(['resource_id', 'project_id', 'quantity']) as $cost) {
            $rid = (int) $cost->resource_id;
            if (!isset($stats[$rid])) {
                continue;
            }
            $qty = max(1, (int) ($cost->quantity ?? 1));
            $stats[$rid]['usage_count'] += 1;
            $stats[$rid]['total_quantity'] += $qty;
            if ($cost->project_id) {
                $projectSets[$rid][(int) $cost->project_id] = true;
            }
        }

        // 2. Direct ResourceAllocation records in the last 3 months (excluding auto-created 'Expensed:' duplicates)
        $allocationsQuery = ResourceAllocation::query()
            ->whereIn('resource_id', $resourceIds)
            ->where('created_at', '>=', $cutoffDateTime)
            ->where(function ($q) {
                $q->whereNull('notes')
                    ->orWhere('notes', 'not like', 'Expensed:%');
            });

        foreach ($allocationsQuery->get(['resource_id', 'project_id', 'quantity']) as $alloc) {
            $rid = (int) $alloc->resource_id;
            if (!isset($stats[$rid])) {
                continue;
            }
            $qty = max(1, (int) ($alloc->quantity ?? 1));
            $stats[$rid]['usage_count'] += 1;
            $stats[$rid]['total_quantity'] += $qty;
            if ($alloc->project_id) {
                $projectSets[$rid][(int) $alloc->project_id] = true;
            }
        }

        foreach ($stats as $rid => &$entry) {
            $entry['projects_count'] = count($projectSets[$rid] ?? []);
            $isFastMoving = $entry['usage_count'] >= 2
                || $entry['projects_count'] >= 2
                || $entry['total_quantity'] >= 3;
            $entry['computed_status'] = $isFastMoving ? 'fast-moving' : 'slow-moving';
        }
        unset($entry);

        return $stats;
    }

    /**
     * Synchronize a single material's demand_status based on its 3-month project usage frequency.
     */
    public function syncDemandStatusForResource(int|Resource $resource): string
    {
        $model = $resource instanceof Resource ? $resource : Resource::find($resource);
        if (!$model) {
            return 'slow-moving';
        }

        $stats = $this->getThreeMonthUsageStats((int) $model->id);
        $computedStatus = $stats[(int) $model->id]['computed_status'] ?? 'slow-moving';

        if ($model->demand_status !== $computedStatus) {
            $model->update(['demand_status' => $computedStatus]);
        }

        return $computedStatus;
    }

    /**
     * Synchronize all catalog materials' demand_status from 3-month project usage when
     * project material usage activity exists in the system, and return the 3-month usage map.
     *
     * @return array<int, array{usage_count: int, projects_count: int, total_quantity: int, computed_status: string}>
     */
    public function syncAllCatalogDemandStatusesIfActiveUsage(): array
    {
        $stats = $this->getThreeMonthUsageStats();

        $hasAnyProjectUsage = ProjectCost::query()->whereNotNull('resource_id')->exists()
            || ResourceAllocation::query()->exists();

        if ($hasAnyProjectUsage && !empty($stats)) {
            $resources = Resource::query()->get(['id', 'demand_status']);
            foreach ($resources as $res) {
                $computed = $stats[(int) $res->id]['computed_status'] ?? 'slow-moving';
                if ($res->demand_status !== $computed) {
                    Resource::whereKey($res->id)->update(['demand_status' => $computed]);
                }
            }
        }

        return $stats;
    }
    /**
     * Generate the next atomic DEX-MAT item code.
     * Starts from DEX-MAT001 if none exist.
     */
    public function generateNextItemCode(): string
    {
        return DB::transaction(function (): string {
            // Find highest existing code matching DEX-MAT###
            $codes = Resource::withTrashed()
                ->where('resource_code', 'LIKE', 'DEX-MAT%')
                ->lockForUpdate()
                ->pluck('resource_code');

            $maxNumber = 0;
            foreach ($codes as $code) {
                if (preg_match('/^DEX-MAT(\d+)$/', $code, $matches)) {
                    $num = (int) $matches[1];
                    if ($num > $maxNumber) {
                        $maxNumber = $num;
                    }
                }
            }

            $nextNumber = $maxNumber + 1;

            return sprintf('DEX-MAT%03d', $nextNumber);
        });
    }

    /**
     * Store a new resource material into catalog.
     * Item code is automatically generated following DEX-MAT000 sequence.
     */
    public function createResource(array $data, User $user): Resource
    {
        return DB::transaction(function () use ($data, $user): Resource {
            $data['resource_code'] = $this->generateNextItemCode();
            $data['type'] = $data['type'] ?? 'material';
            $data['created_by'] = $user->id;
            $data['updated_by'] = $user->id;

            $resource = Resource::create($data);

            app(\App\Services\NotificationService::class)->notifyNewMaterial($resource);

            if ((int) $resource->quantity_available <= Resource::LOW_STOCK_THRESHOLD) {
                app(\App\Services\NotificationService::class)->notifyLowStockMaterial($resource);
            }

            return $resource;
        });
    }

    /**
     * Update an existing material in catalog.
     * Item code is fixed/immutable and cannot be modified.
     */
    public function updateResource(Resource $resource, array $data, User $user): Resource
    {
        return DB::transaction(function () use ($resource, $data, $user): Resource {
            $lockedResource = Resource::query()->lockForUpdate()->findOrFail($resource->id);

            // Item code is fixed, do not allow changing it
            unset($data['resource_code']);
            $data['updated_by'] = $user->id;
            $lockedResource->update($data);

            if ((int) $lockedResource->quantity_available <= Resource::LOW_STOCK_THRESHOLD) {
                app(\App\Services\NotificationService::class)->notifyLowStockMaterial($lockedResource);
            }

            return $lockedResource;
        });
    }

    /**
     * Delete a single material.
     */
    public function deleteResource(Resource $resource): bool
    {
        return DB::transaction(function () use ($resource): bool {
            $locked = Resource::query()->lockForUpdate()->findOrFail($resource->id);

            return (bool) $locked->delete();
        });
    }

    /**
     * Bulk delete materials by their IDs.
     */
    public function bulkDeleteResources(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            $count = 0;
            $resources = Resource::query()->whereIn('id', $ids)->lockForUpdate()->get();
            foreach ($resources as $res) {
                if ($res->delete()) {
                    $count++;
                }
            }

            return $count;
        });
    }
}
