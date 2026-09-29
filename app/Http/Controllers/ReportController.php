<?php

namespace App\Http\Controllers;

/**
 * ReportController
 *
 * Handles the generation and display of system-wide reports and metrics.
 */
class ReportController extends Controller
{
    /**
     * Display the main reports dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Dummy data for frontend UI design
        $metrics = [
            'active_projects' => 12,
            'completed_projects' => 45,
            'total_revenue' => 4500000,
            'total_expenses' => 2100000,
            'active_personnel' => 128,
        ];

        return view('reports.index', compact('metrics'));
    }
}
