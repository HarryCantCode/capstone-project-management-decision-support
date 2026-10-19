<?php

namespace App\Services;

use App\Models\Personnel;
use App\Models\Project;
use App\Models\ProjectPersonnelHistory;
use App\Models\ProjectStatusHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PersonnelService
 *
 * Domain service handling field personnel roster management,
 * profile updates, project assignment audits, archiving (soft delete),
 * and reactivations.
 */
class PersonnelService
{
    /**
     * Get statistics for active and inactive/archived personnel.
     *
     * @return array<string, int>
     */
    public function getRosterStatistics(): array
    {
        return [
            'total'     => Personnel::withTrashed()->count(),
            'active'    => Personnel::count(),
            'assigned'  => Personnel::whereNotNull('project_id')->count(),
            'available' => Personnel::whereNull('project_id')->count(),
            'archived'  => Personnel::onlyTrashed()->count(),
        ];
    }

    /**
     * Build the query for roster listing supporting tabs and filters.
     *
     * @param array<string, mixed> $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRosterQuery(array $filters): Builder
    {
        $tab = $filters['tab'] ?? 'all';
        $search = $filters['search'] ?? null;
        $expertise = $filters['expertise'] ?? null;
        $projectId = $filters['project_id'] ?? null;

        $query = Personnel::query()->with('project');

        // Tab filtering
        if ($tab === 'active') {
            $query->whereNull('deleted_at');
        } elseif ($tab === 'inactive' || $tab === 'archived') {
            $query->onlyTrashed();
        } else {
            $query->withTrashed();
        }

        // Search filtering
        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('employee_id', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('middle_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhereHas('project', function (Builder $pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                         ->orWhere('project_code', 'like', "%{$search}%");
                  });
            });
        }

        // Expertise filtering
        if ($expertise) {
            $query->where('expertise', $expertise);
        }

        // Project filtering
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Update personnel profile information, project assignment, and optional archiving.
     *
     * @param \App\Models\Personnel $personnel
     * @param array<string, mixed> $data
     * @param int|null $userId
     * @return \App\Models\Personnel
     */
    public function updatePersonnel(Personnel $personnel, array $data, ?int $userId = null): Personnel
    {
        return DB::transaction(function () use ($personnel, $data, $userId) {
            $now = now()->toDateString();
            $oldProjectId = $personnel->project_id;
            $newProjectId = !empty($data['project_id']) ? (int) $data['project_id'] : null;

            // Handle project change or unassignment
            if ($oldProjectId && $oldProjectId != $newProjectId) {
                ProjectPersonnelHistory::where('project_id', $oldProjectId)
                    ->where('personnel_id', $personnel->id)
                    ->whereNull('released_at')
                    ->update([
                        'released_at'    => $now,
                        'release_reason' => $newProjectId ? 'reassigned' : 'unassigned',
                        'released_by'    => $userId,
                    ]);

                $oldProject = Project::find($oldProjectId);
                if ($oldProject) {
                    ProjectStatusHistory::create([
                        'project_id'  => $oldProjectId,
                        'from_status' => $oldProject->status,
                        'to_status'   => $oldProject->status,
                        'notes'       => $newProjectId
                            ? "Reassigned manpower {$personnel->employee_id} ({$personnel->full_name} - {$personnel->expertise}) to Project " . (Project::find($newProjectId)?->project_code ?? '')
                            : "Unassigned manpower: {$personnel->employee_id} ({$personnel->full_name} - {$personnel->expertise})",
                        'changed_by'  => $userId,
                        'changed_at'  => now(),
                    ]);
                }
            }

            // Update core attributes
            $personnel->first_name = $data['first_name'];
            $personnel->middle_name = $data['middle_name'] ?? null;
            $personnel->last_name = $data['last_name'];
            $personnel->date_of_birth = $data['date_of_birth'];
            $personnel->address = $data['address'];
            $personnel->expertise = $data['expertise'];
            $personnel->project_id = $newProjectId;
            $personnel->date_assigned = $newProjectId
                ? ($oldProjectId == $newProjectId && $personnel->date_assigned ? $personnel->date_assigned : $now)
                : null;
            $personnel->updated_by = $userId;
            $personnel->save();

            // Handle new assignment history and project audit
            if ($newProjectId && $oldProjectId != $newProjectId) {
                ProjectPersonnelHistory::create([
                    'project_id'   => $newProjectId,
                    'personnel_id' => $personnel->id,
                    'assigned_at'  => $now,
                    'assigned_by'  => $userId,
                ]);

                $newProject = Project::find($newProjectId);
                if ($newProject) {
                    ProjectStatusHistory::create([
                        'project_id'  => $newProjectId,
                        'from_status' => $newProject->status,
                        'to_status'   => $newProject->status,
                        'notes'       => "Assigned manpower: {$personnel->employee_id} ({$personnel->full_name} - {$personnel->expertise})",
                        'changed_by'  => $userId,
                        'changed_at'  => now(),
                    ]);
                }
            }

            // Handle optional archive profile on save
            if (!empty($data['archive_profile'])) {
                $this->archivePersonnel($personnel, $userId);
            }

            return $personnel;
        });
    }

    /**
     * Place personnel profile into archive (soft delete) and release assignments.
     *
     * @param \App\Models\Personnel $personnel
     * @param int|null $userId
     * @return void
     */
    public function archivePersonnel(Personnel $personnel, ?int $userId = null): void
    {
        DB::transaction(function () use ($personnel, $userId) {
            $now = now()->toDateString();
            $oldProjectId = $personnel->project_id;

            if ($oldProjectId) {
                ProjectPersonnelHistory::where('project_id', $oldProjectId)
                    ->where('personnel_id', $personnel->id)
                    ->whereNull('released_at')
                    ->update([
                        'released_at'    => $now,
                        'release_reason' => 'archived',
                        'released_by'    => $userId,
                    ]);

                $oldProject = Project::find($oldProjectId);
                if ($oldProject) {
                    ProjectStatusHistory::create([
                        'project_id'  => $oldProjectId,
                        'from_status' => $oldProject->status,
                        'to_status'   => $oldProject->status,
                        'notes'       => "Archived manpower: {$personnel->employee_id} ({$personnel->full_name} - {$personnel->expertise}) released from project",
                        'changed_by'  => $userId,
                        'changed_at'  => now(),
                    ]);
                }

                $personnel->project_id = null;
                $personnel->date_assigned = null;
            }

            $personnel->updated_by = $userId;
            $personnel->save();
            $personnel->delete();
        });
    }

    /**
     * Restore and reactivate an archived personnel profile back to the active roster.
     *
     * @param \App\Models\Personnel $personnel
     * @param int|null $userId
     * @return void
     */
    public function restorePersonnel(Personnel $personnel, ?int $userId = null): void
    {
        DB::transaction(function () use ($personnel, $userId) {
            $personnel->restore();
            $personnel->project_id = null;
            $personnel->date_assigned = null;
            $personnel->updated_by = $userId;
            $personnel->save();
        });
    }
}
