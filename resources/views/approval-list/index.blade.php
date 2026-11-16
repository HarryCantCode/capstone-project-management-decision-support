@extends('layouts.app')

@section('title', 'Approval List')
@section('meta-description', 'View and track requests submitted to Admin and Manager for approval, inspect details, and send reminder pings.')

@section('content')
<div x-data="approvalListModule()">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h1>Approval List</h1>
            <p class="page-header__description">View and track timeline task completions, expense entries, and file uploads you submitted for review.</p>
        </div>
    </div>

    {{-- Summary KPI Cards (4-Column Grid matching Dashboard, Costing & Staff Requests) --}}
    <div class="costing-kpi-grid dashboard-stats-grid">
        {{-- Card 1: Total Requests --}}
        <div class="costing-kpi-card costing-kpi-card--contract costing-kpi-card--interactive" @click="setActiveTab('my_requests'); setStatusFilter('all')" role="button" tabindex="0" @keydown.enter="setStatusFilter('all')">
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
                    <p class="costing-kpi-card__description">All requests submitted by you across active projects.</p>
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
                    My Requests
                </span>
                <span class="costing-kpi-card__pill costing-kpi-card__pill--neutral">
                    {{ $totalCount }} {{ Str::plural('Entry', $totalCount) }}
                </span>
            </div>
        </div>

        {{-- Card 2: Assigned Tasks (Replaces Unexamined - Same Layout & Glowing Effect) --}}
        <div class="costing-kpi-card costing-kpi-card--assigned costing-kpi-card--interactive" @click="setActiveTab('assigned_tasks')" role="button" tabindex="0" @keydown.enter="setActiveTab('assigned_tasks')">
            <svg class="costing-kpi-card__watermark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="9 11 12 14 22 4"></polyline>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
            </svg>
            <div>
                <div class="costing-kpi-card__header">
                    <span class="costing-kpi-card__label">Assigned Tasks</span>
                    <span class="costing-kpi-card__icon-badge costing-kpi-card__icon-badge--assigned" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 11 12 14 22 4"></polyline>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                        </svg>
                    </span>
                </div>
                <div class="costing-kpi-card__body">
                    <div class="costing-kpi-card__value numeric" style="color: #6366F1;">{{ number_format($assignedTasksCount) }}</div>
                    <p class="costing-kpi-card__description">Timeline tasks assigned to you across active projects.</p>
                </div>
            </div>
            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </span>
                    My Assignments
                </span>
                <span class="costing-kpi-card__pill {{ $assignedTasksCount > 0 ? 'costing-kpi-card__pill--warning' : 'costing-kpi-card__pill--neutral' }}">
                    {{ $assignedTasksCount > 0 ? $assignedTasksCount . ' Active' : 'All Clear' }}
                </span>
            </div>
        </div>

        {{-- Card 3: Approved Requests --}}
        <div class="costing-kpi-card costing-kpi-card--completed costing-kpi-card--interactive" @click="setActiveTab('my_requests'); setStatusFilter('approved')" role="button" tabindex="0" @keydown.enter="setStatusFilter('approved')">
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
                    <p class="costing-kpi-card__description">Verified requests successfully applied to project records.</p>
                </div>
            </div>
            <div class="costing-kpi-card__footer">
                <span class="costing-kpi-card__tag">
                    <span class="costing-kpi-card__tag-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </span>
                    Accepted
                </span>
                <span class="costing-kpi-card__pill costing-kpi-card__pill--success">
                    {{ $totalCount > 0 ? round(($approvedCount / $totalCount) * 100) : 0 }}% Rate
                </span>
            </div>
        </div>

        {{-- Card 4: Rejected Requests --}}
        <div class="costing-kpi-card costing-kpi-card--remaining-negative costing-kpi-card--interactive" @click="setActiveTab('my_requests'); setStatusFilter('rejected')" role="button" tabindex="0" @keydown.enter="setStatusFilter('rejected')">
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
                    <p class="costing-kpi-card__description">Requests declined with feedback or reviewer remarks.</p>
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

    {{-- ─── Tab View Switcher (My Submitted Requests vs. Assigned Tasks) ──────── --}}
    <div style="display: flex; justify-content: flex-start; align-items: center; margin-bottom: var(--space-4);">
        <div class="switch-pill-container" role="tablist" aria-label="Approval List Views">
            <button 
                type="button" 
                role="tab"
                :aria-selected="(activeTab === 'my_requests').toString()"
                class="switch-pill-btn"
                :class="{ 'switch-pill-btn--active': activeTab === 'my_requests' }"
                @click="setActiveTab('my_requests')"
                style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.8125rem;"
            >
                <svg style="width: 15px; height: 15px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                </svg>
                <span>My Submitted Requests</span>
                <span class="badge font-mono" style="font-size: 0.6875rem; padding: 2px 7px; border-radius: 9999px;" :style="activeTab === 'my_requests' ? 'background: rgba(255,255,255,0.25); color: #fff;' : 'background: rgba(44, 95, 124, 0.12); color: var(--color-accent);'">
                    {{ $totalCount }}
                </span>
            </button>
            <button 
                type="button" 
                role="tab"
                :aria-selected="(activeTab === 'assigned_tasks').toString()"
                class="switch-pill-btn"
                :class="{ 'switch-pill-btn--active': activeTab === 'assigned_tasks' }"
                @click="setActiveTab('assigned_tasks')"
                style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.8125rem;"
            >
                <svg style="width: 15px; height: 15px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 11 12 14 22 4"></polyline>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                </svg>
                <span>Assigned Tasks</span>
                <span class="badge font-mono" style="font-size: 0.6875rem; padding: 2px 7px; border-radius: 9999px;" :style="activeTab === 'assigned_tasks' ? 'background: rgba(255,255,255,0.25); color: #fff;' : 'background: rgba(99, 102, 241, 0.15); color: #6366F1;'">
                    {{ $assignedTasksCount }}
                </span>
            </button>
        </div>
    </div>

    {{-- ─── TAB 1: My Submitted Requests ───────────────────────────────────────── --}}
    <div x-show="activeTab === 'my_requests'">

    {{-- Main Register Card --}}
    <div class="card card-glass">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3); padding: var(--space-3) var(--space-5);">
            {{-- Left: Section Title --}}
            <div style="display: flex; align-items: center; gap: var(--space-2); flex-shrink: 0;">
                <span style="font-weight: 700; font-size: 0.95rem; color: var(--color-ink); white-space: nowrap;">My Submitted Requests</span>
            </div>

            {{-- Right: Horizontally Aligned Searchbar & Dropdowns --}}
            <form
                method="GET"
                action="{{ route('approval-list.index') }}"
                x-ref="filterForm"
                role="search"
                style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-left: auto;"
            >
                {{-- Dedicated Searchbar --}}
                <div class="project-search-wrapper" style="width: 230px; flex-shrink: 0;">
                    <label for="approval-search" class="visually-hidden">Search submitted requests</label>
                    <svg class="project-search-icon" width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 15px; height: 15px; flex-shrink: 0;">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                    <input
                        type="text"
                        id="approval-search"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Search project, task, expense..."
                        class="form-control form-control--compact project-search-input"
                        style="width: 100%; height: 34px; font-size: 0.8125rem;"
                        title="Search by project, task title, expense description, or file name"
                        autocomplete="off"
                    >
                    @if (!empty($search))
                        <a href="{{ route('approval-list.index', array_filter(['status' => $status, 'recency' => $recency, 'action_type' => $actionType])) }}" class="project-search-clear" title="Clear search" aria-label="Clear search">
                            <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="width: 12px; height: 12px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4l8 8M12 4l-8 8" />
                            </svg>
                        </a>
                    @endif
                </div>

                {{-- Status Select --}}
                <div style="flex-shrink: 0;">
                    <label for="approval-status" class="visually-hidden">Filter by status</label>
                    <select
                        id="approval-status"
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
                    <label for="approval-recency" class="visually-hidden">Filter by recency</label>
                    <select
                        id="approval-recency"
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
                    <label for="approval-action-type" class="visually-hidden">Filter by request type</label>
                    <select
                        id="approval-action-type"
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
                    <a href="{{ route('approval-list.index') }}" class="btn btn-secondary btn-sm project-filter-reset" style="height: 34px; padding: 0 0.75rem; font-size: 0.8125rem; flex-shrink: 0;" title="Clear all filters and search">
                        <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" class="project-filter-reset__icon" aria-hidden="true" style="width: 12px; height: 12px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4l8 8M12 4l-8 8" />
                        </svg>
                        <span>Clear</span>
                    </a>
                @endif
            </form>
        </div>

        {{-- Table List View --}}
        <div>
            @if ($actions->isEmpty())
                <div class="card-body">
                    <div class="empty-state">
                        <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <h2 class="empty-state__title">No requests found</h2>
                        <p class="empty-state__description">
                            @if (!empty($search) || ($status ?? 'all') !== 'all' || ($recency ?? 'newest') !== 'newest' || ($actionType ?? 'all') !== 'all')
                                No submitted requests matched your current search or filter criteria.
                            @else
                                Any timeline tasks you complete, expenses you record, or project files you upload will appear here for review tracking.
                            @endif
                        </p>
                        @if (!empty($search) || ($status ?? 'all') !== 'all' || ($recency ?? 'newest') !== 'newest' || ($actionType ?? 'all') !== 'all')
                            <a href="{{ route('approval-list.index') }}" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a>
                        @endif
                    </div>
                </div>
            @else
                <div class="table-wrapper" style="border: none; border-radius: 0; box-shadow: none;">
                    <table class="data-table">
                        <caption class="visually-hidden">Staff approval requests register</caption>
                        <thead>
                            <tr>
                                <th scope="col">Request Details</th>
                                <th scope="col">Project</th>
                                <th scope="col">Submitted</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($actions as $action)
                                <tr style="cursor: pointer;" @click="openActionModal({{ $action->id }})">
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
                                        <div style="font-size: 0.75rem; color: var(--color-muted);">{{ $action->created_at?->diffForHumans() }}</div>
                                    </td>

                                    {{-- Status --}}
                                    <td>
                                        @if ($action->status === 'pending')
                                            <span class="status-pill status-pill--ongoing" title="Awaiting Admin or Manager verification">
                                                Unexamined
                                            </span>
                                        @elseif ($action->status === 'approved')
                                            <span class="status-pill status-pill--completed" title="Approved by {{ $action->reviewer?->name ?? 'Admin/Manager' }}">
                                                Approved
                                            </span>
                                            @if ($action->reviewer)
                                                <div style="font-size: 0.6875rem; color: var(--color-muted); margin-top: 2px;">
                                                    by {{ $action->reviewer->name }}
                                                </div>
                                            @endif
                                        @elseif ($action->status === 'rejected')
                                            <span class="status-pill status-pill--delayed" title="Rejected by {{ $action->reviewer?->name ?? 'Admin/Manager' }}">
                                                Rejected
                                            </span>
                                            @if ($action->reviewer)
                                                <div style="font-size: 0.6875rem; color: var(--color-muted); margin-top: 2px;">
                                                    by {{ $action->reviewer->name }}
                                                </div>
                                            @endif
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="text-right" @click.stop>
                                        <div style="display: inline-flex; align-items: center; justify-content: flex-end; gap: 0.5rem;">
                                            {{-- Ping / Reminder Button with Live Countdown (15-min cooldown, Pending only) --}}
                                            @if ($action->status === 'pending')
                                                <template x-if="cooldowns[{{ $action->id }}] > 0">
                                                    <button
                                                        type="button"
                                                        class="btn btn-secondary btn-sm"
                                                        disabled
                                                        style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; height: 30px; opacity: 0.8; cursor: not-allowed; padding: 0 0.65rem;"
                                                        title="Reminder cooldown active. You can ping again after the timer expires."
                                                    >
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px; animation: pulse 2s infinite;" aria-hidden="true">
                                                            <circle cx="12" cy="12" r="10"></circle>
                                                            <polyline points="12 6 12 12 16 14"></polyline>
                                                        </svg>
                                                        <span>Ping in <strong class="font-mono" x-text="formatCooldown(cooldowns[{{ $action->id }}])"></strong></span>
                                                    </button>
                                                </template>

                                                <template x-if="!cooldowns[{{ $action->id }}] || cooldowns[{{ $action->id }}] <= 0">
                                                    <button
                                                        type="button"
                                                        class="btn btn-secondary btn-sm"
                                                        @click="pingRequest({{ $action->id }})"
                                                        :disabled="pingingId === {{ $action->id }}"
                                                        style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; height: 30px; border-color: rgba(56, 189, 248, 0.4); color: var(--color-accent); padding: 0 0.65rem;"
                                                        title="Send a reminder notification to Admin and Manager"
                                                    >
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 12px; height: 12px;" aria-hidden="true">
                                                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                                                        </svg>
                                                        <span x-text="pingingId === {{ $action->id }} ? 'Sending...' : 'Ping'">Ping</span>
                                                    </button>
                                                </template>
                                            @endif

                                            {{-- Details Button --}}
                                            <button
                                                type="button"
                                                class="btn btn-secondary btn-sm"
                                                @click="openActionModal({{ $action->id }})"
                                                style="height: 30px; font-size: 0.75rem; padding: 0 0.65rem;"
                                            >
                                                Details
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Links --}}
                @if ($actions->hasPages())
                    <div class="card-footer pagination-wrapper">
                        {{ $actions->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            @endif
        </div>
    </div>
    </div>{{-- End of Tab 1: My Submitted Requests --}}

    {{-- ─── TAB 2: Assigned Tasks List ────────────────────────────────────────── --}}
    <div x-show="activeTab === 'assigned_tasks'" x-cloak>
        <div class="card card-glass">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3); padding: var(--space-3) var(--space-5);">
                {{-- Left: Section Title & Count Badge --}}
                <div style="display: flex; align-items: center; gap: var(--space-2); flex-shrink: 0;">
                    <span style="font-weight: 700; font-size: 0.95rem; color: var(--color-ink); white-space: nowrap;">Assigned Timeline Tasks</span>
                    <span class="badge font-mono" style="background: rgba(99, 102, 241, 0.12); color: #6366F1; border: 1px solid rgba(99, 102, 241, 0.25); font-size: 0.6875rem;">
                        {{ $assignedTasks->count() }} {{ Str::plural('task', $assignedTasks->count()) }}
                    </span>
                </div>

                {{-- Right: Filter Controls --}}
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-left: auto;">
                    {{-- Search Input --}}
                    <div class="project-search-wrapper" style="width: 240px; flex-shrink: 0;">
                        <label for="assigned-tasks-search" class="visually-hidden">Search assigned tasks</label>
                        <svg class="project-search-icon" width="15" height="15" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 15px; height: 15px; flex-shrink: 0;">
                            <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                        </svg>
                        <input
                            type="text"
                            id="assigned-tasks-search"
                            x-model="assignedTasksSearch"
                            placeholder="Filter task, project..."
                            class="form-control form-control--compact project-search-input"
                            style="width: 100%; height: 34px; font-size: 0.8125rem;"
                            autocomplete="off"
                        >
                    </div>

                    {{-- Status Filter --}}
                    <div style="flex-shrink: 0;">
                        <label for="assigned-tasks-status" class="visually-hidden">Filter by status</label>
                        <select
                            id="assigned-tasks-status"
                            x-model="assignedTasksStatusFilter"
                            class="form-control form-control--compact project-status-select"
                            style="width: auto; min-width: 148px; height: 34px; padding: 0 0.65rem; font-size: 0.8125rem;"
                        >
                            <option value="all">All Statuses ({{ $assignedTasks->count() }})</option>
                            <option value="to_complete">To Complete ({{ $assignedTasksCount }})</option>
                            <option value="in_review">In Review ({{ count($pendingSubtaskIdsForStaff) }})</option>
                            <option value="completed">Completed ({{ $assignedTasks->where('is_completed', true)->count() }})</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-body" style="padding: 0;">
                @if ($assignedTasks->isEmpty())
                    <div class="empty-state" style="padding: var(--space-8); text-align: center;">
                        <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <h2 class="empty-state__title">No assigned tasks</h2>
                        <p class="empty-state__description">
                            You currently do not have any timeline tasks assigned to you by your manager.
                        </p>
                    </div>
                @else
                    <div class="table-wrapper" style="border: none; border-radius: 0; box-shadow: none;">
                        <table class="data-table">
                            <caption class="visually-hidden">Assigned timeline tasks list</caption>
                            <thead>
                                <tr>
                                    <th scope="col" style="min-width: 280px;">Task Details</th>
                                    <th scope="col" style="min-width: 200px;">Project</th>
                                    <th scope="col" style="min-width: 140px;">Target Date</th>
                                    <th scope="col" style="min-width: 120px;">Status</th>
                                    <th scope="col" class="text-right" style="min-width: 140px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($assignedTasks as $task)
                                    @php
                                        $isPendingReview = in_array((int) $task->id, $pendingSubtaskIdsForStaff, true);
                                        $project = $task->task?->project;
                                        $majorTask = $task->task;
                                        $taskStatusCategory = $task->is_completed ? 'completed' : ($isPendingReview ? 'in_review' : 'to_complete');
                                    @endphp
                                    <tr 
                                        x-show="(assignedTasksStatusFilter === 'all' || assignedTasksStatusFilter === '{{ $taskStatusCategory }}') && (!assignedTasksSearch || '{{ strtolower(addslashes($task->title . ' ' . ($project?->name ?? '') . ' ' . ($project?->project_code ?? '') . ' ' . ($majorTask?->task_name ?? ''))) }}'.includes(assignedTasksSearch.toLowerCase()))"
                                    >
                                        {{-- Task Details --}}
                                        <td>
                                            <div style="display: flex; align-items: center; gap: var(--space-3);">
                                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: var(--radius-md); background: rgba(99, 102, 241, 0.12); color: #6366F1; border: 1px solid rgba(99, 102, 241, 0.25); flex-shrink: 0;" title="Assigned Subtask">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="9 11 12 14 22 4"></polyline>
                                                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                                                    </svg>
                                                </span>
                                                <div style="flex: 1; min-width: 0;">
                                                    <div style="font-weight: 600; color: var(--color-ink); line-height: 1.4; font-size: 0.875rem; {{ $task->is_completed ? 'text-decoration: line-through; opacity: 0.65;' : '' }}">
                                                        {{ $task->title }}
                                                    </div>
                                                    @if ($majorTask)
                                                        <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 2px;">
                                                            Major Task: <strong style="color: var(--color-ink); font-weight: 600;">{{ $majorTask->task_name }}</strong>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        {{-- Project --}}
                                        <td>
                                            @if ($project)
                                                <a href="{{ route('projects.show', $project) }}" class="table-link" style="display: inline-flex; flex-direction: column; text-decoration: none;">
                                                    @if ($project->project_code)
                                                        <span class="font-mono" style="font-size: 0.75rem; color: var(--color-accent); font-weight: 600;">{{ $project->project_code }}</span>
                                                    @endif
                                                    <span class="project-name" style="font-size: 0.8125rem; font-weight: 600; color: var(--color-ink);">{{ $project->name }}</span>
                                                </a>
                                            @else
                                                <span class="text-muted" style="font-size: 0.8125rem;">N/A</span>
                                            @endif
                                        </td>

                                        {{-- Target Date --}}
                                        <td>
                                            @if ($task->due_date)
                                                @php
                                                    $isOverdue = !$task->is_completed && $task->due_date->isPast();
                                                @endphp
                                                <div style="display: inline-flex; align-items: center; gap: 6px;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-muted); flex-shrink: 0;">
                                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                                    </svg>
                                                    <span class="font-mono" style="font-size: 0.8125rem; color: var(--color-ink);">
                                                        {{ $task->due_date->format('M j, Y') }}
                                                    </span>
                                                    @if ($isOverdue)
                                                        <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: var(--color-danger); font-size: 0.6875rem; padding: 1px 6px;">Overdue</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-muted font-mono" style="font-size: 0.75rem;">Scheduled Week</span>
                                            @endif
                                        </td>

                                        {{-- Status --}}
                                        <td>
                                            @if ($task->is_completed)
                                                <span class="status-pill status-pill--completed">Completed</span>
                                            @elseif ($isPendingReview)
                                                <span class="status-pill status-pill--ongoing" title="Submitted — Awaiting review">Pending Review</span>
                                            @else
                                                <span class="status-pill status-pill--pending">To Complete</span>
                                            @endif
                                        </td>

                                        {{-- Actions --}}
                                        <td class="text-right">
                                            <div style="display: inline-flex; align-items: center; justify-content: flex-end; gap: 0.5rem;">
                                                @if ($task->is_completed)
                                                    <span class="badge font-mono" style="background: var(--color-surface-alt); border: 1px solid var(--color-border); color: var(--color-muted); font-size: 0.75rem; padding: 4px 10px; border-radius: var(--radius-sm);">Done</span>
                                                @elseif ($isPendingReview)
                                                    <button 
                                                        type="button" 
                                                        class="btn btn-secondary btn-sm"
                                                        disabled
                                                        style="display: inline-flex; align-items: center; gap: 5px; height: 32px; font-size: 0.75rem; opacity: 0.85; cursor: not-allowed; padding: 0 0.75rem;"
                                                        title="Awaiting Admin or Manager approval"
                                                    >
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <circle cx="12" cy="12" r="10"></circle>
                                                            <polyline points="12 6 12 12 16 14"></polyline>
                                                        </svg>
                                                        <span>In Review</span>
                                                    </button>
                                                @elseif ($task->is_locked_by_previous)
                                                    <span 
                                                        class="d-inline-flex" 
                                                        tabindex="0"
                                                        title="You cannot mark this as done when a previous task before your assigned task is not yet done"
                                                        style="cursor: not-allowed;"
                                                    >
                                                        <button 
                                                            type="button" 
                                                            class="btn btn-secondary btn-sm"
                                                            disabled
                                                            style="display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 0.85rem; font-size: 0.75rem; font-weight: 600; opacity: 0.55; cursor: not-allowed; filter: grayscale(100%); pointer-events: none;"
                                                            title="You cannot mark this as done when a previous task before your assigned task is not yet done"
                                                        >
                                                            <svg style="width: 13px; height: 13px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                                <polyline points="20 6 9 17 4 12"></polyline>
                                                            </svg>
                                                            <span>Mark as done</span>
                                                        </button>
                                                    </span>
                                                @else
                                                    <button 
                                                        type="button" 
                                                        class="btn btn-primary btn-sm"
                                                        style="display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 0.85rem; font-size: 0.75rem; font-weight: 600;"
                                                        @click="openSubmitApprovalModal({
                                                            projectId: {{ $project?->id ?? 'null' }},
                                                            taskId: {{ $majorTask?->id ?? 'null' }},
                                                            subtaskId: {{ $task->id }},
                                                            title: @js($task->title),
                                                            projectName: @js($project?->name ?? 'Project'),
                                                            projectCode: @js($project?->project_code ?? ''),
                                                            majorTask: @js($majorTask?->task_name ?? 'Major Task'),
                                                            dueDate: @js($task->due_date ? $task->due_date->format('M j, Y') : 'Scheduled Week')
                                                        })"
                                                    >
                                                        <svg style="width: 13px; height: 13px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <polyline points="20 6 9 17 4 12"></polyline>
                                                        </svg>
                                                        <span>Mark as done</span>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>{{-- End of Tab 2: Assigned Tasks --}}

    {{-- ─── MODAL: Submit Approval Request for Task Completion ─────────────── --}}
    <div class="reassign-modal-overlay" style="display: none; z-index: 1000000;" x-show="submitApprovalModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="if(!submitApprovalSubmitting) submitApprovalModalOpen = false"></div>
        <div class="reassign-modal-content card" x-show="submitApprovalModalOpen" x-transition style="max-width: 540px;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(16, 185, 129, 0.15); display: flex; align-items: center; justify-content: center; color: var(--color-success);">
                        <svg style="width: 18px; height: 18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <div>
                        <span style="font-weight: 700; font-size: 1rem; color: var(--color-ink);">Submit Approval Request</span>
                        <p class="text-muted" style="font-size: 0.75rem; margin-bottom: 0;">Submit task completion for Admin or Manager review.</p>
                    </div>
                </div>
                <button type="button" class="flash-close" @click="if(!submitApprovalSubmitting) submitApprovalModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <div class="card-body" style="padding: var(--space-4);">
                {{-- Task Target Summary Card --}}
                <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3); margin-bottom: var(--space-4);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span class="badge font-mono" style="background: rgba(56, 189, 248, 0.15); color: var(--color-accent); font-size: 0.6875rem;" x-text="submitApprovalTask.projectCode"></span>
                        <span class="text-muted font-mono" style="font-size: 0.75rem;" x-text="'Target: ' + submitApprovalTask.dueDate"></span>
                    </div>
                    <div style="font-size: 0.9375rem; font-weight: 700; color: var(--color-ink);" x-text="submitApprovalTask.title"></div>
                    <div style="font-size: 0.75rem; color: var(--color-muted); margin-top: 2px;">
                        Project: <span style="color: var(--color-ink);" x-text="submitApprovalTask.projectName"></span> &middot; Major Task: <span style="color: var(--color-ink);" x-text="submitApprovalTask.majorTask"></span>
                    </div>
                </div>

                {{-- Remarks / Notes --}}
                <div class="form-group form-group-premium" style="margin-bottom: var(--space-4);">
                    <label for="submit_approval_notes" class="form-label" style="font-size: 0.8125rem;">
                        Completion Remarks / Notes <span class="text-muted">(Optional)</span>
                    </label>
                    <textarea
                        id="submit_approval_notes"
                        class="form-control"
                        rows="3"
                        x-model="submitApprovalNotes"
                        placeholder="Describe completed work, milestone details, or notes for the reviewer..."
                        style="font-size: 0.8125rem;"
                    ></textarea>
                </div>

                {{-- Proof of Completion Attachments --}}
                <div class="form-group form-group-premium" style="margin-bottom: var(--space-2);">
                    <label class="form-label" style="font-size: 0.8125rem;">
                        Proof of Completion Attachments <span class="text-muted">(Photos, documents - Optional)</span>
                    </label>
                    <div 
                        style="border: 2px dashed var(--color-border); border-radius: var(--radius-md); padding: var(--space-4); text-align: center; background: var(--color-surface); cursor: pointer;"
                        @click="$refs.submitApprovalFileInput.click()"
                    >
                        <input 
                            type="file" 
                            x-ref="submitApprovalFileInput" 
                            multiple 
                            style="display: none;" 
                            @change="handleSubmitApprovalFiles($event)"
                        >
                        <svg style="width: 24px; height: 24px; color: var(--color-muted); margin: 0 auto 6px auto; display: block;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        <div style="font-size: 0.8125rem; font-weight: 600; color: var(--color-accent);">Click to select proof files</div>
                        <div style="font-size: 0.6875rem; color: var(--color-muted); margin-top: 2px;">PDF, PNG, JPG, DOCX up to 20MB</div>
                    </div>

                    {{-- Selected Files List --}}
                    <div x-show="submitApprovalFiles.length > 0" style="margin-top: var(--space-2); display: flex; flex-direction: column; gap: 4px;">
                        <template x-for="(file, idx) in submitApprovalFiles" :key="idx">
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 4px 8px; background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 0.75rem;">
                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 340px;" x-text="file.name"></span>
                                <button type="button" @click.stop="submitApprovalFiles.splice(idx, 1)" style="color: var(--color-danger); background: none; border: none; cursor: pointer; font-size: 0.875rem;">&times;</button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center;">
                <button type="button" class="btn btn-secondary" @click="submitApprovalModalOpen = false" :disabled="submitApprovalSubmitting">Cancel</button>
                <button type="button" class="btn btn-primary" @click="submitTaskApproval()" :disabled="submitApprovalSubmitting" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span x-show="submitApprovalSubmitting" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    <span x-text="submitApprovalSubmitting ? 'Submitting...' : 'Submit Approval Request'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ================================================================= --}}
    {{-- MODAL: SINGLE REQUEST DETAILS & REMINDER INSPECTION               --}}
    {{-- ================================================================= --}}
    <template x-teleport="body">
        <div
            class="staff-action-review-overlay"
            x-show="actionModalOpen"
            x-cloak
            style="display: none; z-index: 1000000;"
            @keydown.escape.window="closeActionModal()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="approval-detail-modal-title"
        >
            {{-- Blur Backdrop --}}
            <div class="staff-action-review-backdrop" @click="closeActionModal()"></div>

            {{-- Centered Modal Content Card --}}
            <div
                class="card staff-action-review-content"
                x-show="actionModalOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-95"
                @click.stop
            >
                <template x-if="selectedAction">
                    <div style="display: flex; flex-direction: column; max-height: calc(100vh - 3rem); overflow: hidden;">
                        {{-- Modal Header --}}
                        <div class="card-header" style="position: relative; display: flex; justify-content: space-between; align-items: center; padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--color-border); flex-shrink: 0;">
                            <div style="display: flex; align-items: center; gap: var(--space-2);">
                                <svg width="20" height="20" style="width: 20px; height: 20px; color: var(--color-accent); flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                                <span id="approval-detail-modal-title" style="font-weight: 700; font-size: 1.05rem; color: var(--color-ink);">Request Details</span>
                            </div>
                            <button type="button" class="staff-action-review-close" @click="closeActionModal()" aria-label="Close modal">&times;</button>
                        </div>

                        {{-- Modal Body --}}
                        <div class="card-body" style="padding: var(--space-5); overflow-y: auto; flex: 1 1 auto;">
                            {{-- Summary Metadata --}}
                            <div style="background: var(--color-surface-alt); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: var(--space-4); font-size: 0.8125rem;">
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

                            {{-- Task Completion Details --}}
                            <template x-if="selectedAction.action_type === 'complete_subtask'">
                                <div style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); margin-bottom: var(--space-4);">
                                    <h4 style="margin: 0 0 var(--space-2) 0; font-size: 0.875rem; color: #FACC15;">Timeline Task Details</h4>
                                    <div style="font-size: 0.8125rem;">
                                        <div style="margin-bottom: var(--space-2);">
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">Major Task / Milestone</span>
                                            <strong style="color: var(--color-ink);" x-text="selectedAction.payload.task_name || 'Project Task'"></strong>
                                        </div>
                                        <div>
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">Subtask Ticked</span>
                                            <div style="display: flex; align-items: center; gap: var(--space-2); margin-top: 4px; margin-bottom: var(--space-3);">
                                                <span style="color: var(--color-success); font-weight: 700;">☑</span>
                                                <span class="font-bold" style="color: var(--color-ink);" x-text="selectedAction.payload.subtask_title || selectedAction.payload.subtask_name || 'Subtask'"></span>
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

                            {{-- Expense Entry Details --}}
                            <template x-if="selectedAction.action_type === 'add_expense'">
                                <div style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); margin-bottom: var(--space-4);">
                                    <h4 style="margin: 0 0 var(--space-2) 0; font-size: 0.875rem; color: #FB923C;">Expense Entry Details</h4>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2); font-size: 0.8125rem;">
                                        <div style="grid-column: span 2;">
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">Description</span>
                                            <strong style="color: var(--color-ink);" x-text="selectedAction.payload.description || 'N/A'"></strong>
                                        </div>
                                        <div>
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">Amount</span>
                                            <strong class="font-mono" style="color: var(--color-ink);" x-text="'₱' + Number(selectedAction.payload.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></strong>
                                        </div>
                                        <div>
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">Cost Type</span>
                                            <strong style="color: var(--color-ink);" x-text="selectedAction.payload.cost_type ? selectedAction.payload.cost_type.charAt(0).toUpperCase() + selectedAction.payload.cost_type.slice(1) : 'General'"></strong>
                                        </div>
                                        <template x-if="selectedAction.payload.date">
                                            <div>
                                                <span class="text-muted" style="display: block; font-size: 0.6875rem;">Date Incurred</span>
                                                <span class="font-mono" style="color: var(--color-ink);" x-text="selectedAction.payload.date"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- File Upload Details --}}
                            <template x-if="selectedAction.action_type === 'add_file'">
                                <div style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); margin-bottom: var(--space-4);">
                                    <h4 style="margin: 0 0 var(--space-2) 0; font-size: 0.875rem; color: var(--color-accent);">File Upload Details</h4>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2); font-size: 0.8125rem;">
                                        <div style="grid-column: span 2;">
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">File Name</span>
                                            <strong style="color: var(--color-ink);" x-text="selectedAction.payload.file_name || 'Uploaded File'"></strong>
                                        </div>
                                        <div>
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">File Type</span>
                                            <strong style="color: var(--color-ink);" x-text="selectedAction.payload.file_type || 'General'"></strong>
                                        </div>
                                        <div>
                                            <span class="text-muted" style="display: block; font-size: 0.6875rem;">File Size</span>
                                            <span class="font-mono" style="color: var(--color-ink);" x-text="(selectedAction.payload.file_size ? (selectedAction.payload.file_size / 1024).toFixed(1) + ' KB' : 'N/A')"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            {{-- Review Outcome & Remarks (If Reviewed) --}}
                            <template x-if="selectedAction.status !== 'pending'">
                                <div style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); margin-bottom: var(--space-4); background: var(--color-surface-alt);">
                                    <h4 style="margin: 0 0 var(--space-2) 0; font-size: 0.875rem;" :style="{ color: selectedAction.status === 'approved' ? 'var(--color-success)' : 'var(--color-danger)' }">
                                        Review Outcome &amp; Notes
                                    </h4>
                                    <div style="font-size: 0.8125rem;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                                            <span class="text-muted" style="font-size: 0.75rem;">Reviewed By</span>
                                            <strong style="color: var(--color-ink);" x-text="selectedAction.reviewed_by_name || 'Admin/Manager'"></strong>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                                            <span class="text-muted" style="font-size: 0.75rem;">Reviewed At</span>
                                            <span class="font-mono" style="color: var(--color-ink);" x-text="selectedAction.reviewed_at || 'N/A'"></span>
                                        </div>
                                        <div>
                                            <span class="text-muted" style="display: block; font-size: 0.75rem; margin-bottom: 4px;">Reviewer Remarks</span>
                                            <p style="margin: 0; color: var(--color-ink); font-style: italic; background: var(--color-surface); padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border);" x-text="selectedAction.admin_notes || 'No remarks provided.'"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            {{-- Reminder / Ping Status Log --}}
                            <template x-if="selectedAction.last_pinged_at">
                                <div style="display: flex; align-items: center; gap: var(--space-2); font-size: 0.75rem; color: var(--color-muted); padding: 0 var(--space-2);">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 14px; height: 14px; color: var(--color-accent);" aria-hidden="true">
                                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                                    </svg>
                                    <span>Last reminder sent: <strong style="color: var(--color-ink);" x-text="selectedAction.last_pinged_at"></strong> (<span x-text="selectedAction.last_pinged_diff"></span>)</span>
                                </div>
                            </template>
                        </div>

                        {{-- Modal Footer --}}
                        <div class="card-footer" style="padding: var(--space-3) var(--space-5); border-top: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; background: var(--color-surface-alt);">
                            <div>
                                {{-- Ping button inside modal if pending --}}
                                <template x-if="selectedAction.status === 'pending'">
                                    <div>
                                        <template x-if="cooldowns[selectedAction.id] > 0">
                                            <button
                                                type="button"
                                                class="btn btn-secondary btn-sm"
                                                disabled
                                                style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8125rem; opacity: 0.8; cursor: not-allowed;"
                                            >
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 13px; height: 13px;" aria-hidden="true">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <polyline points="12 6 12 12 16 14"></polyline>
                                                </svg>
                                                <span>Cooldown: <strong class="font-mono" x-text="formatCooldown(cooldowns[selectedAction.id])"></strong></span>
                                            </button>
                                        </template>
                                        <template x-if="!cooldowns[selectedAction.id] || cooldowns[selectedAction.id] <= 0">
                                            <button
                                                type="button"
                                                class="btn btn-primary btn-sm"
                                                @click="pingRequest(selectedAction.id)"
                                                :disabled="pingingId === selectedAction.id"
                                                style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8125rem;"
                                            >
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 13px; height: 13px;" aria-hidden="true">
                                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                                                </svg>
                                                <span x-text="pingingId === selectedAction.id ? 'Sending Reminder...' : 'Ping Reviewer Now'">Ping Reviewer Now</span>
                                            </button>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <button type="button" class="btn btn-secondary btn-sm" @click="closeActionModal()">
                                Close
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>

<script>
function approvalListModule() {
    return {
        selectedAction: null,
        actionModalOpen: false,
        pingingId: null,
        cooldowns: @json($cooldownsMap ?? []),
        actionsMap: @json($actionsMap ?? []),
        timerInterval: null,
        activeTab: '{{ $initialTab ?? "my_requests" }}',
        assignedTasksSearch: '',
        assignedTasksStatusFilter: 'all',
        setActiveTab(tab) {
            this.activeTab = tab;
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url);
        },
        submitApprovalModalOpen: false,
        submitApprovalSubmitting: false,
        submitApprovalTask: {},
        submitApprovalNotes: '',
        submitApprovalFiles: [],
        openSubmitApprovalModal(taskData) {
            this.submitApprovalTask = taskData;
            this.submitApprovalNotes = '';
            this.submitApprovalFiles = [];
            this.submitApprovalModalOpen = true;
        },
        handleSubmitApprovalFiles(event) {
            const files = event.target.files;
            if (!files || files.length === 0) return;
            for (const file of Array.from(files)) {
                if (file.size <= 20 * 1024 * 1024) {
                    this.submitApprovalFiles.push(file);
                }
            }
            event.target.value = '';
        },
        async submitTaskApproval() {
            if (!this.submitApprovalTask || !this.submitApprovalTask.subtaskId) return;
            this.submitApprovalSubmitting = true;
            const t = this.submitApprovalTask;
            const url = `/projects/${t.projectId}/tasks/${t.taskId}/subtasks/${t.subtaskId}/toggle`;
            const formData = new FormData();
            formData.append('_method', 'PATCH');
            formData.append('notes', this.submitApprovalNotes);
            for (let i = 0; i < this.submitApprovalFiles.length; i++) {
                formData.append('proof_files[]', this.submitApprovalFiles[i]);
            }

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                const data = await res.json();
                this.submitApprovalSubmitting = false;

                if (res.ok && (data.success || data.pending_approval)) {
                    this.submitApprovalModalOpen = false;
                    const toastDetail = {
                        type: 'success',
                        title: 'Approval Request Submitted',
                        message: data.message || 'Your task completion request has been submitted for review.',
                        duration: 5000
                    };
                    if (window.showToast) {
                        window.showToast(toastDetail);
                    } else {
                        window.dispatchEvent(new CustomEvent('show-toast', { detail: toastDetail }));
                    }
                    setTimeout(() => {
                        window.location.href = "{{ route('approval-list.index') }}?tab=my_requests";
                    }, 600);
                } else {
                    const toastDetail = {
                        type: 'error',
                        title: 'Submission Failed',
                        message: data.message || 'Could not submit task completion request.',
                        duration: 5000
                    };
                    if (window.showToast) {
                        window.showToast(toastDetail);
                    } else {
                        window.dispatchEvent(new CustomEvent('show-toast', { detail: toastDetail }));
                    }
                }
            } catch (err) {
                this.submitApprovalSubmitting = false;
                const toastDetail = {
                    type: 'error',
                    title: 'Network Error',
                    message: 'A network error occurred while submitting.',
                    duration: 5000
                };
                if (window.showToast) {
                    window.showToast(toastDetail);
                } else {
                    window.dispatchEvent(new CustomEvent('show-toast', { detail: toastDetail }));
                }
            }
        },

        init() {
            // Live countdown timer ticking every 1 second
            this.timerInterval = setInterval(() => {
                for (const id in this.cooldowns) {
                    if (this.cooldowns[id] > 0) {
                        this.cooldowns[id]--;
                    }
                }
            }, 1000);
        },

        destroy() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
            }
        },

        formatCooldown(seconds) {
            if (!seconds || seconds <= 0) return '00:00';
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            return `${m}:${s < 10 ? '0' : ''}${s}`;
        },

        isPingable(actionId) {
            return !(this.cooldowns[actionId] && this.cooldowns[actionId] > 0);
        },

        openActionModal(actionId) {
            if (this.actionsMap[actionId]) {
                this.selectedAction = this.actionsMap[actionId];
                this.actionModalOpen = true;
            } else {
                fetch(`/approval-list/${actionId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.formatted_action) {
                        this.actionsMap[actionId] = res.formatted_action;
                        this.selectedAction = res.formatted_action;
                        this.actionModalOpen = true;
                    }
                })
                .catch(() => {});
            }
        },

        closeActionModal() {
            this.actionModalOpen = false;
        },

        setStatusFilter(status) {
            const select = this.$refs.statusSelect;
            if (select) {
                select.value = status;
                this.$refs.filterForm.submit();
            }
        },

        async pingRequest(actionId) {
            if (this.pingingId || !this.isPingable(actionId)) return;
            this.pingingId = actionId;

            try {
                const response = await fetch(`/approval-list/${actionId}/ping`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    this.cooldowns[actionId] = data.cooldown_seconds || 900;
                    if (this.selectedAction && this.selectedAction.id === actionId) {
                        this.selectedAction.last_pinged_at = data.last_pinged_at || 'Just now';
                        this.selectedAction.last_pinged_diff = 'Just now';
                    }

                    // Toast Notification
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: {
                            type: 'success',
                            title: 'Reminder Sent (Ping)',
                            message: data.message || 'Notification sent to Admin and Manager.',
                            duration: 5000
                        }
                    }));
                } else {
                    if (data.cooldown_seconds) {
                        this.cooldowns[actionId] = data.cooldown_seconds;
                    }
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: {
                            type: 'warning',
                            title: 'Cannot Ping Yet',
                            message: data.message || 'Unable to send reminder.',
                            duration: 5000
                        }
                    }));
                }
            } catch (err) {
                window.dispatchEvent(new CustomEvent('show-toast', {
                    detail: {
                        type: 'error',
                        title: 'Error',
                        message: 'A network error occurred while sending the reminder.',
                        duration: 5000
                    }
                }));
            } finally {
                this.pingingId = null;
            }
        }
    };
}
</script>
@endsection

