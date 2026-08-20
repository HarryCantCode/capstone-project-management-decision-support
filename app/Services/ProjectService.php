<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ProjectService — all project business logic lives here.
 *
 * Controllers are thin: they validate input (via Form Requests),
 * call this service, and return a response. They do not contain
 * transition logic, project code generation, or authorization checks
 * beyond what the Policy already enforces.
 */
class ProjectService
{
    /**
     * Allowed status transitions per OQ-3.
     *
     * Only two linear transitions are permitted:
     *   pending → ongoing
     *   ongoing → completed
     *
     * All other combinations (including rollbacks and skips) are blocked.
     * This map is the authoritative source — tests assert against this behavior.
     *
     * @var array<string, string>
     */
    private const ALLOWED_TRANSITIONS = [
        'pending' => 'ongoing',
        'ongoing' => 'completed',
    ];

    /**
     * Create a new project and set its initial status to 'pending'.
     *
     * project_code is generated here, not from user input, to ensure
     * the format is consistent and the column cannot be spoofed.
     *
     * @param array<string, mixed> $validated Validated data from StoreProjectRequest
     */
    public function create(array $validated): Project
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return Project::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'client_name' => $validated['client_name'],
            'project_code' => $this->generateProjectCode(),
            'status' => 'pending',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Update a project's non-status fields.
     *
     * Status transitions are handled separately via transition() to
     * enforce the allowed paths and record history correctly.
     *
     * @param array<string, mixed> $validated Validated data from UpdateProjectRequest
     */
    public function update(Project $project, array $validated): Project
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $project->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'client_name' => $validated['client_name'],
            'updated_by' => $user->id,
        ]);

        return $project->fresh();
    }

    /**
     * Transition a project from its current status to $targetStatus.
     *
     * Only the transitions defined in ALLOWED_TRANSITIONS are permitted.
     * Attempting an illegal transition throws a ValidationException so
     * the controller can return a 422 with an inline form error.
     *
     * The status history row is written by ProjectObserver, not here —
     * the observer fires on any save() that changes status, providing
     * defense-in-depth against code paths that bypass this service.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function transition(Project $project, string $targetStatus, ?string $notes = null): Project
    {
        $currentStatus = $project->status;

        $this->assertTransitionAllowed($currentStatus, $targetStatus);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $project->update([
            'status' => $targetStatus,
            'updated_by' => $user->id,
        ]);

        // If the user provided notes, attach them to the history row that
        // the observer just wrote. The observer fires before this line
        // because update() triggers the model event synchronously.
        if ($notes !== null) {
            $project->statusHistory()->latest('changed_at')->first()?->update(['notes' => $notes]);
        }

        return $project->fresh();
    }

    /**
     * Assert that a transition from $from → $to is allowed.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function assertTransitionAllowed(string $from, string $to): void
    {
        $allowedTarget = self::ALLOWED_TRANSITIONS[$from] ?? null;

        if ($allowedTarget !== $to) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Cannot transition a project from "%s" to "%s". '
                    . 'Allowed: %s → %s.',
                    $from,
                    $to,
                    $from,
                    $allowedTarget ?? '(no further transitions)',
                ),
            ]);
        }
    }

    /**
     * Generate a unique project code in the format DEX-YYYY-NNN.
     *
     * Year is the current year; NNN is zero-padded and increments
     * globally (not per year, to avoid collisions on year rollover).
     *
     * Uniqueness is also enforced by the DB unique index on project_code,
     * so a race condition here would result in a DB exception rather than
     * a silent duplicate — acceptable for a 10–50-user LAN tool.
     */
    private function generateProjectCode(): string
    {
        $year = now()->year;
        $count = Project::withTrashed()->count() + 1;

        return sprintf('DEX-%d-%03d', $year, $count);
    }
}
