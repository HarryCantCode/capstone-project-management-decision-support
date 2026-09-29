<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * SchedulingController
 *
 * Manages the Manpower module — listing, creating, and reassigning
 * personnel to projects. Restricted to Admin and Manager roles.
 */
class SchedulingController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            'auth',
            'two-factor',
            'role:Admin|Manager',
        ];
    }

    /**
     * Display the personnel list with search and filter support.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request): View
    {
        $search = $request->query('search');
        $expertise = $request->query('expertise');
        $status = $request->query('status');

        $personnel = Personnel::query()
            ->with('project')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('employee_id', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('middle_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhereHas('project', function ($pq) use ($search) {
                          $pq->where('name', 'like', "%{$search}%")
                             ->orWhere('project_code', 'like', "%{$search}%");
                      });
                });
            })
            ->when($expertise, fn ($query) => $query->where('expertise', $expertise))
            ->when($status === 'assigned', fn ($query) => $query->whereHas('project'))
            ->when($status === 'not_assigned', fn ($query) => $query->whereDoesntHave('project'))
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $activeProjects = Project::query()
            ->where('status', '!=', 'completed')
            ->orderBy('name')
            ->get();

        return view('scheduling.index', compact('personnel', 'activeProjects', 'search', 'expertise', 'status'));
    }

    /**
     * Show the form for adding a new personnel.
     *
     * @return \Illuminate\View\View
     */
    public function create(): View
    {
        $this->authorize('create', Personnel::class);

        $nextEmployeeId = Personnel::generateEmployeeId();
        $projects = Project::orderBy('name')->get();
        $expertiseOptions = ['Site Engineer', 'Foreman', 'Safety Officer', 'Worker'];

        return view('scheduling.create', compact('nextEmployeeId', 'projects', 'expertiseOptions'));
    }

    /**
     * Store a newly created personnel record.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create', Personnel::class);

        $validated = $request->validate([
            'first_name'    => ['required', 'string', 'max:255'],
            'middle_name'   => ['nullable', 'string', 'max:255'],
            'last_name'     => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'address'       => ['required', 'string', 'max:1000'],
            'expertise'     => ['required', 'string', 'in:Site Engineer,Foreman,Safety Officer,Worker'],
            'assign_project' => ['nullable', 'boolean'],
            'project_id'    => ['nullable', 'required_if:assign_project,1', 'exists:projects,id'],
        ]);

        $personnel = Personnel::create([
            'first_name'    => $validated['first_name'],
            'middle_name'   => $validated['middle_name'] ?? null,
            'last_name'     => $validated['last_name'],
            'date_of_birth' => $validated['date_of_birth'],
            'address'       => $validated['address'],
            'expertise'     => $validated['expertise'],
            'project_id'    => !empty($validated['assign_project']) ? ($validated['project_id'] ?? null) : null,
            'date_assigned' => !empty($validated['assign_project']) && !empty($validated['project_id']) ? now()->toDateString() : null,
            'created_by'    => auth()->id(),
            'updated_by'    => auth()->id(),
        ]);

        return redirect()
            ->route('scheduling.index')
            ->with('status', "Personnel {$personnel->employee_id} — {$personnel->full_name} added successfully.");
    }

    /**
     * Reassign personnel to a different project (or unassign).
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Personnel $personnel
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reassign(Request $request, Personnel $personnel)
    {
        $this->authorize('update', $personnel);

        $validated = $request->validate([
            'project_id' => ['nullable', 'exists:projects,id'],
        ]);

        $oldProjectId = $personnel->project_id;
        $newProjectId = $validated['project_id'] ?? null;
        $now = now()->toDateString();

        DB::transaction(function () use ($personnel, $oldProjectId, $newProjectId, $now) {
            // If unassigning or switching project, close previous history and record audit trail
            if ($oldProjectId && $oldProjectId != $newProjectId) {
                \App\Models\ProjectPersonnelHistory::where('project_id', $oldProjectId)
                    ->where('personnel_id', $personnel->id)
                    ->whereNull('released_at')
                    ->update([
                        'released_at'    => $now,
                        'release_reason' => $newProjectId ? 'reassigned' : 'unassigned',
                        'released_by'    => auth()->id(),
                    ]);

                $oldProject = Project::find($oldProjectId);
                if ($oldProject) {
                    \App\Models\ProjectStatusHistory::create([
                        'project_id'  => $oldProjectId,
                        'from_status' => $oldProject->status,
                        'to_status'   => $oldProject->status,
                        'notes'       => $newProjectId
                            ? "Reassigned manpower {$personnel->employee_id} ({$personnel->full_name} - {$personnel->expertise}) to Project " . (Project::find($newProjectId)?->project_code ?? '')
                            : "Unassigned manpower: {$personnel->employee_id} ({$personnel->full_name} - {$personnel->expertise})",
                        'changed_by'  => auth()->id(),
                        'changed_at'  => now(),
                    ]);
                }
            }

            $personnel->update([
                'project_id'    => $newProjectId,
                'date_assigned' => $newProjectId ? $now : null,
                'updated_by'    => auth()->id(),
            ]);

            // If assigning to a new project, create history record and audit log
            if ($newProjectId && $oldProjectId != $newProjectId) {
                \App\Models\ProjectPersonnelHistory::create([
                    'project_id'   => $newProjectId,
                    'personnel_id' => $personnel->id,
                    'assigned_at'  => $now,
                    'assigned_by'  => auth()->id(),
                ]);

                $newProject = Project::find($newProjectId);
                if ($newProject) {
                    \App\Models\ProjectStatusHistory::create([
                        'project_id'  => $newProjectId,
                        'from_status' => $newProject->status,
                        'to_status'   => $newProject->status,
                        'notes'       => "Assigned manpower: {$personnel->employee_id} ({$personnel->full_name} - {$personnel->expertise})",
                        'changed_by'  => auth()->id(),
                        'changed_at'  => now(),
                    ]);
                }
            }
        });

        $action = $newProjectId ? 'reassigned' : 'unassigned';

        return redirect()
            ->route('scheduling.index')
            ->with('status', "Personnel {$personnel->employee_id} has been {$action} successfully.");
    }

    /**
     * Reassign multiple selected personnel to a project (or unassign them in bulk).
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulkReassign(Request $request)
    {
        $validated = $request->validate([
            'personnel_ids'   => ['required', 'array', 'min:1'],
            'personnel_ids.*' => ['required', 'exists:personnel,id'],
            'project_id'      => ['nullable', 'exists:projects,id'],
        ]);

        $newProjectId = $validated['project_id'] ?? null;
        $now = now()->toDateString();
        $count = 0;

        $targetProject = $newProjectId ? Project::find($newProjectId) : null;
        if ($targetProject && $targetProject->status === 'completed') {
            return redirect()
                ->route('scheduling.index')
                ->with('error', "Cannot assign personnel to a completed/archived project.");
        }

        $personnelList = Personnel::whereIn('id', $validated['personnel_ids'])->get();
        $oldProjectIds = $personnelList->pluck('project_id')->filter()->unique();
        $oldProjects = Project::whereIn('id', $oldProjectIds)->get()->keyBy('id');

        DB::transaction(function () use ($personnelList, $oldProjects, $newProjectId, $targetProject, $now, &$count) {
            foreach ($personnelList as $personnel) {
                $this->authorize('update', $personnel);

                $oldProjectId = $personnel->project_id;

                // If changing or clearing assignment, close open history record
                if ($oldProjectId && $oldProjectId != $newProjectId) {
                    \App\Models\ProjectPersonnelHistory::where('project_id', $oldProjectId)
                        ->where('personnel_id', $personnel->id)
                        ->whereNull('released_at')
                        ->update([
                            'released_at'    => $now,
                            'release_reason' => $newProjectId ? 'reassigned' : 'unassigned',
                            'released_by'    => auth()->id(),
                        ]);

                    $oldProject = $oldProjects->get($oldProjectId);
                    if ($oldProject) {
                        \App\Models\ProjectStatusHistory::create([
                            'project_id'  => $oldProjectId,
                            'from_status' => $oldProject->status,
                            'to_status'   => $oldProject->status,
                            'notes'       => $newProjectId
                                ? "Reassigned manpower {$personnel->employee_id} ({$personnel->full_name}) to Project " . ($targetProject?->project_code ?? '')
                                : "Unassigned manpower: {$personnel->employee_id} ({$personnel->full_name} - {$personnel->expertise})",
                            'changed_by'  => auth()->id(),
                            'changed_at'  => now(),
                        ]);
                    }
                }

                $personnel->update([
                    'project_id'    => $newProjectId,
                    'date_assigned' => $newProjectId ? $now : null,
                    'updated_by'    => auth()->id(),
                ]);

                // If assigning to a new project, create history record
                if ($newProjectId && $oldProjectId != $newProjectId) {
                    \App\Models\ProjectPersonnelHistory::create([
                        'project_id'   => $newProjectId,
                        'personnel_id' => $personnel->id,
                        'assigned_at'  => $now,
                        'assigned_by'  => auth()->id(),
                    ]);
                }

                $count++;
            }

            if ($targetProject) {
                $summary = $personnelList->map(fn ($p) => "{$p->employee_id} ({$p->full_name} - {$p->expertise})")->implode(', ');
                \App\Models\ProjectStatusHistory::create([
                    'project_id'  => $targetProject->id,
                    'from_status' => $targetProject->status,
                    'to_status'   => $targetProject->status,
                    'notes'       => "Assigned {$count} manpower (Batch): {$summary}",
                    'changed_by'  => auth()->id(),
                    'changed_at'  => now(),
                ]);
            }
        });

        $projectName = $targetProject ? "Project {$targetProject->project_code} ({$targetProject->name})" : "none (Unassigned)";
        $message = $newProjectId
            ? "Successfully assigned {$count} personnel to {$projectName}."
            : "Successfully unassigned {$count} personnel (marked Available).";

        return redirect()
            ->route('scheduling.index')
            ->with('status', $message);
    }
}
