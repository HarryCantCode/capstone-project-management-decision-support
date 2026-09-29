<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
     * Allowed status transitions.
     * Supports transitions across pending, ongoing, delayed, and completed.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['ongoing', 'delayed', 'completed'],
        'ongoing' => ['delayed', 'completed', 'pending'],
        'delayed' => ['ongoing', 'completed', 'pending'],
        'completed' => ['ongoing', 'delayed'],
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

        $startDate = $validated['start_date'] ?? now()->toDateString();
        $warrantyPeriod = !empty($validated['warranty_period']) ? $validated['warranty_period'] : '1 Year';
        $warrantyEndDate = !empty($validated['warranty_end_date'])
            ? $validated['warranty_end_date']
            : Carbon::parse($startDate)->addYear()->toDateString();

        return DB::transaction(function () use ($validated, $user, $startDate, $warrantyPeriod, $warrantyEndDate) {
            $project = Project::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'client_name' => $validated['client_name'],
                'address' => $validated['address'] ?? null,
                'barangay' => $validated['barangay'] ?? null,
                'city' => $validated['city'] ?? null,
                'project_type' => $validated['project_type'] ?? null,
                'warranty_period' => $warrantyPeriod,
                'warranty_end_date' => $warrantyEndDate,
                'payment_status' => $validated['payment_status'] ?? 'Partial',
                'project_code' => $this->generateProjectCode(),
                'status' => 'pending',
                'start_date' => $startDate,
                'target_completion_date' => $validated['target_completion_date'] ?? null,
                'contract_price' => $validated['contract_price'] ?? 0.00,
                'actual_spend' => 0.00,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Create initial default Major Task: Pre-construction & Regulatory Compliance (covering Week 1)
            $this->createDefaultPermitTask($project, $user->id);

            $stats = Project::getDashboardStats();

            $html = view('projects.partials.row', compact('project'))->render();
            \App\Events\ProjectCreated::dispatch($html, $stats);

            return $project;
        });
    }

    /**
     * Create the default initial Major Task (Pre-construction & Regulatory Compliance)
     * covering Week 1, with standard sub tasks checklist.
     */
    public function createDefaultPermitTask(Project $project, ?int $userId = null): ProjectTask
    {
        $startDate = $project->start_date ? Carbon::parse($project->start_date) : now();
        $endDate = $startDate->copy()->addDays(6);

        $task = ProjectTask::create([
            'project_id' => $project->id,
            'task_name' => 'Pre-construction & Regulatory Compliance',
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'description' => 'Securing required permits, obtaining design approvals, verifying site readiness, and preparing the installation area prior to elevator construction and mobilization.',
            'status' => 'pending',
            'sort_order' => 1,
            'created_by' => $userId ?? Auth::id(),
        ]);

        $defaultSubtasks = [
            ['title' => 'LGU building & mechanical permits', 'due_date' => $startDate->copy()->addDays(1)->toDateString()],
            ['title' => 'Approval of project design / plan', 'due_date' => $startDate->copy()->addDays(2)->toDateString()],
            ['title' => 'Site inspection and readiness assessment', 'due_date' => $startDate->copy()->addDays(4)->toDateString()],
            ['title' => 'Initial site mobilization and safety barricade staging', 'due_date' => $startDate->copy()->addDays(6)->toDateString()],
        ];

        foreach ($defaultSubtasks as $index => $subtaskData) {
            $task->subtasks()->create([
                'title' => $subtaskData['title'],
                'due_date' => $subtaskData['due_date'],
                'is_completed' => false,
                'sort_order' => $index + 1,
            ]);
        }

        return $task;
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

        $startDate = $validated['start_date'] ?? $project->start_date;
        $warrantyPeriod = !empty($validated['warranty_period'])
            ? $validated['warranty_period']
            : ($project->warranty_period ?? '1 Year');
        $warrantyEndDate = !empty($validated['warranty_end_date'])
            ? $validated['warranty_end_date']
            : ($startDate ? \Carbon\Carbon::parse($startDate)->addYear()->toDateString() : $project->warranty_end_date);

        $project->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'client_name' => $validated['client_name'],
            'address' => $validated['address'] ?? $project->address,
            'barangay' => $validated['barangay'] ?? $project->barangay,
            'city' => $validated['city'] ?? $project->city,
            'project_type' => $validated['project_type'] ?? $project->project_type,
            'warranty_period' => $warrantyPeriod,
            'warranty_end_date' => $warrantyEndDate,
            'payment_status' => $validated['payment_status'] ?? $project->payment_status,
            'start_date' => $startDate,
            'target_completion_date' => $validated['target_completion_date'] ?? $project->target_completion_date,
            'contract_price' => $validated['contract_price'] ?? $project->contract_price,
            'updated_by' => $user->id,
        ]);

        return $project->fresh();
    }

    /**
     * Add an itemized cost entry to the project and recalculate total actual spend.
     *
     * @param array<string, mixed> $data
     */
    public function addCost(Project $project, array $data): \App\Models\ProjectCost
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return DB::transaction(function () use ($project, $data, $user) {
            $cost = $project->costs()->create([
                'description' => $data['description'],
                'cost_type' => $data['cost_type'] ?? 'materials',
                'amount' => $data['amount'],
                'incurred_date' => $data['incurred_date'] ?? now()->toDateString(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Recalculate total spend
            $totalSpend = (float) $project->costs()->sum('amount');
            $project->update([
                'actual_spend' => number_format($totalSpend, 2, '.', ''),
                'updated_by' => $user->id,
            ]);

            return $cost;
        });
    }

    /**
     * Delete an itemized cost entry and recalculate total actual spend.
     */
    public function deleteCost(Project $project, \App\Models\ProjectCost $cost): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        DB::transaction(function () use ($project, $cost, $user) {
            $cost->delete();

            $totalSpend = (float) $project->costs()->sum('amount');
            $project->update([
                'actual_spend' => number_format($totalSpend, 2, '.', ''),
                'updated_by' => $user->id,
            ]);
        });
    }

    /**
     * Update project actual spend tracking directly.
     */
    public function updateSpend(Project $project, float $amount): Project
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $project->update([
            'actual_spend' => number_format($amount, 2, '.', ''),
            'updated_by' => $user->id,
        ]);

        return $project->fresh();
    }

    /**
     * Transition a project from its current status to $targetStatus.
     */
    public function transition(Project $project, string $targetStatus, ?string $notes = null): Project
    {
        $currentStatus = $project->status;

        $this->assertTransitionAllowed($currentStatus, $targetStatus);

        return DB::transaction(function () use ($project, $targetStatus, $notes) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            $project->update([
                'status' => $targetStatus,
                'updated_by' => $user->id,
            ]);

            // When a project is marked completed, release all assigned manpower to make them available
            if ($targetStatus === 'completed') {
                $now = now()->toDateString();
                $assignedPersonnel = $project->personnel()->get();
                $releasedCount = $assignedPersonnel->count();

                foreach ($assignedPersonnel as $person) {
                    \App\Models\ProjectPersonnelHistory::where('project_id', $project->id)
                        ->where('personnel_id', $person->id)
                        ->whereNull('released_at')
                        ->update([
                            'released_at'    => $now,
                            'release_reason' => 'project_completed',
                            'released_by'    => $user->id,
                        ]);

                    $person->update([
                        'project_id'    => null,
                        'date_assigned' => null,
                        'updated_by'    => $user->id,
                    ]);
                }

                if ($releasedCount > 0) {
                    \App\Models\ProjectStatusHistory::create([
                        'project_id'  => $project->id,
                        'from_status' => 'completed',
                        'to_status'   => 'completed',
                        'notes'       => "Project completed & archived: Automatically released all assigned manpower ({$releasedCount} personnel) to available pool",
                        'changed_by'  => $user->id,
                        'changed_at'  => now(),
                    ]);
                }
            }

            // If the user provided notes, attach them to the history row that the observer wrote
            if ($notes !== null) {
                $latest = $project->statusHistory()->latest('changed_at')->first();
                if ($latest) {
                    $latest->update(['notes' => $latest->notes ? "{$latest->notes} — Reason: {$notes}" : $notes]);
                }
            }

            return $project->fresh();
        });
    }

    /**
     * Delete a project, releasing any assigned manpower and updating stats.
     */
    public function delete(Project $project): void
    {
        DB::transaction(function () use ($project) {
            $user = Auth::user();
            $userId = $user ? $user->id : $project->updated_by;
            $now = now()->toDateString();

            // Release all assigned personnel
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

            $projectId = $project->id;
            $project->delete();

            $stats = Project::getDashboardStats();
            \App\Events\ProjectDeleted::dispatch($projectId, $stats);
        });
    }

    /**
     * Assert that a transition from $from → $to is allowed.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function assertTransitionAllowed(string $from, string $to): void
    {
        $allowedTargets = self::ALLOWED_TRANSITIONS[$from] ?? [];

        if (!in_array($to, $allowedTargets, true)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Cannot transition a project from "%s" to "%s".',
                    $from,
                    $to,
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
        $latest = Project::withTrashed()
            ->orderByDesc('id')
            ->value('project_code');

        $next = 1;
        if ($latest && preg_match('/DEX-\d+-(\d+)/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('DEX-%d-%03d', $year, $next);
    }
}
