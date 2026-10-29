@extends('layouts.app')

@section('title', 'Dashboard')
@section('meta-description', 'Overview of Dex International Co. project status and activity.')

@section('content')
    <div class="page-header">
        <div>
            <h1>Dashboard</h1>
            <p class="page-header__description">
                Good {{ now()->format('g:i A') }} — here's what's happening today.
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('projects.create') }}" class="btn btn-primary">+ New Project</a>
        </div>
    </div>

    {{-- Status summary cards --}}
    <div class="dashboard-stats mb-6" x-data="dashboardStats({{ json_encode($stats) }})">
        <div class="stat-card card-glass">
            <div class="stat-card__value numeric" x-text="stats.projects_total"></div>
            <div class="stat-card__label">Total Projects</div>
        </div>
        <div class="stat-card card-glass">
            <div class="stat-card__value numeric" x-text="stats.projects_ongoing"></div>
            <div class="stat-card__label">
                <span class="status-pill status-pill--ongoing" aria-label="Ongoing projects">Ongoing</span>
            </div>
        </div>
        <div class="stat-card card-glass">
            <div class="stat-card__value numeric" x-text="stats.projects_pending"></div>
            <div class="stat-card__label">
                <span class="status-pill status-pill--pending" aria-label="Pending projects">Pending</span>
            </div>
        </div>
        <div class="stat-card card-glass">
            <div class="stat-card__value numeric" x-text="stats.projects_completed"></div>
            <div class="stat-card__label">
                <span class="status-pill status-pill--completed" aria-label="Completed projects">Completed</span>
            </div>
        </div>
    </div>

    {{-- Multi-module Dashboard Grid --}}
    <div class="dashboard-grid">
        
        {{-- Recent Projects --}}
        <div class="card card-glass dashboard-panel">
            <div class="card-header">
                <span>Recent Projects</span>
                <a href="{{ route('projects.index') }}" class="btn btn-secondary btn-sm">View all</a>
            </div>
            <div class="card-body">
                <div class="empty-state" style="padding: var(--space-6) var(--space-4);">
                    <h3 class="empty-state__title" style="font-size: 0.875rem;">Projects Module Active</h3>
                    <p class="empty-state__description" style="font-size: 0.8125rem;">Navigate to the Projects tab to view and manage active timelines.</p>
                </div>
            </div>
        </div>

        {{-- Resource Allocations --}}
        <div class="card card-glass dashboard-panel">
            <div class="card-header">
                <span>Resource Utilization</span>
                <a href="{{ route('resources.index') }}" class="btn btn-secondary btn-sm">Manage</a>
            </div>
            <div class="card-body">
                <div class="empty-state" style="padding: var(--space-6) var(--space-4);">
                    <h3 class="empty-state__title" style="font-size: 0.875rem;">Inventory Active</h3>
                    <p class="empty-state__description" style="font-size: 0.8125rem;">Stock levels and material allocations are actively being tracked.</p>
                </div>
            </div>
        </div>

        {{-- Manpower & Scheduling --}}
        <div class="card card-glass dashboard-panel">
            <div class="card-header">
                <span>Personnel & Scheduling</span>
                <a href="{{ route('scheduling.index') }}" class="btn btn-secondary btn-sm">View Schedule</a>
            </div>
            <div class="card-body">
                <div class="empty-state" style="padding: var(--space-6) var(--space-4);">
                    <h3 class="empty-state__title" style="font-size: 0.875rem;">Scheduling & Roster</h3>
                    <p class="empty-state__description" style="font-size: 0.8125rem;">Employee scheduling and shift management.</p>
                </div>
            </div>
        </div>

        {{-- Costing & Reports --}}
        <div class="card card-glass dashboard-panel">
            <div class="card-header">
                <span>Financials & Insights</span>
                <div>
                    <a href="{{ route('costing.index') }}" class="btn btn-secondary btn-sm">Costing</a>
                    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">Reports</a>
                </div>
            </div>
            <div class="card-body">
                <div class="empty-state" style="padding: var(--space-6) var(--space-4);">
                    <h3 class="empty-state__title" style="font-size: 0.875rem;">Reporting Dashboard</h3>
                    <p class="empty-state__description" style="font-size: 0.8125rem;">Real-time budget tracking and insights.</p>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('styles')
<style>
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1.5rem;
}
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
}
.dashboard-panel {
    display: flex;
    flex-direction: column;
}
.dashboard-panel .card-body {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}
.mb-6 { margin-bottom: 1.5rem; }
@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('dashboardStats', (initialStats) => ({
            stats: initialStats,
            init() {
                if (window.Echo) {
                    window.Echo.channel('projects')
                        .listen('ProjectCreated', (e) => {
                            this.stats = e.stats;
                        })
                        .listen('ProjectDeleted', (e) => {
                            this.stats = e.stats;
                        });
                }
            }
        }));
    });
</script>
@endpush
