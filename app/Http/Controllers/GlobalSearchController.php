<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\Project;
use App\Models\Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GlobalSearchController
 *
 * Real-time omni-search for Projects, Manpower, and Resources.
 * Enforces role-based visibility via model policies.
 */
class GlobalSearchController extends Controller
{
    /**
     * Search across Projects, Manpower, and Resources.
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
                'total' => 0,
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $projects = [];
        $manpower = [];
        $resources = [];

        // 1. Projects Search (Requires viewAny Project permission)
        if ($user && $user->can('viewAny', Project::class)) {
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
                        ->orWhere('condition', 'like', "%{$query}%");
                })
                ->orderBy('name', 'asc')
                ->limit(5)
                ->get();

            $resources = $resourceResults->map(function (Resource $r) {
                $cond = strtolower((string) $r->condition);
                $statusClass = match ($cond) {
                    'good', 'new', 'operational' => 'completed',
                    'fair', 'maintenance', 'in use' => 'ongoing',
                    'poor', 'damaged' => 'delayed',
                    default => 'pending',
                };

                return [
                    'id' => $r->id,
                    'type' => 'resource',
                    'code' => $r->resource_code,
                    'title' => $r->name,
                    'subtitle' => "Type: {$r->type}",
                    'meta' => "Available: {$r->quantity_available} units · " . ($r->condition ?: 'Normal'),
                    'status' => $r->condition ?: 'Available',
                    'status_class' => $statusClass,
                    'url' => route('resources.show', $r),
                ];
            })->all();
        }

        $total = count($projects) + count($manpower) + count($resources);

        return response()->json([
            'query' => $query,
            'projects' => $projects,
            'manpower' => $manpower,
            'resources' => $resources,
            'total' => $total,
        ]);
    }
}
