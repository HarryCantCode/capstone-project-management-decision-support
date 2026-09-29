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
        $changes = [];
        $fromStatus = $project->getOriginal('status') ?? $project->status;
        $toStatus = $project->status;

        if ($project->isDirty('status')) {
            $changes[] = "Status transitioned from " . ucfirst($fromStatus) . " to " . ucfirst($toStatus);
        }

        if ($project->isDirty('name')) {
            $orig = $project->getOriginal('name');
            $changes[] = "Project name changed from '{$orig}' to '{$project->name}'";
        }

        if ($project->isDirty('client_name')) {
            $orig = $project->getOriginal('client_name');
            $changes[] = "Client changed from '{$orig}' to '{$project->client_name}'";
        }

        if ($project->isDirty('contract_price')) {
            $orig = $project->getOriginal('contract_price');
            $origFormatted = $orig !== null ? '₱' . number_format((float) $orig, 2) : 'None';
            $newFormatted = '₱' . number_format((float) $project->contract_price, 2);
            $changes[] = "Contract price updated from {$origFormatted} to {$newFormatted}";
        }

        if ($project->isDirty('actual_spend')) {
            $orig = $project->getOriginal('actual_spend');
            $origFormatted = $orig !== null ? '₱' . number_format((float) $orig, 2) : '₱0.00';
            $newFormatted = '₱' . number_format((float) $project->actual_spend, 2);
            $changes[] = "Actual spend updated from {$origFormatted} to {$newFormatted}";
        }

        if ($project->isDirty('start_date')) {
            $orig = $project->getOriginal('start_date');
            $origDate = $orig ? \Carbon\Carbon::parse($orig)->format('M j, Y') : 'None';
            $newDate = $project->start_date ? $project->start_date->format('M j, Y') : 'None';
            $changes[] = "Start date updated from {$origDate} to {$newDate}";
        }

        if ($project->isDirty('target_completion_date')) {
            $orig = $project->getOriginal('target_completion_date');
            $origDate = $orig ? \Carbon\Carbon::parse($orig)->format('M j, Y') : 'None';
            $newDate = $project->target_completion_date ? $project->target_completion_date->format('M j, Y') : 'None';
            $changes[] = "Target completion date updated from {$origDate} to {$newDate}";
        }

        if ($project->isDirty('description')) {
            $changes[] = "Project description updated";
        }

        if ($project->isDirty('project_type')) {
            $orig = $project->getOriginal('project_type') ?? 'None';
            $changes[] = "Project type updated from '{$orig}' to '{$project->project_type}'";
        }

        if ($project->isDirty('payment_status')) {
            $orig = $project->getOriginal('payment_status') ?? 'None';
            $changes[] = "Payment status updated from '{$orig}' to '{$project->payment_status}'";
        }

        if ($project->isDirty('address') || $project->isDirty('barangay') || $project->isDirty('city')) {
            $changes[] = "Project site address updated to '{$project->full_address}'";
        }

        if ($project->isDirty('warranty_period')) {
            $changes[] = "Warranty period updated to '{$project->warranty_period}'";
        }

        if (!empty($changes)) {
            ProjectStatusHistory::create([
                'project_id'  => $project->id,
                'from_status' => $fromStatus,
                'to_status'   => $toStatus,
                'notes'       => implode(' · ', $changes),
                'changed_by'  => Auth::id() ?? $project->updated_by,
                'changed_at'  => now(),
            ]);
        }
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
            'notes' => 'Project created with initial status ' . ucfirst($project->status) . ' and contract value ₱' . number_format($project->contract_price, 2),
            'changed_by' => Auth::id() ?? $project->created_by,
            'changed_at' => now(),
        ]);
    }

    /**
     * Handle the Project "deleting" event.
     *
     * Automatically unassigns any currently assigned field personnel and closes
     * open manpower history records so workers are released back to the available pool.
     */
    public function deleting(Project $project): void
    {
        $userId = Auth::id() ?? $project->updated_by;
        $now = now()->toDateString();

        $assignedPersonnel = $project->personnel()->get();
        foreach ($assignedPersonnel as $person) {
            \App\Models\ProjectPersonnelHistory::where('project_id', $project->id)
                ->where('personnel_id', $person->id)
                ->whereNull('released_at')
                ->update([
                    'released_at'    => $now,
                    'release_reason' => 'project_deleted',
                    'released_by'    => $userId,
                ]);

            $person->update([
                'project_id'    => null,
                'date_assigned' => null,
                'updated_by'    => $userId,
            ]);
        }
    }
}
