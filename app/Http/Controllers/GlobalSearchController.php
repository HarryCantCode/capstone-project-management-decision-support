<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\Project;
use App\Models\ProjectCost;
use App\Models\ProjectFile;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Models\Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GlobalSearchController
 *
 * Real-time omni-search for Projects, Manpower, Resources,
 * Project Major Tasks, Specific Tasks, Project Files, and Project Expenses.
 * Enforces role-based visibility via model policies.
 */
class GlobalSearchController extends Controller
{
    /**
     * Search across Projects, Manpower, Resources, Tasks, Subtasks, Files, and Expenses.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $rawQuery = (string) $request->query('q', '');
        $query = trim($rawQuery);

        if ($query === '') {
            return response()->json([
                'query' => '',
                'projects' => [],
                'manpower' => [],
                'resources' => [],
                'major_tasks' => [],
                'specific_tasks' => [],
                'files' => [],
                'expenses' => [],
                'total' => 0,
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $projects = [];
        $manpower = [];
        $resources = [];
        $majorTasks = [];
        $specificTasks = [];
        $files = [];
        $expenses = [];

        $canViewProjects = $user && $user->can('viewAny', Project::class);

        // 1. Projects Search (Requires viewAny Project permission)
        if ($canViewProjects) {
            $projectResults = Project::query()
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('project_code', 'like', "%{$query}%")
                        ->orWhere('client_name', 'like', "%{$query}%")
                        ->orWhere('city', 'like', "%{$query}%")
                        ->orWhere('address', 'like', "%{$query}%")
                        ->orWhere('barangay', 'like', "%{$query}%")
                        ->orWhere('project_type', 'like', "%{$query}%");
                })
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get();

            $projects = $projectResults->map(function (Project $p) {
                return [
                    'id' => $p->id,
                    'type' => 'project',
                    'code' => $p->project_code,
                    'title' => $p->name,
                    'subtitle' => $p->client_name,
                    'meta' => "{$p->city} · {$p->project_type}",
                    'status' => ucfirst($p->status),
                    'status_class' => match (strtolower($p->status)) {
                        'completed' => 'completed',
                        'ongoing' => 'ongoing',
                        'delayed' => 'delayed',
                        default => 'pending',
                    },
                    'url' => route('projects.show', $p),
                ];
            })->all();
        }

        // 2. Manpower Search (Requires viewAny Personnel permission)
        if ($user && $user->can('viewAny', Personnel::class)) {
            $manpowerResults = Personnel::query()
                ->with('project')
                ->where(function ($q) use ($query) {
                    $q->where('employee_id', 'like', "%{$query}%")
                        ->orWhere('first_name', 'like', "%{$query}%")
                        ->orWhere('middle_name', 'like', "%{$query}%")
                        ->orWhere('last_name', 'like', "%{$query}%")
                        ->orWhere('expertise', 'like', "%{$query}%")
                        ->orWhereHas('project', function ($pq) use ($query) {
                            $pq->where('name', 'like', "%{$query}%")
                                ->orWhere('project_code', 'like', "%{$query}%");
                        });
                })
                ->orderBy('last_name', 'asc')
                ->limit(5)
                ->get();

            $manpower = $manpowerResults->map(function (Personnel $m) {
                $isAssigned = (bool) ($m->project_id && $m->project);
                $assignmentText = $isAssigned
                    ? "Assigned to {$m->project->project_code} ({$m->project->name})"
                    : "Available (Not Assigned)";

                return [
                    'id' => $m->id,
                    'type' => 'manpower',
                    'code' => $m->employee_id,
                    'title' => $m->full_name,
                    'subtitle' => $m->expertise,
                    'meta' => $assignmentText,
                    'status' => $isAssigned ? 'Assigned' : 'Available',
                    'status_class' => $isAssigned ? 'completed' : 'pending',
                    'url' => route('scheduling.index', ['search' => $m->employee_id]),
                ];
            })->all();
        }

        // 3. Resources Search (Requires viewAny Resource permission)
        if ($user && $user->can('viewAny', Resource::class)) {
            $resourceResults = Resource::query()
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('resource_code', 'like', "%{$query}%")
                        ->orWhere('type', 'like', "%{$query}%")
                        ->orWhere('demand_status', 'like', "%{$query}%")
                        ->orWhere('condition', 'like', "%{$query}%");
                })
                ->orderBy('name', 'asc')
                ->limit(5)
                ->get();

            $resources = $resourceResults->map(function (Resource $r) {
                $demand = strtolower((string) ($r->demand_status ?: 'fast-moving'));
                $isFastMoving = $demand === 'fast-moving';
                $statusText = $isFastMoving ? 'Fast-moving' : 'Slow-moving';
                $statusClass = $isFastMoving ? 'completed' : 'ongoing';

                return [
                    'id' => $r->id,
                    'type' => 'resource',
                    'code' => $r->resource_code,
                    'title' => $r->name,
                    'subtitle' => "Type: {$r->type}",
                    'meta' => "Available: {$r->quantity_available} units · " . ($r->condition ?: 'Normal'),
                    'status' => $statusText,
                    'status_class' => $statusClass,
                    'url' => route('resources.show', $r),
                ];
            })->all();
        }

        // Project-scoped items (Require viewAny Project permission)
        if ($canViewProjects) {
            // 4. Major Tasks Search
            $taskResults = ProjectTask::query()
                ->with(['project', 'subtasks'])
                ->where(function ($q) use ($query) {
                    $q->where('task_name', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                })
                ->whereHas('project')
                ->orderBy('start_date', 'desc')
                ->limit(5)
                ->get();

            $majorTasks = $taskResults->map(function (ProjectTask $t) {
                $status = $t->computed_status;
                $statusLabel = $status === 'completed' ? 'Completed' : ($status === 'in_progress' ? 'In Progress' : 'Scheduled');
                $statusClass = $status === 'completed' ? 'completed' : ($status === 'in_progress' ? 'ongoing' : 'pending');
                $dateRange = ($t->start_date && $t->end_date)
                    ? "{$t->start_date->format('M j')} - {$t->end_date->format('M j, Y')}"
                    : "No dates set";

                return [
                    'id' => $t->id,
                    'project_id' => $t->project_id,
                    'type' => 'major_task',
                    'code' => $t->project?->project_code ?? 'TASK',
                    'title' => $t->task_name,
                    'subtitle' => "Project: " . ($t->project?->name ?? 'N/A'),
                    'meta' => "📅 {$dateRange} · {$t->progress_percentage}% done",
                    'status' => $statusLabel,
                    'status_class' => $statusClass,
                    'url' => route('projects.show', ['project' => $t->project_id, 'task_id' => $t->id]) . '#project-timeline-heading',
                ];
            })->all();

            // 5. Specific Tasks (Subtasks) Search
            $subtaskResults = ProjectSubtask::query()
                ->with(['task.project'])
                ->where('title', 'like', "%{$query}%")
                ->whereHas('task.project')
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get();

            $specificTasks = $subtaskResults->map(function (ProjectSubtask $st) {
                $task = $st->task;
                $project = $task?->project;
                $statusLabel = $st->is_completed ? 'Completed' : 'Pending';
                $statusClass = $st->is_completed ? 'completed' : 'pending';
                $dueDate = $st->due_date ? "Due {$st->due_date->format('M j, Y')}" : 'No due date';

                return [
                    'id' => $st->id,
                    'task_id' => $st->project_task_id,
                    'project_id' => $project?->id,
                    'type' => 'specific_task',
                    'code' => $project?->project_code ?? 'SUBTASK',
                    'title' => $st->title,
                    'subtitle' => ($task ? "Stage: {$task->task_name}" : '') . ($project ? " · {$project->name}" : ''),
                    'meta' => "📅 {$dueDate}",
                    'status' => $statusLabel,
                    'status_class' => $statusClass,
                    'url' => route('projects.show', ['project' => $project?->id, 'task_id' => $st->project_task_id]) . '#subtask-row-' . $st->id,
                ];
            })->all();

            // 6. Project Files Search
            $fileResults = ProjectFile::query()
                ->with(['project', 'subtask'])
                ->where(function ($q) use ($query) {
                    $q->where('file_name', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                })
                ->whereHas('project')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $files = $fileResults->map(function (ProjectFile $f) {
                $project = $f->project;
                $typeLabel = $f->category_label;

                return [
                    'id' => $f->id,
                    'project_id' => $project?->id,
                    'type' => 'file',
                    'code' => $project?->project_code ?? 'FILE',
                    'title' => $f->file_name,
                    'subtitle' => "Project: " . ($project?->name ?? 'N/A') . ($f->subtask ? " · Task: {$f->subtask->title}" : ''),
                    'meta' => "📎 {$typeLabel} · {$f->formatted_size}",
                    'status' => strtoupper(pathinfo($f->file_name, PATHINFO_EXTENSION) ?: 'FILE'),
                    'status_class' => 'completed',
                    'url' => route('projects.show', ['project' => $project?->id, 'file_id' => $f->id]) . '#project-files-card',
                ];
            })->all();

            // 7. Project Costs / Logged Expenses Search
            $expenseResults = ProjectCost::query()
                ->with(['project', 'resource'])
                ->where(function ($q) use ($query) {
                    $q->where('description', 'like', "%{$query}%")
                        ->orWhere('cost_type', 'like', "%{$query}%");
                })
                ->whereHas('project')
                ->orderBy('incurred_date', 'desc')
                ->limit(5)
                ->get();

            $expenses = $expenseResults->map(function (ProjectCost $c) {
                $project = $c->project;
                $formattedAmount = '₱' . number_format((float) $c->amount, 2);
                $incurredDate = $c->incurred_date ? $c->incurred_date->format('M j, Y') : 'No date';
                $costTypeLabel = ucfirst((string) $c->cost_type);

                return [
                    'id' => $c->id,
                    'project_id' => $project?->id,
                    'type' => 'expense',
                    'code' => $project?->project_code ?? 'COST',
                    'title' => $c->description ?: "Expense ({$costTypeLabel})",
                    'subtitle' => "Project: " . ($project?->name ?? 'N/A') . " · {$costTypeLabel}",
                    'meta' => "💸 {$formattedAmount} · Date: {$incurredDate}",
                    'status' => $formattedAmount,
                    'status_class' => 'ongoing',
                    'url' => route('costing.show', ['project' => $project?->id, 'search' => $c->description]),
                ];
            })->all();
        }

        $total = count($projects)
            + count($manpower)
            + count($resources)
            + count($majorTasks)
            + count($specificTasks)
            + count($files)
            + count($expenses);

        return response()->json([
            'query' => $query,
            'projects' => $projects,
            'manpower' => $manpower,
            'resources' => $resources,
            'major_tasks' => $majorTasks,
            'specific_tasks' => $specificTasks,
            'files' => $files,
            'expenses' => $expenses,
            'total' => $total,
        ]);
    }
}
