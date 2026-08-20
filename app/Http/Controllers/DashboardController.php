<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Response;
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
     * Short (2 min) — status changes should be visible promptly.
     */
    private const CACHE_TTL_SECONDS = 120;

    public function __invoke(): View
    {
        // Authorization: Dashboard is for Admin and Manager only.
        // Inventory Staff receives a 403 — hard block, not redirect.
        if (! Auth::user()->hasAnyRole(['Admin', 'Manager'])) {
            abort(403);
        }

        // Counts are stubbed here — real queries added in Phase 2
        // when the projects table exists.
        $stats = cache()->remember(
            key: 'dashboard.stats.' . Auth::id(),
            ttl: self::CACHE_TTL_SECONDS,
            callback: fn () => $this->buildStats(),
        );

        return view('dashboard', compact('stats'));
    }

    /**
     * Build dashboard statistics.
     *
     * Runs aggregate queries only when the cache is cold.
     * Each stat is computed independently so a missing table
     * (during early phases) doesn't break the whole dashboard.
     *
     * @return array<string, int|string>
     */
    private function buildStats(): array
    {
        // Phase 2+ will replace these with real Eloquent queries, e.g.:
        // 'projects_total' => Project::count(),
        // 'projects_ongoing' => Project::where('status', 'ongoing')->count(),
        // For now return zeros so the dashboard view renders without errors.
        return [
            'projects_total' => 0,
            'projects_pending' => 0,
            'projects_ongoing' => 0,
            'projects_completed' => 0,
        ];
    }
}
