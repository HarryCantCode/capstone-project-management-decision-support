@extends('layouts.app')

@section('title', 'Staff Request Approvals')
@section('meta-description', 'Review, filter, and process task completions, expense entries, and file uploads submitted by staff members.')

@section('content')
<div x-data="staffRequestsModule()">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h1>Staff Request Approvals</h1>
            <p class="page-header__description">Review, filter, and process timeline task completions, expense entries, and file uploads submitted by staff.</p>
        </div>
        <div class="page-actions">
            {{-- View Mode Switcher: Request Approvals List vs View from Staff List --}}
            <div class="switch-pill-container" role="tablist" aria-label="Staff requests view mode">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="viewMode === 'list'"
                    class="switch-pill-btn"
                    :class="{ 'switch-pill-btn--active': viewMode === 'list' }"
                    @click="switchView('list')"
                    style="gap: 6px;"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width: 14px; height: 14px; flex-shrink: 0;">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg>
                    <span>Request Approvals ({{ $totalCount }})</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="viewMode === 'staff'"
                    class="switch-pill-btn"
                    :class="{ 'switch-pill-btn--active': viewMode === 'staff' }"
                    @click="switchView('staff')"
                    style="gap: 6px;"
                >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width: 14px; height: 14px; flex-shrink: 0;">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span>View from Staff List ({{ $staffMembers->count() }})</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Summary KPI Cards (4-Column Grid matching Dashboard & Costing) --}}
    <div class="costing-kpi-grid dashboard-stats-grid">
        {{-- Card 1: Total Requests --}}
        <div class="costing-kpi-card costing-kpi-card--contract costing-kpi-card--interactive" @click="setStatusFilter('all')" role="button" tabindex="0" @keydown.enter="setStatusFilter('all')">
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
            </svg>
            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Total Requests</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--contract" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                            <line x1="9" y1="12" x2="15" y2="12"></line>
                            <line x1="9" y1="16" x2="13" y2="16"></line>
                        </svg>
                    </span>
                </div>
                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric">{{ number_format($totalCount) }}</div>
                    <p class="costing-kpi-card__description">Cumulative staff submissions across all active projects.</p>
                </div>
            </div>
            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                        </svg>
                    </span>
                    All Submissions
                </span>
                <span class="costing-kpi-card__pill costing-kpi-card__pill--neutral">
                    {{ $staffMembers->count() }} {{ Str::plural('Staff', $staffMembers->count()) }}
                </span>
            </div>
        </div>

        {{-- Card 2: Unexamined Requests --}}
        <div class="costing-kpi-card costing-kpi-card--pending costing-kpi-card--interactive" @click="setStatusFilter('unexamined')" role="button" tabindex="0" @keydown.enter="setStatusFilter('unexamined')">
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Unexamined</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--pending" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </span>
                </div>
                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric">{{ number_format($unexaminedCount) }}</div>
                    <p class="costing-kpi-card__description">Requests awaiting Admin or Manager verification.</p>
                </div>
            </div>
            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </span>
                    Review Queue
                </span>
                <span class="costing-kpi-card__pill {{ $unexaminedCount > 0 ? 'costing-kpi-card__pill--warning' : 'costing-kpi-card__pill--neutral' }}">
                    {{ $unexaminedCount > 0 ? 'Needs Review' : 'Queue Clear' }}
                </span>
            </div>
        </div>

        {{-- Card 3: Approved Requests --}}
        <div class="costing-kpi-card costing-kpi-card--completed costing-kpi-card--interactive" @click="setStatusFilter('approved')" role="button" tabindex="0" @keydown.enter="setStatusFilter('approved')">
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Approved</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--completed" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </span>
                </div>
                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric costing-kpi-card__value--success">{{ number_format($approvedCount) }}</div>
                    <p class="costing-kpi-card__description">Verified requests applied to project timelines &amp; ledgers.</p>
                </div>
            </div>
            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </span>
                    Verified
                </span>
                <span class="costing-kpi-card__pill costing-kpi-card__pill--success">
                    {{ $totalCount > 0 ? round(($approvedCount / $totalCount) * 100) : 0 }}% Rate
                </span>
            </div>
        </div>

        {{-- Card 4: Rejected Requests --}}
        <div class="costing-kpi-card costing-kpi-card--remaining-negative costing-kpi-card--interactive" @click="setStatusFilter('rejected')" role="button" tabindex="0" @keydown.enter="setStatusFilter('rejected')">
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Rejected</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--remaining-negative" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                    </span>
                </div>
                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric {{ $rejectedCount > 0 ? 'costing-kpi-card__value--danger' : '' }}">{{ number_format($rejectedCount) }}</div>
                    <p class="costing-kpi-card__description">Declined requests returned with reviewer remarks.</p>
                </div>
            </div>
            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </span>
                    Declined
                </span>
                <span class="costing-kpi-card__pill {{ $rejectedCount > 0 ? 'costing-kpi-card__pill--danger' : 'costing-kpi-card__pill--neutral' }}">
                    {{ $totalCount > 0 ? round(($rejectedCount / $totalCount) * 100) : 0 }}% Rate
                </span>
            </div>
        </div>
    </div>

    {{-- Main Module Register Card --}}
    <div class="card card-glass">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3); padding: var(--space-3) var(--space-5);">
            {{-- Left: Module Section Title --}}
            <div style="display: flex; align-items: center; gap: var(--space-2); flex-shrink: 0;">
                <span style="font-weight: 700; font-size: 0.95rem; color: var(--color-ink); white-space: nowrap;" x-text="viewMode === 'staff' ? 'Staff Directory & Request Summary' : 'Staff Request Approvals'">Staff Request Approvals</span>
            </div>

            {{-- Right: Horizontally Aligned Searchbar & Dropdown Filters --}}
            <form
                method="GET"
                action="{{ route('pending-actions.index') }}"
                x-ref="filterForm"
                role="search"
                style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-left: auto;"
            >
                <input type="hidden" name="view" :value="viewMode">

                {{-- Dedicated Module Searchbar --}}
                <div class="project-search-wrapper" style="width: 230px; flex-shrink: 0;">
                    <label for="staff-requests-search" class="visually-hidden">Search staff request approvals</label>
                    <svg class="project-search-icon" width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 15px; height: 15px; flex-shrink: 0;">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                    <input
                        type="text"
                        id="staff-requests-search"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Search staff, project, task..."
                        class="form-control form-control--compact project-search-input"
                        style="width: 100%; height: 34px; font-size: 0.8125rem;"
                        title="Search by staff name, project, task title, expense description, or file name"
                        autocomplete="off"
                    >
                    @if (!empty($search))
                        <a href="{{ route('pending-actions.index', array_filter(['status' => $status, 'recency' => $recency, 'action_type' => $actionType, 'view' => $view])) }}" class="project-search-clear" title="Clear search" aria-label="Clear search">
                            <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="width: 12px; height: 12px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4l8 8M12 4l-8 8" />
                            </svg>
                        </a>
                    @endif
                </div>

                {{-- Status Select --}}
                <div style="flex-shrink: 0;">
                    <label for="filter-status" class="visually-hidden">Filter by status</label>
                    <select
                        id="filter-status"
                        name="status"
                        x-ref="statusSelect"
                        class="form-control form-control--compact project-status-select"
                        style="width: auto; min-width: 148px; height: 34px; padding: 0 0.65rem; font-size: 0.8125rem;"
                        onchange="this.form.submit()"
                    >
                        <option value="all" @selected(($status ?? 'all') === 'all')>All Statuses ({{ $totalCount }})</option>
                        <option value="unexamined" @selected(in_array($status ?? '', ['unexamined', 'pending'], true))>Unexamined ({{ $unexaminedCount }})</option>
                        <option value="approved" @selected(($status ?? '') === 'approved')>Approved ({{ $approvedCount }})</option>
                        <option value="rejected" @selected(($status ?? '') === 'rejected')>Rejected ({{ $rejectedCount }})</option>
                    </select>
                </div>

                {{-- Recency Select --}}
                <div style="flex-shrink: 0;">
                    <label for="filter-recency" class="visually-hidden">Filter by recency</label>
                    <select
                        id="filter-recency"
                        name="recency"
                        class="form-control form-control--compact project-status-select"
                        style="width: auto; min-width: 148px; height: 34px; padding: 0 0.65rem; font-size: 0.8125rem;"
                        onchange="this.form.submit()"
                    >
                        <option value="newest" @selected(($recency ?? 'newest') === 'newest')>Most Recent First</option>
                        <option value="oldest" @selected(($recency ?? '') === 'oldest')>Oldest First</option>
                        <option value="today" @selected(($recency ?? '') === 'today')>Submitted Today</option>
                        <option value="7days" @selected(($recency ?? '') === '7days')>Last 7 Days</option>
                        <option value="30days" @selected(($recency ?? '') === '30days')>Last 30 Days</option>
                    </select>
                </div>

                {{-- Action Type Select --}}
                <div style="flex-shrink: 0;">
                    <label for="filter-action-type" class="visually-hidden">Filter by request type</label>
                    <select
                        id="filter-action-type"
                        name="action_type"
                        class="form-control form-control--compact project-status-select"
                        style="width: auto; min-width: 152px; height: 34px; padding: 0 0.65rem; font-size: 0.8125rem;"
                        onchange="this.form.submit()"
                    >
                        <option value="all" @selected(($actionType ?? 'all') === 'all')>All Request Types</option>
                        <option value="complete_subtask" @selected(($actionType ?? '') === 'complete_subtask')>Task Completion</option>
                        <option value="add_expense" @selected(($actionType ?? '') === 'add_expense')>Expense Entry</option>
                        <option value="add_file" @selected(($actionType ?? '') === 'add_file')>File Upload</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 0.35rem; height: 34px; padding: 0 0.85rem; font-size: 0.8125rem; flex-shrink: 0;">
                    <svg width="13" height="13" style="width: 13px; height: 13px; flex-shrink: 0;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                    </svg>
                    Filter
                </button>

                @if (!empty($search) || ($status ?? 'all') !== 'all' || ($recency ?? 'newest') !== 'newest' || ($actionType ?? 'all') !== 'all')
                    <a href="{{ route('pending-actions.index', ['view' => $view]) }}" class="btn btn-secondary btn-sm project-filter-reset" style="height: 34px; padding: 0 0.75rem; font-size: 0.8125rem; flex-shrink: 0;" title="Clear all filters and search">
                        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" class="project-filter-reset__icon" aria-hidden="true" style="width: 12px; height: 12px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4l8 8M12 4l-8 8" />
                        </svg>
                        <span>Clear</span>
                    </a>
                @endif
            </form>
        </div>

        {{-- ================================================================= --}}
        {{-- VIEW 1: REQUEST APPROVALS LIST VIEW                               --}}
        {{-- ================================================================= --}}
        <div x-show="viewMode === 'list'">
            @if ($actions->isEmpty())
                <div class="card-body">
                    <div class="empty-state">
                        <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <h2 class="empty-state__title">No staff requests found</h2>
                        <p class="empty-state__description">
                            @if (!empty($search) || ($status ?? 'all') !== 'all' || ($recency ?? 'newest') !== 'newest' || ($actionType ?? 'all') !== 'all')
                                No staff requests matched your current search or filter criteria.
                            @else
                                Staff requests for timeline task completions, expense entries, and file uploads will appear here for review.
                            @endif
                        </p>
                        @if (!empty($search) || ($status ?? 'all') !== 'all' || ($recency ?? 'newest') !== 'newest' || ($actionType ?? 'all') !== 'all')
                            <a href="{{ route('pending-actions.index', ['view' => 'list']) }}" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a>
                        @endif
                    </div>
                </div>
            @else
                <div class="table-wrapper" style="border: none; border-radius: 0; box-shadow: none;">
                    <table class="data-table">
                        <caption class="visually-hidden">Staff request approvals register</caption>
                        <thead>
                            <tr>
                                <th scope="col">Staff Member</th>
                                <th scope="col">Request Details</th>
                                <th scope="col">Project</th>
                                <th scope="col">Submitted</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($actions as $action)
                                @php
                                    $staffUser = $action->user;
                                    $staffInitials = $staffUser
                                        ? strtoupper(substr($staffUser->first_name ?? $staffUser->name, 0, 1) . substr($staffUser->last_name ?? '', 0, 1))
                                        : 'ST';
                                @endphp
                                <tr style="cursor: pointer;" @click="openActionModal({{ $action->id }})">
                                    {{-- Staff Member --}}
                                    <td>
                                        <div style="display: flex; align-items: center; gap: var(--space-3);">
                                            <span
                                                class="font-mono"
                                                style="width: 34px; height: 34px; border-radius: 50%; background: var(--color-accent-light); color: var(--color-accent); font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid var(--color-border);"
                                                aria-hidden="true"
                                            >
                                                {{ $staffInitials }}
                                            </span>
                                            <div>
                                                <div style="font-weight: 600; color: var(--color-ink);">{{ $staffUser?->name ?? 'Unknown Staff' }}</div>
                                                <div class="font-mono" style="font-size: 0.75rem; color: var(--color-muted);">
                                                    {{ $staffUser?->employee_number ?? $staffUser?->email ?? 'Staff' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Request Details --}}
                                    <td>
                                        <div style="display: flex; align-items: flex-start; gap: var(--space-3);">
                                            @if ($action->action_type === 'complete_subtask')
                                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: var(--radius-md); background: rgba(234, 179, 8, 0.16); color: #EAB308; border: 1px solid rgba(234, 179, 8, 0.32); flex-shrink: 0; margin-top: 2px;" title="Timeline Task Completion">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;">
                                                        <polyline points="9 11 12 14 22 4"></polyline>
                                                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                                                    </svg>
                                                </span>
                                            @elseif ($action->action_type === 'add_expense')
                                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: var(--radius-md); background: rgba(249, 115, 22, 0.16); color: #F97316; border: 1px solid rgba(249, 115, 22, 0.32); flex-shrink: 0; margin-top: 2px;" title="Expense Entry">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;">
                                                        <line x1="12" y1="1" x2="12" y2="23"></line>
                                                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                                    </svg>
                                                </span>
                                            @else
                                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: var(--radius-md); background: var(--color-accent-light); color: var(--color-accent); border: 1px solid rgba(56, 189, 248, 0.28); flex-shrink: 0; margin-top: 2px;" title="File Upload">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 16px; height: 16px;">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                        <polyline points="14 2 14 8 20 8"></polyline>
                                                    </svg>
                                                </span>
                                            @endif

                                            <div style="flex: 1; min-width: 0;">
                                                <div style="margin-bottom: 4px;">
                                                    @if ($action->action_type === 'complete_subtask')
                                                        <span class="user-menu-role" style="background: rgba(234, 179, 8, 0.16); color: #FACC15; border: 1px solid rgba(234, 179, 8, 0.35);">
                                                            {{ $action->action_type_label }}
                                                        </span>
                                                    @elseif ($action->action_type === 'add_expense')
                                                        <span class="user-menu-role" style="background: rgba(249, 115, 22, 0.16); color: #FB923C; border: 1px solid rgba(249, 115, 22, 0.35);">
                                                            {{ $action->action_type_label }}
                                                        </span>
                                                    @else
                                                        <span class="user-menu-role">
                                                            {{ $action->action_type_label }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div style="font-weight: 600; color: var(--color-ink); line-height: 1.4;">
                                                    {{ $action->summary_title }}
                                                </div>
                                                <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 2px;">
                                                    {{ $action->summary_subtitle }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Project --}}
                                    <td @click.stop>
                                        @if ($action->project)
                                            <a href="{{ route('projects.show', $action->project_id) }}" class="table-link">
                                                @if ($action->project->project_code)
                                                    <span class="font-mono" style="font-size: 0.75rem;">{{ $action->project->project_code }}</span>
                                                @endif
                                                <span class="project-name">{{ $action->project->name }}</span>
                                            </a>
                                        @else
                                            <span class="text-muted">Archived / Deleted Project</span>
                                        @endif
                                    </td>

                                    {{-- Submitted --}}
                                    <td>
                                        <div class="font-mono" style="font-size: 0.8125rem; color: var(--color-ink);">{{ $action->created_at?->format('M j, Y') }}</div>
                                        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 2px;">
                                            {{ $action->created_at?->format('g:i A') }} · {{ $action->created_at?->diffForHumans() }}
                                        </div>
                                    </td>

                                    {{-- Status --}}
                                    <td>
                                        @if ($action->status === 'pending')
                                            <span class="status-pill status-pill--ongoing">Unexamined</span>
                                        @elseif ($action->status === 'approved')
                                            <span class="status-pill status-pill--completed">Approved</span>
                                            @if ($action->reviewer)
                                                <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 2px;">
                                                    by {{ $action->reviewer->name }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="status-pill status-pill--delayed">Rejected</span>
                                            @if ($action->reviewer)
                                                <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 2px;">
                                                    by {{ $action->reviewer->name }}
                                                </div>
                                            @endif
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="text-right" style="white-space: nowrap;" @click.stop>
                                        <div style="display: inline-flex; gap: 0.375rem; align-items: center; justify-content: flex-end;">
                                            @if ($action->status === 'pending')
                                                <button
                                                    type="button"
                                                    class="btn btn-primary btn-sm"
                                                    @click="quickProcessAction({{ $action->id }}, 'approve')"
                                                    :disabled="processingActionId === {{ $action->id }}"
                                                    title="Approve this staff request"
                                                >
                                                    Approve
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn btn-danger btn-sm"
                                                    @click="openActionModal({{ $action->id }})"
                                                    :disabled="processingActionId === {{ $action->id }}"
                                                    title="Reject or add reviewer notes"
                                                >
                                                    Reject
                                                </button>
                                            @endif
                                            <button
                                                type="button"
                                                class="btn btn-secondary btn-sm"
                                                @click="openActionModal({{ $action->id }})"
                                            >
                                                Review
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($actions->hasPages())
                    <div class="card-footer pagination-wrapper">
                        {{ $actions->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            @endif
        </div>

        {{-- ================================================================= --}}
        {{-- VIEW 2: VIEW FROM STAFF LIST                                      --}}
        {{-- ================================================================= --}}
        <div x-show="viewMode === 'staff'" x-cloak>
            @if ($staffMembers->isEmpty())
                <div class="card-body">
                    <div class="empty-state">
                        <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                        <h2 class="empty-state__title">No staff members found</h2>
                        <p class="empty-state__description">No staff accounts matched your current search query.</p>
                        @if (!empty($search))
                            <a href="{{ route('pending-actions.index', ['view' => 'staff']) }}" class="btn btn-secondary btn-sm">Clear Search</a>
                        @endif
                    </div>
                </div>
            @else
                <div class="table-wrapper" style="border: none; border-radius: 0; box-shadow: none;">
                    <table class="data-table">
                        <caption class="visually-hidden">Staff members and their request counts</caption>
                        <thead>
                            <tr>
                                <th scope="col">Staff Member</th>
                                <th scope="col">Role &amp; Employee ID</th>
                                <th scope="col" class="text-right">Total Requests</th>
                                <th scope="col">Request Status Breakdown</th>
                                <th scope="col">Most Recent Request</th>
                                <th scope="col" class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($staffMembers as $staff)
                                @php
                                    $initials = strtoupper(substr($staff->first_name ?? $staff->name, 0, 1) . substr($staff->last_name ?? '', 0, 1));
                                    $latestReq = $staff->pendingActions->first();
                                @endphp
                                <tr style="cursor: pointer;" @click="openStaffModal({{ $staff->id }})">
                                    {{-- Staff Member --}}
                                    <td>
                                        <div style="display: flex; align-items: center; gap: var(--space-3);">
                                            <span
                                                class="font-mono"
                                                style="width: 34px; height: 34px; border-radius: 50%; background: var(--color-accent-light); color: var(--color-accent); font-weight: 700; font-size: 0.75rem; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid var(--color-border);"
                                                aria-hidden="true"
                                            >
                                                {{ $initials }}
                                            </span>
                                            <div>
                                                <div style="font-weight: 600; color: var(--color-ink);">{{ $staff->name }}</div>
                                                <div style="font-size: 0.75rem; color: var(--color-muted);">
                                                    {{ $staff->email }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Role / Position --}}
                                    <td>
                                        <div style="font-weight: 500; color: var(--color-ink);">{{ $staff->job_title ?: 'Staff Member' }}</div>
                                        @if ($staff->employee_number)
                                            <div class="font-mono" style="font-size: 0.75rem; color: var(--color-muted);">ID: {{ $staff->employee_number }}</div>
                                        @endif
                                    </td>

                                    {{-- Total Requests --}}
                                    <td class="cell-numeric">
                                        <span class="font-mono font-bold" style="font-size: 0.9375rem; color: var(--color-ink);">
                                            {{ number_format($staff->total_requests_count) }}
                                        </span>
                                        <span style="font-size: 0.75rem; color: var(--color-muted); font-family: var(--font-body); margin-left: 2px;">
                                            {{ Str::plural('request', $staff->total_requests_count) }}
                                        </span>
                                    </td>

                                    {{-- Request Breakdown --}}
                                    <td>
                                        <div style="display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap;">
                                            <span class="status-pill status-pill--ongoing" style="font-size: 0.75rem;">
                                                {{ $staff->unexamined_requests_count }} Unexamined
                                            </span>
                                            <span class="status-pill status-pill--completed" style="font-size: 0.75rem;">
                                                {{ $staff->approved_requests_count }} Approved
                                            </span>
                                            <span class="status-pill status-pill--delayed" style="font-size: 0.75rem;">
                                                {{ $staff->rejected_requests_count }} Rejected
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Most Recent Request --}}
                                    <td>
                                        @if ($latestReq)
                                            <div class="font-mono" style="font-size: 0.8125rem; color: var(--color-ink);">{{ $latestReq->created_at?->format('M j, Y · g:i A') }}</div>
                                            <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 2px;">
                                                {{ $latestReq->action_type_label }} · {{ $latestReq->created_at?->diffForHumans() }}
                                            </div>
                                        @else
                                            <span class="text-muted" style="font-size: 0.8125rem;">No requests submitted</span>
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="text-right" style="white-space: nowrap;" @click.stop>
                                        <button
                                            type="button"
                                            class="btn btn-primary btn-sm"
                                            @click="openStaffModal({{ $staff->id }})"
                                        >
                                            View Requests ({{ $staff->total_requests_count }})
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- MODAL 1: STAFF MEMBER'S REQUESTS MODAL (Opened from Staff List View)  --}}
    {{-- ===================================================================== --}}
    <template x-teleport="body">
        <div
            class="staff-action-review-overlay"
            x-show="staffModalOpen"
            x-cloak
            style="display: none;"
            @keydown.escape.window="if (!actionModalOpen) closeStaffModal()"
        >
            <div class="staff-action-review-backdrop" @click="closeStaffModal()"></div>

            <div
                class="card staff-action-review-content"
                style="max-width: 820px; width: 95vw; max-height: calc(100vh - 3rem);"
                x-show="staffModalOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-95"
            >
                <template x-if="selectedStaff">
                    <div style="display: flex; flex-direction: column; height: 100%; max-height: calc(100vh - 3rem); overflow: hidden;">
                        {{-- Modal Header --}}
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--color-border); flex-shrink: 0;">
                            <div style="display: flex; align-items: center; gap: var(--space-3);">
                                <span
                                    class="font-mono"
                                    style="width: 38px; height: 38px; border-radius: 50%; background: var(--color-accent-light); color: var(--color-accent); font-weight: 700; font-size: 0.8125rem; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid var(--color-border);"
                                    x-text="selectedStaff.initials"
                                ></span>
                                <div>
                                    <div style="font-weight: 700; font-size: 1.05rem; color: var(--color-ink);" x-text="selectedStaff.name + ' — Request Approvals'"></div>
                                    <div style="font-size: 0.75rem; color: var(--color-muted); font-weight: 400;">
                                        <span x-text="selectedStaff.job_title"></span> ·
                                        <span x-text="selectedStaff.email"></span>
                                        <template x-if="selectedStaff.employee_number && selectedStaff.employee_number !== 'N/A'">
                                            <span> · <span class="font-mono" x-text="'ID: ' + selectedStaff.employee_number"></span></span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="staff-action-review-close" @click="closeStaffModal()" aria-label="Close staff requests modal">&times;</button>
                        </div>

                        {{-- In-Modal Filter Bar --}}
                        <div style="padding: var(--space-3) var(--space-5); background: var(--color-surface-alt); border-bottom: 1px solid var(--color-border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--space-3); flex-shrink: 0;">
                            <span style="font-size: 0.8125rem; font-weight: 600; color: var(--color-muted);">
                                Filter Requests
                            </span>

                            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-left: auto;">
                                <div class="project-search-wrapper" style="width: 250px; flex-shrink: 0;">
                                    <label for="staff-modal-search" class="visually-hidden">Search this staff member's requests</label>
                                    <svg class="project-search-icon" width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 15px; height: 15px; flex-shrink: 0;">
                                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                    </svg>
                                    <input
                                        type="text"
                                        id="staff-modal-search"
                                        x-model="staffModalSearch"
                                        class="form-control form-control--compact project-search-input"
                                        style="width: 100%; height: 34px; font-size: 0.8125rem;"
                                        placeholder="Search project or task..."
                                    >
                                </div>

                                <div style="flex-shrink: 0;">
                                    <label for="staff-modal-status" class="visually-hidden">Filter by status</label>
                                    <select
                                        id="staff-modal-status"
                                        x-model="staffModalStatus"
                                        class="form-control form-control--compact project-status-select"
                                        style="width: auto; min-width: 150px; height: 34px; padding: 0 0.65rem; font-size: 0.8125rem;"
                                    >
                                        <option value="all" x-text="'All Statuses (' + selectedStaff.total_count + ')'"></option>
                                        <option value="pending" x-text="'Unexamined (' + selectedStaff.unexamined_count + ')'"></option>
                                        <option value="approved" x-text="'Approved (' + selectedStaff.approved_count + ')'"></option>
                                        <option value="rejected" x-text="'Rejected (' + selectedStaff.rejected_count + ')'"></option>
                                    </select>
                                </div>

                                <div style="flex-shrink: 0;">
                                    <label for="staff-modal-sort" class="visually-hidden">Sort by recency</label>
                                    <select
                                        id="staff-modal-sort"
                                        x-model="staffModalSort"
                                        class="form-control form-control--compact project-status-select"
                                        style="width: auto; min-width: 150px; height: 34px; padding: 0 0.65rem; font-size: 0.8125rem;"
                                    >
                                        <option value="newest">Most Recent First</option>
                                        <option value="oldest">Oldest First</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- Modal Body: Scrollable List of Requests --}}
                        <div class="card-body" style="padding: var(--space-4) var(--space-5); overflow-y: auto; flex: 1 1 auto;">
                            <template x-if="filteredStaffRequests().length === 0">
                                <div class="empty-state" style="padding: var(--space-8) var(--space-4);">
                                    <h3 class="empty-state__title">No requests match this filter</h3>
                                    <p class="empty-state__description" style="margin-bottom: 0;">
                                        Try selecting a different status from the dropdown or clearing the search filter above.
                                    </p>
                                </div>
                            </template>

                            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                                <template x-for="req in filteredStaffRequests()" :key="req.id">
                                    <div style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); background: var(--color-surface); display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-4);">
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="margin-bottom: 4px;">
                                                <template x-if="req.action_type === 'complete_subtask'">
                                                    <span class="user-menu-role" style="background: rgba(234, 179, 8, 0.16); color: #FACC15; border: 1px solid rgba(234, 179, 8, 0.35);" x-text="req.action_type_label"></span>
                                                </template>
                                                <template x-if="req.action_type === 'add_expense'">
                                                    <span class="user-menu-role" style="background: rgba(249, 115, 22, 0.16); color: #FB923C; border: 1px solid rgba(249, 115, 22, 0.35);" x-text="req.action_type_label"></span>
                                                </template>
                                                <template x-if="req.action_type !== 'complete_subtask' && req.action_type !== 'add_expense'">
                                                    <span class="user-menu-role" x-text="req.action_type_label"></span>
                                                </template>
                                            </div>

                                            <div style="font-weight: 700; font-size: 0.875rem; color: var(--color-ink); line-height: 1.4;" x-text="req.summary_title"></div>

                                            <div style="font-size: 0.8125rem; color: var(--color-ink); margin-top: 4px;" x-text="req.summary_subtitle"></div>

                                            <div style="display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap; font-size: 0.75rem; color: var(--color-muted); margin-top: 4px;">
                                                <span>Project: <strong style="color: var(--color-ink);" x-text="req.project_name"></strong></span>
                                                <span>·</span>
                                                <span class="font-mono" x-text="req.submitted_at"></span>
                                                <span x-text="'(' + req.submitted_diff + ')'"></span>
                                                <template x-if="req.reviewed_by_name">
                                                    <span>· Reviewed by <strong x-text="req.reviewed_by_name"></strong></span>
                                                </template>
                                            </div>
                                        </div>

                                        <div style="display: flex; flex-direction: column; align-items: flex-end; justify-content: space-between; gap: var(--space-2); flex-shrink: 0;">
                                            {{-- Status Badge Placed on Top of Review Button --}}
                                            <div>
                                                <template x-if="req.status === 'pending'">
                                                    <span class="status-pill status-pill--ongoing" style="font-size: 0.75rem;">Unexamined</span>
                                                </template>
                                                <template x-if="req.status === 'approved'">
                                                    <span class="status-pill status-pill--completed" style="font-size: 0.75rem;">Approved</span>
                                                </template>
                                                <template x-if="req.status === 'rejected'">
                                                    <span class="status-pill status-pill--delayed" style="font-size: 0.75rem;">Rejected</span>
                                                </template>
                                            </div>

                                            {{-- Action Buttons Below Status Badge --}}
                                            <div style="display: inline-flex; align-items: center; gap: 0.375rem;">
                                                <template x-if="req.status === 'pending'">
                                                    <button
                                                        type="button"
                                                        class="btn btn-primary btn-sm"
                                                        @click="quickProcessAction(req.id, 'approve')"
                                                        :disabled="processingActionId === req.id"
                                                    >
                                                        Approve
                                                    </button>
                                                </template>
                                                <template x-if="req.status === 'pending'">
                                                    <button
                                                        type="button"
                                                        class="btn btn-danger btn-sm"
                                                        @click="openActionModal(req.id)"
                                                        :disabled="processingActionId === req.id"
                                                    >
                                                        Reject
                                                    </button>
                                                </template>
                                                <button
                                                    type="button"
                                                    class="btn btn-secondary btn-sm"
                                                    @click="openActionModal(req.id)"
                                                >
                                                    Review
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Modal Footer --}}
                        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; padding: var(--space-3) var(--space-5); border-top: 1px solid var(--color-border); flex-shrink: 0;">
                            <span class="font-mono" style="font-size: 0.75rem; color: var(--color-muted);" x-text="filteredStaffRequests().length + ' request(s) shown'"></span>
                            <button type="button" class="btn btn-secondary btn-sm" @click="closeStaffModal()">Close</button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    {{-- ===================================================================== --}}
    {{-- MODAL 2: SINGLE REQUEST REVIEW & APPROVAL MODAL                       --}}
    {{-- ===================================================================== --}}
    <template x-teleport="body">
        <div
            class="staff-action-review-overlay"
            x-show="actionModalOpen"
            x-cloak
            style="display: none; z-index: 1000000;"
            @keydown.escape.window="closeActionModal()"
        >
            <div class="staff-action-review-backdrop" @click="closeActionModal()"></div>

            <div
                class="card staff-action-review-content"
                x-show="actionModalOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-95"
            >
                <template x-if="selectedAction">
                    <div style="display: flex; flex-direction: column; max-height: calc(100vh - 3rem); overflow: hidden;">
                        {{-- Modal Header (Identical to layouts/app.blade.php Review Staff Action Modal) --}}
                        <div class="card-header" style="position: relative; display: flex; justify-content: space-between; align-items: center; padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--color-border); flex-shrink: 0;">
                            <div style="display: flex; align-items: center; gap: var(--space-2);">
                                <svg width="20" height="20" style="width: 20px; height: 20px; color: var(--color-accent); flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                                    <path d="m9 14 2 2 4-4"></path>
                                </svg>
                                <span style="font-weight: 700; font-size: 1.05rem; color: var(--color-ink);">Review Staff Request</span>
                            </div>
                            <button type="button" class="staff-action-review-close" @click="closeActionModal()" aria-label="Close modal">&times;</button>
                        </div>

                        {{-- Modal Body --}}
                        <div class="card-body" style="padding: var(--space-5); overflow-y: auto; flex: 1 1 auto;">
                            {{-- Meta Details Card --}}
                            <div style="background: var(--color-surface-alt); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: var(--space-4); font-size: 0.8125rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                                    <span class="text-muted font-bold" style="font-size: 0.6875rem; text-transform: uppercase;">Submitted By</span>
                                    <span class="font-bold" style="color: var(--color-ink);" x-text="selectedAction.staff_name + (selectedAction.staff_employee_number && selectedAction.staff_employee_number !== 'N/A' ? ' (' + selectedAction.staff_employee_number + ')' : '')"></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                                    <span class="text-muted font-bold" style="font-size: 0.6875rem; text-transform: uppercase;">Project</span>
                                    <a :href="selectedAction.project_url" class="font-mono" style="color: var(--color-accent); font-weight: 600; text-decoration: none;" x-text="(selectedAction.project_code ? selectedAction.project_code + ' — ' : '') + selectedAction.project_name"></a>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                                    <span class="text-muted font-bold" style="font-size: 0.6875rem; text-transform: uppercase;">Submitted At</span>
                                    <span class="font-mono" style="color: var(--color-ink);" x-text="selectedAction.submitted_at + ' (' + selectedAction.submitted_diff + ')'"></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                                    <span class="text-muted font-bold" style="font-size: 0.6875rem; text-transform: uppercase;">Request Type</span>
                                    <div>
                                        <template x-if="selectedAction.action_type === 'complete_subtask'">
                                            <span class="user-menu-role" style="background: rgba(234, 179, 8, 0.16); color: #FACC15; border: 1px solid rgba(234, 179, 8, 0.35);" x-text="selectedAction.action_type_label"></span>
                                        </template>
                                        <template x-if="selectedAction.action_type === 'add_expense'">
                                            <span class="user-menu-role" style="background: rgba(249, 115, 22, 0.16); color: #FB923C; border: 1px solid rgba(249, 115, 22, 0.35);" x-text="selectedAction.action_type_label"></span>
                                        </template>
                                        <template x-if="selectedAction.action_type !== 'complete_subtask' && selectedAction.action_type !== 'add_expense'">
                                            <span class="user-menu-role" x-text="selectedAction.action_type_label"></span>
                                        </template>
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span class="text-muted font-bold" style="font-size: 0.6875rem; text-transform: uppercase;">Review Status</span>
                                    <template x-if="selectedAction.status === 'pending'">
                                        <span class="status-pill status-pill--ongoing" style="font-size: 0.75rem;">Unexamined</span>
                                    </template>
                                    <template x-if="selectedAction.status === 'approved'">
                                        <span class="status-pill status-pill--completed" style="font-size: 0.75rem;">Approved</span>
                                    </template>
                                    <template x-if="selectedAction.status === 'rejected'">
                                        <span class="status-pill status-pill--delayed" style="font-size: 0.75rem;">Rejected</span>
                                    </template>
                                </div>
                            </div>

                            {{-- Complete Subtask Payload --}}
                            <template x-if="selectedAction.action_type === 'complete_subtask'">
                                <div style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); margin-bottom: var(--space-4);">
                                    <h4 style="margin: 0 0 var(--space-2) 0; font-size: 0.875rem; color: var(--color-accent);">Timeline Task Completion Request</h4>
                                    <div style="font-size: 0.8125rem;">
                                        <div style="margin-bottom: var(--space-2);">
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">Major Task / Milestone</span>
                                            <strong style="color: var(--color-ink);" x-text="selectedAction.payload.task_name || 'Project Task'"></strong>
                                        </div>
                                        <div>
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">Subtask Ticked by Staff</span>
                                            <div style="display: flex; align-items: center; gap: var(--space-2); margin-top: 4px; margin-bottom: var(--space-3);">
                                                <span style="color: var(--color-success); font-weight: 700;">☑</span>
                                                <span class="font-bold" style="color: var(--color-ink);" x-text="selectedAction.payload.subtask_name || 'Subtask'"></span>
                                            </div>
                                        </div>
                                        <template x-if="selectedAction.payload.notes">
                                            <div style="background: var(--color-surface-alt); padding: var(--space-2) var(--space-3); border-radius: var(--radius-sm); border-left: 3px solid var(--color-accent); margin-bottom: var(--space-3);">
                                                <span class="text-muted font-bold" style="display: block; font-size: 0.6875rem; text-transform: uppercase;">Staff Remarks & Notes</span>
                                                <p style="margin: 4px 0 0 0; color: var(--color-ink); white-space: pre-wrap;" x-text="selectedAction.payload.notes"></p>
                                            </div>
                                        </template>
                                        <template x-if="selectedAction.files && selectedAction.files.length > 0">
                                            <div style="border-top: 1px dashed var(--color-border); padding-top: var(--space-2); margin-top: var(--space-2); display: flex; flex-direction: column; gap: var(--space-2);">
                                                <span class="text-muted font-bold" style="display: block; font-size: 0.6875rem; text-transform: uppercase;">
                                                    Completion Proof Attachment<span x-show="selectedAction.files.length > 1">s (<span x-text="selectedAction.files.length"></span>)</span>
                                                </span>
                                                <template x-for="(fItem, fIdx) in selectedAction.files" :key="fIdx">
                                                    <template x-if="fItem.exists && fItem.url">
                                                        <a :href="fItem.url" target="_blank" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; justify-content: space-between; gap: 6px; font-size: 0.75rem; width: 100%;">
                                                            <span style="display: inline-flex; align-items: center; gap: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0;">
                                                                <svg style="width: 14px; height: 14px; color: var(--color-accent); flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                                    <circle cx="12" cy="12" r="3"></circle>
                                                                </svg>
                                                                <span x-text="fItem.name" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"></span>
                                                            </span>
                                                            <span class="text-muted font-mono" style="font-size: 0.6875rem; flex-shrink: 0;" x-text="fItem.size"></span>
                                                        </a>
                                                    </template>
                                                </template>
                                            </div>
                                        </template>
                                        <template x-if="(!selectedAction.files || selectedAction.files.length === 0) && selectedAction.file_exists && selectedAction.file_url">
                                            <div style="border-top: 1px dashed var(--color-border); padding-top: var(--space-2); margin-top: var(--space-2);">
                                                <span class="text-muted font-bold" style="display: block; font-size: 0.6875rem; text-transform: uppercase; margin-bottom: 4px;">Completion Proof Attachment</span>
                                                <a :href="selectedAction.file_url" target="_blank" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem;">
                                                    <svg style="width: 14px; height: 14px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                        <circle cx="12" cy="12" r="3"></circle>
                                                    </svg>
                                                    <span x-text="'View Proof: ' + (selectedAction.payload.file_name || 'Attached File')"></span>
                                                </a>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Add Expense Payload --}}
                            <template x-if="selectedAction.action_type === 'add_expense'">
                                <div style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); margin-bottom: var(--space-4);">
                                    <h4 style="margin: 0 0 var(--space-2) 0; font-size: 0.875rem; color: var(--color-accent);">Expense Entry Details</h4>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2); font-size: 0.8125rem;">
                                        <div>
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">Description</span>
                                            <strong style="color: var(--color-ink);" x-text="selectedAction.payload.description || 'N/A'"></strong>
                                        </div>
                                        <div>
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">Amount</span>
                                            <strong class="font-mono" style="color: var(--color-ink);" x-text="'₱' + Number(selectedAction.payload.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></strong>
                                        </div>
                                        <div>
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">Category</span>
                                            <span style="text-transform: capitalize; color: var(--color-ink);" x-text="selectedAction.payload.category || 'General'"></span>
                                        </div>
                                        <div>
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">Date</span>
                                            <span class="font-mono" style="color: var(--color-ink);" x-text="selectedAction.payload.date || '—'"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            {{-- Add File Payload --}}
                            <template x-if="selectedAction.action_type === 'add_file'">
                                <div style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); margin-bottom: var(--space-4);">
                                    <h4 style="margin: 0 0 var(--space-2) 0; font-size: 0.875rem; color: var(--color-accent);">Uploaded File Details</h4>
                                    <div style="display: flex; justify-content: space-between; align-items: center; gap: var(--space-2); font-size: 0.8125rem;">
                                        <div>
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">File Name</span>
                                            <strong style="color: var(--color-ink);" x-text="selectedAction.payload.file_name || 'Uploaded File'"></strong>
                                        </div>
                                        {{-- View / Download hidden if request was rejected (file removed upon rejection) --}}
                                        <template x-if="selectedAction.payload.file_path && selectedAction.status !== 'rejected'">
                                            <a :href="'/storage/' + selectedAction.payload.file_path" target="_blank" class="btn btn-secondary btn-sm">
                                                View / Download
                                            </a>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Reviewer Notes Input (if pending) or Reviewer Audit Summary --}}
                            <template x-if="selectedAction.status === 'pending'">
                                <div>
                                    <div class="form-group" style="margin-bottom: var(--space-3);">
                                        <label for="reviewer-notes-input" class="form-label" style="font-size: 0.8125rem;">Reviewer Notes (Optional)</label>
                                        <input
                                            type="text"
                                            id="reviewer-notes-input"
                                            x-model="reviewNotes"
                                            class="form-control form-control--compact"
                                            placeholder="Add an optional note or reason for the staff member..."
                                        >
                                    </div>

                                    <div style="background: var(--color-accent-light); border-left: 3px solid var(--color-accent); padding: var(--space-3); border-radius: 0 var(--radius-md) var(--radius-md) 0; font-size: 0.8125rem;">
                                        <p style="margin: 0; font-weight: 500; color: var(--color-ink);">
                                            Approving will immediately apply this <strong x-text="selectedAction.action_type_label"></strong> to the project records.
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <template x-if="selectedAction.status !== 'pending'">
                                <div style="background: var(--color-surface-alt); border-left: 3px solid var(--color-accent); padding: var(--space-3); border-radius: 0 var(--radius-md) var(--radius-md) 0; font-size: 0.8125rem;">
                                    <p style="margin: 0; font-weight: 600; color: var(--color-ink);" x-text="'Status: ' + selectedAction.status_label"></p>
                                    <p class="text-muted" style="margin: 4px 0 0 0; font-size: 0.75rem;" x-text="selectedAction.reviewed_by_name ? ('Reviewed by ' + selectedAction.reviewed_by_name + (selectedAction.reviewed_at ? ' on ' + selectedAction.reviewed_at : '')) : 'This request has already been processed.'"></p>
                                    <template x-if="selectedAction.admin_notes">
                                        <p style="margin: 6px 0 0 0; font-size: 0.8125rem; color: var(--color-ink);">
                                            <strong>Notes:</strong> <span x-text="selectedAction.admin_notes"></span>
                                        </p>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Modal Footer --}}
                        <div class="card-footer" style="display: flex; justify-content: flex-end; gap: var(--space-2); padding: var(--space-4) var(--space-5); border-top: 1px solid var(--color-border); flex-shrink: 0;">
                            <button
                                type="button"
                                class="btn btn-secondary btn-sm"
                                @click="closeActionModal()"
                                :disabled="processingActionId === selectedAction.id"
                                x-text="selectedAction.status === 'pending' ? 'Cancel' : 'Close'"
                            ></button>

                            <template x-if="selectedAction.status === 'pending'">
                                <div style="display: inline-flex; gap: var(--space-2);">
                                    <button
                                        type="button"
                                        class="btn btn-danger btn-sm"
                                        @click="submitDecision(selectedAction.id, 'reject')"
                                        :disabled="processingActionId === selectedAction.id"
                                    >
                                        <span x-show="processingActionId !== selectedAction.id">Reject Request</span>
                                        <span x-show="processingActionId === selectedAction.id">Processing...</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm"
                                        @click="submitDecision(selectedAction.id, 'approve')"
                                        :disabled="processingActionId === selectedAction.id"
                                    >
                                        <span x-show="processingActionId !== selectedAction.id">Approve Request</span>
                                        <span x-show="processingActionId === selectedAction.id">Processing...</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>

<script>
function staffRequestsModule() {
    return {
        viewMode: @json($view ?? 'list'),
        actionsMap: @json($actionsMap ?? []),
        staffRequestsMap: @json($staffRequestsMap ?? []),

        // Staff Modal State
        staffModalOpen: false,
        selectedStaff: null,
        staffModalStatus: 'all',
        staffModalSearch: '',
        staffModalSort: 'newest',

        // Single Action Review Modal State
        actionModalOpen: false,
        selectedAction: null,
        reviewNotes: '',
        processingActionId: null,

        switchView(mode) {
            this.viewMode = mode;
            const url = new URL(window.location.href);
            url.searchParams.set('view', mode);
            window.history.replaceState({}, '', url.toString());
        },

        setStatusFilter(statusValue) {
            if (this.$refs.statusSelect) {
                this.$refs.statusSelect.value = statusValue;
                this.$refs.filterForm.submit();
            }
        },

        openStaffModal(staffId) {
            const staffData = this.staffRequestsMap[staffId];
            if (!staffData) return;
            this.selectedStaff = staffData;
            this.staffModalStatus = 'all';
            this.staffModalSearch = '';
            this.staffModalSort = 'newest';
            this.staffModalOpen = true;
        },

        closeStaffModal() {
            this.staffModalOpen = false;
        },

        filteredStaffRequests() {
            if (!this.selectedStaff || !Array.isArray(this.selectedStaff.requests)) {
                return [];
            }

            let list = [...this.selectedStaff.requests];

            if (this.staffModalStatus !== 'all') {
                list = list.filter(item => item.status === this.staffModalStatus);
            }

            const q = (this.staffModalSearch || '').trim().toLowerCase();
            if (q !== '') {
                list = list.filter(item => {
                    return (item.summary_title || '').toLowerCase().includes(q)
                        || (item.summary_subtitle || '').toLowerCase().includes(q)
                        || (item.project_name || '').toLowerCase().includes(q)
                        || (item.action_type_label || '').toLowerCase().includes(q)
                        || (item.status_label || '').toLowerCase().includes(q);
                });
            }

            list.sort((a, b) => {
                return this.staffModalSort === 'oldest'
                    ? (a.submitted_timestamp - b.submitted_timestamp)
                    : (b.submitted_timestamp - a.submitted_timestamp);
            });

            return list;
        },

        openActionModal(actionId) {
            const actionData = this.actionsMap[actionId];
            if (actionData) {
                this.selectedAction = actionData;
                this.reviewNotes = '';
                this.actionModalOpen = true;
                return;
            }

            fetch(`/pending-actions/${actionId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(r => r.json())
            .then(data => {
                this.actionsMap[actionId] = data;
                this.selectedAction = data;
                this.reviewNotes = '';
                this.actionModalOpen = true;
            });
        },

        closeActionModal() {
            this.actionModalOpen = false;
        },

        quickProcessAction(actionId, decision) {
            this.reviewNotes = '';
            this.submitDecision(actionId, decision);
        },

        submitDecision(actionId, decision) {
            if (this.processingActionId) return;
            this.processingActionId = actionId;

            fetch(`/pending-actions/${actionId}/${decision}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ notes: this.reviewNotes })
            })
            .then(r => r.json())
            .then(res => {
                this.processingActionId = null;
                if (res.success) {
                    this.actionModalOpen = false;
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: {
                            type: 'success',
                            title: decision === 'approve' ? 'Request Approved' : 'Request Rejected',
                            message: res.message,
                            duration: 4000
                        }
                    }));
                    setTimeout(() => window.location.reload(), 450);
                } else {
                    alert(res.message || 'Could not process staff request.');
                }
            })
            .catch(() => {
                this.processingActionId = null;
                alert('An error occurred while processing the staff request.');
            });
        }
    };
}
</script>
@endsection
