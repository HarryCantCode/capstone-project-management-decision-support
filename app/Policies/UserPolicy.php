<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * UserPolicy — governs who can manage user accounts.
 *
 * Only Admin can create, update, or delete users.
 * All three roles can view their own profile (handled at controller level).
 *
 * Hard 403 for all non-Admin attempts to reach user management routes —
 * per OQ-7, non-primary roles get zero access, not read-only access.
 */
class UserPolicy
{
    use HandlesAuthorization;

    /** Any authenticated user can see the user list (Admin only in practice — route guards handle this). */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    /** Any authenticated user can view a user's profile. */
    public function view(User $user, User $model): bool
    {
        return $user->hasRole('Admin') || $user->id === $model->id;
    }

    /** Only Admin can create new users. */
    public function create(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    /**
     * Only Admin can update any user account.
     * Non-admin users editing their own profile is handled via a separate
     * account settings route — not through this policy.
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasRole('Admin');
    }

    /**
     * Only Admin can delete users.
     * An Admin cannot delete their own account (prevents accidental
     * lockout of the last admin).
     */
    public function delete(User $user, User $model): bool
    {
        return $user->hasRole('Admin') && $user->id !== $model->id;
    }
}
