@extends('layouts.app')

@section('title', $project->name . ' — Costing Breakdown')
@section('meta-description', 'Detailed project expense ledger, search, multi-criteria filtering, and budget tracking.')

@section('content')
@php
    $isProjectReadOnly = $project->trashed() || $project->status === 'completed';
@endphp
<div x-data="costingBreakdown()">

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
            @if(Auth::user()->hasAnyRole(['Admin', 'Manager', 'Staff']))
                @if ($isProjectReadOnly)
                    <button type="button" class="btn btn-secondary" disabled style="opacity: 0.6; cursor: not-allowed;" title="{{ $project->trashed() ? 'Cannot log expenses on an archived project' : 'Cannot log expenses on a completed project unless reactivated' }}">
                        + Log New Expense / Cost
                    </button>
                @else
                    <button type="button" class="btn btn-primary" @click="openAddCostModal()">
                        + Log New Expense / Cost
                    </button>
                @endif
            @endif
            <a href="{{ route('projects.show', $project) }}" class="btn btn-secondary">Project Details</a>
        </div>
    </div>

    {{-- Financial Summary KPI Cards for this project --}}
    <div class="project-detail-grid mb-6">
        
        {{-- Financial Metrics Card --}}
        <section class="card card-glass" aria-labelledby="project-finances-heading">
            <div class="card-header">
                <h2 id="project-finances-heading">Financial Overview</h2>
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
                            type="text"
                            inputmode="decimal"
                            data-amount-input
                            name="min_amount"
                            value="{{ $minAmount !== null && $minAmount !== '' ? number_format((float) str_replace(',', '', (string) $minAmount), 0, '.', ',') : '' }}"
                            placeholder="0.00"
                            class="form-control form-control--compact font-mono"
                        >
                    </div>

                    {{-- 6. Max Amount --}}
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="filter-max-amount" class="form-label" style="font-size: 0.75rem; text-transform: uppercase;">Max Amount (₱)</label>
                        <input
                            id="filter-max-amount"
                            type="text"
                            inputmode="decimal"
                            data-amount-input
                            name="max_amount"
                            value="{{ $maxAmount !== null && $maxAmount !== '' ? number_format((float) str_replace(',', '', (string) $maxAmount), 0, '.', ',') : '' }}"
                            placeholder="9,999,999.00"
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
            @if(Auth::user()->hasAnyRole(['Admin', 'Manager', 'Staff']))
                @if ($isProjectReadOnly)
                    <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.6; cursor: not-allowed;" title="{{ $project->trashed() ? 'Cannot log expenses on an archived project' : 'Cannot log expenses on a completed project unless reactivated' }}">
                        + Log New Expense / Cost
                    </button>
                @else
                    <button type="button" class="btn btn-primary btn-sm" @click="openAddCostModal()">
                        + Log New Expense / Cost
                    </button>
                @endif
            @endif
        </div>

        @if ($costs->isEmpty() && ($pendingExpenses ?? collect())->isEmpty())
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
                        @if(Auth::user()->hasAnyRole(['Admin', 'Manager', 'Staff']))
                            @if ($isProjectReadOnly)
                                <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.6; cursor: not-allowed;" title="{{ $project->trashed() ? 'Cannot log expenses on an archived project' : 'Cannot log expenses on a completed project unless reactivated' }}">
                                    + Log First Expense Item
                                </button>
                            @else
                                <button type="button" class="btn btn-primary btn-sm" @click="openAddCostModal()">
                                    + Log First Expense Item
                                </button>
                            @endif
                        @endif
                    @endif
                </div>
            </div>
        @else
            @if (($pendingExpenses ?? collect())->isNotEmpty())
                <div style="background: rgba(245, 158, 11, 0.08); border-bottom: 1px solid rgba(245, 158, 11, 0.25); padding: var(--space-2) var(--space-6); display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; background: #f59e0b; color: #fff; font-size: 11px; font-weight: bold;">⏳</span>
                        <span style="color: var(--color-ink);"><strong>{{ $pendingExpenses->count() }} {{ Str::plural('Expense', $pendingExpenses->count()) }} Pending Approval:</strong> Submitted by staff and awaiting review by a Manager or Administrator.</span>
                    </div>
                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 0.75rem;">Grayed out entries are pending review</span>
                </div>
            @endif
            <div class="table-wrapper">
                <table class="data-table">
                    <caption class="visually-hidden">Complete cost breakdown for {{ $project->name }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Description (What this is for)</th>
                            <th scope="col">Category</th>
                            <th scope="col" class="text-center">Qty</th>
                            <th scope="col" class="text-right">Amount (PHP)</th>
                            <th scope="col">Incurred Date</th>
                            <th scope="col">Logged By</th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Pending Expenses submitted by Staff (grayed out with pending indicator) --}}
                        @foreach (($pendingExpenses ?? []) as $pendingExpense)
                            <tr 
                                class="expense-row-pending"
                                style="opacity: 0.65; background: var(--color-surface-alt); transition: opacity 0.2s ease;"
                                onmouseover="this.style.opacity='0.85'" 
                                onmouseout="this.style.opacity='0.65'"
                            >
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                        <strong style="color: var(--color-muted);">{{ $pendingExpense->payload['description'] ?? 'Expense Entry' }}</strong>
                                        <span class="status-pill status-pill--ongoing" style="font-size: 0.6875rem; padding: 1px 8px; background: rgba(245, 158, 11, 0.15); color: #d97706; border-color: rgba(245, 158, 11, 0.3);" title="Awaiting Manager or Admin approval">
                                            Pending Review
                                        </span>
                                    </div>
                                    @if(!empty($pendingExpense->payload['resource_name']))
                                        <div style="font-size: 0.75rem; color: var(--color-muted);">Catalog: {{ $pendingExpense->payload['resource_name'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge" style="background: var(--color-surface); text-transform: capitalize; color: var(--color-muted); font-weight: 600;">
                                        {{ $pendingExpense->payload['cost_type'] ?? 'materials' }}
                                    </span>
                                </td>
                                <td class="text-center font-mono font-bold" style="color: var(--color-muted);">
                                    {{ $pendingExpense->payload['quantity'] ?? 1 }}
                                </td>
                                <td class="cell-numeric font-mono" style="color: var(--color-muted); font-size: 0.9375rem; font-style: italic;">
                                    ₱{{ number_format((float) ($pendingExpense->payload['amount'] ?? 0), 2) }}
                                </td>
                                <td class="font-mono" style="color: var(--color-muted);">
                                    {{ !empty($pendingExpense->payload['incurred_date']) ? \Carbon\Carbon::parse($pendingExpense->payload['incurred_date'])->format('M j, Y') : $pendingExpense->created_at->format('M j, Y') }}
                                </td>
                                <td style="color: var(--color-muted);">
                                    {{ $pendingExpense->user ? $pendingExpense->user->name : 'Staff' }}
                                </td>
                                <td class="text-right">
                                    @if(Auth::user()->hasAnyRole(['Admin', 'Manager']))
                                        <a 
                                            href="{{ route('pending-actions.index', ['action_type' => 'add_expense', 'project_id' => $project->id]) }}" 
                                            class="btn btn-secondary btn-sm" 
                                            style="padding: 1px 8px; font-size: 0.75rem; border-color: rgba(245, 158, 11, 0.4); color: #d97706;"
                                            title="Review this staff expense in Pending Actions"
                                        >
                                            Review
                                        </a>
                                    @else
                                        <a 
                                            href="{{ route('approval-list.index', ['action_type' => 'add_expense', 'project_id' => $project->id]) }}" 
                                            class="status-pill status-pill--ongoing" 
                                            style="font-size: 0.6875rem; padding: 1px 8px; text-decoration: none;"
                                            title="View in your Approval List"
                                        >
                                            Awaiting Review
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
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
                                <td class="text-center font-mono font-bold">
                                    {{ $cost->quantity ?? 1 }}
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
                                        @if (!$isProjectReadOnly)
                                            <button
                                                type="button"
                                                class="btn btn-secondary btn-sm"
                                                style="color: var(--color-danger); border-color: #fad2cf;"
                                                @click="openDeleteCostModal('{{ route('projects.costs.destroy', ['project' => $project, 'cost' => $cost]) }}', '{{ addslashes($cost->description) }}', '{{ number_format($cost->amount, 2) }}')"
                                            >
                                                Delete
                                            </button>
                                        @endif
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
        <div class="reassign-modal-content card" x-show="addCostModalOpen" x-transition style="max-width: 580px;">
            <div class="card-header">
                <span>Log Project Expense / Cost</span>
                <button type="button" class="flash-close" @click="addCostModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <form action="{{ route('projects.costs.store', $project) }}" method="POST" @submit="if (!validateCostSubmission($event)) { $event.preventDefault(); }">
                @csrf
                <input type="hidden" name="redirect_to" value="costing">
                <input type="hidden" name="resource_id" :value="selectedMaterialId || ''">
                <div class="card-body">
                    <p class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-4);">
                        Project: <strong>{{ $project->name }}</strong> (<span class="font-mono">{{ $project->project_code }}</span>)<br>
                        Allocated Budget: <strong class="text-success font-mono">₱{{ number_format($project->contract_price, 2) }}</strong>
                    </p>

                    {{-- Option to choose from Materials Catalog with Search & Clear --}}
                    <div class="form-group form-group-premium" style="background: var(--color-surface-alt); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: var(--space-4);" @click.outside="materialDropdownOpen = false">
                        <label class="form-label" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; gap: 8px; flex-wrap: wrap;">
                            <span style="font-weight: 600;">Choose from Materials Catalog (Optional)</span>
                            <template x-if="selectedMaterialId">
                                <button type="button" @click="clearMaterialSelection()" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.72rem; color: var(--color-accent);">
                                    ✕ Clear &amp; Switch to Custom Fill-Up
                                </button>
                            </template>
                            <template x-if="!selectedMaterialId">
                                <span class="text-muted" style="font-size: 0.75rem; font-weight: normal;">Leave empty below to manually fill up a custom expense</span>
                            </template>
                        </label>

                        {{-- Searchable Dropdown Container --}}
                        <div style="position: relative;">
                            <div style="display: flex; align-items: center; position: relative;">
                                <input
                                    type="text"
                                    class="form-control"
                                    placeholder="Search material name, DEX code, or model..."
                                    x-model="materialSearch"
                                    @focus="materialDropdownOpen = true"
                                    @input="materialDropdownOpen = true"
                                    style="padding-right: 32px;"
                                >
                                <button
                                    type="button"
                                    x-show="selectedMaterialId || materialSearch"
                                    @click="clearMaterialSelection()"
                                    title="Clear chosen material"
                                    style="position: absolute; right: 8px; background: none; border: none; font-size: 1.15rem; line-height: 1; color: var(--color-muted); cursor: pointer; padding: 2px 6px;"
                                >
                                    &times;
                                </button>
                            </div>

                            {{-- Dropdown options list --}}
                            <div
                                x-show="materialDropdownOpen"
                                x-cloak
                                style="position: absolute; top: calc(100% + 4px); left: 0; right: 0; max-height: 260px; overflow-y: auto; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); box-shadow: 0 8px 24px rgba(0,0,0,0.14); z-index: 50; padding: 4px;"
                            >
                                <template x-for="item in filteredMaterials" :key="item.id">
                                    <div
                                        @click="selectMaterial(item)"
                                        style="padding: 10px 14px; margin-bottom: 4px; cursor: pointer; border-radius: var(--radius-sm); border: 1px solid var(--color-border-subtle); background: var(--color-surface); font-size: 0.8125rem; display: flex; justify-content: space-between; align-items: center; gap: var(--space-3); transition: all 0.15s ease;"
                                        :style="selectedMaterialId == item.id ? 'background: rgba(37,99,235,0.08); border-color: rgba(37,99,235,0.3); font-weight: 600;' : ''"
                                        onmouseover="if(this.dataset.selected !== 'true') this.style.background='var(--color-surface-alt)'"
                                        onmouseout="if(this.dataset.selected !== 'true') this.style.background='var(--color-surface)'"
                                        :data-selected="selectedMaterialId == item.id"
                                    >
                                        <div style="display: flex; flex-direction: column; gap: 3px; min-width: 0;">
                                            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                                <span class="badge" style="background: var(--color-surface-alt); border: 1px solid var(--color-border); font-size: 0.75rem; font-family: var(--font-mono); font-weight: 600; padding: 1px 6px;" x-text="item.resource_code"></span>
                                                <span style="font-weight: 600; color: var(--color-ink);" x-text="item.name"></span>
                                            </div>
                                            <div x-show="item.model" class="text-muted" style="font-size: 0.75rem; line-height: 1.3;" x-text="'Model / Spec: ' + item.model"></div>
                                            <div style="font-size: 0.72rem; color: var(--color-muted);">
                                                Available: <span class="font-mono font-bold" :class="item.quantity_available > 0 ? 'text-success' : 'text-danger'" x-text="item.quantity_available"></span> units
                                            </div>
                                        </div>
                                        <div style="text-align: right; flex-shrink: 0;">
                                            <div class="font-mono text-success" style="font-weight: 700; font-size: 0.875rem;">
                                                ₱<span x-text="Number(item.price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                            </div>
                                            <span class="text-muted" style="font-size: 0.7rem;">unit price</span>
                                        </div>
                                    </div>
                                </template>
                                <div x-show="filteredMaterials.length === 0" style="padding: 16px; font-size: 0.8125rem; color: var(--color-muted); text-align: center;">
                                    No matching materials found in catalog.
                                </div>
                            </div>
                        </div>

                        <div x-show="selectedMaterialId" x-cloak style="margin-top: var(--space-3); padding-top: var(--space-3); border-top: 1px dashed var(--color-border); display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); font-size: 0.8125rem;">
                            <div>
                                <label for="costing_material_qty_input" class="form-label" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; margin-bottom: 4px;">
                                    <span>Quantity (Cannot be 0)</span>
                                    <span style="font-weight: 600;" :class="isQuantityExcessive ? 'text-danger' : 'text-muted'">
                                        Avail: <span class="font-mono" x-text="selectedMaterialStock"></span>
                                    </span>
                                </label>
                                <input
                                    type="number"
                                    id="costing_material_qty_input"
                                    min="1"
                                    :max="selectedMaterialId ? selectedMaterialStock : null"
                                    :disabled="!selectedMaterialId"
                                    step="1"
                                    class="form-control font-mono"
                                    :style="isQuantityExcessive ? 'border-color: var(--color-danger); background: var(--color-danger-light);' : ''"
                                    x-model.number="materialQuantity"
                                    @input="recalculateMaterialTotal()"
                                    placeholder="Qty"
                                >
                                <p x-show="isQuantityExcessive" class="text-danger" style="font-size: 0.72rem; margin-top: 4px; margin-bottom: 0;">
                                    Quantity exceeds available stock (<span x-text="selectedMaterialStock"></span>)!
                                </p>
                            </div>
                            <div>
                                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 4px;">Unit Price</label>
                                <div class="font-mono text-muted" style="padding: 8px 12px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-weight: 600;">
                                    ₱<span x-text="Number(materialUnitPrice).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group form-group-premium">
                        <label for="cost_description" class="form-label">What is this cost for? (Description) <span class="required">*</span></label>
                        <input
                            id="cost_description"
                            name="description"
                            type="text"
                            class="form-control"
                            x-model="costForm.description"
                            :readonly="Boolean(selectedMaterialId)"
                            :style="selectedMaterialId ? 'background: var(--color-surface-alt); cursor: not-allowed;' : ''"
                            placeholder="e.g. Scaffolding rental, Elevator cables, Concrete mix"
                            required
                        >
                    </div>

                    {{-- Quantity for manual non-catalog expense --}}
                    <div x-show="!selectedMaterialId" class="form-group form-group-premium">
                        <label for="costing_manual_cost_quantity" class="form-label">Quantity <span class="required">*</span></label>
                        <input
                            id="costing_manual_cost_quantity"
                            name="quantity"
                            type="number"
                            min="1"
                            step="1"
                            class="form-control font-mono"
                            x-model.number="materialQuantity"
                            :disabled="Boolean(selectedMaterialId)"
                            :required="!selectedMaterialId"
                            placeholder="1"
                        >
                        <p class="form-hint">Default is 1, minimum is 1 (cannot be 0).</p>
                    </div>
                    <input type="hidden" name="quantity" :value="materialQuantity || 1" :disabled="!selectedMaterialId">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-4);">
                        <div class="form-group form-group-premium" style="margin-bottom: 0;">
                            <label for="cost_type" class="form-label">Category <span class="required">*</span></label>
                            <select
                                id="cost_type"
                                name="cost_type"
                                class="form-control"
                                x-model="costForm.cost_type"
                                :disabled="Boolean(selectedMaterialId)"
                                :style="selectedMaterialId ? 'background: var(--color-surface-alt); cursor: not-allowed;' : ''"
                                required
                            >
                                <option value="materials">Materials</option>
                                <option value="equipment" x-bind:disabled="Boolean(selectedMaterialId)">Equipment Rental</option>
                                <option value="labor" x-bind:disabled="Boolean(selectedMaterialId)">Labor / Overtime</option>
                                <option value="subcontractor" x-bind:disabled="Boolean(selectedMaterialId)">Subcontractor</option>
                                <option value="other" x-bind:disabled="Boolean(selectedMaterialId)">Other / Miscellaneous</option>
                            </select>
                            <input type="hidden" name="cost_type" value="materials" :disabled="!selectedMaterialId">
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
                        <label for="cost_amount" class="form-label">Amount (PHP ₱) <span class="required">*</span></label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 600; color: var(--color-muted); font-family: var(--font-mono); pointer-events: none; z-index: 2;">₱&nbsp;</span>
                            <input
                                id="cost_amount"
                                name="amount"
                                type="text"
                                inputmode="decimal"
                                data-amount-input
                                class="form-control font-mono"
                                style="padding-left: 38px !important;"
                                x-model="costForm.amount"
                                @input="if (!selectedMaterialId) { costForm.amount = window.formatAmountWithCommas ? window.formatAmountWithCommas($event.target.value) : $event.target.value; }"
                                :readonly="Boolean(selectedMaterialId)"
                                :style="selectedMaterialId ? 'padding-left: 38px !important; background: var(--color-surface-alt); cursor: not-allowed;' : 'padding-left: 38px !important;'"
                                placeholder="100.00"
                                required
                            >
                        </div>
                        <p class="form-hint" x-show="selectedMaterialId">Calculated as Quantity &times; Unit Price (locked for catalog materials, minimum ₱100.00).</p>
                        <p class="form-hint" x-show="!selectedMaterialId">This will automatically recalculate the actual spend and remaining balance for this project (minimum ₱100.00).</p>
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

    {{-- Hidden form for cost deletion --}}
    <form id="costing-delete-cost-form" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
        <input type="hidden" name="redirect_to" value="costing">
    </form>

    {{-- Centered In-App Delete Expenditure Confirmation Modal --}}
    <div
        class="reassign-modal-overlay"
        style="display: none;"
        x-show="deleteCostModalOpen"
        x-cloak
        role="dialog"
        aria-modal="true"
        aria-labelledby="costing-delete-cost-modal-title"
    >
        <div class="reassign-modal-backdrop" @click="deleteCostModalOpen = false"></div>
        <div
            class="reassign-modal-content card"
            style="max-width: 480px; width: 100%; text-align: center; padding: var(--space-6);"
            x-show="deleteCostModalOpen"
            x-transition:enter="modal-enter"
            x-transition:leave="modal-leave"
        >
            <div style="width: 54px; height: 54px; border-radius: 50%; background: rgba(239, 68, 68, 0.12); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-4); color: var(--color-danger, #ef4444);">
                <svg style="width: 28px; height: 28px;" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>

            <h3 id="costing-delete-cost-modal-title" style="margin: 0 0 var(--space-2); font-size: 1.15rem; font-weight: 700; color: var(--color-ink);">
                Delete Cost Entry
            </h3>
            <p style="color: var(--color-muted); font-size: 0.875rem; line-height: 1.5; margin: 0 0 var(--space-5);">
                Are you sure you want to delete the cost entry for <strong style="color: var(--color-ink);" x-text="deleteCostTarget.description"></strong> (<span class="font-mono font-bold" x-text="'₱' + deleteCostTarget.amount"></span>)? This action cannot be undone.
            </p>

            <div style="display: flex; gap: var(--space-3); justify-content: center;">
                <button
                    type="button"
                    class="btn btn-secondary"
                    style="min-width: 110px;"
                    @click="deleteCostModalOpen = false"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="btn btn-danger"
                    style="min-width: 110px; background: var(--color-danger, #ef4444); border-color: var(--color-danger, #ef4444); color: #fff;"
                    @click="confirmDeleteCost()"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function costingBreakdown() {
    return {
        addCostModalOpen: false,
        openAddCostModal() {
            this.clearMaterialSelection();
            this.costForm.cost_type = 'materials';
            this.addCostModalOpen = true;
        },
        deleteCostModalOpen: false,
        deleteCostTarget: {
            url: '',
            description: '',
            amount: ''
        },
        openDeleteCostModal(url, description, amount) {
            this.deleteCostTarget = {
                url: url,
                description: description,
                amount: amount
            };
            this.deleteCostModalOpen = true;
        },
        confirmDeleteCost() {
            if (!this.deleteCostTarget.url) return;
            const form = document.getElementById('costing-delete-cost-form');
            form.action = this.deleteCostTarget.url;
            form.submit();
        },
        selectedMaterialId: '',
        materialSearch: '',
        materialDropdownOpen: false,
        materialQuantity: 1,
        materialUnitPrice: 0,
        costForm: {
            description: '',
            cost_type: 'materials',
            amount: ''
        },
        catalogMaterialsList: [
            @foreach ($catalogMaterials as $mat)
            {
                id: {{ $mat->id }},
                name: @js($mat->name),
                resource_code: @js($mat->resource_code),
                model: @js($mat->model ?? ''),
                price: {{ (float) ($mat->price > 0 ? $mat->price : $mat->unit_cost) }},
                quantity_available: {{ (int) $mat->quantity_available }}
            },
            @endforeach
        ],
        get selectedMaterial() {
            if (!this.selectedMaterialId) return null;
            return this.catalogMaterialsList.find(item => item.id == this.selectedMaterialId) || null;
        },
        get selectedMaterialStock() {
            return this.selectedMaterial ? (this.selectedMaterial.quantity_available || 0) : 0;
        },
        get isQuantityExcessive() {
            if (!this.selectedMaterialId || !this.selectedMaterial) return false;
            const qty = parseInt(this.materialQuantity, 10) || 0;
            return qty > this.selectedMaterialStock;
        },
        validateCostSubmission(e) {
            const rawVal = this.costForm.amount || (e && e.target && e.target.elements && e.target.elements['amount'] ? e.target.elements['amount'].value : '');
            const amt = window.parseAmountValue ? window.parseAmountValue(rawVal) : parseFloat(String(rawVal).replace(/,/g, ''));
            if (isNaN(amt) || amt < 100) {
                window.dispatchEvent(new CustomEvent('show-toast', {
                    detail: { type: 'error', title: 'Minimum Amount Required', message: 'Expenditure amount cannot be less than ₱100.00.', duration: 5000 }
                }));
                return false;
            }
            if (!this.materialQuantity || this.materialQuantity < 1) {
                this.materialQuantity = 1;
            }

            if (this.selectedMaterialId && this.isQuantityExcessive) {
                const stock = this.selectedMaterialStock;
                const matName = this.selectedMaterial ? this.selectedMaterial.name : 'material';
                const msg = `Quantity entered (${this.materialQuantity}) exceeds available stock (${stock}) for ${matName}. Please adjust before proceeding.`;
                window.dispatchEvent(new CustomEvent('show-toast', {
                    detail: { type: 'error', title: 'Excessive Quantity', message: msg, duration: 6000 }
                }));
                return false;
            }
            return true;
        },
        get filteredMaterials() {
            if (!this.materialSearch) {
                return this.catalogMaterialsList;
            }
            const q = this.materialSearch.toLowerCase().trim();
            return this.catalogMaterialsList.filter(item => {
                return (item.name && item.name.toLowerCase().includes(q)) ||
                       (item.resource_code && item.resource_code.toLowerCase().includes(q)) ||
                       (item.model && item.model.toLowerCase().includes(q));
            });
        },
        selectMaterial(mat) {
            this.selectedMaterialId = mat.id;
            this.materialUnitPrice = mat.price;
            this.materialSearch = `[${mat.resource_code}] ${mat.name}${mat.model ? ' (' + mat.model + ')' : ''}`;
            this.materialDropdownOpen = false;
            if (!this.materialQuantity || this.materialQuantity < 1) {
                this.materialQuantity = 1;
            }
            const modelInfo = mat.model ? ` (${mat.model})` : '';
            this.costForm.description = `${mat.name}${modelInfo} [${mat.resource_code}]`;
            this.costForm.cost_type = 'materials';
            this.recalculateMaterialTotal();
        },
        clearMaterialSelection() {
            this.selectedMaterialId = '';
            this.materialSearch = '';
            this.materialDropdownOpen = false;
            this.materialUnitPrice = 0;
            this.costForm.description = '';
            this.costForm.amount = '';
            this.materialQuantity = 1;
        },
        recalculateMaterialTotal() {
            if (this.selectedMaterialId && this.materialUnitPrice) {
                const qty = Math.max(1, parseInt(this.materialQuantity, 10) || 1);
                this.materialQuantity = qty;
                const total = (qty * this.materialUnitPrice).toFixed(2);
                this.costForm.amount = window.formatAmountWithCommas ? window.formatAmountWithCommas(total) : total;

                if (this.isQuantityExcessive) {
                    const stock = this.selectedMaterialStock;
                    const matName = this.selectedMaterial ? this.selectedMaterial.name : 'material';
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: {
                            type: 'warning',
                            title: 'Quantity Exceeds Available Stock',
                            message: `Requested ${qty} units of "${matName}", but only ${stock} units are currently available.`,
                            duration: 5000
                        }
                    }));
                }
            }
        }
    };
}
</script>
@endpush
