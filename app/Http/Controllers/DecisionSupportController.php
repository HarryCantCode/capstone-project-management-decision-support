<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\DecisionSupport\DecisionSupportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * DecisionSupportController
 *
 * Handles requests for the Decision Support module, allowing Managers and Admins
 * to generate and view recommendations for project resourcing and timelines.
 */
class DecisionSupportController extends Controller implements HasMiddleware
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
     * Display the Decision Support scaffold dashboard.
     * Analyzes the provided project ID if present.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Services\DecisionSupport\DecisionSupportService $service
     * @return \Illuminate\View\View
     */
    public function index(Request $request, DecisionSupportService $service)
    {
        $projects = Project::latest()->get();
        $selectedProject = null;
        $result = null;

        if ($request->has('project_id')) {
            $selectedProject = Project::findOrFail($request->project_id);
            // Simulate generation of placeholder recommendation
            $result = $service->generateRecommendation($selectedProject);
        }

        return view('decision-support.index', compact('projects', 'selectedProject', 'result'));
    }
}
