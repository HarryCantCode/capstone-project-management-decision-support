<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResourceAllocationRequest;
use App\Models\Project;
use App\Models\Resource;
use App\Services\ResourceAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * ResourceAllocationController
 *
 * Handles the assignment of resources (personnel, equipment) to projects.
 */
class ResourceAllocationController extends Controller
{
    public function __construct(
        private readonly ResourceAllocationService $resourceAllocationService,
    ) {
    }

    /**
     * Show the form for allocating a specific resource to a project.
     *
     * @param \App\Models\Resource $resource
     * @return \Illuminate\View\View
     */
    public function create(Resource $resource): View
    {
        $this->authorize('allocate', $resource);

        $projects = Project::query()
            ->whereIn('status', ['pending', 'ongoing'])
            ->orderBy('name')
            ->get(['id', 'name', 'project_code']);

        return view('resources.allocate', compact('resource', 'projects'));
    }

    /**
     * Store a newly created resource allocation in storage.
     *
     * @param \App\Http\Requests\StoreResourceAllocationRequest $request
     * @param \App\Models\Resource $resource
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreResourceAllocationRequest $request, Resource $resource): RedirectResponse
    {
        $this->authorize('allocate', $resource);

        $project = Project::findOrFail($request->integer('project_id'));
        $validated = $request->validated();

        $this->resourceAllocationService->allocate(
            resource: $resource,
            project: $project,
            quantity: $validated['quantity'],
            notes: $validated['notes'] ?? null,
        );

        return redirect()
            ->route('resources.show', $resource)
            ->with('status', "{$resource->name} allocated successfully.");
    }
}
