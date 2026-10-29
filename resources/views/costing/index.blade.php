@extends('layouts.app')

@section('title', 'Project Costing')
@section('meta-description', 'Track and analyze real-time project expenditures, allocated budgets, and balances.')

@section('content')
    <div class="page-header">
        <div>
            <h1>Project Costing & Budget Center</h1>
            <p class="page-header__description">Monitor real-time budgets, actual expenditures, and remaining funds across all projects.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('projects.create') }}" class="btn btn-primary">+ Create New Project</a>
        </div>
    </div>

    {{-- Financial Summary KPI Cards --}}
    <div class="costing-kpi-grid">
        {{-- Card 1: Total Allocated Contract Value --}}
        <div class="costing-kpi-card costing-kpi-card--contract">
            {{-- Background Watermark Icon --}}
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>

            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Total Allocated Contract Value</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--contract" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>
                    </span>
                </div>
                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric">
                        ₱{{ number_format($totalAllocated, 2) }}
                    </div>
                    <p class="costing-kpi-card__description">
                        Total contract baseline across all registered projects in the portfolio.
                    </p>
                </div>
            </div>
            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </span>
                    Portfolio Scope
                </span>
                <span class="costing-kpi-card__pill costing-kpi-card__pill--neutral">
                    <span class="costing-kpi-card__pill-icon" aria-hidden="true">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </span>
                    {{ $projects->total() }} {{ Str::plural('Project', $projects->total()) }}
                </span>
            </div>
        </div>

        {{-- Card 2: Total Actual Spend --}}
        <div class="costing-kpi-card costing-kpi-card--spend">
            {{-- Background Watermark Icon --}}
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>

            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Total Actual Spend</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--spend" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </span>
                </div>
                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric">
                        ₱{{ number_format($totalSpent, 2) }}
                    </div>
                    <p class="costing-kpi-card__description">
                        Cumulative expenses recorded for materials, labor, and operations.
                    </p>
                </div>
            </div>
            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                            <polyline points="17 6 23 6 23 12"></polyline>
                        </svg>
                    </span>
                    Actual Outflow
                </span>
                @php
                    $spendRate = $totalAllocated > 0 ? round(($totalSpent / $totalAllocated) * 100, 1) : 0;
                @endphp
                <span class="costing-kpi-card__pill {{ $spendRate > 100 ? 'costing-kpi-card__pill--danger' : ($spendRate > 85 ? 'costing-kpi-card__pill--warning' : 'costing-kpi-card__pill--neutral') }}">
                    <span class="costing-kpi-card__pill-icon" aria-hidden="true">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </span>
                    {{ $spendRate }}% Used
                </span>
            </div>
        </div>

        {{-- Card 3: Total Remaining Budget --}}
        <div class="costing-kpi-card {{ $totalRemaining >= 0 ? 'costing-kpi-card--remaining-positive' : 'costing-kpi-card--remaining-negative' }}">
            {{-- Background Watermark Icon --}}
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            </svg>

            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Total Remaining Budget</span>
                    <span class="costing-kpi-card__icon-badge {{ $totalRemaining >= 0 ? 'costing-kpi-card__icon-badge--remaining-positive' : 'costing-kpi-card__icon-badge--remaining-negative' }}" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            <polyline points="9 12 11 14 15 10"></polyline>
                        </svg>
                    </span>
                </div>
                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric {{ $totalRemaining >= 0 ? 'costing-kpi-card__value--success' : 'costing-kpi-card__value--danger' }}">
                        ₱{{ number_format($totalRemaining, 2) }}
                    </div>
                    <p class="costing-kpi-card__description">
                        Net available funds remaining before reaching project contract limits.
                    </p>
                </div>
            </div>
            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </span>
                    Available Capital
                </span>
                @php
                    $remPercentage = $totalAllocated > 0 ? round(($totalRemaining / $totalAllocated) * 100, 1) : 0;
                @endphp
                <span class="costing-kpi-card__pill {{ $totalRemaining >= 0 ? 'costing-kpi-card__pill--neutral' : 'costing-kpi-card__pill--danger' }}">
                    <span class="costing-kpi-card__pill-icon" aria-hidden="true">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </span>
                    {{ $remPercentage }}% Left
                </span>
            </div>
        </div>
    </div>

    {{-- Costing Projects Table --}}
    <div class="card card-glass">
        <div class="card-header">
            <span>Project Budget &amp; Costing Ledger</span>
            <form method="GET" action="{{ route('costing.index') }}" class="project-header-actions" role="search">
                <div class="project-search-wrapper">
                    <label for="costing-search" class="visually-hidden">Search projects</label>
                    <svg class="project-search-icon" width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 16px; height: 16px; flex-shrink: 0;">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                    <input
                        type="text"
                        id="costing-search"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Search name, code, client..."
                        class="form-control form-control--compact project-search-input"
                        title="Search by project name, project code, or client name"
                    >
                    @if (!empty($search))
                        <a href="{{ route('costing.index', array_filter(['status' => $status])) }}" class="project-search-clear" title="Clear search" aria-label="Clear search">
                            <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="width: 12px; height: 12px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4l8 8M12 4l-8 8" />
                            </svg>
                        </a>
                    @endif
                </div>

                <div class="project-status-wrapper">
                    <label for="status" class="visually-hidden">Filter projects by status</label>
                    <select id="status" name="status" class="form-control form-control--compact project-status-select" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        <option value="pending" @selected(($status ?? '') === 'pending')>Pending</option>
                        <option value="ongoing" @selected(($status ?? '') === 'ongoing')>Ongoing</option>
                        <option value="delayed" @selected(($status ?? '') === 'delayed')>Delayed</option>
                        <option value="completed" @selected(($status ?? '') === 'completed')>Completed</option>
                    </select>
                </div>

                @if (!empty($search) || !empty($status))
                    <a href="{{ route('costing.index') }}" class="btn btn-secondary btn-sm project-filter-reset" title="Clear all filters and search">
                        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" class="project-filter-reset__icon" aria-hidden="true" style="width: 12px; height: 12px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4l8 8M12 4l-8 8" />
                        </svg>
                        <span>Clear</span>
                    </a>
                @endif
            </form>
        </div>

        @if ($projects->isEmpty())
            <div class="card-body">
                <div class="empty-state">
                    <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <h2 class="empty-state__title">No project costing records found</h2>
                    <p class="empty-state__description">
                        @if (!empty($search) || !empty($status))
                            No projects matched your search criteria.
                        @else
                            Projects created in the system will automatically project their contract prices and expenditures here.
                        @endif
                    </p>
                    @if (!empty($search) || !empty($status))
                        <a href="{{ route('costing.index') }}" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a>
                    @elseif (empty($search) && empty($status))
                        <a href="{{ route('projects.create') }}" class="btn btn-primary">+ Create First Project</a>
                    @endif
                </div>
            </div>
        @else
            <div class="table-wrapper">
                <table class="data-table">
                    <caption class="visually-hidden">Project costing and expenditure summary</caption>
                    <thead>
                        <tr>
                            <th scope="col">Project Code & Name</th>
                            <th scope="col">Client</th>
                            <th scope="col" class="text-right">Allocated Budget</th>
                            <th scope="col" class="text-right">Actual Spend</th>
                            <th scope="col" class="text-right">Remaining</th>
                            <th scope="col" style="min-width: 170px;">Budget Utilization Percentage</th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $project)
                            <tr>
                                <td>
                                    <a href="{{ route('costing.show', $project) }}" class="table-link" title="Click to view full expense breakdown">
                                        <span class="font-mono text-muted" style="font-size: 0.75rem; display: block;">{{ $project->project_code }}</span>
                                        <strong class="project-name" style="font-size: 0.9375rem; color: var(--color-accent);">{{ $project->name }}</strong>
                                    </a>
                                </td>
                                <td>
                                    <span>{{ $project->client_name }}</span>
                                    <div class="text-muted" style="font-size: 0.75rem;">
                                        Target: {{ $project->target_completion_date ? $project->target_completion_date->format('M j, Y') : 'Not set' }}
                                    </div>
                                </td>
                                <td class="cell-numeric text-success font-bold">
                                    ₱{{ number_format($project->contract_price, 2) }}
                                </td>
                                <td class="cell-numeric">
                                    <div style="font-weight: 700; color: var(--color-ink);">
                                        ₱{{ number_format($project->actual_spend, 2) }}
                                    </div>
                                </td>
                                <td class="cell-numeric {{ $project->remaining_budget >= 0 ? 'text-success' : 'text-danger' }} font-bold">
                                    ₱{{ number_format($project->remaining_budget, 2) }}
                                </td>
                                <td>
                                    <div style="min-width: 140px; max-width: 190px;">
                                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px;">
                                            <span class="font-mono font-bold" style="font-size: 0.875rem; color: {{ $project->spend_percentage > 100 ? 'var(--color-danger)' : ($project->spend_percentage > 85 ? '#C98A2C' : 'var(--color-ink)') }};">
                                                {{ $project->spend_percentage }}%
                                            </span>
                                            <span class="text-muted" style="font-size: 0.75rem;">
                                                @if ($project->spend_percentage > 100)
                                                    <span class="text-danger" style="font-weight: 600;">Over budget</span>
                                                @elseif ($project->spend_percentage >= 85)
                                                    <span style="color: #C98A2C; font-weight: 600;">High spend</span>
                                                @else
                                                    <span>utilized</span>
                                                @endif
                                            </span>
                                        </div>
                                        <div style="width: 100%; height: 6px; background: var(--color-border); border-radius: var(--radius-pill); overflow: hidden;">
                                            <div style="height: 100%; width: {{ min(100, $project->spend_percentage) }}%; background: {{ $project->spend_percentage > 100 ? 'var(--color-danger)' : ($project->spend_percentage > 85 ? '#C98A2C' : 'var(--color-success)') }}; border-radius: var(--radius-pill); transition: width 0.3s ease;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('costing.show', $project) }}" class="btn btn-secondary btn-sm">
                                        View Breakdown →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer pagination-wrapper">
                {{ $projects->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
