<?php

namespace App\Policies;

use App\Models\Resource;
use App\Models\User;

/**
 * ResourcePolicy
 *
 * Determines access control for viewing and managing resources.
 * Allows Admin, Manager, and Inventory Staff to view and allocate.
 */
class ResourcePolicy
{
    /**
     * Determine whether the user can view the list of resources.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager', 'Staff']);
    }

    /**
     * Determine whether the user can view the specific resource.
     */
    public function view(User $user, Resource $resource): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager', 'Staff']);
    }

    /**
     * Determine whether the user can allocate the resource to a project.
     */
    public function allocate(User $user, Resource $resource): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager', 'Staff']);
    }

    /**
     * Determine whether the user can create resources.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager', 'Staff']);
    }

    /**
     * Determine whether the user can update the resource.
     */
    public function update(User $user, Resource $resource): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager', 'Staff']);
    }

    /**
     * Determine whether the user can delete the resource.
     */
    public function delete(User $user, Resource $resource): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager', 'Staff']);
    }

    /**
     * Determine whether the user can delete multiple resources.
     */
    public function deleteAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager', 'Staff']);
    }
}
