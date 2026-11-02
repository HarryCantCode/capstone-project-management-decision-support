<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResourceRequest;
use App\Http\Requests\UpdateResourceRequest;
use App\Models\Resource;
use App\Services\ResourceCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ResourceController
 *
 * Manages the Materials Catalog inventory, configuration, and allocations.
 */
class ResourceController extends Controller
{
    public function __construct(
        protected ResourceCatalogService $catalogService,
    ) {
    }

    /**
     * Display a listing of materials catalog items with filtering and search.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Resource::class);

        $demandStatus = $request->query('demand_status');
        $search = $request->query('search');

        $resources = Resource::query()
            ->when($demandStatus, fn ($q) => $q->where('demand_status', $demandStatus))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('resource_code', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('id', 'asc')
            ->paginate(15)
            ->withQueryString();

        $nextItemCode = $this->catalogService->generateNextItemCode();

        return view('resources.index', compact('resources', 'demandStatus', 'search', 'nextItemCode'));
    }

    /**
     * Store a newly created material in the catalog.
     */
    public function store(StoreResourceRequest $request): RedirectResponse
    {
        $this->catalogService->createResource($request->validated(), $request->user());

        return redirect()->route('resources.index')->with('success', 'Material added to catalog successfully.');
    }

    /**
     * Display the specified material and its allocations.
     */
    public function show(Resource $resource): View
    {
        $this->authorize('view', $resource);

        $resource->load(['allocations.project', 'allocations.creator']);

        return view('resources.show', compact('resource'));
    }

    /**
     * Update the specified material in the catalog.
     */
    public function update(UpdateResourceRequest $request, Resource $resource): RedirectResponse
    {
        $this->catalogService->updateResource($resource, $request->validated(), $request->user());

        return redirect()->route('resources.index')->with('success', "Material {$resource->resource_code} updated successfully.");
    }

    /**
     * Remove the specified material from the catalog.
     */
    public function destroy(Resource $resource): RedirectResponse
    {
        $this->authorize('delete', $resource);

        $this->catalogService->deleteResource($resource);

        return redirect()->route('resources.index')->with('success', "Material {$resource->resource_code} removed from catalog.");
    }

    /**
     * Remove multiple materials from the catalog.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorize('deleteAny', Resource::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:resources,id'],
        ]);

        $deletedCount = $this->catalogService->bulkDeleteResources($validated['ids']);

        return redirect()->route('resources.index')->with('success', "Successfully deleted {$deletedCount} selected material(s).");
    }
}
