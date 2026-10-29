<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\Project;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Renders the analytical dashboard for Admin and Manager roles.
 *
 * Inventory Staff cannot reach this route — enforced by the route's
 * can('viewAny', \App\Models\Project::class) middleware (Admin/Manager
 * both pass; Inventory Staff gets 403).
 *
 * All counts are cached with a short TTL to avoid repeated aggregate
 * queries on every page load (per coding-standards.md performance rules).
 */
class DashboardController extends Controller
{
    /**
     * Cache TTL for dashboard aggregate counts.
     * Short (60 sec) — status and task changes should be visible promptly.
     */
    private const CACHE_TTL_SECONDS = 60;

    public function __invoke(): View
    {
        // Authorization: Dashboard is for Admin and Manager only.
        // Inventory Staff receives a 403 — hard block, not redirect.
        if (! Auth::user()->hasAnyRole(['Admin', 'Manager'])) {
            abort(403);
        }

        $dashboardData = cache()->remember(
            key: 'dashboard.stats.global',
            ttl: self::CACHE_TTL_SECONDS,
            callback: fn () => $this->buildDashboardData(),
        );

        return view('dashboard', $dashboardData);
    }

    /**
     * Build all dashboard statistics, recent projects with progression,
     * manpower metrics, and task overview data.
     *
     * @return array<string, mixed>
     */
    private function buildDashboardData(): array
    {
        // 1. Project status metrics
        $stats = Project::getDashboardStats();

        // 2. Recent projects up to 3 with task counts for progression calculation
        $recentProjectsRaw = Project::with(['tasks.subtasks'])
            ->latest('updated_at')
            ->take(3)
            ->get();

        $recentProjects = $recentProjectsRaw->map(function (Project $project) {
            $tasks = $project->tasks;
            $totalSubtasks = $tasks->sum(fn ($task) => $task->subtasks->count());
            $completedSubtasks = $tasks->sum(fn ($task) => $task->subtasks->where('is_completed', true)->count());

            if ($totalSubtasks > 0) {
                $progressPercentage = (int) round(($completedSubtasks / $totalSubtasks) * 100);
            } else {
                $progressPercentage = $project->status === 'completed' ? 100 : 0;
            }

            return [
                'id' => $project->id,
                'name' => $project->name,
                'project_code' => $project->project_code,
                'client_name' => $project->client_name,
                'status' => $project->status,
                'target_completion_date' => $project->target_completion_date?->format('M d, Y'),
                'total_tasks' => $tasks->count(),
                'total_subtasks' => $totalSubtasks,
                'completed_subtasks' => $completedSubtasks,
                'progress_percentage' => $progressPercentage,
                'url' => route('projects.show', $project),
            ];
        });

        // 3. Manpower Statistics
        // Assigned workers must be assigned to an active (non-deleted) project
        $totalPersonnel = Personnel::count();
        $assignedPersonnel = Personnel::whereHas('project')->count();
        $availablePersonnel = max(0, $totalPersonnel - $assignedPersonnel);
        $manpowerUtilization = $totalPersonnel > 0 ? (int) round(($assignedPersonnel / $totalPersonnel) * 100) : 0;

        $topExpertise = Personnel::selectRaw('expertise, count(*) as count')
            ->groupBy('expertise')
            ->orderByDesc('count')
            ->take(4)
            ->get();

        $manpowerStats = [
            'total' => $totalPersonnel,
            'assigned' => $assignedPersonnel,
            'available' => $availablePersonnel,
            'utilization_rate' => $manpowerUtilization,
            'by_expertise' => $topExpertise,
        ];

        // 4. Task Overview
        // Exact tasks belonging exclusively to active (non-deleted) projects
        $activeTasksQuery = ProjectTask::whereHas('project');
        $totalMotherTasks = (clone $activeTasksQuery)->count();
        $completedMotherTasks = (clone $activeTasksQuery)->where('status', 'completed')->count();
        $inProgressMotherTasks = (clone $activeTasksQuery)->where('status', 'in_progress')->count();
        $pendingMotherTasks = (clone $activeTasksQuery)->where('status', 'pending')->count();

        // Baby tasks / subtasks metrics on active projects
        $activeSubtasksQuery = ProjectSubtask::whereHas('task.project');
        $totalSubtasks = (clone $activeSubtasksQuery)->count();
        $completedSubtasks = (clone $activeSubtasksQuery)->where('is_completed', true)->count();
        $pendingSubtasks = max(0, $totalSubtasks - $completedSubtasks);

        $taskOverview = [
            'total_mother_tasks' => $totalMotherTasks,
            'in_progress_mother_tasks' => $inProgressMotherTasks,
            'completed_mother_tasks' => $completedMotherTasks,
            'pending_mother_tasks' => $pendingMotherTasks,
            'total_subtasks' => $totalSubtasks,
            'completed_subtasks' => $completedSubtasks,
            'pending_subtasks' => $pendingSubtasks,
            'completion_rate' => $totalSubtasks > 0 ? (int) round(($completedSubtasks / $totalSubtasks) * 100) : 0,
        ];

        return [
            'stats' => $stats,
            'recentProjects' => $recentProjects,
            'manpowerStats' => $manpowerStats,
            'taskOverview' => $taskOverview,
        ];
    }
}
