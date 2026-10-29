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

    {{-- Retained Status summary cards (Clickable to Projects module with optional status filter) --}}
    <div class="costing-kpi-grid dashboard-stats-grid mb-6" x-data="dashboardStats({{ json_encode($stats) }})">
        {{-- Card 1: Total Projects --}}
        <a href="{{ route('projects.index') }}" class="costing-kpi-card costing-kpi-card--interactive costing-kpi-card--contract" title="View all projects">
            {{-- Background Watermark Icon --}}
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>

            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Total Projects</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--contract" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </span>
                </div>
                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric" x-text="stats.projects_total"></div>
                    <p class="costing-kpi-card__description">
                        All project initiatives tracked across the system.
                    </p>
                </div>
            </div>
            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                    </span>
                    Complete Records
                </span>
                <span class="costing-kpi-card__pill costing-kpi-card__pill--neutral">
                    <span>View All</span>
                    <span class="costing-kpi-card__arrow" aria-hidden="true">&rarr;</span>
                </span>
            </div>
        </a>

        {{-- Card 2: Ongoing Projects --}}
        <a href="{{ route('projects.index', ['status' => 'ongoing']) }}" class="costing-kpi-card costing-kpi-card--interactive costing-kpi-card--ongoing" title="View ongoing projects">
            {{-- Background Watermark Icon --}}
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
            </svg>

            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Ongoing Projects</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--ongoing" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </span>
                </div>
                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric text-ongoing" x-text="stats.projects_ongoing"></div>
                    <p class="costing-kpi-card__description">
                        Currently in active construction and field operations.
                    </p>
                </div>
            </div>
            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                    </span>
                    Active Works
                </span>
                <span class="costing-kpi-card__pill costing-kpi-card__pill--ongoing">
                    <span class="status-pill status-pill--ongoing" aria-label="Ongoing projects">Ongoing</span>
                    <span class="costing-kpi-card__arrow" aria-hidden="true">&rarr;</span>
                </span>
            </div>
        </a>

        {{-- Card 3: Pending Projects --}}
        <a href="{{ route('projects.index', ['status' => 'pending']) }}" class="costing-kpi-card costing-kpi-card--interactive costing-kpi-card--pending" title="View pending projects">
            {{-- Background Watermark Icon --}}
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>

            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Pending Projects</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--pending" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </span>
                </div>
                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric text-pending" x-text="stats.projects_pending"></div>
                    <p class="costing-kpi-card__description">
                        Awaiting kickoff, documentation, or client approval.
                    </p>
                </div>
            </div>
            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 14 14"></polyline>
                        </svg>
                    </span>
                    Awaiting Start
                </span>
                <span class="costing-kpi-card__pill costing-kpi-card__pill--pending">
                    <span class="status-pill status-pill--pending" aria-label="Pending projects">Pending</span>
                    <span class="costing-kpi-card__arrow" aria-hidden="true">&rarr;</span>
                </span>
            </div>
        </a>

        {{-- Card 4: Completed Projects --}}
        <a href="{{ route('projects.index', ['status' => 'completed']) }}" class="costing-kpi-card costing-kpi-card--interactive costing-kpi-card--completed" title="View completed projects">
            {{-- Background Watermark Icon --}}
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>

            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Completed Projects</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--completed" aria-hidden="true">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </span>
                </div>
                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric text-completed" x-text="stats.projects_completed"></div>
                    <p class="costing-kpi-card__description">
                        Successfully finished and turned over to clients.
                    </p>
                </div>
            </div>
            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </span>
                    Turned Over
                </span>
                <span class="costing-kpi-card__pill costing-kpi-card__pill--completed">
                    <span class="status-pill status-pill--completed" aria-label="Completed projects">Completed</span>
                    <span class="costing-kpi-card__arrow" aria-hidden="true">&rarr;</span>
                </span>
            </div>
        </a>
    </div>

    {{-- Main Analytical Dashboard Grid --}}
    <div class="dashboard-grid">
        
        {{-- Section 1: Task Overview with Graphical Milestone Organizer --}}
        <div class="card card-glass dashboard-panel">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title">Task Overview</h2>
                    <span class="card-header__subtitle">Schedule breakdown &amp; execution metrics</span>
                </div>
                <a href="{{ route('projects.index') }}" class="btn btn-secondary btn-sm">Manage Tasks</a>
            </div>
            <div class="card-body">
                <div class="task-overview-grid mb-4">
                    <div class="task-stat-box task-stat-box--total">
                        <div class="task-stat-box__icon" aria-hidden="true">
                            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                        </div>
                        <div class="task-stat-box__value numeric">{{ $taskOverview['total_mother_tasks'] }}</div>
                        <div class="task-stat-box__label">Total Tasks</div>
                    </div>

                    <div class="task-stat-box task-stat-box--progress">
                        <div class="task-stat-box__icon" aria-hidden="true">
                            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="task-stat-box__value numeric">{{ $taskOverview['in_progress_mother_tasks'] }}</div>
                        <div class="task-stat-box__label">In Progress</div>
                    </div>

                    <div class="task-stat-box task-stat-box--completed">
                        <div class="task-stat-box__icon" aria-hidden="true">
                            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="task-stat-box__value numeric">{{ $taskOverview['completed_mother_tasks'] }}</div>
                        <div class="task-stat-box__label">Completed</div>
                    </div>
                </div>

                {{-- Graphical Organizer: Task Pipeline Flow Diagram --}}
                <div class="graphical-pipeline mb-4">
                    <div class="graphical-pipeline__title">Task Stage Distribution</div>
                    <div class="pipeline-bar-wrapper">
                        @php
                            $totalM = max(1, $taskOverview['total_mother_tasks']);
                            $pctPending = round(($taskOverview['pending_mother_tasks'] / $totalM) * 100);
                            $pctProg = round(($taskOverview['in_progress_mother_tasks'] / $totalM) * 100);
                            $pctComp = round(($taskOverview['completed_mother_tasks'] / $totalM) * 100);
                        @endphp
                        <div class="pipeline-segment pipeline-segment--pending" style="width: {{ $taskOverview['total_mother_tasks'] > 0 ? $pctPending : 0 }}%;" title="Pending: {{ $taskOverview['pending_mother_tasks'] }}"></div>
                        <div class="pipeline-segment pipeline-segment--progress" style="width: {{ $taskOverview['total_mother_tasks'] > 0 ? $pctProg : 0 }}%;" title="In Progress: {{ $taskOverview['in_progress_mother_tasks'] }}"></div>
                        <div class="pipeline-segment pipeline-segment--completed" style="width: {{ $taskOverview['total_mother_tasks'] > 0 ? $pctComp : 0 }}%;" title="Completed: {{ $taskOverview['completed_mother_tasks'] }}"></div>
                    </div>
                    <div class="pipeline-legend">
                        <div class="pipeline-legend__item">
                            <span class="legend-dot legend-dot--pending"></span>
                            <span>Pending ({{ $taskOverview['pending_mother_tasks'] }})</span>
                        </div>
                        <div class="pipeline-legend__item">
                            <span class="legend-dot legend-dot--progress"></span>
                            <span>In Progress ({{ $taskOverview['in_progress_mother_tasks'] }})</span>
                        </div>
                        <div class="pipeline-legend__item">
                            <span class="legend-dot legend-dot--completed"></span>
                            <span>Completed ({{ $taskOverview['completed_mother_tasks'] }})</span>
                        </div>
                    </div>
                </div>

                {{-- Subtask / Checklist Execution Status --}}
                <div class="task-checklist-summary">
                    <div class="task-checklist-header">
                        <span class="task-checklist-title">Checklist Execution Rate</span>
                        <span class="task-checklist-rate numeric">{{ $taskOverview['completion_rate'] }}%</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill progress-bar-fill--completed" style="width: {{ $taskOverview['completion_rate'] }}%;"></div>
                    </div>
                    <div class="task-checklist-footer">
                        <span><strong>{{ $taskOverview['completed_subtasks'] }}</strong> completed</span>
                        <span><strong>{{ $taskOverview['pending_subtasks'] }}</strong> remaining</span>
                        <span><strong>{{ $taskOverview['total_subtasks'] }}</strong> total subtasks</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 2: Manpower Statistics with Graphical Radial & Distribution Organizer --}}
        <div class="card card-glass dashboard-panel">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title">Manpower Statistics</h2>
                    <span class="card-header__subtitle">Personnel deployment &amp; specialization distribution</span>
                </div>
                <a href="{{ route('scheduling.index') }}" class="btn btn-secondary btn-sm">Manage Roster</a>
            </div>
            <div class="card-body">
                
                {{-- Graphical Organizer: Circular Gauge & Deployment Status --}}
                <div class="manpower-graphic-organizer mb-4">
                    <div class="donut-chart-container">
                        @php
                            $utilRate = $manpowerStats['utilization_rate'];
                            $circumference = 2 * 3.14159 * 42; // r = 42
                            $offset = $circumference - ($utilRate / 100) * $circumference;
                        @endphp
                        <svg class="donut-svg" width="110" height="110" viewBox="0 0 100 100">
                            <circle class="donut-track" cx="50" cy="50" r="42" fill="transparent" stroke-width="10"></circle>
                            <circle class="donut-fill" cx="50" cy="50" r="42" fill="transparent" stroke-width="10"
                                stroke-dasharray="{{ $circumference }}"
                                stroke-dashoffset="{{ $offset }}"
                                stroke-linecap="round"
                                transform="rotate(-90 50 50)"></circle>
                        </svg>
                        <div class="donut-center-text">
                            <span class="donut-value numeric">{{ $manpowerStats['utilization_rate'] }}%</span>
                            <span class="donut-sublabel">Assigned</span>
                        </div>
                    </div>

                    <div class="manpower-graphic-summary">
                        <div class="graphic-stat-row">
                            <div class="graphic-stat-dot graphic-stat-dot--assigned"></div>
                            <div class="graphic-stat-info">
                                <span class="graphic-stat-label">Currently Assigned</span>
                                <span class="graphic-stat-val numeric">{{ $manpowerStats['assigned'] }} workers</span>
                            </div>
                        </div>
                        <div class="graphic-stat-row">
                            <div class="graphic-stat-dot graphic-stat-dot--available"></div>
                            <div class="graphic-stat-info">
                                <span class="graphic-stat-label">Available for Deployment</span>
                                <span class="graphic-stat-val numeric">{{ $manpowerStats['available'] }} workers</span>
                            </div>
                        </div>
                        <div class="graphic-stat-row">
                            <div class="graphic-stat-dot graphic-stat-dot--total"></div>
                            <div class="graphic-stat-info">
                                <span class="graphic-stat-label">Total Field Workers</span>
                                <span class="graphic-stat-val numeric">{{ $manpowerStats['total'] }} workers</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Graphical Organizer: Specialization Breakdown Bar Graph --}}
                @if($manpowerStats['by_expertise']->isNotEmpty())
                    <div class="manpower-expertise-block">
                        <h4 class="manpower-expertise-heading">Distribution by Specialization</h4>
                        <div class="manpower-expertise-list">
                            @foreach($manpowerStats['by_expertise'] as $item)
                                @php
                                    $expPercentage = $manpowerStats['total'] > 0 ? round(($item->count / $manpowerStats['total']) * 100) : 0;
                                @endphp
                                <div class="manpower-expertise-item">
                                    <div class="manpower-expertise-meta">
                                        <span class="manpower-expertise-name">{{ $item->expertise ?? 'Unassigned Role' }}</span>
                                        <span class="manpower-expertise-count numeric">{{ $item->count }} ({{ $expPercentage }}%)</span>
                                    </div>
                                    <div class="progress-bar-container progress-bar-container--sm">
                                        <div class="progress-bar-fill progress-bar-fill--primary" style="width: {{ $expPercentage }}%;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Section 3: Recent Projects & Progression (At the bottom, spanning full width) --}}
        <div class="card card-glass dashboard-panel dashboard-panel--projects">
            <div class="card-header">
                <div>
                    <h2 class="card-header__title">Recent Projects &amp; Progression</h2>
                    <span class="card-header__subtitle">Latest active installations, status, and milestone completion</span>
                </div>
                <a href="{{ route('projects.index') }}" class="btn btn-secondary btn-sm">All Projects &rarr;</a>
            </div>
            <div class="card-body p-0">
                @if($recentProjects->isEmpty())
                    <div class="empty-state" style="padding: var(--space-8) var(--space-4);">
                        <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="empty-state__icon">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <h3 class="empty-state__title" style="font-size: 0.9375rem;">No projects recorded yet</h3>
                        <p class="empty-state__description" style="font-size: 0.8125rem;">Create a project to start tracking timeline tasks and overall progress.</p>
                        <a href="{{ route('projects.create') }}" class="btn btn-primary btn-sm mt-3">+ New Project</a>
                    </div>
                @else
                    <div class="recent-projects-list">
                        @foreach($recentProjects as $project)
                            <a href="{{ $project['url'] }}" class="recent-project-row" title="Open {{ $project['name'] }}">
                                <div class="recent-project-main">
                                    <div class="recent-project-title-wrap">
                                        <span class="recent-project-code">{{ $project['project_code'] }}</span>
                                        <h3 class="recent-project-name">{{ $project['name'] }}</h3>
                                    </div>
                                    <div class="recent-project-meta">
                                        <span class="recent-project-client">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline-block; vertical-align: -2px;">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                            {{ $project['client_name'] }}
                                        </span>
                                        @if($project['target_completion_date'])
                                            <span class="recent-project-date">
                                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline-block; vertical-align: -2px;">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                Target: {{ $project['target_completion_date'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="recent-project-progress-col">
                                    <div class="recent-project-status-badge">
                                        <span class="status-pill status-pill--{{ $project['status'] }}">
                                            {{ in_array($project['status'], ['ongoing', 'in_progress']) ? 'Ongoing' : ucfirst($project['status']) }}
                                        </span>
                                        <span class="recent-project-percentage numeric">{{ $project['progress_percentage'] }}%</span>
                                    </div>
                                    
                                    {{-- Status Progress Bar --}}
                                    <div class="progress-bar-container" role="progressbar" aria-valuenow="{{ $project['progress_percentage'] }}" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar-fill progress-bar-fill--{{ $project['status'] }}" style="width: {{ $project['progress_percentage'] }}%;"></div>
                                    </div>

                                    <div class="recent-project-subtasks-summary">
                                        @if($project['total_subtasks'] > 0)
                                            <span>{{ $project['completed_subtasks'] }} of {{ $project['total_subtasks'] }} checklist tasks done</span>
                                        @else
                                            <span>{{ $project['total_tasks'] }} major tasks scheduled</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="recent-project-arrow" aria-hidden="true">
                                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    </div>
@endsection

@push('styles')
<style>
/* Stat Cards Enhancement */
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.25rem;
}

.stat-card--interactive {
    text-decoration: none;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    cursor: pointer;
    transition: transform var(--transition-fast), box-shadow var(--transition-fast), border-color var(--transition-fast);
}

.stat-card--interactive:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: var(--color-primary-light, #93c5fd);
}

.stat-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--space-2);
}

.stat-card__icon {
    width: 38px;
    height: 38px;
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-surface-alt, #f3f4f6);
    color: var(--color-ink-muted, #6b7280);
}

.stat-card__icon--ongoing {
    background: rgba(37, 99, 235, 0.1);
    color: #2563eb;
}

.stat-card__icon--pending {
    background: rgba(217, 119, 6, 0.1);
    color: #d97706;
}

.stat-card__icon--completed {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.stat-card__label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.8125rem;
    color: var(--color-muted);
    font-weight: 500;
}

.stat-card__arrow {
    opacity: 0;
    transform: translateX(-4px);
    transition: opacity var(--transition-fast), transform var(--transition-fast);
    color: var(--color-primary);
    font-weight: bold;
}

.stat-card--interactive:hover .stat-card__arrow {
    opacity: 1;
    transform: translateX(0);
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1.5rem;
}

.dashboard-panel {
    display: flex;
    flex-direction: column;
}

.dashboard-panel--projects {
    grid-column: span 2;
}

.card-header__title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--color-ink);
    margin: 0;
}

.card-header__subtitle {
    display: block;
    font-size: 0.75rem;
    color: var(--color-muted);
    margin-top: 2px;
}

/* Recent Projects Progression List */
.recent-projects-list {
    display: flex;
    flex-direction: column;
}

.recent-project-row {
    display: grid;
    grid-template-columns: 1fr 340px auto;
    align-items: center;
    gap: 1.5rem;
    padding: var(--space-4) var(--space-6);
    border-bottom: 1px solid var(--color-border);
    text-decoration: none;
    color: inherit;
    transition: background-color var(--transition-fast);
}

.recent-project-row:last-child {
    border-bottom: none;
}

.recent-project-row:hover {
    background-color: var(--color-surface-hover, rgba(0, 0, 0, 0.02));
}

.recent-project-title-wrap {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.recent-project-code {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: var(--radius-sm);
    background: var(--color-surface-alt);
    color: var(--color-ink-muted);
    border: 1px solid var(--color-border);
}

.recent-project-name {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--color-ink);
    margin: 0;
}

.recent-project-meta {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    font-size: 0.8125rem;
    color: var(--color-muted);
}

.recent-project-progress-col {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.recent-project-status-badge {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.recent-project-percentage {
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--color-ink);
}

.recent-project-subtasks-summary {
    font-size: 0.75rem;
    color: var(--color-muted);
    text-align: right;
}

.recent-project-arrow {
    color: var(--color-muted);
    transition: transform var(--transition-fast), color var(--transition-fast);
}

.recent-project-row:hover .recent-project-arrow {
    color: var(--color-primary);
    transform: translateX(3px);
}

/* Progress Bar Component */
.progress-bar-container {
    width: 100%;
    height: 8px;
    background-color: var(--color-border, #e5e7eb);
    border-radius: var(--radius-full, 9999px);
    overflow: hidden;
}

.progress-bar-container--sm {
    height: 6px;
}

.progress-bar-fill {
    height: 100%;
    border-radius: var(--radius-full, 9999px);
    transition: width 0.4s ease-in-out;
}

.progress-bar-fill--primary {
    background: var(--color-primary, #2563eb);
}

.progress-bar-fill--ongoing,
.progress-bar-fill--in_progress {
    background: linear-gradient(90deg, #3b82f6, #2563eb);
}

.progress-bar-fill--completed {
    background: linear-gradient(90deg, #10b981, #059669);
}

.progress-bar-fill--pending {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}

.progress-bar-fill--delayed {
    background: linear-gradient(90deg, #ef4444, #dc2626);
}

/* Task Overview Grid */
.task-overview-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.875rem;
}

.task-stat-box {
    background: var(--color-surface-alt);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-4);
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.task-stat-box__icon {
    margin-bottom: 2px;
}

.task-stat-box--total .task-stat-box__icon { color: var(--color-ink-muted); }
.task-stat-box--progress .task-stat-box__icon { color: #2563eb; }
.task-stat-box--completed .task-stat-box__icon { color: #10b981; }

.task-stat-box__value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-ink);
    line-height: 1.1;
}

.task-stat-box__label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--color-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

/* Graphical Pipeline Organizer */
.graphical-pipeline {
    background: var(--color-surface-alt);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-3) var(--space-4);
}

.graphical-pipeline__title {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--color-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: var(--space-2);
}

.pipeline-bar-wrapper {
    display: flex;
    height: 10px;
    border-radius: var(--radius-full);
    overflow: hidden;
    background: var(--color-border);
    margin-bottom: var(--space-3);
}

.pipeline-segment {
    height: 100%;
    transition: width 0.3s ease;
}

.pipeline-segment--pending { background-color: #f59e0b; }
.pipeline-segment--progress { background-color: #2563eb; }
.pipeline-segment--completed { background-color: #10b981; }

.pipeline-legend {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--color-muted);
}

.pipeline-legend__item {
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.legend-dot--pending { background-color: #f59e0b; }
.legend-dot--progress { background-color: #2563eb; }
.legend-dot--completed { background-color: #10b981; }

.task-checklist-summary {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-4);
}

.task-checklist-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-2);
}

.task-checklist-title {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--color-ink);
}

.task-checklist-rate {
    font-size: 0.875rem;
    font-weight: 700;
    color: #10b981;
}

.task-checklist-footer {
    display: flex;
    justify-content: space-between;
    margin-top: var(--space-2);
    font-size: 0.75rem;
    color: var(--color-muted);
}

/* Manpower Graphic Organizer with Circular Radial Gauge */
.manpower-graphic-organizer {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    background: var(--color-surface-alt);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: var(--space-4);
}

.donut-chart-container {
    position: relative;
    width: 110px;
    height: 110px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.donut-svg {
    width: 100%;
    height: 100%;
}

.donut-track {
    stroke: var(--color-border, #e5e7eb);
}

.donut-fill {
    stroke: #2563eb;
    transition: stroke-dashoffset 0.6s ease;
}

.donut-center-text {
    position: absolute;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.donut-value {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--color-ink);
    line-height: 1.1;
}

.donut-sublabel {
    font-size: 0.6875rem;
    color: var(--color-muted);
    text-transform: uppercase;
}

.manpower-graphic-summary {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.graphic-stat-row {
    display: flex;
    align-items: center;
    gap: 0.625rem;
}

.graphic-stat-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}

.graphic-stat-dot--assigned { background-color: #2563eb; }
.graphic-stat-dot--available { background-color: #10b981; }
.graphic-stat-dot--total { background-color: #6b7280; }

.graphic-stat-info {
    display: flex;
    flex-direction: column;
}

.graphic-stat-label {
    font-size: 0.6875rem;
    color: var(--color-muted);
    font-weight: 500;
}

.graphic-stat-val {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--color-ink);
}

.manpower-expertise-block {
    margin-top: var(--space-4);
    padding-top: var(--space-4);
    border-top: 1px solid var(--color-border);
}

.manpower-expertise-heading {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--color-ink);
    margin-bottom: var(--space-3);
}

.manpower-expertise-list {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.manpower-expertise-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    margin-bottom: 2px;
}

.manpower-expertise-name {
    color: var(--color-ink);
    font-weight: 500;
}

.manpower-expertise-count {
    color: var(--color-muted);
}

.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.mt-3 { margin-top: 0.75rem; }
.p-0 { padding: 0 !important; }

@media (max-width: 1024px) {
    .dashboard-panel--projects {
        grid-column: span 1;
    }
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    .recent-project-row {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    .recent-project-arrow {
        display: none;
    }
    .manpower-graphic-organizer {
        flex-direction: column;
        align-items: flex-start;
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
