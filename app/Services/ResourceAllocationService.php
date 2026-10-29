<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Resource;
use App\Models\ResourceAllocation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ResourceAllocationService
 *
 * Handles the business logic for allocating resources to projects.
 * Ensures data integrity through database transactions and locks.
 */
class ResourceAllocationService
{
    /**
     * Confirm an allocation and decrement stock in one transaction.
     *
     * The row lock prevents two simultaneous office users from allocating the
     * same final unit. Stock is decremented only after availability is checked.
     *
     * @throws ValidationException
     */
    public function allocate(Resource $resource, Project $project, int $quantity, ?string $notes): ResourceAllocation
    {
        /** @var User $user */
        $user = Auth::user();

        return DB::transaction(function () use ($resource, $project, $quantity, $notes, $user): ResourceAllocation {
            $lockedResource = Resource::query()
                ->lockForUpdate()
                ->findOrFail($resource->id);

            if ($lockedResource->quantity_available < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Only {$lockedResource->quantity_available} unit(s) of {$lockedResource->name} are available.",
                ]);
            }

            $allocation = ResourceAllocation::create([
                'project_id' => $project->id,
                'resource_id' => $lockedResource->id,
                'quantity' => $quantity,
                'notes' => $notes,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $lockedResource->decrement('quantity_available', $quantity, [
                'updated_by' => $user->id,
            ]);

            return $allocation;
        });
    }
}
