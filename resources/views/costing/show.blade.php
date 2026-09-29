@extends('layouts.app')

@section('title', $project->name . ' — Costing Breakdown')
@section('meta-description', 'Detailed project expense ledger, search, multi-criteria filtering, and budget tracking.')

@section('content')
<div x-data="{ addCostModalOpen: false }">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <div style="display: flex; gap: var(--space-3); font-size: 0.875rem; margin-bottom: var(--space-2);">
                <a href="{{ route('costing.index') }}" class="back-link">← All Project Costing</a>
                <span class="text-muted">|</span>
                <a href="{{ route('projects.show', $project) }}" class="back-link">Go to Project Overview →</a>
            </div>
            <h1>{{ $project->name }} — Costing Breakdown</h1>
            <p class="page-header__description">
                <span class="font-mono">{{ $project->project_code }}</span> · {{ $project->client_name }} · Target: {{ $project->target_completion_date ? $project->target_completion_date->format('M j, Y') : 'Not set' }}
            </p>
        </div>
        <div class="page-actions">
            @can('update', $project)
                <button type="button" class="btn btn-primary" @click="addCostModalOpen = true">
                    + Log New Expense / Cost
                </button>
            @endcan
            <a href="{{ route('projects.show', $project) }}" class="btn btn-secondary">Project Details</a>
        </div>
    </div>

    {{-- Financial Summary KPI Cards for this project --}}
    <div class="project-detail-grid mb-6">
        
        {{-- Financial Metrics Card --}}
        <section class="card card-glass" aria-labelledby="project-finances-heading">
            <div class="card-header">
                <h2 id="project-finances-heading">Financial Overview</h2>
                <span class="badge-condition {{ $project->costing_status === 'On Track' ? 'badge-condition--good' : ($project->costing_status === 'At Risk' ? 'badge-condition--fair' : 'badge-condition--needs_repair') }}">
                    {{ $project->costing_status }}
                </span>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; justify-content: space-between; gap: var(--space-4);">
                <div class="financial-stats-grid">
                    <div class="financial-stat-card">
                        <span class="financial-stat-card__label" title="Allocated Budget">Allocated Budget</span>
                        <div class="financial-stat-card__value text-success" title="₱{{ number_format($project->contract_price, 2) }}">
                            ₱{{ number_format($project->contract_price, 2) }}
                        </div>
                    </div>
                    <div class="financial-stat-card">
                        <span class="financial-stat-card__label" title="Total Actual Spend">Total Actual Spend</span>
                        <div class="financial-stat-card__value" style="color: var(--color-ink);" title="₱{{ number_format($project->actual_spend, 2) }}">
                            ₱{{ number_format($project->actual_spend, 2) }}
                        </div>
                    </div>
                    <div class="financial-stat-card">
                        <span class="financial-stat-card__label" title="Remaining Budget">Remaining Budget</span>
                        <div class="financial-stat-card__value {{ $project->remaining_budget >= 0 ? 'text-success' : 'text-danger' }}" title="₱{{ number_format($project->remaining_budget, 2) }}">
                            ₱{{ number_format($project->remaining_budget, 2) }}
                        </div>
                    </div>
                </div>

                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; margin-bottom: 6px;">
                        <span class="text-muted">Budget Utilization</span>
                        <span class="font-mono font-bold">{{ $project->spend_percentage }}% used</span>
                    </div>
                    <div style="width: 100%; height: 8px; background: var(--color-border); border-radius: var(--radius-pill); overflow: hidden;">
                        <div style="height: 100%; width: {{ min(100, $project->spend_percentage) }}%; background: {{ $project->spend_percentage > 100 ? 'var(--color-danger)' : ($project->spend_percentage > 85 ? '#C98A2C' : 'var(--color-success)') }}; transition: width 0.3s ease;"></div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Category Breakdown Summary Card --}}
        <section class="card card-glass" aria-labelledby="category-spending-heading">
            <div class="card-header">
                <h2 id="category-spending-heading">Spending by Category</h2>
                <span class="text-muted" style="font-size: 0.8125rem;">{{ $project->costs->count() }} total entries</span>
            </div>
            <div class="card-body" style="padding: var(--space-4) var(--space-6);">
                <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                    @php
                        $categories = [
                            'materials' => 'Materials',
                            'equipment' => 'Equipment Rental',
                            'labor' => 'Labor / Overtime',
                            'subcontractor' => 'Subcontractor',
                            'other' => 'Other / Misc'
                        ];
                    @endphp

                    @foreach ($categories as $catKey => $catLabel)
                        @php
                            $catTotal = (float) ($categoryTotals[$catKey]->total ?? 0.00);
                            $catCount = (int) ($categoryTotals[$catKey]->count ?? 0);
                            $catPct = $project->actual_spend > 0 ? round(($catTotal / (float) $project->actual_spend) * 100, 1) : 0;
                        @endphp
                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; margin-bottom: 3px;">
                                <span>
                                    <strong>{{ $catLabel }}</strong>
                                    <span class="text-muted" style="font-size: 0.75rem;">({{ $catCount }} {{ Str::plural('item', $catCount) }})</span>
                                </span>
                                <span class="font-mono font-bold">₱{{ number_format($catTotal, 2) }} ({{ $catPct }}%)</span>
                            </div>
                            <div style="width: 100%; height: 6px; background: var(--color-surface-alt); border-radius: var(--radius-pill); overflow: hidden;">
                                <div style="height: 100%; width: {{ $catPct }}%; background: var(--color-accent);"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

    </div>

    {{-- Advanced Search & Multi-criteria Filter Bar --}}
    <div class="card mb-6">
        <div class="card-header" style="background: var(--color-surface-alt); padding: var(--space-3) var(--space-6);">
            <strong style="font-size: 0.875rem;">Filter & Search Project Expenditures</strong>
            @if (!empty($search) || !empty($category) || !empty($startDate) || !empty($endDate) || !empty($minAmount) || !empty($maxAmount))
                <a href="{{ route('costing.show', $project) }}" class="btn btn-secondary btn-sm" style="color: var(--color-danger);">✕ Clear Filters</a>
            @endif
        </div>
        <div class="card-body" style="padding: var(--space-4) var(--space-6);">
            <form method="GET" action="{{ route('costing.show', $project) }}">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4); align-items: flex-end;">
                    
                    {{-- 1. Search Description --}}
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="filter-search" class="form-label" style="font-size: 0.75rem; text-transform: uppercase;">Search Description</label>
                        <input
                            id="filter-search"
                            type="text"
                            name="search"
                            value="{{ $search ?? '' }}"
                            placeholder="e.g. Scaffolding, Elevator cables..."
                            class="form-control form-control--compact"
                        >
                    </div>

                    {{-- 2. Category Filter --}}
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="filter-category" class="form-label" style="font-size: 0.75rem; text-transform: uppercase;">Category</label>
                        <select id="filter-category" name="category" class="form-control form-control--compact">
                            <option value="">All Categories</option>
                            <option value="materials" @selected(($category ?? '') === 'materials')>Materials</option>
                            <option value="equipment" @selected(($category ?? '') === 'equipment')>Equipment Rental</option>
                            <option value="labor" @selected(($category ?? '') === 'labor')>Labor / Overtime</option>
                            <option value="subcontractor" @selected(($category ?? '') === 'subcontractor')>Subcontractor</option>
                            <option value="other" @selected(($category ?? '') === 'other')>Other / Misc</option>
                        </select>
                    </div>

                    {{-- 3. Date From --}}
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="filter-start-date" class="form-label" style="font-size: 0.75rem; text-transform: uppercase;">Date From</label>
                        <input
                            id="filter-start-date"
                            type="date"
                            name="start_date"
                            value="{{ $startDate ?? '' }}"
                            class="form-control form-control--compact font-mono"
                        >
                    </div>

                    {{-- 4. Date To --}}
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="filter-end-date" class="form-label" style="font-size: 0.75rem; text-transform: uppercase;">Date To</label>
                        <input
                            id="filter-end-date"
                            type="date"
                            name="end_date"
                            value="{{ $endDate ?? '' }}"
                            class="form-control form-control--compact font-mono"
                        >
                    </div>

                    {{-- 5. Min Amount --}}
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="filter-min-amount" class="form-label" style="font-size: 0.75rem; text-transform: uppercase;">Min Amount (₱)</label>
                        <input
                            id="filter-min-amount"
                            type="number"
                            step="0.01"
                            name="min_amount"
                            value="{{ $minAmount ?? '' }}"
                            placeholder="0.00"
                            class="form-control form-control--compact font-mono"
                        >
                    </div>

                    {{-- 6. Max Amount --}}
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="filter-max-amount" class="form-label" style="font-size: 0.75rem; text-transform: uppercase;">Max Amount (₱)</label>
                        <input
                            id="filter-max-amount"
                            type="number"
                            step="0.01"
                            name="max_amount"
                            value="{{ $maxAmount ?? '' }}"
                            placeholder="9999999.00"
                            class="form-control form-control--compact font-mono"
                        >
                    </div>

                    {{-- Submit Button --}}
                    <div style="display: flex; gap: var(--space-2);">
                        <button type="submit" class="btn btn-primary btn-sm" style="flex: 1;">Apply Filters</button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- Full Costing & Expenditures Breakdown Table --}}
    <div class="card card-glass">
        <div class="card-header">
            <div>
                <span style="font-weight: 700; font-size: 1rem;">Itemized Expense Ledger</span>
                <span class="text-muted" style="font-size: 0.8125rem; margin-left: var(--space-2);">
                    Showing {{ $costs->total() }} {{ Str::plural('entry', $costs->total()) }}
                </span>
            </div>
            @can('update', $project)
                <button type="button" class="btn btn-primary btn-sm" @click="addCostModalOpen = true">
                    + Log New Expense / Cost
                </button>
            @endcan
        </div>

        @if ($costs->isEmpty())
            <div class="card-body">
                <div class="empty-state">
                    <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <h2 class="empty-state__title">No expense entries found</h2>
                    <p class="empty-state__description">
                        @if (!empty($search) || !empty($category) || !empty($startDate) || !empty($endDate) || !empty($minAmount) || !empty($maxAmount))
                            No cost items matched your active search and filter criteria.
                        @else
                            No expenses have been recorded for this project yet.
                        @endif
                    </p>
                    @if (!empty($search) || !empty($category) || !empty($startDate) || !empty($endDate) || !empty($minAmount) || !empty($maxAmount))
                        <a href="{{ route('costing.show', $project) }}" class="btn btn-secondary btn-sm">Clear Active Filters</a>
                    @else
                        @can('update', $project)
                            <button type="button" class="btn btn-primary btn-sm" @click="addCostModalOpen = true">
                                + Log First Expense Item
                            </button>
                        @endcan
                    @endif
                </div>
            </div>
        @else
            <div class="table-wrapper">
                <table class="data-table">
                    <caption class="visually-hidden">Complete cost breakdown for {{ $project->name }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Description (What this is for)</th>
                            <th scope="col">Category</th>
                            <th scope="col" class="text-right">Amount (PHP)</th>
                            <th scope="col">Incurred Date</th>
                            <th scope="col">Logged By</th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($costs as $cost)
                            <tr>
                                <td>
                                    <strong>{{ $cost->description }}</strong>
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--color-surface-alt); text-transform: capitalize; font-weight: 600;">
                                        {{ $cost->cost_type }}
                                    </span>
                                </td>
                                <td class="cell-numeric text-success font-bold" style="font-size: 0.9375rem;">
                                    ₱{{ number_format($cost->amount, 2) }}
                                </td>
                                <td class="font-mono">
                                    {{ $cost->incurred_date ? $cost->incurred_date->format('M j, Y') : $cost->created_at->format('M j, Y') }}
                                </td>
                                <td>
                                    {{ $cost->creator ? $cost->creator->name : 'System' }}
                                </td>
                                <td class="text-right">
                                    @can('update', $project)
                                        <form method="POST" action="{{ route('projects.costs.destroy', ['project' => $project, 'cost' => $cost]) }}" class="inline-form" style="justify-content: flex-end;" onsubmit="return confirm('Delete this cost entry of ₱{{ number_format($cost->amount, 2) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="redirect_to" value="costing">
                                            <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--color-danger); border-color: #fad2cf;">
                                                Delete
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer pagination-wrapper">
                {{ $costs->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    {{-- Modal: Log New Expense Entry --}}
    <div class="reassign-modal-overlay" style="display: none;" x-show="addCostModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="addCostModalOpen = false"></div>
        <div class="reassign-modal-content card" x-show="addCostModalOpen" x-transition>
            <div class="card-header">
                <span>Log Project Expense / Cost</span>
                <button type="button" class="flash-close" @click="addCostModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <form action="{{ route('projects.costs.store', $project) }}" method="POST">
                @csrf
                <input type="hidden" name="redirect_to" value="costing">
                <div class="card-body">
                    <p class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-4);">
                        Project: <strong>{{ $project->name }}</strong> (<span class="font-mono">{{ $project->project_code }}</span>)<br>
                        Allocated Budget: <strong class="text-success font-mono">₱{{ number_format($project->contract_price, 2) }}</strong>
                    </p>

                    <div class="form-group form-group-premium">
                        <label for="cost_description" class="form-label">What is this cost for? (Description)</label>
                        <input
                            id="cost_description"
                            name="description"
                            type="text"
                            class="form-control"
                            placeholder="e.g. Scaffolding rental, Elevator cables, Concrete mix"
                            required
                        >
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-4);">
                        <div class="form-group form-group-premium" style="margin-bottom: 0;">
                            <label for="cost_type" class="form-label">Category</label>
                            <select id="cost_type" name="cost_type" class="form-control" required>
                                <option value="materials">Materials</option>
                                <option value="equipment">Equipment Rental</option>
                                <option value="labor">Labor / Overtime</option>
                                <option value="subcontractor">Subcontractor</option>
                                <option value="other">Other / Miscellaneous</option>
                            </select>
                        </div>

                        <div class="form-group form-group-premium" style="margin-bottom: 0;">
                            <label for="incurred_date" class="form-label">Incurred Date</label>
                            <input
                                id="incurred_date"
                                name="incurred_date"
                                type="date"
                                class="form-control font-mono"
                                value="{{ now()->format('Y-m-d') }}"
                            >
                        </div>
                    </div>

                    <div class="form-group form-group-premium">
                        <label for="cost_amount" class="form-label">Amount (PHP ₱)</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-weight: 600; color: var(--color-muted); font-family: var(--font-mono);">₱</span>
                            <input
                                id="cost_amount"
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="form-control font-mono"
                                style="padding-left: 32px;"
                                placeholder="0.00"
                                required
                            >
                        </div>
                        <p class="form-hint">This will automatically recalculate the actual spend and remaining balance for this project.</p>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Cost Entry</button>
                        <button type="button" class="btn btn-secondary" @click="addCostModalOpen = false">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
