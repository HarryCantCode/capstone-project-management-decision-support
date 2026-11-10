<?php

namespace App\Policies;

use App\Models\StaffPendingAction;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * StaffPendingActionPolicy — governs access to staff request approvals.
 *
 * Only Admin and Manager roles can view and review (approve/reject)
 * actions requested by Staff users.
 */
class StaffPendingActionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the staff request approvals module (Admin/Manager).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']);
    }

    /**
     * Determine whether the user can view the staff's own approval list.
     */
    public function viewOwnList(User $user): bool
    {
        return $user->hasAnyRole(['Staff', 'Admin', 'Manager']);
    }

    /**
     * Determine whether the user can view a specific staff request.
     */
    public function view(User $user, StaffPendingAction $pendingAction): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']) || (int) $user->id === (int) $pendingAction->user_id;
    }

    /**
     * Determine whether the user can ping/remind about a pending request.
     */
    public function ping(User $user, StaffPendingAction $pendingAction): bool
    {
        return (int) $user->id === (int) $pendingAction->user_id && $pendingAction->status === 'pending';
    }

    /**
     * Determine whether the user can approve or reject a staff request.
     */
    public function review(User $user, StaffPendingAction $pendingAction): bool
    {
        return $user->hasAnyRole(['Admin', 'Manager']);
    }
}
