<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ProjectPolicy — governs access to project records.
 *
 * Primary roles: Admin + Manager (OQ-7: hard 403 for Inventory Staff everywhere).
 *
 * Access matrix:
 *   viewAny / view:   Admin, Manager
 *   create / update:  Admin, Manager
 *   delete:           Admin only (soft delete — Manager cannot delete projects)
 *   transition:       Admin, Manager
 */
class ProjectPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']);
    }

    /**
     * Only Admin can soft-delete a project.
     *
     * Deletion is irreversible in practice (soft-deleted projects don't
     * appear in reports). This is scoped to Admin because a Manager
     * accidentally deleting a project mid-engagement would be high-impact.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->hasRole('Admin');
    }

    /**
     * Status transition — same access as update.
     * Transition validity is checked in ProjectService, not here.
     */
    public function transition(User $user, Project $project): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']);
    }
}
