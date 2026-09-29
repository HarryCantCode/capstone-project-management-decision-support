<?php

namespace App\Policies;

use App\Models\Personnel;
use App\Models\User;

/**
 * PersonnelPolicy
 *
 * Restricts manpower management to Admin and Manager roles.
 * Inventory Staff and other roles cannot access any personnel operations.
 */
class PersonnelPolicy
{
    /**
     * Determine whether the user can view the personnel list.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']);
    }

    /**
     * Determine whether the user can view a specific personnel record.
     */
    public function view(User $user, Personnel $personnel): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']);
    }

    /**
     * Determine whether the user can create new personnel.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']);
    }

    /**
     * Determine whether the user can update / reassign personnel.
     */
    public function update(User $user, Personnel $personnel): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']);
    }
}
