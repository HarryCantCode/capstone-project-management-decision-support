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
        <section class="card card-glass" aria-labelledby="project-finances-heading" data-tour="costing-show-financial-overview">
            <div class="card-header">
                <h2 id="project-finances-heading">Financial Overview</h2>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; justify-content: space-between; gap: var(--space-4);">
                <div class="financial-stats-grid">
                    <div class="financial-stat-card" data-tour="costing-show-allocated-budget">
                        <span class="financial-stat-card__label" title="Allocated Budget">Allocated Budget</span>
                        <div class="financial-stat-card__value text-success" title="₱{{ number_format($project->contract_price, 2) }}">
                            ₱{{ number_format($project->contract_price, 2) }}
                        </div>
                    </div>
                    <div class="financial-stat-card" data-tour="costing-show-actual-spend">
                        <span class="financial-stat-card__label" title="Total Actual Spend">Total Actual Spend</span>
                        <div class="financial-stat-card__value" style="color: var(--color-ink);" title="₱{{ number_format($project->actual_spend, 2) }}">
                            ₱{{ number_format($project->actual_spend, 2) }}
                        </div>
                    </div>
                    <div class="financial-stat-card" data-tour="costing-show-remaining-budget">
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
        <section class="card card-glass" aria-labelledby="category-spending-heading" data-tour="costing-show-category-spending">
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
    <div class="card card-glass" data-tour="costing-show-ledger-card">
        <div class="card-header">
            <div>
                <span style="font-weight: 700; font-size: 1rem;">Itemized Expense Ledger</span>
                <span class="text-muted" style="font-size: 0.8125rem; margin-left: var(--space-2);">
                    Showing {{ $costs->total() }} {{ Str::plural('entry', $costs->total()) }}
                </span>
            </div>
            @if(Auth::user()->hasAnyRole(['Admin', 'Manager', 'Staff']))
                @if ($isProjectReadOnly)
                    <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.6; cursor: not-allowed;" title="{{ $project->trashed() ? 'Cannot log expenses on an archived project' : 'Cannot log expenses on a completed project unless reactivated' }}" data-tour="costing-show-log-expense-btn">
                        + Log New Expense / Cost
                    </button>
                @else
                    <button type="button" class="btn btn-primary btn-sm" @click="openAddCostModal()" data-tour="costing-show-log-expense-btn">
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
                            <th scope="col" data-tour="costing-show-col-description">Description (What this is for)</th>
                            <th scope="col" data-tour="costing-show-col-category">Category</th>
                            <th scope="col" class="text-center" data-tour="costing-show-col-qty">Qty</th>
                            <th scope="col" class="text-right" data-tour="costing-show-col-amount">Amount (PHP)</th>
                            <th scope="col" data-tour="costing-show-col-date">Incurred Date</th>
                            <th scope="col" data-tour="costing-show-col-logged-by">Logged By</th>
                            <th scope="col" class="text-right" data-tour="costing-show-col-actions">Actions</th>
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
                                    @php
                                        $pendingInvPath = $pendingExpense->payload['invoice_path'] ?? $pendingExpense->payload['file_path'] ?? null;
                                        $pendingInvName = $pendingExpense->payload['invoice_original_name'] ?? $pendingExpense->payload['file_name'] ?? 'View Invoice';
                                    @endphp
                                    @if($pendingInvPath)
                                        <div style="margin-top: 4px;">
                                            <a href="{{ Storage::disk('public')->url($pendingInvPath) }}" target="_blank" class="invoice-pill-link" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.72rem; padding: 2px 7px; border-radius: 4px; background: rgba(37, 99, 235, 0.08); color: var(--color-accent); border: 1px solid rgba(37, 99, 235, 0.25); text-decoration: none;" title="View attached invoice: {{ $pendingInvName }}">
                                                <svg style="width: 12px; height: 12px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                    <polyline points="14 2 14 8 20 8"></polyline>
                                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                                </svg>
                                                <span>Invoice: {{ Str::limit($pendingInvName, 24) }}</span>
                                            </a>
                                        </div>
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
                                    @if($cost->invoice_path)
                                        <div style="margin-top: 4px;">
                                            <a href="{{ Storage::disk('public')->url($cost->invoice_path) }}" target="_blank" class="invoice-pill-link" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.72rem; padding: 2px 7px; border-radius: 4px; background: rgba(37, 99, 235, 0.08); color: var(--color-accent); border: 1px solid rgba(37, 99, 235, 0.25); text-decoration: none;" title="View attached invoice: {{ $cost->invoice_original_name }}">
                                                <svg style="width: 12px; height: 12px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                    <polyline points="14 2 14 8 20 8"></polyline>
                                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                                </svg>
                                                <span>Invoice: {{ Str::limit($cost->invoice_original_name ?: 'View File', 24) }}</span>
                                            </a>
                                        </div>
                                    @endif
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
    <div
        class="reassign-modal-overlay"
        style="display: none;"
        x-show="addCostModalOpen"
        x-cloak
        @keydown.escape.window="addCostModalOpen = false"
        role="dialog"
        aria-modal="true"
        aria-labelledby="costing-log-expense-title"
    >
        <div class="reassign-modal-backdrop" @click="addCostModalOpen = false"></div>
        <div
            class="reassign-modal-content card"
            x-show="addCostModalOpen"
            x-transition
            style="max-width: 540px; width: 100%; max-height: calc(100vh - 2.5rem); display: flex; flex-direction: column; overflow: hidden; transform: none;"
        >
            {{-- Minimalist Modal Header --}}
            <div class="card-header" style="padding: 14px 20px; background: var(--color-surface); border-bottom: 1px solid var(--color-border); flex-shrink: 0;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; border-radius: var(--radius-md); background: var(--color-accent-light); color: var(--color-accent); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg width="17" height="17" style="width: 17px; height: 17px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 id="costing-log-expense-title" style="margin: 0; font-size: 0.9375rem; font-weight: 600; color: var(--color-ink); line-height: 1.25;">Log Project Expense</h2>
                        <span class="text-muted" style="font-size: 0.75rem; font-weight: 400;">Record a verified expenditure against project budget</span>
                    </div>
                </div>
                <button type="button" class="staff-action-review-close" @click="addCostModalOpen = false" aria-label="Close modal">&times;</button>
            </div>

            <form action="{{ route('projects.costs.store', $project) }}" method="POST" enctype="multipart/form-data" @submit="if (!validateCostSubmission($event)) { $event.preventDefault(); }" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
                @csrf
                <input type="hidden" name="redirect_to" value="costing">
                <input type="hidden" name="resource_id" :value="selectedMaterialId || ''">

                <div class="card-body" style="padding: 18px 20px; overflow-y: auto; flex: 1;">
                    {{-- Compact Project & Budget Context Strip --}}
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 14px; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); margin-bottom: 14px; font-size: 0.8125rem;">
                        <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                            <span class="font-mono" style="font-size: 0.72rem; font-weight: 600; padding: 2px 6px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); color: var(--color-muted); flex-shrink: 0;">{{ $project->project_code }}</span>
                            <strong style="color: var(--color-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $project->name }}</strong>
                        </div>
                        <div style="flex-shrink: 0; text-align: right;">
                            <span class="text-muted" style="font-size: 0.75rem;">Budget:</span>
                            <strong class="font-mono text-success" style="font-size: 0.8125rem; margin-left: 3px;">₱{{ number_format($project->contract_price, 2) }}</strong>
                        </div>
                    </div>

                    {{-- Minimizable Materials Catalog Selector --}}
                    <div class="form-group form-group-premium" style="border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); margin-bottom: 14px;" @click.outside="materialDropdownOpen = false">
                        {{-- Accordion Header --}}
                        <div
                            @click="catalogSectionOpen = !catalogSectionOpen"
                            style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--color-surface-alt); cursor: pointer; user-select: none; border-radius: var(--radius-md); transition: background 120ms ease;"
                            :style="'display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: var(--color-surface-alt); cursor: pointer; user-select: none; transition: background 120ms ease; ' + (catalogSectionOpen ? 'border-bottom: 1px solid var(--color-border); border-radius: var(--radius-md) var(--radius-md) 0 0;' : 'border-radius: var(--radius-md);')"
                        >
                            <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                <svg width="15" height="15" style="width: 15px; height: 15px; color: var(--color-accent); flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                                </svg>
                                <span style="font-weight: 600; font-size: 0.8125rem; color: var(--color-ink);">Choose from Materials Catalog</span>
                                <span class="text-muted" style="font-size: 0.75rem; font-weight: 400;" x-show="!selectedMaterialId">(Optional)</span>
                                <template x-if="selectedMaterialId">
                                    <span class="font-mono" style="background: var(--color-accent-light); color: var(--color-accent); font-size: 0.6875rem; padding: 1px 6px; border-radius: var(--radius-sm); font-weight: 600;" x-text="selectedMaterial ? selectedMaterial.resource_code : 'Selected'"></span>
                                </template>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                                <template x-if="selectedMaterialId">
                                    <button type="button" @click.stop="clearMaterialSelection()" style="background: none; border: none; padding: 2px 6px; font-size: 0.75rem; font-weight: 600; color: var(--color-danger); cursor: pointer;">
                                        Clear
                                    </button>
                                </template>
                                <span class="text-muted" style="font-size: 0.75rem; font-weight: 500;" x-text="catalogSectionOpen ? 'Hide' : 'Browse'"></span>
                                <svg width="14" height="14" style="width: 14px; height: 14px; color: var(--color-muted); flex-shrink: 0; transition: transform 150ms ease;" :style="'width: 14px; height: 14px; color: var(--color-muted); flex-shrink: 0; transition: transform 150ms ease; ' + (catalogSectionOpen ? 'transform: rotate(180deg);' : 'transform: rotate(0deg);')" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </div>
                        </div>

                        {{-- Accordion Body --}}
                        <div x-show="catalogSectionOpen" x-cloak style="padding: 12px; background: var(--color-surface); border-radius: 0 0 var(--radius-md) var(--radius-md);">
                            <div style="position: relative;">
                                <div style="display: flex; align-items: center; position: relative;">
                                    <svg width="14" height="14" style="position: absolute; left: 11px; width: 14px; height: 14px; color: var(--color-muted); pointer-events: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    </svg>
                                    <input
                                        type="text"
                                        class="form-control"
                                        placeholder="Search material name, code, or specification..."
                                        x-model="materialSearch"
                                        @focus="materialDropdownOpen = true"
                                        @input="materialDropdownOpen = true"
                                        style="padding: 6px 30px 6px 32px; font-size: 0.8125rem; height: 36px; line-height: 1.4;"
                                    >
                                    <button
                                        type="button"
                                        x-show="selectedMaterialId || materialSearch"
                                        @click="clearMaterialSelection()"
                                        title="Clear selection"
                                        style="position: absolute; right: 8px; background: none; border: none; font-size: 1.1rem; line-height: 1; color: var(--color-muted); cursor: pointer; padding: 2px 5px;"
                                    >
                                        &times;
                                    </button>
                                </div>

                                {{-- Dropdown options list --}}
                                <div
                                    x-show="materialDropdownOpen"
                                    x-cloak
                                    style="position: absolute; top: calc(100% + 4px); left: 0; right: 0; max-height: 220px; overflow-y: auto; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); box-shadow: var(--shadow-lg); z-index: 60; padding: 4px;"
                                >
                                    <template x-for="item in filteredMaterials" :key="item.id">
                                        <div
                                            @click="selectMaterial(item)"
                                            style="padding: 8px 12px; margin-bottom: 2px; cursor: pointer; border-radius: var(--radius-sm); font-size: 0.8125rem; display: flex; justify-content: space-between; align-items: center; gap: 10px; transition: background 120ms ease;"
                                            :style="'padding: 8px 12px; margin-bottom: 2px; cursor: pointer; border-radius: var(--radius-sm); font-size: 0.8125rem; display: flex; justify-content: space-between; align-items: center; gap: 10px; transition: background 120ms ease; ' + (selectedMaterialId == item.id ? 'background: var(--color-accent-light); font-weight: 600;' : '')"
                                            onmouseover="if(this.dataset.selected !== 'true') this.style.background='var(--color-surface-alt)'"
                                            onmouseout="if(this.dataset.selected !== 'true') this.style.background='transparent'"
                                            :data-selected="selectedMaterialId == item.id"
                                        >
                                            <div style="min-width: 0; flex: 1;">
                                                <div style="display: flex; align-items: center; gap: 6px;">
                                                    <span class="font-mono" style="font-size: 0.72rem; font-weight: 600; color: var(--color-accent);" x-text="item.resource_code"></span>
                                                    <span style="font-weight: 600; color: var(--color-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="item.name"></span>
                                                </div>
                                                <div style="font-size: 0.72rem; color: var(--color-muted); margin-top: 1px;">
                                                    Stock: <span class="font-mono" :class="item.quantity_available > 0 ? 'text-success' : 'text-danger'" x-text="item.quantity_available"></span>
                                                    <span x-show="item.model" x-text="' • ' + item.model"></span>
                                                </div>
                                            </div>
                                            <div class="font-mono text-success" style="font-weight: 600; font-size: 0.8125rem; flex-shrink: 0;">
                                                ₱<span x-text="Number(item.price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                            </div>
                                        </div>
                                    </template>
                                    <div x-show="filteredMaterials.length === 0" style="padding: 12px; font-size: 0.8125rem; color: var(--color-muted); text-align: center;">
                                        No matching materials found.
                                    </div>
                                </div>
                            </div>

                            {{-- Catalog Quantity & Unit Price Sub-Grid --}}
                            <div x-show="selectedMaterialId" x-cloak style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--color-border); display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div>
                                    <label for="costing_material_qty_input" class="form-label" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; margin-bottom: 4px;">
                                        <span>Quantity <span class="required">*</span></span>
                                        <span class="font-mono" style="font-size: 0.72rem;" :class="isQuantityExcessive ? 'text-danger' : 'text-muted'">
                                            Avail: <span x-text="selectedMaterialStock"></span>
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
                                        style="padding: 6px 10px; height: 36px; font-size: 0.8125rem; line-height: 1.4;"
                                        :style="'padding: 6px 10px; height: 36px; font-size: 0.8125rem; line-height: 1.4; ' + (isQuantityExcessive ? 'border-color: var(--color-danger); background: var(--color-danger-light);' : '')"
                                        x-model.number="materialQuantity"
                                        @input="recalculateMaterialTotal()"
                                        placeholder="1"
                                    >
                                </div>
                                <div>
                                    <label class="form-label" style="font-size: 0.75rem; margin-bottom: 4px;">Unit Price</label>
                                    <div class="font-mono text-muted" style="height: 36px; padding: 0 10px; display: flex; align-items: center; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.8125rem; font-weight: 600;">
                                        ₱<span x-text="Number(materialUnitPrice).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                    </div>
                                </div>
                                <p x-show="isQuantityExcessive" class="text-danger" style="grid-column: span 2; font-size: 0.72rem; margin: 0;">
                                    Quantity exceeds available stock (<span x-text="selectedMaterialStock"></span>).
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Description Field --}}
                    <div class="form-group form-group-premium" style="margin-bottom: 14px;">
                        <label for="cost_description" class="form-label" style="font-size: 0.8125rem; font-weight: 600; margin-bottom: 5px;">
                            Description <span class="required" style="color: var(--color-danger);">*</span>
                        </label>
                        <input
                            id="cost_description"
                            name="description"
                            type="text"
                            class="form-control"
                            style="padding: 8px 12px; font-size: 0.875rem; height: 40px; line-height: 1.4;"
                            :style="'padding: 8px 12px; font-size: 0.875rem; height: 40px; line-height: 1.4; ' + (selectedMaterialId ? 'background: var(--color-surface-alt); cursor: not-allowed;' : '')"
                            x-model="costForm.description"
                            :readonly="Boolean(selectedMaterialId)"
                            placeholder="e.g. Scaffolding rental, Elevator cables, Concrete mix"
                            required
                        >
                    </div>

                    {{-- Category & Incurred Date Grid --}}
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                        <div class="form-group form-group-premium" style="margin-bottom: 0;">
                            <label for="cost_type" class="form-label" style="font-size: 0.8125rem; font-weight: 600; margin-bottom: 5px;">
                                Category <span class="required" style="color: var(--color-danger);">*</span>
                            </label>
                            <select
                                id="cost_type"
                                name="cost_type"
                                class="form-control"
                                style="padding: 6px 12px; font-size: 0.875rem; height: 40px; line-height: 1.4;"
                                :style="'padding: 6px 12px; font-size: 0.875rem; height: 40px; line-height: 1.4; ' + (selectedMaterialId ? 'background: var(--color-surface-alt); cursor: not-allowed;' : '')"
                                x-model="costForm.cost_type"
                                :disabled="Boolean(selectedMaterialId)"
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
                            <label for="incurred_date" class="form-label" style="font-size: 0.8125rem; font-weight: 600; margin-bottom: 5px;">
                                Incurred Date
                            </label>
                            <input
                                id="incurred_date"
                                name="incurred_date"
                                type="date"
                                class="form-control font-mono"
                                style="padding: 6px 12px; font-size: 0.84375rem; height: 40px; line-height: 1.4;"
                                value="{{ now()->format('Y-m-d') }}"
                            >
                        </div>
                    </div>

                    {{-- Quantity & Amount Grid --}}
                    <div
                        style="display: grid; grid-template-columns: 110px 1fr; gap: 12px; margin-bottom: 14px;"
                        :style="'display: grid; gap: 12px; margin-bottom: 14px; ' + (selectedMaterialId ? 'grid-template-columns: 1fr;' : 'grid-template-columns: 110px 1fr;')"
                    >
                        <div x-show="!selectedMaterialId" class="form-group form-group-premium" style="margin-bottom: 0;">
                            <label for="costing_manual_cost_quantity" class="form-label" style="font-size: 0.8125rem; font-weight: 600; margin-bottom: 5px;">
                                Quantity <span class="required" style="color: var(--color-danger);">*</span>
                            </label>
                            <input
                                id="costing_manual_cost_quantity"
                                name="quantity"
                                type="number"
                                min="1"
                                step="1"
                                class="form-control font-mono"
                                style="padding: 6px 12px; font-size: 0.875rem; height: 40px; line-height: 1.4;"
                                x-model.number="materialQuantity"
                                :disabled="Boolean(selectedMaterialId)"
                                :required="!selectedMaterialId"
                                placeholder="1"
                            >
                        </div>
                        <input type="hidden" name="quantity" :value="materialQuantity || 1" :disabled="!selectedMaterialId">

                        <div class="form-group form-group-premium" style="margin-bottom: 0;">
                            <label for="cost_amount" class="form-label" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8125rem; font-weight: 600; margin-bottom: 5px;">
                                <span>Amount (PHP) <span class="required" style="color: var(--color-danger);">*</span></span>
                                <span class="text-muted font-mono" style="font-size: 0.7rem; font-weight: 400;">Min ₱100.00</span>
                            </label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 600; color: var(--color-muted); font-family: var(--font-mono); font-size: 0.875rem; pointer-events: none; z-index: 2;">₱</span>
                                <input
                                    id="cost_amount"
                                    name="amount"
                                    type="text"
                                    inputmode="decimal"
                                    data-amount-input
                                    class="form-control font-mono"
                                    style="padding: 6px 12px 6px 30px !important; font-size: 0.875rem; height: 40px; line-height: 1.4;"
                                    :style="'padding: 6px 12px 6px 30px !important; font-size: 0.875rem; height: 40px; line-height: 1.4; ' + (selectedMaterialId ? 'background: var(--color-surface-alt); cursor: not-allowed;' : '')"
                                    x-model="costForm.amount"
                                    @input="if (!selectedMaterialId) { costForm.amount = window.formatAmountWithCommas ? window.formatAmountWithCommas($event.target.value) : $event.target.value; }"
                                    :readonly="Boolean(selectedMaterialId)"
                                    placeholder="0.00"
                                    required
                                >
                            </div>
                        </div>
                    </div>

                    {{-- Attach Invoice Field (Edge-to-Edge Long Rectangular Button) --}}
                    <div class="form-group form-group-premium" style="margin-bottom: 4px;">
                        <label class="form-label" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8125rem; font-weight: 600; margin-bottom: 5px;">
                            <span>Attach Invoice <span class="required" style="color: var(--color-danger);">*</span></span>
                            <span class="text-muted" style="font-size: 0.72rem; font-weight: 400;">PDF, DOCX, JPG, PNG (Max 20MB)</span>
                        </label>

                        {{-- Empty State: Full-Width Long Rectangular Button --}}
                        <template x-if="!invoiceFile">
                            <button
                                type="button"
                                id="costing_invoice_container"
                                @click="$refs.invoiceFileInput.click()"
                                @dragover.prevent="isDraggingInvoice = true"
                                @dragleave.prevent="isDraggingInvoice = false"
                                @drop.prevent="handleInvoiceDrop($event)"
                                style="width: 100%; height: 42px; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 0 16px; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); color: var(--color-ink); font-family: var(--font-body); font-size: 0.84375rem; font-weight: 600; cursor: pointer; transition: all 150ms ease; box-sizing: border-box;"
                                :style="'width: 100%; height: 42px; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 0 16px; border-radius: var(--radius-md); font-family: var(--font-body); font-size: 0.84375rem; font-weight: 600; cursor: pointer; transition: all 150ms ease; box-sizing: border-box; ' + (invoiceError ? 'border: 1px solid var(--color-danger); background: var(--color-danger-light); color: var(--color-danger); box-shadow: 0 0 0 3px rgba(192, 57, 43, 0.12);' : (isDraggingInvoice ? 'border: 1px solid var(--color-accent); background: var(--color-accent-light); color: var(--color-accent);' : 'background: var(--color-surface-alt); border: 1px solid var(--color-border); color: var(--color-ink);'))"
                                onmouseover="if(!this.dataset.invalid) { this.style.borderColor='var(--color-accent)'; this.style.color='var(--color-accent)'; }"
                                onmouseout="if(!this.dataset.invalid) { this.style.borderColor='var(--color-border)'; this.style.color='var(--color-ink)'; }"
                                :data-invalid="invoiceError ? 'true' : ''"
                            >
                                <svg width="16" height="16" style="width: 16px; height: 16px; color: var(--color-accent); flex-shrink: 0;" :style="'width: 16px; height: 16px; flex-shrink: 0; ' + (invoiceError ? 'color: var(--color-danger);' : 'color: var(--color-accent);')" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                                </svg>
                                <span>Attach Invoice or Receipt</span>
                            </button>
                        </template>

                        {{-- Attached State: Full-Width Rectangular File Bar --}}
                        <template x-if="invoiceFile">
                            <div style="width: 100%; min-height: 42px; display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 6px 12px; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-left: 3px solid var(--color-accent); border-radius: var(--radius-md); box-sizing: border-box;">
                                <div style="display: flex; align-items: center; gap: 10px; min-width: 0; flex: 1;">
                                    <svg width="18" height="18" style="width: 18px; height: 18px; color: var(--color-accent); flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                    </svg>
                                    <div style="min-width: 0; flex: 1; display: flex; align-items: baseline; gap: 8px;">
                                        <span style="font-weight: 600; font-size: 0.8125rem; color: var(--color-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="invoiceFile.name"></span>
                                        <span class="text-muted font-mono" style="font-size: 0.72rem; flex-shrink: 0;" x-text="formatFileSize(invoiceFile.size)"></span>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-sm"
                                        style="padding: 2px 10px; font-size: 0.75rem; height: 28px;"
                                        @click="$refs.invoiceFileInput.click()"
                                    >
                                        Change
                                    </button>
                                    <button
                                        type="button"
                                        @click="clearInvoice()"
                                        title="Remove attached invoice"
                                        style="width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; color: var(--color-muted); background: transparent; border: none; border-radius: var(--radius-sm); font-size: 1.1rem; cursor: pointer; line-height: 1;"
                                        onmouseover="this.style.color='var(--color-danger)'"
                                        onmouseout="this.style.color='var(--color-muted)'"
                                    >
                                        &times;
                                    </button>
                                </div>
                            </div>
                        </template>

                        {{-- Hidden native file input --}}
                        <input
                            type="file"
                            name="invoice"
                            x-ref="invoiceFileInput"
                            @change="handleInvoiceSelected($event)"
                            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp"
                            style="display: none;"
                        >

                        {{-- Inline Validation Error --}}
                        <div x-show="invoiceError" x-cloak style="color: var(--color-danger); font-size: 0.75rem; margin-top: 5px; display: flex; align-items: center; gap: 4px;">
                            <svg width="14" height="14" style="width: 14px; height: 14px; flex-shrink: 0;" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            <span>Invoice attachment is required before saving this expense.</span>
                        </div>
                    </div>

                    @if(Auth::user()->hasRole('Staff'))
                        <div style="margin-top: 12px; padding: 8px 12px; background: var(--status-ongoing-bg); border: 1px solid rgba(201, 138, 44, 0.25); border-radius: var(--radius-sm); font-size: 0.75rem; color: var(--status-ongoing-text);">
                            <strong>Staff Notice:</strong> Logged expenses are submitted for manager approval before reflecting in project totals.
                        </div>
                    @endif
                </div>

                {{-- Minimalist Right-Aligned Modal Footer --}}
                <div class="card-footer" style="padding: 12px 20px; display: flex; justify-content: flex-end; align-items: center; gap: 8px; border-top: 1px solid var(--color-border); background: var(--color-surface-alt); flex-shrink: 0;">
                    <button type="button" class="btn btn-secondary btn-sm" style="padding: 7px 16px; font-size: 0.84375rem;" @click="addCostModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" style="padding: 7px 18px; font-size: 0.84375rem;">Save Cost Entry</button>
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
        catalogSectionOpen: false,
        invoiceFile: null,
        invoiceError: false,
        isDraggingInvoice: false,
        formatFileSize(bytes) {
            if (!bytes) return '0 B';
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
            if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return bytes + ' B';
        },
        getFileIcon(name) {
            if (!name) return '📄';
            const ext = name.split('.').pop().toLowerCase();
            if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext)) return '🖼️';
            if (['pdf'].includes(ext)) return '📕';
            if (['doc', 'docx'].includes(ext)) return '📘';
            if (['xls', 'xlsx', 'csv'].includes(ext)) return '📊';
            if (['zip', 'rar', '7z'].includes(ext)) return '📦';
            return '📄';
        },
        handleInvoiceSelected(e) {
            const file = e.target.files && e.target.files[0];
            if (file) {
                this.invoiceFile = file;
                this.invoiceError = false;
            }
        },
        handleInvoiceDrop(e) {
            this.isDraggingInvoice = false;
            const files = e.dataTransfer ? e.dataTransfer.files : null;
            if (files && files.length > 0) {
                this.invoiceFile = files[0];
                this.invoiceError = false;
                try {
                    const dt = new DataTransfer();
                    dt.items.add(files[0]);
                    if (this.$refs.invoiceFileInput) {
                        this.$refs.invoiceFileInput.files = dt.files;
                    }
                } catch (_) {}
            }
        },
        clearInvoice() {
            this.invoiceFile = null;
            this.invoiceError = false;
            this.isDraggingInvoice = false;
            if (this.$refs.invoiceFileInput) {
                this.$refs.invoiceFileInput.value = '';
            }
        },
        openAddCostModal() {
            this.clearMaterialSelection();
            this.catalogSectionOpen = false;
            this.costForm.cost_type = 'materials';
            this.clearInvoice();
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

            // Invoice file attachment is required
            const hasFileInRef = this.$refs.invoiceFileInput && this.$refs.invoiceFileInput.files && this.$refs.invoiceFileInput.files.length > 0;
            if (!this.invoiceFile && !hasFileInRef) {
                this.invoiceError = true;
                const container = document.getElementById('costing_invoice_container') || document.getElementById('costing_invoice_dropzone');
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                window.dispatchEvent(new CustomEvent('show-toast', {
                    detail: {
                        type: 'error',
                        title: 'Invoice Required',
                        message: 'Please attach an invoice file before submitting this expense.',
                        duration: 5000
                    }
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
            this.catalogSectionOpen = true;
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
