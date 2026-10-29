<?php

namespace App\Services;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResourceCatalogService
{
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

            return Resource::create($data);
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
