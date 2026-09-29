<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * EquipmentController
 *
 * Manages the inventory tracking specifically for tools and equipment.
 * Allows updating conditions and searching across available inventory.
 */
class EquipmentController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            'auth',
            'two-factor',
            'role:Staff|Manager|Admin',
        ];
    }

    /**
     * Display a listing of the equipment and tools.
     * Supports filtering by condition and searching by name/code.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $query = Resource::whereIn('type', ['tool', 'equipment']);

        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('resource_code', 'like', '%' . $request->search . '%');
            });
        }

        $equipments = $query->paginate(15);
        $conditions = ['good' => 'Good', 'needs_maintenance' => 'Needs Maintenance', 'out_of_service' => 'Out of Service'];

        return view('equipment.index', compact('equipments', 'conditions'));
    }

    /**
     * Update the condition of the specified equipment.
     * Ensures only resources of type 'tool' or 'equipment' are modified.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Resource $equipment
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Resource $equipment)
    {
        // Ensure the resource is actually equipment/tool
        if (!in_array($equipment->type, ['tool', 'equipment'])) {
            abort(404);
        }

        $validated = $request->validate([
            'condition' => 'required|in:good,needs_maintenance,out_of_service',
        ]);

        $equipment->condition = $validated['condition'];
        $equipment->updated_by = auth()->id();
        $equipment->save();

        return redirect()->route('equipment.index')->with('status', 'Equipment condition updated successfully.');
    }
}
