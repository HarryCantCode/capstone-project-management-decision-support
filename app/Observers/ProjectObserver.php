<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\ProjectStatusHistory;
use Illuminate\Support\Facades\Auth;

/**
 * ProjectObserver — records a history row whenever a project's status changes.
 *
 * Why an observer instead of doing this in ProjectService::transition()?
 * The observer fires automatically on any save, regardless of which code path
 * triggered it. This is intentional defense-in-depth: if a future developer
 * bypasses ProjectService and calls $project->save() directly (which they
 * shouldn't, but could), the history is still recorded.
 *
 * The actual transition validation (is this move allowed?) lives in
 * ProjectService — the observer is only responsible for recording the
 * fact that a change happened.
 */
class ProjectObserver
{
    /**
     * Listen for status changes on the updated event.
     *
     * isDirty('status') ensures we only write a history row when the
     * status column actually changed — not on every project save.
     */
    public function updated(Project $project): void
    {
        if (! $project->isDirty('status')) {
            return;
        }

        ProjectStatusHistory::create([
            'project_id' => $project->id,
            'from_status' => $project->getOriginal('status'),
            'to_status' => $project->status,
            'changed_by' => Auth::id() ?? $project->updated_by,
            'changed_at' => now(),
        ]);
    }

    /**
     * Record the initial status when a project is first created.
     *
     * from_status is null here (project didn't exist before).
     */
    public function created(Project $project): void
    {
        ProjectStatusHistory::create([
            'project_id' => $project->id,
            'from_status' => null,
            'to_status' => $project->status,
            'changed_by' => Auth::id() ?? $project->created_by,
            'changed_at' => now(),
        ]);
    }
}
