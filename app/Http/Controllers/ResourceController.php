<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\View\View;

/**
 * ResourceController
 *
 * Manages the display and detailing of system resources.
 */
class ResourceController extends Controller
{
    /**
     * Display a listing of resources, optionally filtered by type.
     *
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        $this->authorize('viewAny', Resource::class);

        $type = request()->query('type');

        $resources = Resource::query()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('resources.index', compact('resources', 'type'));
    }

    /**
     * Display the specified resource and its allocations.
     *
     * @param \App\Models\Resource $resource
     * @return \Illuminate\View\View
     */
    public function show(Resource $resource): View
    {
        $this->authorize('view', $resource);

        $resource->load(['allocations.project', 'allocations.creator']);

        return view('resources.show', compact('resource'));
    }
}
