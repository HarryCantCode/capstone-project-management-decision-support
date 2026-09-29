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
    <div class="dashboard-stats mb-6" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-4);">
        <div class="stat-card card-glass" style="border-left: 4px solid var(--color-success);">
            <div class="stat-card__value numeric text-success" style="font-size: 1.5rem; font-weight: 700;">
                ₱{{ number_format($totalAllocated, 2) }}
            </div>
            <div class="stat-card__label" style="font-weight: 600; color: var(--color-muted);">Total Allocated Contract Value</div>
            <span class="text-muted" style="font-size: 0.75rem;">Across {{ $projects->total() }} registered {{ Str::plural('project', $projects->total()) }}</span>
        </div>

        <div class="stat-card card-glass" style="border-left: 4px solid var(--color-accent);">
            <div class="stat-card__value numeric" style="font-size: 1.5rem; font-weight: 700; color: var(--color-ink);">
                ₱{{ number_format($totalSpent, 2) }}
            </div>
            <div class="stat-card__label" style="font-weight: 600; color: var(--color-muted);">Total Actual Spend</div>
            <span class="text-muted" style="font-size: 0.75rem;">
                {{ $totalAllocated > 0 ? round(($totalSpent / $totalAllocated) * 100, 1) : 0 }}% of total portfolio value
            </span>
        </div>

        <div class="stat-card card-glass" style="border-left: 4px solid {{ $totalRemaining >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
            <div class="stat-card__value numeric {{ $totalRemaining >= 0 ? 'text-success' : 'text-danger' }}" style="font-size: 1.5rem; font-weight: 700;">
                ₱{{ number_format($totalRemaining, 2) }}
            </div>
            <div class="stat-card__label" style="font-weight: 600; color: var(--color-muted);">Total Remaining Budget</div>
            <span class="text-muted" style="font-size: 0.75rem;">
                {{ $totalRemaining >= 0 ? 'Healthy overall portfolio balance' : 'Deficit across projects' }}
            </span>
        </div>
    </div>

    {{-- Filter and Search Bar --}}
    <div class="card mb-6">
        <div class="card-body" style="padding: var(--space-4) var(--space-6);">
            <form method="GET" action="{{ route('costing.index') }}" class="search-filter-bar">
                <div class="search-input-wrapper">
                    <svg class="search-input-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Search by project name, code, or client..."
                        class="form-control search-input"
                    >
                </div>

                <div class="filter-group">
                    <label for="status" class="visually-hidden">Filter by status</label>
                    <select id="status" name="status" class="form-control form-control--compact filter-select">
                        <option value="">All Statuses</option>
                        <option value="pending" @selected(($status ?? '') === 'pending')>Pending</option>
                        <option value="ongoing" @selected(($status ?? '') === 'ongoing')>Ongoing</option>
                        <option value="delayed" @selected(($status ?? '') === 'delayed')>Delayed</option>
                        <option value="completed" @selected(($status ?? '') === 'completed')>Completed</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                @if (!empty($search) || !empty($status))
                    <a href="{{ route('costing.index') }}" class="btn btn-secondary btn-sm" style="color: var(--color-danger);">✕ Clear</a>
                @endif
            </form>
        </div>
    </div>

    {{-- Costing Projects Table --}}
    <div class="card card-glass">
        <div class="card-header">
            <div>
                <span style="font-weight: 700; font-size: 1rem;">Project Budget & Costing Ledger</span>
                <span class="text-muted" style="font-size: 0.8125rem; margin-left: var(--space-2);">
                    — Click any project to inspect its full itemized expense breakdown
                </span>
            </div>
            <span class="text-muted" style="font-size: 0.8125rem;">
                {{ $projects->total() }} {{ Str::plural('project', $projects->total()) }}
            </span>
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
                    @if (empty($search) && empty($status))
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
