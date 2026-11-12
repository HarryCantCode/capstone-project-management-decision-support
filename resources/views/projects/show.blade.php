@extends('layouts.app')

@section('title', 'Project Details')
@section('meta-description', 'View project details, interactive timeline, assigned manpower, itemized costing, and activity history.')

@php
    $tasks = $project->tasks;
    $totalTasksCount = $tasks->count();
    $nextWeekNum = $totalTasksCount + 1;

    $projectStartDate = $project->start_date ? \Carbon\Carbon::parse($project->start_date) : $project->created_at;
    $projectTargetDate = $project->target_completion_date ? \Carbon\Carbon::parse($project->target_completion_date) : null;

    // Auto-calculate next week default start & end dates
    if ($tasks->isNotEmpty()) {
        $latestTask = $tasks->sortByDesc('end_date')->first();
        $defaultStartDate = \Carbon\Carbon::parse($latestTask->end_date)->addDay();
    } else {
        $defaultStartDate = $projectStartDate->copy();
    }
    $defaultEndDate = $defaultStartDate->copy()->addDays(6);

    $totalSubtasksAcrossProject = $tasks->sum(fn ($t) => $t->subtasks->count());
    $completedSubtasksAcrossProject = $tasks->sum(fn ($t) => $t->subtasks->where('is_completed', true)->count());
    $projectOverallProgress = $totalSubtasksAcrossProject > 0
        ? (int) round(($completedSubtasksAcrossProject / $totalSubtasksAcrossProject) * 100)
        : ($project->status === 'completed' ? 100 : 0);

    // Build initial subtask lock map for sequential dependency enforcement
    // 1. Forward lock: an uncompleted subtask is locked if any previous subtask is uncompleted.
    // 2. Reverse lock: a completed subtask is locked from being unchecked if any subsequent subtask is completed.
    $orderedSubtasks = $tasks->flatMap(fn ($t) => $t->subtasks)->values();
    $initialSubtaskLocks = [];
    $initialSubtaskCompleted = [];
    $initialSubtaskPending = [];
    $pendingSubtaskIdSet = array_flip($pendingSubtaskIds ?? []);
    $isStaffViewer = Auth::user()->hasRole('Staff');
    $foundFirstUncompleted = false;
    $totalOrderedSubtasks = $orderedSubtasks->count();
    $isProjectReadOnly = $project->trashed() || $project->status === 'completed';

    foreach ($orderedSubtasks as $idx => $st) {
        $isPendingReview = isset($pendingSubtaskIdSet[$st->id]) && ! $st->is_completed;
        $initialSubtaskCompleted[$st->id] = (bool) $st->is_completed;
        $initialSubtaskPending[$st->id] = $isPendingReview;

        if ($st->is_completed) {
            $hasCompletedAfter = false;
            for ($k = $idx + 1; $k < $totalOrderedSubtasks; $k++) {
                if ($orderedSubtasks[$k]->is_completed) {
                    $hasCompletedAfter = true;
                    break;
                }
            }
            $initialSubtaskLocks[$st->id] = $hasCompletedAfter;
        } elseif ($isPendingReview) {
            // Task has been submitted and is pending review: strictly lock this task from further actions
            $initialSubtaskLocks[$st->id] = true;
        } else {
            if (!$foundFirstUncompleted) {
                $initialSubtaskLocks[$st->id] = false;
                $foundFirstUncompleted = true;
            } else {
                $initialSubtaskLocks[$st->id] = true;
            }
        }
    }
@endphp

@section('content')
<div x-data="projectDetail()">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <a href="{{ route('projects.index') }}" class="back-link">← All projects</a>
            <h1>{{ $project->name }}</h1>
            <p class="page-header__description">
                <span class="font-mono">{{ $project->project_code }}</span> · {{ $project->client_name }}
            </p>
        </div>
        <div class="page-actions">
            @can('view', $project)
                <a
                    href="{{ route('reports.index', ['project_id' => $project->id]) }}"
                    class="btn btn-primary btn-sm"
                    style="display: inline-flex; align-items: center; gap: 0.35rem;"
                    title="Export project overview, financials, elevator specs, expenditures, and manpower as PDF"
                    id="btn-export-project-pdf"
                >
                    <svg style="width: 14px; height: 14px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1zm3.293-7.707a1 1 0 0 1 1.414 0L9 10.586V3a1 1 0 1 1 2 0v7.586l1.293-1.293a1 1 0 1 1 1.414 1.414l-3 3a1 1 0 0 1-1.414 0l-3-3a1 1 0 0 1 0-1.414z" clip-rule="evenodd"/>
                    </svg>
                    Export PDF
                </a>
            @endcan
            @if ($project->trashed())
                @can('restore', $project)
                    <form method="POST" action="{{ route('projects.restore', $project->id) }}" class="inline-form" onsubmit="return confirm('Restore archived project back to active work?');">
                        @csrf
                        <button type="submit" class="btn btn-primary">Restore Project</button>
                    </form>
                @endcan
            @else
                @can('update', $project)
                    @if ($project->status === 'completed')
                        {{-- Completed project: direct Edit Project link, no mark as completed, no x button --}}
                        <a href="{{ route('projects.edit', $project) }}" class="btn btn-secondary" id="btn-header-edit-project">
                            Edit Project
                        </a>
                    @else
                        <div x-data="{ editingActionsOpen: false }" style="display: inline-flex; align-items: center; gap: var(--space-2);">
                            {{-- Default state: Edit Project button --}}
                            <button
                                type="button"
                                class="btn btn-secondary"
                                x-show="!editingActionsOpen"
                                @click="editingActionsOpen = true"
                                id="btn-header-edit-project"
                            >
                                Edit Project
                            </button>

                            {{-- Expanded state when Edit Project is clicked --}}
                            <div x-show="editingActionsOpen" x-cloak style="display: inline-flex; align-items: center; gap: var(--space-2);">
                                @can('transition', $project)
                                    <button
                                        type="button"
                                        class="btn btn-secondary"
                                        @click="markCompletedModalOpen = true"
                                        style="color: var(--color-success); border-color: var(--color-success);"
                                        id="btn-header-mark-completed"
                                    >
                                        ✓ Mark as Completed
                                    </button>
                                @endcan

                                <a href="{{ route('projects.edit', $project) }}" class="btn btn-secondary">
                                    Edit Project
                                </a>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    style="padding: var(--space-2) var(--space-3); line-height: 1; font-weight: 700;"
                                    @click="editingActionsOpen = false"
                                    title="Cancel and hide actions"
                                    aria-label="Cancel editing options"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>
                    @endif
                @endcan
                @cannot('update', $project)
                    @if ($project->status !== 'completed')
                        @can('transition', $project)
                            <button type="button" class="btn btn-secondary" @click="markCompletedModalOpen = true" style="color: var(--color-success); border-color: var(--color-success);" id="btn-header-mark-completed">
                                ✓ Mark as Completed
                            </button>
                        @endcan
                    @endif
                @endcannot
                @can('archive', $project)
                    <button type="button" class="btn btn-danger" @click="archiveModalOpen = true" id="btn-header-archive-project">
                        Archive Project
                    </button>
                @endcan
            @endif
        </div>
    </div>

    {{-- Top Overview Grid --}}
    <div class="project-detail-grid mb-6">
        
        {{-- Overview Card --}}
        <section class="card card-glass" aria-labelledby="project-overview-heading">
            <div class="card-header">
                <h2 id="project-overview-heading">Overview</h2>
                <x-status-pill :status="$project->status" id="project-overview-status-pill" />
            </div>
            <div class="card-body detail-list">
                <div><dt>Client</dt><dd>{{ $project->client_name }}</dd></div>
                <div><dt>Project Code</dt><dd class="font-mono">{{ $project->project_code }}</dd></div>
                <div><dt>Project Type</dt><dd><span class="badge" style="background: var(--color-surface-alt); border: 1px solid var(--color-border); font-weight: 600; padding: 2px 8px; border-radius: var(--radius-sm);">{{ $project->project_type ?: 'General' }}</span></dd></div>
                <div><dt>Payment Status</dt><dd><span class="status-pill status-pill--{{ strtolower($project->payment_status ?? 'partial') }}">{{ $project->payment_status ?: 'Partial' }}</span></dd></div>
                <div class="detail-list__full"><dt>Site Location</dt><dd>{{ $project->full_address ?: 'Not specified' }}</dd></div>
                <div><dt>Start Date</dt><dd class="font-mono">{{ $project->start_date ? $project->start_date->format('M j, Y') : ($project->created_at->format('M j, Y')) }}</dd></div>
                <div><dt>Target Completion</dt><dd class="font-mono font-bold" style="color: var(--color-accent);">{{ $project->target_completion_date ? $project->target_completion_date->format('M j, Y') : 'Not set' }}</dd></div>
                <div>
                    <dt>Warranty Period</dt>
                    <dd class="font-mono">
                        <button 
                            type="button" 
                            @click="warrantyInfoModalOpen = true" 
                            style="background: none; border: none; padding: 0; font-family: inherit; font-size: inherit; color: var(--color-ink); cursor: pointer; display: inline-flex; flex-direction: row; align-items: center; gap: 6px; flex-wrap: nowrap; white-space: nowrap; text-align: left;"
                            title="Click to view Warranty & Maintenance inclusion terms"
                        >
                            <span>{{ $project->warranty_period ?? '1 Year' }}</span>
                            @if($project->warranty_end_date)
                                <span class="text-muted" style="font-size: 0.8125rem; font-weight: normal;">(Until {{ $project->warranty_end_date->format('M j, Y') }})</span>
                            @endif
                            <svg style="width: 14px; height: 14px; color: var(--color-primary); flex-shrink: 0; opacity: 0.85;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                        </button>
                    </dd>
                </div>
                <div><dt>Created by</dt><dd>{{ $project->creator?->name ?? 'System' }}</dd></div>
                <div><dt>Last updated</dt><dd>{{ $project->updated_at->format('M j, Y · g:i A') }} by {{ $project->updater?->name ?? ($project->creator?->name ?? 'System') }}</dd></div>
                <div class="detail-list__full"><dt>Description</dt><dd>{{ $project->description ?: 'No description provided.' }}</dd></div>
            </div>
        </section>

        {{-- Financials & Budget Tracking Card --}}
        <div style="display: flex; flex-direction: column; gap: var(--space-6);">
        <section class="card card-glass" aria-labelledby="project-costing-heading">
            <div class="card-header">
                <h2 id="project-costing-heading">Financials & Budget</h2>
                @if($project->payment_status)
                    <span class="status-pill status-pill--{{ strtolower($project->payment_status) }}" title="Milestone Payment Status">
                        {{ $project->payment_status }}
                    </span>
                @endif
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
                        <span class="financial-stat-card__label" title="Actual Spend">Actual Spend</span>
                        <div class="financial-stat-card__value" style="color: var(--color-ink);" title="₱{{ number_format($project->actual_spend, 2) }}">
                            ₱{{ number_format($project->actual_spend, 2) }}
                        </div>
                    </div>
                    <div class="financial-stat-card">
                        <span class="financial-stat-card__label" title="Remaining Budget">Remaining</span>
                        <div class="financial-stat-card__value {{ $project->remaining_budget >= 0 ? 'text-success' : 'text-danger' }}" title="₱{{ number_format($project->remaining_budget, 2) }}">
                            ₱{{ number_format($project->remaining_budget, 2) }}
                        </div>
                    </div>
                </div>

                {{-- Spend Progress Bar --}}
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.8125rem; margin-bottom: 6px;">
                        <span class="text-muted">Budget Utilization</span>
                        <span class="font-mono font-bold">{{ $project->spend_percentage }}% used</span>
                    </div>
                    <div style="width: 100%; height: 8px; background: var(--color-border); border-radius: var(--radius-pill); overflow: hidden;">
                        <div style="height: 100%; width: {{ min(100, $project->spend_percentage) }}%; background: {{ $project->spend_percentage > 100 ? 'var(--color-danger)' : ($project->spend_percentage > 85 ? '#C98A2C' : 'var(--color-success)') }}; transition: width 0.3s ease;"></div>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: var(--space-2); border-top: 1px solid var(--color-border);">
                    <a href="{{ route('costing.show', $project) }}" class="table-link" style="font-size: 0.8125rem;">View Full Costing & Breakdown →</a>
                    <span class="text-muted" style="font-size: 0.75rem;">{{ $project->costs->count() }} logged expenses</span>
                </div>
            </div>
        </section>

            {{-- ─── Uploaded Project Files & Attachments (Placed directly below Financials & Budget) ─── --}}
            <section 
                class="card card-glass" 
                aria-labelledby="project-files-heading" 
                id="project-files-card"
                x-data="{
                    fileCategory: 'all',
                    fileSearch: '',
                    matchesCategory(fileType, searchBlob) {
                        let catMatch = false;
                        if (this.fileCategory === 'all') {
                            catMatch = true;
                        } else if (this.fileCategory === 'project_document') {
                            catMatch = (fileType === 'project_document' || fileType === 'general');
                        } else if (this.fileCategory === 'invoice') {
                            catMatch = (fileType === 'invoice' || fileType === 'project_invoice');
                        } else {
                            catMatch = (fileType === this.fileCategory);
                        }
                        if (!catMatch) return false;

                        const q = (this.fileSearch || '').toLowerCase().trim();
                        if (!q) return true;
                        return searchBlob && searchBlob.includes(q);
                    }
                }"
            >
                @php
                    $allProjectFiles = $project->files;
                    $countAllFiles = $allProjectFiles->count();
                    $countDocFiles = $allProjectFiles->filter(fn($f) => in_array($f->file_type, ['project_document', 'general']))->count();
                    $countTransFiles = $allProjectFiles->where('file_type', 'project_transaction')->count();
                    $countInvFiles = $allProjectFiles->filter(fn($f) => in_array($f->file_type, ['invoice', 'project_invoice']))->count();
                    $countNotesFiles = $allProjectFiles->where('file_type', 'project_notes')->count();
                    $countDrawingFiles = $allProjectFiles->where('file_type', 'project_drawing')->count();
                    $countTaskFiles = $allProjectFiles->where('file_type', 'task_attachment')->count();
                @endphp
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3); padding: var(--space-3) var(--space-4);">
                    <div style="display: flex; align-items: center; gap: var(--space-2);">
                        <svg style="width: 20px; height: 20px; color: var(--color-primary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <h2 id="project-files-heading" style="margin: 0; font-size: 1rem; font-weight: 700;">Project Files & Documents</h2>
                        <span class="badge font-mono" style="background: var(--color-surface-alt); font-size: 0.8125rem; font-weight: 700; padding: 2px 8px; border-radius: 9999px;">{{ $countAllFiles }}</span>
                    </div>

                    <div style="display: flex; align-items: center; gap: 18px; flex-wrap: wrap;">
                        {{-- Search Bar: Medium size with rounded pill corners placed on the left side of + Upload File --}}
                        <div style="position: relative; width: 220px;">
                            <svg style="position: absolute; left: 11px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; color: var(--color-muted); pointer-events: none;" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                            <input
                                type="search"
                                x-model="fileSearch"
                                class="form-control"
                                placeholder="Search files..."
                                aria-label="Search files"
                                style="padding-left: 32px !important; padding-right: 12px; height: 34px !important; font-size: 0.8125rem !important; border-radius: 9999px !important; width: 100%;"
                            >
                        </div>

                        @if(Auth::user()->hasAnyRole(['Admin', 'Manager', 'Staff']))
                            @if ($isProjectReadOnly)
                                <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.6; cursor: not-allowed; white-space: nowrap; font-size: 0.8125rem; padding: 0 14px; height: 34px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center;" title="{{ $project->trashed() ? 'Cannot upload files to an archived project' : 'Cannot upload files to a completed project unless reactivated' }}">
                                    + Upload File
                                </button>
                            @else
                                <button type="button" class="btn btn-primary btn-sm" @click="openUploadProjectFileModal(fileCategory === 'all' ? 'project_document' : fileCategory)" style="white-space: nowrap; font-size: 0.8125rem; padding: 0 14px; height: 34px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center;">
                                    + Upload File
                                </button>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="card-body" style="padding: var(--space-4);">
                    @if ($allProjectFiles->isEmpty())
                        <div style="text-align: center; padding: var(--space-5) var(--space-3); color: var(--color-muted);">
                            <svg style="width: 36px; height: 36px; margin: 0 auto var(--space-2); opacity: 0.4;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <p style="margin: 0; font-size: 0.875rem; font-weight: 600;">No files uploaded yet.</p>
                            <p class="form-hint" style="margin-top: 4px; font-size: 0.8125rem;">Supported categories: Project document, transaction, invoice, notes, and drawings / blueprints.</p>
                        </div>
                    @else
                        {{-- Category Filter Navigation --}}
                        <div style="margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid var(--color-border); display: flex; flex-direction: column; gap: 8px;">
                            {{-- Category Filter Pills --}}
                            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                <button
                                    type="button"
                                    class="btn btn-sm"
                                    :class="fileCategory === 'all' ? 'btn-primary' : 'btn-secondary'"
                                    @click="fileCategory = 'all'"
                                    style="padding: 4px 11px; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; line-height: 1.4; transition: all 0.15s ease;"
                                >
                                    All <span style="opacity: 0.85; font-weight: 700; margin-left: 2px;">({{ $countAllFiles }})</span>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm"
                                    :class="fileCategory === 'project_document' ? 'btn-primary' : 'btn-secondary'"
                                    @click="fileCategory = 'project_document'"
                                    style="padding: 4px 11px; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; line-height: 1.4; transition: all 0.15s ease;"
                                >
                                    Project document <span style="opacity: 0.85; font-weight: 700; margin-left: 2px;">({{ $countDocFiles }})</span>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm"
                                    :class="fileCategory === 'project_transaction' ? 'btn-primary' : 'btn-secondary'"
                                    @click="fileCategory = 'project_transaction'"
                                    style="padding: 4px 11px; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; line-height: 1.4; transition: all 0.15s ease;"
                                >
                                    Project transaction <span style="opacity: 0.85; font-weight: 700; margin-left: 2px;">({{ $countTransFiles }})</span>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm"
                                    :class="fileCategory === 'invoice' ? 'btn-primary' : 'btn-secondary'"
                                    @click="fileCategory = 'invoice'"
                                    style="padding: 4px 11px; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; line-height: 1.4; transition: all 0.15s ease;"
                                >
                                    Project invoice <span style="opacity: 0.85; font-weight: 700; margin-left: 2px;">({{ $countInvFiles }})</span>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm"
                                    :class="fileCategory === 'project_notes' ? 'btn-primary' : 'btn-secondary'"
                                    @click="fileCategory = 'project_notes'"
                                    style="padding: 4px 11px; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; line-height: 1.4; transition: all 0.15s ease;"
                                >
                                    Project notes <span style="opacity: 0.85; font-weight: 700; margin-left: 2px;">({{ $countNotesFiles }})</span>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm"
                                    :class="fileCategory === 'project_drawing' ? 'btn-primary' : 'btn-secondary'"
                                    @click="fileCategory = 'project_drawing'"
                                    style="padding: 4px 11px; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; line-height: 1.4; transition: all 0.15s ease;"
                                >
                                    Project drawing / blueprint <span style="opacity: 0.85; font-weight: 700; margin-left: 2px;">({{ $countDrawingFiles }})</span>
                                </button>
                                @if ($countTaskFiles > 0)
                                    <button
                                        type="button"
                                        class="btn btn-sm"
                                        :class="fileCategory === 'task_attachment' ? 'btn-primary' : 'btn-secondary'"
                                        @click="fileCategory = 'task_attachment'"
                                        style="padding: 4px 11px; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; line-height: 1.4; transition: all 0.15s ease;"
                                    >
                                        Task Attachment <span style="opacity: 0.85; font-weight: 700; margin-left: 2px;">({{ $countTaskFiles }})</span>
                                    </button>
                                @endif
                            </div>

                            {{-- Reset Filter Button: Pinned to the bottom-right corner when filtering/searching is active --}}
                            <div 
                                x-show="fileCategory !== 'all' || fileSearch" 
                                x-cloak
                                style="display: flex; justify-content: flex-end; align-items: center; gap: 8px; font-size: 0.75rem; color: var(--color-muted); padding-top: 2px;"
                            >
                                <span>Filtered results &bull;</span>
                                <button 
                                    type="button" 
                                    @click="fileCategory = 'all'; fileSearch = ''" 
                                    class="btn btn-secondary btn-sm"
                                    style="padding: 2px 10px; font-size: 0.75rem; height: 26px; border-radius: 9999px; display: inline-flex; align-items: center; gap: 4px; font-weight: 600; cursor: pointer; color: var(--color-ink);"
                                    title="Reset filter"
                                >
                                    <svg style="width: 11px; height: 11px; opacity: 0.8;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                    Reset filter
                                </button>
                            </div>
                        </div>

                        {{-- Categorized Files List (Clean 2-row card, limited to ~4 files view with smooth scroll) --}}
                        <div class="project-files-scroll-list" style="display: flex; flex-direction: column; gap: 8px; max-height: 310px; overflow-y: auto; padding-right: 4px;">
                            @foreach ($allProjectFiles as $file)
                                @php
                                    $searchBlob = strtolower($file->file_name . ' ' . str_replace(["\r", "\n", '"', "'", "\\"], ' ', $file->description ?? ''));
                                    $searchBlobJson = json_encode($searchBlob);
                                    $fileType = $file->file_type;
                                    $fileData = [
                                        'id' => $file->id,
                                        'name' => $file->file_name,
                                        'size' => $file->formatted_size,
                                        'type' => $file->file_type,
                                        'category_label' => $file->category_label,
                                        'description' => $file->description ?? '',
                                        'uploader' => $file->uploader ? $file->uploader->name : 'System',
                                        'date' => $file->created_at->format('M j, Y · g:i A'),
                                        'download_url' => route('projects.files.download', [$project, $file]),
                                        'update_url' => route('projects.files.update', [$project, $file]),
                                        'delete_url' => route('projects.files.destroy', [$project, $file]),
                                        'is_img' => $file->is_image,
                                        'is_pdf' => $file->is_pdf,
                                        'is_word' => $file->is_word,
                                        'subtask_title' => $file->subtask ? $file->subtask->title : null,
                                    ];
                                @endphp
                                <div 
                                    class="project-file-item"
                                    x-show='matchesCategory("{{ $fileType }}", {{ $searchBlobJson }})'
                                    style="display: flex; flex-direction: column; gap: 5px; padding: 9px 13px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); cursor: pointer; transition: all 0.15s ease;"
                                    @click="openViewFileModal({{ json_encode($fileData) }})"
                                    title="Click to view details, update description, download, or delete"
                                >
                                    {{-- Row 1: [Fixed Icon Cell] [File Name] [Small Badge] ─────── [Size (normal)] [Actions] --}}
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%;">
                                        {{-- Left: Fixed Icon Cell + File Name + Small Badge --}}
                                        <div style="display: flex; align-items: center; gap: 8px; min-width: 0; flex: 1;">
                                            {{-- Fixed Icon Cell (always directly on left side of name) --}}
                                            <div style="width: 28px; height: 28px; min-width: 28px; border-radius: 6px; background: var(--color-surface-alt); border: 1px solid var(--color-border); display: flex; align-items: center; justify-content: center; font-size: 1.05rem; flex-shrink: 0;">
                                                @if ($file->is_image)
                                                    🖼️
                                                @elseif ($file->is_pdf)
                                                    📕
                                                @elseif ($file->is_word)
                                                    📘
                                                @elseif ($file->file_type === 'project_drawing')
                                                    📐
                                                @else
                                                    📄
                                                @endif
                                            </div>

                                            {{-- File Name --}}
                                            <span class="table-link font-semibold" style="font-size: 0.875rem; color: var(--color-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 360px;" title="{{ $file->file_name }}">
                                                {{ $file->file_name }}
                                            </span>

                                            {{-- Small Category / Task Badge --}}
                                            @if (in_array($file->file_type, ['invoice', 'project_invoice']))
                                                <span class="badge" style="background: rgba(34, 197, 94, 0.14); color: var(--color-success); font-size: 0.6875rem; font-weight: 600; padding: 1px 6px; border-radius: 4px; flex-shrink: 0;">Project invoice</span>
                                            @elseif ($file->file_type === 'project_transaction')
                                                <span class="badge" style="background: rgba(245, 158, 11, 0.14); color: #b45309; font-size: 0.6875rem; font-weight: 600; padding: 1px 6px; border-radius: 4px; flex-shrink: 0;">Project transaction</span>
                                            @elseif ($file->file_type === 'project_notes')
                                                <span class="badge" style="background: rgba(139, 92, 246, 0.14); color: #7c3aed; font-size: 0.6875rem; font-weight: 600; padding: 1px 6px; border-radius: 4px; flex-shrink: 0;">Project notes</span>
                                            @elseif ($file->file_type === 'project_drawing')
                                                <span class="badge" style="background: rgba(6, 182, 212, 0.14); color: #0891b2; font-size: 0.6875rem; font-weight: 600; padding: 1px 6px; border-radius: 4px; flex-shrink: 0;">Project drawing</span>
                                            @elseif ($file->file_type === 'task_attachment' && $file->subtask)
                                                <span class="badge" style="background: var(--color-surface-alt); font-size: 0.6875rem; font-weight: 600; padding: 1px 6px; border-radius: 4px; border: 1px solid var(--color-border); flex-shrink: 0;" title="Subtask: {{ $file->subtask->title }}">
                                                    Task #{{ $file->subtask->id }}
                                                </span>
                                            @else
                                                <span class="badge" style="background: rgba(44, 95, 124, 0.14); color: var(--color-accent); font-size: 0.6875rem; font-weight: 600; padding: 1px 6px; border-radius: 4px; flex-shrink: 0;">Project document</span>
                                            @endif
                                        </div>

                                        {{-- Right: File Size (not bold, at top right) + Actions --}}
                                        <div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;" @click.stop>
                                            <span class="font-mono text-muted" style="font-size: 0.75rem; font-weight: normal; color: var(--color-muted);">
                                                {{ $file->formatted_size }}
                                            </span>

                                            {{-- Action Buttons (Locked fixed size, no overlap) --}}
                                            <div style="display: flex; align-items: center; gap: 5px;">
                                                {{-- Preview Button --}}
                                                <a 
                                                    href="{{ route('projects.files.download', [$project, $file]) }}?preview=1" 
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="btn btn-secondary btn-sm" 
                                                    style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; padding: 0; border-radius: var(--radius-sm);" 
                                                    title="Preview File in new tab"
                                                >
                                                    <svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                        <circle cx="12" cy="12" r="3"></circle>
                                                    </svg>
                                                </a>

                                                {{-- Download Button --}}
                                                <a 
                                                    href="{{ route('projects.files.download', [$project, $file]) }}" 
                                                    class="btn btn-secondary btn-sm" 
                                                    style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; padding: 0; border-radius: var(--radius-sm);" 
                                                    title="Direct Download"
                                                >
                                                    <svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                        <polyline points="7 10 12 15 17 10"></polyline>
                                                        <line x1="12" y1="15" x2="12" y2="3"></line>
                                                    </svg>
                                                </a>

                                                {{-- Details & Edit Button --}}
                                                <button 
                                                    type="button" 
                                                    class="btn btn-secondary btn-sm" 
                                                    style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; padding: 0; border-radius: var(--radius-sm);" 
                                                    @click="openViewFileModal({{ json_encode($fileData) }})" 
                                                    title="Details & Edit"
                                                >
                                                    <svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="12" cy="12" r="3"></circle>
                                                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Row 2: Submitter & Date at bottom, with neatly truncated description notes --}}
                                    <div style="display: flex; align-items: center; gap: 7px; font-size: 0.75rem; color: var(--color-muted); padding-left: 36px; overflow: hidden; white-space: nowrap;">
                                        @if ($file->uploader)
                                            <span>by <span style="color: var(--color-ink); font-weight: 500;">{{ $file->uploader->name }}</span></span>
                                            <span style="opacity: 0.35;">&bull;</span>
                                        @endif
                                        <span>{{ $file->created_at->format('M j, Y') }}</span>
                                        @if ($file->description)
                                            <span style="opacity: 0.35;">&bull;</span>
                                            <span style="font-style: italic; color: var(--color-ink); opacity: 0.85; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 360px;" title="{{ Str::limit(strip_tags(str_replace(["\r", "\n", '"'], ' ', $file->description)), 200) }}">
                                                "{{ Str::limit(strip_tags(str_replace(["\r", "\n", '"'], ' ', $file->description)), 45) }}"
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
        </div>

    </div>

    {{-- ─── Elevator Technical Specifications ───────────────────────────────────── --}}
    <section class="card card-glass mb-6" aria-labelledby="elevator-specs-heading">
        <div class="card-header">
            <h2 id="elevator-specs-heading" style="margin: 0; font-size: 1.0625rem; font-weight: 700;">Elevator Technical Specifications</h2>
        </div>
        <div class="card-body">
            <div class="detail-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: var(--space-4);">
                <div>
                    <dt style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink); margin-bottom: 2px;">Rated Capacity</dt>
                    <dd class="font-mono" style="font-size: 0.9375rem; font-weight: normal; color: var(--color-ink);">{{ $project->rated_capacity ?: 'Not specified' }}</dd>
                </div>
                <div>
                    <dt style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink); margin-bottom: 2px;">Lifting Speed</dt>
                    <dd class="font-mono" style="font-size: 0.9375rem; font-weight: normal; color: var(--color-ink);">{{ $project->lifting_speed ?: 'Not specified' }}</dd>
                </div>
                <div>
                    <dt style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink); margin-bottom: 2px;">No. of Stops</dt>
                    <dd class="font-mono" style="font-size: 0.9375rem; font-weight: normal; color: var(--color-ink);">{{ $project->no_of_stops ? $project->no_of_stops . ' Stops' : 'Not specified' }}</dd>
                </div>
                <div>
                    <dt style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink); margin-bottom: 2px;">Cage Dimension</dt>
                    <dd class="font-mono" style="font-size: 0.9375rem; font-weight: normal; color: var(--color-ink);">{{ $project->cage_dimension ?: 'Not specified' }}</dd>
                </div>
                <div>
                    <dt style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink); margin-bottom: 2px;">Door Operation</dt>
                    <dd style="font-size: 0.9375rem; font-weight: normal; color: var(--color-ink);">{{ $project->door_operation ?: 'Not specified' }}</dd>
                </div>
                <div>
                    <dt style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink); margin-bottom: 2px;">Power Source</dt>
                    <dd class="font-mono" style="font-size: 0.9375rem; font-weight: normal; color: var(--color-ink);">{{ $project->power_source ?: 'Not specified' }}</dd>
                </div>
                <div style="grid-column: 1 / -1; display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: var(--space-4); padding-top: var(--space-3); border-top: 1px solid var(--color-border);">
                    <div>
                        <dt style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink); margin-bottom: 2px;">Operation</dt>
                        <dd style="font-size: 0.9375rem; font-weight: normal; color: var(--color-ink);">{{ $project->operation ?: 'Not specified' }}</dd>
                    </div>
                    <div>
                        <dt style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink); margin-bottom: 2px;">Wall and Ceiling</dt>
                        <dd style="font-size: 0.9375rem; font-weight: normal; color: var(--color-ink);">{{ $project->wall_and_ceiling ?: 'Not specified' }}</dd>
                    </div>
                    <div>
                        <dt style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-ink); margin-bottom: 2px;">Flooring</dt>
                        <dd style="font-size: 0.9375rem; font-weight: normal; color: var(--color-ink);">{{ $project->flooring ?: 'Not specified' }}</dd>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ─── Dynamic Project Progress Timeline (Major Tasks & Sub Tasks) ──────────── --}}
    <section class="card card-glass mb-6" aria-labelledby="project-timeline-heading">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);">
            <div>
                <h2 id="project-timeline-heading" style="display: inline-block; margin-right: var(--space-3); margin-bottom: 0;">Project Progress Timeline</h2>
                <span class="text-muted" style="font-size: 0.8125rem;">
                    @if ($projectTargetDate)
                        Target Date: <strong class="font-mono">{{ $projectTargetDate->format('M j, Y') }}</strong> ·
                    @endif
                    <span class="badge" style="background: var(--color-surface-alt); font-weight: 600;">
                        {{ $tasks->count() }} {{ Str::plural('Major Task / Week', $tasks->count()) }}
                    </span>
                    <span id="project-overall-progress-container" style="{{ $totalSubtasksAcrossProject > 0 ? '' : 'display: none;' }}">
                        · Overall Progress: <strong id="project-overall-progress-text" class="font-mono" style="color: {{ $projectOverallProgress === 100 ? 'var(--color-success)' : 'var(--color-accent)' }};">{{ $projectOverallProgress }}%</strong> <span id="project-overall-count-text">({{ $completedSubtasksAcrossProject }}/{{ $totalSubtasksAcrossProject }} tasks)</span>
                    </span>
                </span>
            </div>
            <div style="display: flex; gap: var(--space-2); align-items: center; flex-wrap: wrap;">
                @if ($projectTargetDate && $projectTargetDate->isPast() && $project->status !== 'completed')
                    @can('transition', $project)
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm"
                            @click="markCompletedModalOpen = true"
                            style="background: #ecfdf5; color: var(--color-success); border-color: #a7f3d0; font-weight: 600;"
                            id="btn-timeline-mark-completed"
                            title="Target completion date reached. Click to mark project as completed."
                        >
                            ✓ Target Date Reached · Complete Project?
                        </button>
                    @endcan
                @endif

                @can('update', $project)
                    @if ($isProjectReadOnly)
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm"
                            disabled
                            style="opacity: 0.6; cursor: not-allowed;"
                            title="{{ $project->trashed() ? 'Cannot add major tasks to an archived project' : 'Cannot add major tasks to a completed project unless reactivated' }}"
                            id="btn-add-major-task"
                        >
                            + Add Major Task
                        </button>
                    @else
                        <button
                            type="button"
                            class="btn btn-primary btn-sm"
                            @click="createMajorTaskModalOpen = true"
                            id="btn-add-major-task"
                        >
                            + Add Major Task
                        </button>
                    @endif
                @endcan

                @if ($tasks->isNotEmpty())
                    <button
                        type="button"
                        class="btn btn-secondary btn-sm"
                        @click="toggleTimeline()"
                        id="btn-toggle-timeline-view"
                    >
                        <span x-show="timelineMode === 'minimized'">⊞ Expand Full Progress</span>
                        <span x-show="timelineMode === 'expanded'" x-cloak>⊟ Stage Carousel View</span>
                    </button>
                @endif
            </div>
        </div>

        @if ($projectTargetDate && $projectTargetDate->isPast() && $project->status !== 'completed')
            <div style="background: #f0fdf4; border-bottom: 1px solid #bbf7d0; padding: var(--space-3) var(--space-6); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);" id="timeline-target-date-alert">
                <div style="display: flex; align-items: center; gap: var(--space-2); font-size: 0.875rem; color: #166534;">
                    <svg style="width: 18px; height: 18px; flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 14 14"></polyline>
                    </svg>
                    <span>
                        <strong>Timeline Target Date Reached:</strong> Scheduled completion date was <strong>{{ $projectTargetDate->format('M j, Y') }}</strong>. Review tasks below or confirm completion.
                    </span>
                </div>
                @can('transition', $project)
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        @click="markCompletedModalOpen = true"
                        style="background: var(--color-success); border-color: var(--color-success); white-space: nowrap;"
                    >
                        Mark Project as Completed
                    </button>
                @endcan
            </div>
        @endif

        <div class="card-body">
            @if ($tasks->isEmpty())
                <div class="empty-state" style="padding: var(--space-8) var(--space-4); text-align: center;">
                    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--color-accent-light); color: var(--color-accent); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-3);">
                        <svg style="width: 24px; height: 24px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: var(--space-2);">No Timeline Tasks Scheduled</h3>
                    <p class="text-muted" style="max-width: 520px; margin: 0 auto var(--space-4); font-size: 0.875rem;">
                        This project does not have any weekly major tasks configured yet. You can create your first major task with week-based schedules.
                    </p>
                    @can('update', $project)
                        <div style="display: flex; gap: var(--space-3); justify-content: center; flex-wrap: wrap;">
                            @if ($isProjectReadOnly)
                                <button type="button" class="btn btn-secondary" disabled style="opacity: 0.6; cursor: not-allowed;" title="{{ $project->trashed() ? 'Cannot add tasks to an archived project' : 'Cannot add tasks to a completed project unless reactivated' }}" id="btn-add-first-major-task">
                                    + Add First Major Task
                                </button>
                            @else
                                <button type="button" class="btn btn-primary" @click="createMajorTaskModalOpen = true" id="btn-add-first-major-task">
                                    + Add First Major Task
                                </button>
                            @endif
                        </div>
                    @endcan
                </div>
            @else
                {{-- 1. Stage Carousel View (Minimized View) --}}
                <div x-show="timelineMode === 'minimized'" x-transition>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-2);">
                        <p class="text-muted" style="font-size: 0.875rem; margin-bottom: 0;">
                            Weekly milestones timeline. Click any card to select it, inspect specific sub tasks, and tick checkboxes below.
                        </p>
                        @if ($tasks->count() > 3)
                            <div style="display: flex; gap: var(--space-1); align-items: center;">
                                <button type="button" class="btn btn-secondary btn-sm" @click="scrollCarousel('left')" title="Scroll Previous" style="padding: 2px 8px; font-weight: bold;">
                                    ‹
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" @click="scrollCarousel('right')" title="Scroll Next" style="padding: 2px 8px; font-weight: bold;">
                                    ›
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- Carousel Track --}}
                    <div class="timeline-carousel-container">
                        <div class="timeline-carousel-track" id="timeline-carousel-track">
                            @foreach ($tasks as $taskIndex => $task)
                                @php
                                    $taskProgress = $task->progress_percentage;
                                    $taskStatus = $task->computed_status;
                                    $statusPillClass = $taskStatus === 'completed' ? 'completed' : ($taskStatus === 'in_progress' ? 'ongoing' : 'pending');
                                    $statusLabel = $taskStatus === 'completed' ? 'Completed' : ($taskStatus === 'in_progress' ? 'In Progress' : 'Scheduled');
                                    $subtasksCount = $task->subtasks->count();
                                    $completedCount = $task->subtasks->where('is_completed', true)->count();
                                @endphp
                                <div 
                                    class="timeline-stage-card card" 
                                    :class="{ 'timeline-stage-card--active': activeTaskId === {{ $task->id }} }"
                                    @click="activeTaskId = {{ $task->id }}; checklistMinimized = false"
                                    style="cursor: pointer;"
                                    id="card-task-{{ $task->id }}"
                                >
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                                        <span class="badge" style="background: var(--color-accent-light); color: var(--color-accent); font-weight: 700; font-size: 0.75rem;">
                                            Week {{ $taskIndex + 1 }}
                                        </span>
                                        <span class="status-pill status-pill--{{ $statusPillClass }}" id="card-status-pill-{{ $task->id }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </div>

                                    <h3 style="font-size: 0.9375rem; font-weight: 600; color: var(--color-ink); margin-bottom: var(--space-1); line-height: 1.3;">
                                        {{ $task->task_name }}
                                    </h3>
                                    <p class="font-mono text-muted" style="font-size: 0.75rem; margin-bottom: var(--space-2);">
                                        {{ $task->start_date->format('M j') }} – {{ $task->end_date->format('M j, Y') }} ({{ $task->duration_days }} {{ Str::plural('day', $task->duration_days) }})
                                    </p>
                                    @if ($task->description)
                                        <p style="font-size: 0.8125rem; color: var(--color-muted); line-height: 1.4; margin-bottom: var(--space-3); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                            {{ $task->description }}
                                        </p>
                                    @endif

                                    {{-- Mini progress bar --}}
                                    <div style="margin-top: auto; padding-top: var(--space-2);">
                                        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; margin-bottom: 4px;">
                                            <span class="text-muted" id="card-task-count-{{ $task->id }}">{{ $completedCount }}/{{ $subtasksCount }} tasks</span>
                                            <span class="font-mono font-bold" id="card-progress-text-{{ $task->id }}">{{ $taskProgress }}%</span>
                                        </div>
                                        <div style="width: 100%; height: 6px; background: var(--color-surface-alt); border-radius: var(--radius-pill); overflow: hidden;">
                                            <div 
                                                id="card-progress-bar-{{ $task->id }}"
                                                style="height: 100%; width: {{ $taskProgress }}%; background: {{ $taskStatus === 'completed' ? 'var(--color-success)' : ($taskStatus === 'in_progress' ? 'var(--color-accent)' : 'var(--color-border)') }}; transition: width 0.3s ease;">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Selected Major Task Details & Sub Tasks Checklist --}}
                    @foreach ($tasks as $taskIndex => $task)
                        <div x-show="activeTaskId === {{ $task->id }} && !checklistMinimized" x-cloak style="margin-top: var(--space-6); background: var(--color-surface-alt); padding: var(--space-5); border-radius: var(--radius-lg); border: 1px solid var(--color-border);" id="task-detail-{{ $task->id }}">
                            
                            {{-- Header row with week title, dates, and actions --}}
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: var(--space-3); margin-bottom: var(--space-3);">
                                <div>
                                    <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: 4px;">
                                        <span class="badge" style="background: var(--color-accent-light); color: var(--color-accent); font-weight: 700; font-size: 0.8125rem;">
                                            Week {{ $taskIndex + 1 }} Major Task
                                        </span>
                                        <span class="status-pill status-pill--{{ $task->computed_status === 'completed' ? 'completed' : ($task->computed_status === 'in_progress' ? 'ongoing' : 'pending') }}" id="detail-status-pill-{{ $task->id }}">
                                            {{ $task->computed_status === 'completed' ? 'Completed' : ($task->computed_status === 'in_progress' ? 'In Progress' : 'Scheduled') }}
                                        </span>
                                    </div>
                                    <h3 style="font-size: 1.125rem; font-weight: 700; color: var(--color-ink); margin: 0 0 4px 0;">
                                        {{ $task->task_name }}
                                    </h3>
                                    <span class="font-mono text-muted" style="font-size: 0.8125rem;">
                                        📅 {{ $task->start_date->format('M j, Y') }} — {{ $task->end_date->format('M j, Y') }} ({{ $task->duration_days }} {{ Str::plural('Day', $task->duration_days) }})
                                    </span>
                                </div>

                                <div style="display: flex; gap: var(--space-2); align-items: center;">
                                    <button 
                                        type="button" 
                                        class="btn btn-secondary btn-sm"
                                        @click="checklistMinimized = true"
                                        title="Minimize checklist section"
                                        id="btn-minimize-checklist-{{ $task->id }}"
                                        style="display: inline-flex; align-items: center; gap: 4px;"
                                    >
                                        − Minimize
                                    </button>

                                    @can('update', $project)
                                        @if (!$isProjectReadOnly)
                                            <button 
                                                type="button" 
                                                class="btn btn-secondary btn-sm"
                                                @click="openEditTaskModal({{ json_encode($task) }})"
                                                id="btn-edit-major-task-{{ $task->id }}"
                                            >
                                                ✎ Edit Major Task
                                            </button>
                                            
                                            <button 
                                                type="button" 
                                                class="btn btn-secondary btn-sm" 
                                                style="color: var(--color-danger); border-color: #fad2cf;" 
                                                id="btn-delete-major-task-{{ $task->id }}"
                                                @click="openDeleteTaskModal('{{ route('projects.tasks.destroy', [$project, $task]) }}', '{{ addslashes($task->task_name) }}')"
                                            >
                                                🗑 Delete
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            </div>

                            @if ($task->description)
                                <p style="font-size: 0.875rem; color: var(--color-ink); background: var(--color-surface); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: var(--space-4);">
                                    {{ $task->description }}
                                </p>
                            @endif

                            {{-- Sub Tasks / Specific Tasks Checklist --}}
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-3);">
                                    <h4 style="font-size: 0.9375rem; font-weight: 700; color: var(--color-ink); margin: 0;">
                                        Specific Tasks / Sub Tasks Checklist
                                    </h4>
                                    <span class="text-muted" style="font-size: 0.75rem;">
                                        Tick checkboxes to record granular task completion
                                    </span>
                                </div>

                                @if ($task->subtasks->isEmpty())
                                    <div style="background: var(--color-surface); padding: var(--space-4); border-radius: var(--radius-md); border: 1px dashed var(--color-border); text-align: center; margin-bottom: var(--space-4);">
                                        <p class="text-muted" style="margin-bottom: 0; font-size: 0.875rem;">
                                            No sub tasks added yet for Week {{ $taskIndex + 1 }}. Use the input below to add specific actionable tasks.
                                        </p>
                                    </div>
                                @else
                                    <ul style="list-style: none; display: flex; flex-direction: column; gap: var(--space-2); padding: 0; margin-bottom: var(--space-4);">
                                        @foreach ($task->subtasks as $subtask)
                                            @php
                                                $isSubtaskLocked = $initialSubtaskLocks[$subtask->id] ?? false;
                                                $isSubtaskPending = $initialSubtaskPending[$subtask->id] ?? false;

                                                $subtaskProofFiles = $subtask->files->map(function($f) use ($project) {
                                                    return [
                                                        'id' => $f->id,
                                                        'name' => $f->file_name,
                                                        'size' => $f->formatted_size,
                                                        'url' => route('projects.files.download', [$project, $f]),
                                                        'is_img' => $f->is_image,
                                                        'is_pdf' => $f->is_pdf,
                                                        'is_word' => $f->is_word,
                                                    ];
                                                })->values();

                                                $completedByName = $subtask->completedBy?->name ?? ($subtask->is_completed ? ($project->creator?->name ?? 'Admin / Manager') : null);
                                                $completedByRole = $subtask->completedBy?->getRoleNames()->first() ?? ($subtask->is_completed ? 'Staff / User' : null);

                                                $approvedByName = $subtask->approvedBy?->name ?? ($subtask->is_completed ? ($project->creator?->name ?? 'Direct Sign-off') : null);
                                                $approvedByRole = $subtask->approvedBy?->getRoleNames()->first() ?? ($subtask->is_completed ? 'Manager / Admin' : null);

                                                $subtaskProofPayload = [
                                                    'subtask_id' => $subtask->id,
                                                    'task_name' => $task->task_name,
                                                    'subtask_title' => $subtask->title,
                                                    'is_completed' => (bool) $subtask->is_completed,
                                                    'completed_at' => $subtask->completed_at ? $subtask->completed_at->format('M j, Y · g:i A') : ($subtask->updated_at ? $subtask->updated_at->format('M j, Y · g:i A') : 'Completed'),
                                                    'due_date' => $subtask->due_date ? $subtask->due_date->format('M j, Y') : null,
                                                    'completed_by_name' => $completedByName,
                                                    'completed_by_role' => $completedByRole,
                                                    'approved_by_name' => $approvedByName,
                                                    'approved_by_role' => $approvedByRole,
                                                    'is_direct' => $subtask->completed_by && ($subtask->completed_by === $subtask->approved_by),
                                                    'notes' => $subtask->notes ?? '',
                                                    'files' => $subtaskProofFiles,
                                                ];
                                            @endphp
                                            <li 
                                                style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); background: var(--color-surface); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--color-border); transition: background 0.15s ease;"
                                                :class="{ 
                                                    'subtask-row-locked': !!subtaskLocks[{{ $subtask->id }}] || !!pendingSubtasks[{{ $subtask->id }}],
                                                    'subtask-row-forward-locked': (!!subtaskLocks[{{ $subtask->id }}] || !!pendingSubtasks[{{ $subtask->id }}]) && !subtaskCompleted[{{ $subtask->id }}]
                                                }"
                                                id="subtask-row-{{ $subtask->id }}"
                                                x-data="{ showLockedTooltip: false }"
                                                class="dex-tooltip-wrap"
                                                @mouseleave="showLockedTooltip = false"
                                            >
                                                {{-- Floating Brand-Orange Tooltip on Hover when untickable --}}
                                                <div 
                                                    x-show="showLockedTooltip && (!!subtaskLocks[{{ $subtask->id }}] || !!pendingSubtasks[{{ $subtask->id }}])"
                                                    x-cloak
                                                    class="dex-tooltip-bubble"
                                                >
                                                    <span x-text="pendingSubtasks[{{ $subtask->id }}] ? 'This task is awaiting review by a Manager or Admin.' : (subtaskCompleted[{{ $subtask->id }}] ? 'You cannot uncheck your previous task unless you uncheck the task that comes after it.' : 'You cannot proceed to mark these tasks as in-progress, as you still have previous on-going task.')"></span>
                                                </div>

                                                <label 
                                                    style="display: flex; align-items: center; gap: var(--space-3); flex: 1; margin-bottom: 0; cursor: {{ ($isProjectReadOnly || $isSubtaskLocked || $isSubtaskPending) ? 'not-allowed' : 'pointer' }};"
                                                    :style="(subtaskLocks[{{ $subtask->id }}] || pendingSubtasks[{{ $subtask->id }}]) ? 'cursor: not-allowed !important;' : ''"
                                                    @mouseenter="if (subtaskLocks[{{ $subtask->id }}] || pendingSubtasks[{{ $subtask->id }}]) showLockedTooltip = true"
                                                    @click="if (subtaskLocks[{{ $subtask->id }}] || pendingSubtasks[{{ $subtask->id }}]) showLockedTooltip = true"
                                                >
                                                    <input 
                                                        type="checkbox" 
                                                        class="subtask-checkbox subtask-check-{{ $subtask->id }}"
                                                        :class="{
                                                            'is-forward-locked': (!!subtaskLocks[{{ $subtask->id }}] || !!pendingSubtasks[{{ $subtask->id }}]) && !subtaskCompleted[{{ $subtask->id }}],
                                                            'is-reverse-locked': !!subtaskLocks[{{ $subtask->id }}] && !!subtaskCompleted[{{ $subtask->id }}]
                                                        }"
                                                        id="subtask-check-{{ $subtask->id }}"
                                                        {{ $subtask->is_completed ? 'checked' : '' }}
                                                        {{ ($isProjectReadOnly || $isSubtaskLocked || $isSubtaskPending || ($subtask->is_completed && Auth::user()->hasRole('Staff'))) ? 'disabled' : '' }}
                                                        :disabled="{{ ($isProjectReadOnly || ($subtask->is_completed && Auth::user()->hasRole('Staff'))) ? 'true' : '!!subtaskLocks[' . $subtask->id . '] || !!pendingSubtasks[' . $subtask->id . ']' }}"
                                                        @change="onSubtaskCheckboxClick({{ $project->id }}, {{ $task->id }}, {{ $subtask->id }}, '{{ addslashes($task->task_name) }}', '{{ addslashes($subtask->title) }}', $el)"
                                                        style="width: 18px; height: 18px; accent-color: var(--color-success); cursor: {{ ($isProjectReadOnly || $isSubtaskLocked || $isSubtaskPending || ($subtask->is_completed && Auth::user()->hasRole('Staff'))) ? 'not-allowed' : 'pointer' }};"
                                                        :style="(subtaskLocks[{{ $subtask->id }}] || pendingSubtasks[{{ $subtask->id }}] || {{ ($subtask->is_completed && Auth::user()->hasRole('Staff')) ? 'true' : 'false' }}) ? 'cursor: not-allowed !important;' : ''"
                                                        title="{{ $isSubtaskPending ? 'This task is awaiting review by a Manager or Admin' : (($subtask->is_completed && Auth::user()->hasRole('Staff')) ? 'Staff users cannot uncheck completed tasks' : '') }}"
                                                    >
                                                    <span 
                                                        class="subtask-title-{{ $subtask->id }}"
                                                        style="font-size: 0.875rem; {{ $subtask->is_completed ? 'text-decoration: line-through; color: var(--color-muted);' : 'color: var(--color-ink); font-weight: 500;' }}"
                                                        :style="((subtaskLocks[{{ $subtask->id }}] || pendingSubtasks[{{ $subtask->id }}]) && !subtaskCompleted[{{ $subtask->id }}]) ? 'color: var(--color-muted) !important;' : ''"
                                                    >
                                                        {{ $subtask->title }}
                                                    </span>
                                                    <span
                                                        x-show="!!pendingSubtasks[{{ $subtask->id }}] && !subtaskCompleted[{{ $subtask->id }}]"
                                                        x-cloak
                                                        class="status-pill status-pill--ongoing"
                                                        style="font-size: 0.6875rem; padding: 1px 8px;"
                                                        title="Submitted by staff — awaiting Admin or Manager review"
                                                    >
                                                        Pending Review
                                                    </span>
                                                </label>

                                                <div style="display: flex; align-items: center; gap: var(--space-2); flex-shrink: 0;">
                                                    @if ($subtask->due_date)
                                                        <span class="badge font-mono" style="background: var(--color-surface-alt); color: var(--color-ink); font-size: 0.75rem; padding: 2px 8px; border: 1px solid var(--color-border);" title="Target Date">
                                                            📅 {{ $subtask->due_date->format('M j, Y') }}
                                                        </span>
                                                    @endif

                                                    {{-- Proof of Completion / View Files Modal Trigger --}}
                                                    <div id="subtask-proof-container-{{ $subtask->id }}" style="display: inline-flex; align-items: center; gap: var(--space-1); {{ $subtask->is_completed ? '' : 'display: none;' }}">
                                                        @if ($subtask->is_completed)
                                                            <button 
                                                                type="button" 
                                                                class="btn btn-secondary btn-sm" 
                                                                style="padding: 2px 8px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; border-color: var(--color-border); color: var(--color-ink);"
                                                                title="View Task Completion Details & Proofs"
                                                                @click="openTaskProofModal(@js($subtaskProofPayload))"
                                                                id="btn-proof-subtask-{{ $subtask->id }}"
                                                            >
                                                                <svg style="width: 13px; height: 13px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                                    <circle cx="12" cy="12" r="3"></circle>
                                                                </svg>
                                                                <span>View</span>
                                                            </button>
                                                        @endif
                                                    </div>

                                                    @can('update', $project)
                                                        @if (!$isProjectReadOnly)
                                                            <button 
                                                                type="button" 
                                                                class="btn-icon" 
                                                                title="Edit Sub Task"
                                                                x-show="!pendingSubtasks[{{ $subtask->id }}]"
                                                                @click="openEditSubtaskModal({{ $task->id }}, {{ $subtask->id }}, '{{ addslashes($subtask->title) }}', '{{ $subtask->due_date ? $subtask->due_date->format('Y-m-d') : '' }}', '{{ $task->start_date->format('Y-m-d') }}', '{{ $task->end_date->format('Y-m-d') }}')"
                                                                style="background: none; border: none; cursor: pointer; color: var(--color-muted); padding: 4px; border-radius: var(--radius-sm);"
                                                                id="btn-edit-subtask-{{ $subtask->id }}"
                                                            >
                                                                <svg style="width: 15px; height: 15px;" viewBox="0 0 20 20" fill="currentColor">
                                                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                                </svg>
                                                            </button>

                                                            <form 
                                                                method="POST" 
                                                                action="{{ route('projects.tasks.subtasks.destroy', [$project, $task, $subtask]) }}" 
                                                                onsubmit="return confirm('Delete this specific sub task?');" 
                                                                class="inline-form"
                                                            >
                                                                @csrf
                                                                @method('DELETE')
                                                                <button 
                                                                    type="submit" 
                                                                    class="btn-icon" 
                                                                    title="Delete Sub Task"
                                                                    style="background: none; border: none; cursor: pointer; color: var(--color-danger); padding: 4px; border-radius: var(--radius-sm);"
                                                                    id="btn-delete-subtask-{{ $subtask->id }}"
                                                                >
                                                                    <svg style="width: 15px; height: 15px;" viewBox="0 0 20 20" fill="currentColor">
                                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                                    </svg>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    @endcan
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                {{-- Quick Add Sub Task Form --}}
                                @can('update', $project)
                                    @if ($isProjectReadOnly)
                                        <div style="display: flex; gap: var(--space-2); margin-top: var(--space-3); flex-wrap: wrap;">
                                            <input 
                                                type="text" 
                                                placeholder="+ Add a specific sub task for Week {{ $taskIndex + 1 }}..." 
                                                disabled 
                                                class="form-control form-control--compact" 
                                                style="flex: 2; min-width: 220px; opacity: 0.6; cursor: not-allowed;"
                                            >
                                            <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.6; cursor: not-allowed;" title="{{ $project->trashed() ? 'Cannot add sub tasks to an archived project' : 'Cannot add sub tasks to a completed project unless reactivated' }}">
                                                + Add Sub Task
                                            </button>
                                        </div>
                                    @else
                                        <form 
                                            method="POST" 
                                            action="{{ route('projects.tasks.subtasks.store', [$project, $task]) }}" 
                                            style="display: flex; gap: var(--space-2); margin-top: var(--space-3); flex-wrap: wrap;"
                                        >
                                            @csrf
                                            <input 
                                                type="text" 
                                                name="title" 
                                                placeholder="+ Add a specific sub task for Week {{ $taskIndex + 1 }}..." 
                                                required 
                                                class="form-control form-control--compact" 
                                                style="flex: 2; min-width: 220px;"
                                            >
                                            <div style="display: flex; align-items: center; gap: var(--space-1); flex-wrap: wrap;">
                                                <label class="form-hint" style="margin-bottom: 0; font-size: 0.75rem; white-space: nowrap;">Target Date (Week: {{ $task->start_date->format('M j') }}–{{ $task->end_date->format('M j') }}):</label>
                                                <input 
                                                    type="date" 
                                                    name="due_date" 
                                                    class="form-control form-control--compact" 
                                                    style="width: 150px;"
                                                    value="{{ $task->start_date->format('Y-m-d') }}"
                                                    min="{{ $task->start_date->format('Y-m-d') }}"
                                                    max="{{ $task->end_date->format('Y-m-d') }}"
                                                    title="Allowed dates: {{ $task->start_date->format('M j, Y') }} to {{ $task->end_date->format('M j, Y') }}"
                                                >
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                + Add Sub Task
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- 2. Expanded View: Full Weekly Breakdown Timeline --}}
                <div x-show="timelineMode === 'expanded'" x-cloak x-transition>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-2);">
                        <p class="text-muted" style="font-size: 0.875rem; margin-bottom: 0;">
                            Sequential timeline breakdown from project kickoff to target completion.
                        </p>
                        <button type="button" class="btn btn-secondary btn-sm" @click="timelineMode = 'minimized'">
                            Collapse to Stage Carousel
                        </button>
                    </div>

                    <div class="timeline-weekly-container">
                        @foreach ($tasks as $taskIndex => $task)
                            @php
                                $isCompleted = ($task->computed_status === 'completed');
                                $isInProgress = ($task->computed_status === 'in_progress');
                                $statusClass = $isCompleted ? 'completed' : ($isInProgress ? 'ongoing' : 'pending');
                                $statusLabel = $isCompleted ? 'Completed' : ($isInProgress ? 'In Progress' : 'Scheduled');
                            @endphp
                            <div class="timeline-week-row {{ $isInProgress ? 'timeline-week-row--current' : '' }}">
                                <div class="timeline-week-indicator">
                                    <div class="timeline-week-dot {{ $isCompleted ? 'timeline-week-dot--passed' : ($isInProgress ? 'timeline-week-dot--current' : '') }}" id="expanded-week-dot-{{ $task->id }}" data-week-num="{{ $taskIndex + 1 }}">
                                        @if ($isCompleted)
                                            ✓
                                        @else
                                            {{ $taskIndex + 1 }}
                                        @endif
                                    </div>
                                    @if ($taskIndex < $tasks->count() - 1)
                                        <div class="timeline-week-line {{ $isCompleted ? 'timeline-week-line--passed' : '' }}"></div>
                                    @endif
                                </div>

                                <div class="timeline-week-content card" style="flex: 1; margin-bottom: var(--space-4);">
                                    <div class="card-header" style="padding: var(--space-3) var(--space-4);">
                                        <div style="display: flex; align-items: center; gap: var(--space-3); flex-wrap: wrap;">
                                            <strong style="font-size: 0.9375rem;">Week {{ $taskIndex + 1 }}: {{ $task->task_name }}</strong>
                                            <span class="font-mono text-muted" style="font-size: 0.8125rem;">
                                                {{ $task->start_date->format('M j') }} – {{ $task->end_date->format('M j, Y') }}
                                            </span>
                                            <span class="badge" style="background: var(--color-surface-alt); font-size: 0.6875rem;">
                                                {{ $task->duration_days }} {{ Str::plural('Day', $task->duration_days) }}
                                            </span>
                                        </div>
                                        <span class="status-pill status-pill--{{ $statusClass }}" id="expanded-status-pill-{{ $task->id }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </div>
                                    <div class="card-body" style="padding: var(--space-4);">
                                        @if ($task->description)
                                            <p style="font-size: 0.875rem; color: var(--color-ink); margin-bottom: var(--space-3);">
                                                {{ $task->description }}
                                            </p>
                                        @endif

                                        <div style="margin-bottom: var(--space-3);">
                                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; margin-bottom: 4px;">
                                                <span class="text-muted" id="expanded-progress-count-{{ $task->id }}">Progress ({{ $task->subtasks->where('is_completed', true)->count() }}/{{ $task->subtasks->count() }} tasks)</span>
                                                <span class="font-mono font-bold" id="expanded-progress-text-{{ $task->id }}">{{ $task->progress_percentage }}%</span>
                                            </div>
                                            <div style="width: 100%; height: 6px; background: var(--color-surface-alt); border-radius: var(--radius-pill); overflow: hidden;">
                                                <div id="expanded-progress-bar-{{ $task->id }}" style="height: 100%; width: {{ $task->progress_percentage }}%; background: {{ $isCompleted ? 'var(--color-success)' : ($isInProgress ? 'var(--color-accent)' : 'var(--color-border)') }}; transition: width 0.3s ease;"></div>
                                            </div>
                                        </div>

                                        {{-- Sub tasks checklist in expanded view --}}
                                        @if ($task->subtasks->isNotEmpty())
                                            <div style="margin-top: var(--space-3); border-top: 1px solid var(--color-surface-alt); padding-top: var(--space-3);">
                                                <span style="font-size: 0.75rem; font-weight: 700; color: var(--color-muted); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: var(--space-2);">
                                                    Specific Sub Tasks:
                                                </span>
                                                <ul style="list-style: none; display: flex; flex-direction: column; gap: var(--space-1); padding: 0; margin-bottom: 0;">
                                                    @foreach ($task->subtasks as $subtask)
                                                        @php
                                                            $isSubtaskLocked = $initialSubtaskLocks[$subtask->id] ?? false;
                                                            $isSubtaskPending = $initialSubtaskPending[$subtask->id] ?? false;

                                                            $subtaskProofFiles = $subtask->files->map(function($f) use ($project) {
                                                                return [
                                                                    'id' => $f->id,
                                                                    'name' => $f->file_name,
                                                                    'size' => $f->formatted_size,
                                                                    'url' => route('projects.files.download', [$project, $f]),
                                                                    'is_img' => $f->is_image,
                                                                    'is_pdf' => $f->is_pdf,
                                                                    'is_word' => $f->is_word,
                                                                ];
                                                            })->values();

                                                            $completedByName = $subtask->completedBy?->name ?? ($subtask->is_completed ? ($project->creator?->name ?? 'Admin / Manager') : null);
                                                            $completedByRole = $subtask->completedBy?->getRoleNames()->first() ?? ($subtask->is_completed ? 'Staff / User' : null);

                                                            $approvedByName = $subtask->approvedBy?->name ?? ($subtask->is_completed ? ($project->creator?->name ?? 'Direct Sign-off') : null);
                                                            $approvedByRole = $subtask->approvedBy?->getRoleNames()->first() ?? ($subtask->is_completed ? 'Manager / Admin' : null);

                                                            $subtaskProofPayload = [
                                                                'subtask_id' => $subtask->id,
                                                                'task_name' => $task->task_name,
                                                                'subtask_title' => $subtask->title,
                                                                'is_completed' => (bool) $subtask->is_completed,
                                                                'completed_at' => $subtask->completed_at ? $subtask->completed_at->format('M j, Y · g:i A') : ($subtask->updated_at ? $subtask->updated_at->format('M j, Y · g:i A') : 'Completed'),
                                                                'due_date' => $subtask->due_date ? $subtask->due_date->format('M j, Y') : null,
                                                                'completed_by_name' => $completedByName,
                                                                'completed_by_role' => $completedByRole,
                                                                'approved_by_name' => $approvedByName,
                                                                'approved_by_role' => $approvedByRole,
                                                                'is_direct' => $subtask->completed_by && ($subtask->completed_by === $subtask->approved_by),
                                                                'notes' => $subtask->notes ?? '',
                                                                'files' => $subtaskProofFiles,
                                                            ];
                                                        @endphp
                                                        <li 
                                                            style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); font-size: 0.8125rem;"
                                                            :class="{ 
                                                                'subtask-row-locked': !!subtaskLocks[{{ $subtask->id }}] || !!pendingSubtasks[{{ $subtask->id }}],
                                                                'subtask-row-forward-locked': (!!subtaskLocks[{{ $subtask->id }}] || !!pendingSubtasks[{{ $subtask->id }}]) && !subtaskCompleted[{{ $subtask->id }}]
                                                            }"
                                                            id="expanded-subtask-row-{{ $subtask->id }}"
                                                            x-data="{ showExpandedLockedTooltip: false }"
                                                            class="dex-tooltip-wrap"
                                                            @mouseleave="showExpandedLockedTooltip = false"
                                                        >
                                                            {{-- Floating Brand-Orange Tooltip on Hover when untickable --}}
                                                            <div 
                                                                x-show="showExpandedLockedTooltip && (!!subtaskLocks[{{ $subtask->id }}] || !!pendingSubtasks[{{ $subtask->id }}])"
                                                                x-cloak
                                                                class="dex-tooltip-bubble"
                                                            >
                                                                <span x-text="pendingSubtasks[{{ $subtask->id }}] ? 'This task is awaiting review by a Manager or Admin.' : (subtaskCompleted[{{ $subtask->id }}] ? 'You cannot uncheck your previous task unless you uncheck the task that comes after it.' : 'You cannot proceed to mark these tasks as in-progress, as you still have previous on-going task.')"></span>
                                                            </div>

                                                            <label 
                                                                style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: 0; cursor: {{ ($isProjectReadOnly || $isSubtaskLocked || $isSubtaskPending) ? 'not-allowed' : 'pointer' }}; flex: 1;"
                                                                :style="(subtaskLocks[{{ $subtask->id }}] || pendingSubtasks[{{ $subtask->id }}]) ? 'cursor: not-allowed !important;' : ''"
                                                                @mouseenter="if (subtaskLocks[{{ $subtask->id }}] || pendingSubtasks[{{ $subtask->id }}]) showExpandedLockedTooltip = true"
                                                                @click="if (subtaskLocks[{{ $subtask->id }}] || pendingSubtasks[{{ $subtask->id }}]) showExpandedLockedTooltip = true"
                                                            >
                                                                <input 
                                                                    type="checkbox" 
                                                                    class="subtask-checkbox subtask-check-{{ $subtask->id }}"
                                                                    :class="{
                                                                        'is-forward-locked': (!!subtaskLocks[{{ $subtask->id }}] || !!pendingSubtasks[{{ $subtask->id }}]) && !subtaskCompleted[{{ $subtask->id }}],
                                                                        'is-reverse-locked': !!subtaskLocks[{{ $subtask->id }}] && !!subtaskCompleted[{{ $subtask->id }}]
                                                                    }"
                                                                    id="expanded-subtask-check-{{ $subtask->id }}"
                                                                    {{ $subtask->is_completed ? 'checked' : '' }}
                                                                    {{ ($isProjectReadOnly || $isSubtaskLocked || $isSubtaskPending || ($subtask->is_completed && Auth::user()->hasRole('Staff'))) ? 'disabled' : '' }}
                                                                    :disabled="{{ ($isProjectReadOnly || ($subtask->is_completed && Auth::user()->hasRole('Staff'))) ? 'true' : '!!subtaskLocks[' . $subtask->id . '] || !!pendingSubtasks[' . $subtask->id . ']' }}"
                                                                    @change="onSubtaskCheckboxClick({{ $project->id }}, {{ $task->id }}, {{ $subtask->id }}, '{{ addslashes($task->task_name) }}', '{{ addslashes($subtask->title) }}', $el)"
                                                                    style="width: 15px; height: 15px; accent-color: var(--color-success); cursor: {{ ($isProjectReadOnly || $isSubtaskLocked || $isSubtaskPending || ($subtask->is_completed && Auth::user()->hasRole('Staff'))) ? 'not-allowed' : 'pointer' }};"
                                                                    :style="(subtaskLocks[{{ $subtask->id }}] || pendingSubtasks[{{ $subtask->id }}] || {{ ($subtask->is_completed && Auth::user()->hasRole('Staff')) ? 'true' : 'false' }}) ? 'cursor: not-allowed !important;' : ''"
                                                                    title="{{ $isSubtaskPending ? 'This task is awaiting review by a Manager or Admin' : (($subtask->is_completed && Auth::user()->hasRole('Staff')) ? 'Staff users cannot uncheck completed tasks' : '') }}"
                                                                >
                                                                <span 
                                                                    class="subtask-title-{{ $subtask->id }}"
                                                                    style="{{ $subtask->is_completed ? 'text-decoration: line-through; color: var(--color-muted);' : 'color: var(--color-ink);' }}"
                                                                    :style="((subtaskLocks[{{ $subtask->id }}] || pendingSubtasks[{{ $subtask->id }}]) && !subtaskCompleted[{{ $subtask->id }}]) ? 'color: var(--color-muted) !important;' : ''"
                                                                >
                                                                    {{ $subtask->title }}
                                                                </span>
                                                                <span
                                                                    x-show="!!pendingSubtasks[{{ $subtask->id }}] && !subtaskCompleted[{{ $subtask->id }}]"
                                                                    x-cloak
                                                                    class="status-pill status-pill--ongoing"
                                                                    style="font-size: 0.6875rem; padding: 1px 8px;"
                                                                    title="Submitted by staff — awaiting Admin or Manager review"
                                                                >
                                                                    Pending Review
                                                                </span>
                                                            </label>
                                                            <div style="display: flex; align-items: center; gap: var(--space-2);">
                                                                @if ($subtask->due_date)
                                                                    <span class="badge font-mono" style="background: var(--color-surface-alt); color: var(--color-muted); font-size: 0.6875rem;">
                                                                        📅 {{ $subtask->due_date->format('M j') }}
                                                                    </span>
                                                                @endif

                                                                {{-- Proof of Completion / View Files Modal Trigger --}}
                                                                <div id="expanded-subtask-proof-container-{{ $subtask->id }}" style="display: inline-flex; align-items: center; gap: var(--space-1); {{ $subtask->is_completed ? '' : 'display: none;' }}">
                                                                    @if ($subtask->is_completed)
                                                                        <button 
                                                                            type="button" 
                                                                            class="btn btn-secondary btn-sm" 
                                                                            style="padding: 1px 6px; font-size: 0.6875rem; display: inline-flex; align-items: center; gap: 4px; border-color: var(--color-border); color: var(--color-ink);"
                                                                            title="View Task Completion Details & Proofs"
                                                                            @click="openTaskProofModal(@js($subtaskProofPayload))"
                                                                            id="btn-expanded-proof-subtask-{{ $subtask->id }}"
                                                                        >
                                                                            <svg style="width: 12px; height: 12px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                                                <circle cx="12" cy="12" r="3"></circle>
                                                                            </svg>
                                                                            <span>View</span>
                                                                        </button>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    {{-- ─── Expenditures Table (3 Recent Entries Shortcut) ────────────────────── --}}
    <section class="card card-glass mb-6" aria-labelledby="project-expenditures-heading">
        <div
            class="card-header"
            style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);"
            :style="expendituresMinimized ? 'border-bottom: none;' : ''"
        >
            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: var(--space-2);">
                <h2 id="project-expenditures-heading" style="margin: 0; font-size: 1.0625rem; font-weight: 700;">Expenditures</h2>
                <span class="text-muted" style="font-size: 0.8125rem;">
                    Total Spend: <strong class="font-mono text-dark">₱{{ number_format($project->actual_spend, 2) }}</strong> ({{ $project->costs->count() }} {{ Str::plural('entry', $project->costs->count()) }})
                </span>
                <span class="badge" style="background: var(--color-surface-alt); border: 1px solid var(--color-border); font-size: 0.75rem; font-weight: 600;">
                    Recent 3 Entries
                </span>
            </div>
            <div style="display: inline-flex; flex-direction: row; gap: var(--space-2); align-items: center; flex-wrap: nowrap; margin-left: auto;">
                <a href="{{ route('costing.show', $project) }}" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; white-space: nowrap;">
                    Full Breakdown ↗
                </a>
                @if(Auth::user()->hasAnyRole(['Admin', 'Manager', 'Staff']))
                    @if ($isProjectReadOnly)
                        <button type="button" class="btn btn-secondary btn-sm" disabled style="display: inline-flex; align-items: center; opacity: 0.6; cursor: not-allowed; white-space: nowrap;" title="{{ $project->trashed() ? 'Cannot log expenses on an archived project' : 'Cannot log expenses on a completed project unless reactivated' }}">
                            + Log New Expense / Cost
                        </button>
                    @else
                        <button type="button" class="btn btn-primary btn-sm" @click="openAddCostModal()" style="display: inline-flex; align-items: center; white-space: nowrap;">
                            + Log New Expense / Cost
                        </button>
                    @endif
                @endif
                <button
                    type="button"
                    class="btn btn-secondary btn-sm"
                    @click="expendituresMinimized = !expendituresMinimized"
                    :aria-expanded="!expendituresMinimized"
                    id="btn-toggle-expenditures"
                    style="display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;"
                >
                    <span x-show="expendituresMinimized">⊞ Expand</span>
                    <span x-show="!expendituresMinimized" x-cloak>⊟ Minimize</span>
                </button>
            </div>
        </div>

        @php
            $unifiedExpenditures = collect();

            foreach (($pendingExpenses ?? []) as $pe) {
                $incurredDate = !empty($pe->payload['incurred_date']) 
                    ? \Carbon\Carbon::parse($pe->payload['incurred_date']) 
                    : $pe->created_at;
                $unifiedExpenditures->push([
                    'is_pending' => true,
                    'description' => $pe->payload['description'] ?? 'Expense Entry',
                    'cost_type' => $pe->payload['cost_type'] ?? 'materials',
                    'resource_name' => $pe->payload['resource_name'] ?? null,
                    'quantity' => $pe->payload['quantity'] ?? 1,
                    'amount' => (float) ($pe->payload['amount'] ?? 0),
                    'date_display' => $incurredDate ? $incurredDate->format('M j, Y') : $pe->created_at->format('M j, Y'),
                    'sort_time' => $pe->created_at ? $pe->created_at->timestamp : 0,
                    'logged_by' => $pe->user ? $pe->user->name : 'Staff',
                    'model' => $pe,
                ]);
            }

            foreach ($project->costs as $c) {
                $incurredDate = $c->incurred_date ?: $c->created_at;
                $unifiedExpenditures->push([
                    'is_pending' => false,
                    'description' => $c->description,
                    'cost_type' => $c->cost_type,
                    'resource_name' => null,
                    'quantity' => $c->quantity ?? 1,
                    'amount' => (float) $c->amount,
                    'date_display' => $incurredDate ? $incurredDate->format('M j, Y') : $c->created_at->format('M j, Y'),
                    'sort_time' => $c->created_at ? $c->created_at->timestamp : 0,
                    'logged_by' => $c->creator ? $c->creator->name : 'System',
                    'model' => $c,
                ]);
            }

            $recentExpenditures = $unifiedExpenditures->sortByDesc('sort_time')->take(3);
            $totalExpendituresCount = $unifiedExpenditures->count();
        @endphp

        <div x-show="!expendituresMinimized" x-transition x-cloak>
        @if ($recentExpenditures->isEmpty())
            <div class="card-body">
                <div class="empty-state" style="padding: var(--space-6) var(--space-4);">
                    <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <h3 class="empty-state__title">No itemized costs logged yet</h3>
                    <p class="empty-state__description">
                        Manually type down what each cost is for (materials, labor, equipment rental, permits) to keep an accurate spend ledger.
                    </p>
                    @if(Auth::user()->hasAnyRole(['Admin', 'Manager', 'Staff']))
                        @if ($isProjectReadOnly)
                            <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.6; cursor: not-allowed;" title="{{ $project->trashed() ? 'Cannot log expenses on an archived project' : 'Cannot log expenses on a completed project unless reactivated' }}">
                                + Log First Cost Entry
                            </button>
                        @else
                            <button type="button" class="btn btn-primary btn-sm" @click="openAddCostModal()">
                                + Log First Cost Entry
                            </button>
                        @endif
                    @endif
                </div>
            </div>
        @else
            {{-- Indicator banner for recent entries and pending items --}}
            <div style="background: var(--color-surface-alt); padding: var(--space-2) var(--space-6); border-bottom: 1px solid var(--color-border); font-size: 0.8125rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <span class="text-muted">
                    📌 <strong>Quick View:</strong> Displaying the <strong>{{ $recentExpenditures->count() }} most recent expenditures</strong> for this project.
                </span>
                @if (($pendingExpenses ?? collect())->isNotEmpty())
                    <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); font-size: 0.6875rem; font-weight: 600;">
                        ⏳ {{ $pendingExpenses->count() }} {{ Str::plural('Expense', $pendingExpenses->count()) }} Pending Review
                    </span>
                @endif
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <caption class="visually-hidden">Itemized costs logged for {{ $project->name }}</caption>
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
                        @foreach ($recentExpenditures as $exp)
                            @if ($exp['is_pending'])
                                <tr 
                                    class="expense-row-pending"
                                    style="opacity: 0.65; background: var(--color-surface-alt); transition: opacity 0.2s ease;"
                                    onmouseover="this.style.opacity='0.85'" 
                                    onmouseout="this.style.opacity='0.65'"
                                >
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                            <strong style="color: var(--color-muted);">{{ $exp['description'] }}</strong>
                                            <span class="status-pill status-pill--ongoing" style="font-size: 0.6875rem; padding: 1px 8px; background: rgba(245, 158, 11, 0.15); color: #d97706; border-color: rgba(245, 158, 11, 0.3);" title="Awaiting Manager or Admin approval">
                                                Pending Review
                                            </span>
                                        </div>
                                        @if(!empty($exp['resource_name']))
                                            <div style="font-size: 0.75rem; color: var(--color-muted);">Catalog: {{ $exp['resource_name'] }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge" style="background: var(--color-surface); text-transform: capitalize; color: var(--color-muted); font-weight: 600;">
                                            {{ $exp['cost_type'] }}
                                        </span>
                                    </td>
                                    <td class="text-center font-mono font-bold" style="color: var(--color-muted);">
                                        {{ $exp['quantity'] }}
                                    </td>
                                    <td class="cell-numeric font-mono" style="color: var(--color-muted); font-style: italic;">
                                        ₱{{ number_format($exp['amount'], 2) }}
                                    </td>
                                    <td class="font-mono" style="color: var(--color-muted);">
                                        {{ $exp['date_display'] }}
                                    </td>
                                    <td style="color: var(--color-muted);">
                                        {{ $exp['logged_by'] }}
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
                            @else
                                @php $cost = $exp['model']; @endphp
                                <tr>
                                    <td><strong>{{ $cost->description }}</strong></td>
                                    <td>
                                        <span class="badge" style="background: var(--color-surface-alt); text-transform: capitalize;">
                                            {{ $cost->cost_type }}
                                        </span>
                                    </td>
                                    <td class="text-center font-mono font-bold">
                                        {{ $cost->quantity ?? 1 }}
                                    </td>
                                    <td class="cell-numeric text-success">
                                        ₱{{ number_format($cost->amount, 2) }}
                                    </td>
                                    <td class="font-mono">
                                        {{ $exp['date_display'] }}
                                    </td>
                                    <td>{{ $cost->creator ? $cost->creator->name : 'System' }}</td>
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
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($totalExpendituresCount > 3)
                <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8125rem; background: var(--color-surface-alt); flex-wrap: wrap; gap: 8px;">
                    <span class="text-muted">
                        Showing {{ $recentExpenditures->count() }} most recent expenditures (out of {{ $totalExpendituresCount }} total logged).
                    </span>
                    <a href="{{ route('costing.show', $project) }}" class="table-link font-bold">View Complete History in Full Breakdown ↗</a>
                </div>
            @endif
        @endif
        </div>
    </section>

    {{-- ─── Assigned Manpower / Project Personnel (Active & Archived Records) ─── --}}
    <section class="card card-glass mb-6" aria-labelledby="assigned-manpower-heading">
        <div
            class="card-header"
            style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);"
            :style="manpowerMinimized ? 'border-bottom: none;' : ''"
        >
            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: var(--space-2);">
                <h2 id="assigned-manpower-heading" style="margin: 0; font-size: 1.0625rem; font-weight: 700;">
                    @if ($project->status === 'completed')
                        Manpower & Construction Team (Archived)
                    @else
                        Assigned Manpower
                    @endif
                </h2>
                @if ($project->status === 'completed')
                    <span class="badge" style="background: rgba(34, 197, 94, 0.15); color: var(--color-success); font-weight: 600;">
                        {{ $project->personnelHistory->unique('personnel_id')->count() }} Total {{ Str::plural('Contributor', $project->personnelHistory->unique('personnel_id')->count()) }}
                    </span>
                @else
                    <span class="text-muted" style="font-size: 0.8125rem;">({{ $project->personnel->count() }} {{ Str::plural('personnel', $project->personnel->count()) }})</span>
                @endif
            </div>
            <div style="display: inline-flex; flex-direction: row; gap: var(--space-2); align-items: center; flex-wrap: nowrap; margin-left: auto;">
                @if ($project->status !== 'completed')
                    @can('update', $project)
                        @if ($project->trashed())
                            <button type="button" class="btn btn-secondary btn-sm" disabled style="display: inline-flex; align-items: center; opacity: 0.6; cursor: not-allowed; white-space: nowrap;" title="Cannot assign personnel to an archived project" id="btn-assign-personnel">
                                + Assign Personnel
                            </button>
                        @else
                            <button type="button" class="btn btn-primary btn-sm" @click="assignModalOpen = true" style="display: inline-flex; align-items: center; white-space: nowrap;" id="btn-assign-personnel">
                                + Assign Personnel
                            </button>
                        @endif
                    @endcan
                @endif
                <button
                    type="button"
                    class="btn btn-secondary btn-sm"
                    @click="manpowerMinimized = !manpowerMinimized"
                    :aria-expanded="!manpowerMinimized"
                    id="btn-toggle-manpower"
                    style="display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;"
                >
                    <span x-show="manpowerMinimized">⊞ Expand</span>
                    <span x-show="!manpowerMinimized" x-cloak>⊟ Minimize</span>
                </button>
            </div>
        </div>

        <div x-show="!manpowerMinimized" x-transition x-cloak>
        @if ($project->status === 'completed')
            {{-- Completed & Archived Callout Banner --}}
            <div style="background: var(--color-surface-alt); padding: var(--space-3) var(--space-6); border-bottom: 1px solid var(--color-border); font-size: 0.875rem;">
                <div style="display: flex; align-items: center; gap: var(--space-2); color: var(--color-success); font-weight: 600;">
                    <svg style="width: 18px; height: 18px; flex-shrink: 0;" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>Project Completed & Archived</span>
                </div>
                <p class="text-muted" style="margin-top: 4px; margin-bottom: 0; font-size: 0.8125rem;">
                    All assigned manpower have been released and returned to the active manpower pool. Below is the historical register of all field personnel who helped in this construction project.
                </p>
            </div>

            @if ($project->personnelHistory->isEmpty())
                <div class="card-body">
                    <div class="empty-state" style="padding: var(--space-6) var(--space-4);">
                        <p class="empty-state__description">No historical manpower records logged for this project.</p>
                    </div>
                </div>
            @else
                <div class="table-wrapper">
                    <table class="data-table" id="archived-personnel-table">
                        <caption class="visually-hidden">Manpower history for completed project {{ $project->name }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">Employee ID</th>
                                <th scope="col">Name</th>
                                <th scope="col">Expertise / Role</th>
                                <th scope="col">Assigned Date</th>
                                <th scope="col">Release Date</th>
                                <th scope="col" class="text-right">Current Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($project->personnelHistory as $history)
                                @if ($history->personnel)
                                    <tr>
                                        <td><span class="font-mono font-bold" style="color: var(--color-accent);">{{ $history->personnel->employee_id }}</span></td>
                                        <td><strong>{{ $history->personnel->full_name }}</strong></td>
                                        <td>{{ $history->personnel->expertise }}</td>
                                        <td class="font-mono">
                                            {{ $history->assigned_at ? $history->assigned_at->format('M j, Y') : '—' }}
                                        </td>
                                        <td class="font-mono">
                                            @if ($history->released_at)
                                                <span>{{ $history->released_at->format('M j, Y') }}</span>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    {{ $history->release_reason === 'project_completed' ? 'Project Completed' : ($history->release_reason === 'unassigned' ? 'Unassigned' : 'Released') }}
                                                </div>
                                            @else
                                                <span class="text-muted">Active during construction</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <span class="badge" style="background: var(--color-surface-alt); border: 1px solid var(--color-border); font-weight: 600; color: {{ $history->personnel->project_id ? 'var(--color-accent)' : 'var(--color-success)' }};">
                                                {{ $history->personnel->project_id ? 'Assigned to Another Project' : 'Available for Work' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        @else
            {{-- Active Project Manpower --}}
            @if ($project->personnel->isEmpty())
                <div class="card-body">
                    <div class="empty-state" style="padding: var(--space-8) var(--space-4);">
                        <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                        <h3 class="empty-state__title">No manpower assigned yet</h3>
                        <p class="empty-state__description">
                            Assign field workers, engineers, and foremen to track project manpower.
                        </p>
                        @can('update', $project)
                            @if ($project->trashed())
                                <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.6; cursor: not-allowed;" title="Cannot assign manpower to an archived project">
                                    + Assign First Worker
                                </button>
                            @else
                                <button type="button" class="btn btn-primary btn-sm" @click="assignModalOpen = true">
                                    + Assign First Worker
                                </button>
                            @endif
                        @endcan
                    </div>
                </div>
            @else
                <div class="table-wrapper">
                    <table class="data-table" id="assigned-personnel-table">
                        <caption class="visually-hidden">Field personnel assigned to {{ $project->name }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">Employee ID</th>
                                <th scope="col">Name</th>
                                <th scope="col">Expertise / Role</th>
                                <th scope="col">Date Assigned</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($project->personnel as $person)
                                <tr id="personnel-row-{{ $person->id }}">
                                    <td><span class="font-mono font-bold" style="color: var(--color-accent);">{{ $person->employee_id }}</span></td>
                                    <td><strong>{{ $person->full_name }}</strong></td>
                                    <td>{{ $person->expertise }}</td>
                                    <td>
                                        @if ($person->date_assigned)
                                            <span class="font-mono">{{ $person->date_assigned->format('M j, Y') }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="status-pill status-pill--completed">
                                            Assigned
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        @can('update', $project)
                                            @if (!$isProjectReadOnly)
                                                <button
                                                    type="button"
                                                    class="btn btn-secondary btn-sm"
                                                    style="color: var(--color-danger); border-color: #fad2cf;"
                                                    @click="openUnassignPersonnelModal('{{ route('projects.unassign-personnel', ['project' => $project, 'personnel' => $person]) }}', '{{ addslashes($person->full_name) }}')"
                                                >
                                                    Unassign
                                                </button>
                                            @endif
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
        </div>
    </section>

    {{-- ─── Status & Activity Audit Trail History ──────────────────────────── --}}
    <div class="project-detail-grid">
        <section class="card card-glass" aria-labelledby="status-history-heading" style="grid-column: 1 / -1;">
            <div
                class="card-header"
                style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);"
                :style="auditTrailMinimized ? 'border-bottom: none;' : ''"
            >
                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: var(--space-2);">
                    <h2 id="status-history-heading" style="margin: 0; font-size: 1.0625rem; font-weight: 700;">Audit Trail &amp; Status History</h2>
                    <span id="audit-trail-count" class="text-muted" style="font-size: 0.8125rem;">({{ $project->statusHistory->count() }} {{ Str::plural('event', $project->statusHistory->count()) }})</span>
                </div>
                <div style="display: inline-flex; flex-direction: row; gap: var(--space-2); align-items: center; flex-wrap: nowrap; margin-left: auto;">
                    <button
                        type="button"
                        class="btn btn-secondary btn-sm"
                        @click="auditTrailMinimized = !auditTrailMinimized"
                        :aria-expanded="!auditTrailMinimized"
                        id="btn-toggle-audit-trail"
                        style="display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;"
                    >
                        <span x-show="auditTrailMinimized">⊞ Expand</span>
                        <span x-show="!auditTrailMinimized" x-cloak>⊟ Minimize</span>
                    </button>
                </div>
            </div>
            <div class="table-wrapper" x-show="!auditTrailMinimized" x-transition x-cloak style="max-height: 290px; overflow-y: auto; position: relative;">
                <table class="data-table">
                    <caption class="visually-hidden">Project status and update history</caption>
                    <thead style="position: sticky; top: 0; background: var(--color-surface); z-index: 2;">
                        <tr>
                            <th scope="col" style="background: var(--color-surface);">Date & Time</th>
                            <th scope="col" style="background: var(--color-surface);">Status</th>
                            <th scope="col" style="background: var(--color-surface);">Updated By</th>
                            <th scope="col" style="background: var(--color-surface);">Activity & Changes</th>
                        </tr>
                    </thead>
                    <tbody id="audit-trail-tbody">
                        @forelse ($project->statusHistory as $history)
                            <tr>
                                <td class="font-mono" style="font-size: 0.8125rem; white-space: nowrap;">
                                    {{ $history->changed_at->format('M j, Y · g:i A') }}
                                </td>
                                <td>
                                    <x-status-pill :status="$history->to_status" />
                                </td>
                                <td>
                                    <strong>{{ $history->changedBy ? $history->changedBy->name : 'System' }}</strong>
                                </td>
                                <td>
                                    {{ $history->notes ?: '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr id="audit-trail-empty-row">
                                <td colspan="4" class="text-center text-muted" style="padding: var(--space-4);">No history records logged yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

    </div>

    {{-- ─── Modal 1: Add Itemized Cost Entry ──────────────────────────────────── --}}
    <div class="reassign-modal-overlay" style="display: none;" x-show="addCostModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="addCostModalOpen = false"></div>
        <div class="reassign-modal-content card" x-show="addCostModalOpen" x-transition style="max-width: 580px;">
            <div class="card-header">
                <span>Add Cost Entry to Project</span>
                <button type="button" class="flash-close" @click="addCostModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <form action="{{ route('projects.costs.store', $project) }}" method="POST" @submit="if (!validateCostSubmission($event)) { $event.preventDefault(); }">
                @csrf
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
                                <label for="material_qty_input" class="form-label" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; margin-bottom: 4px;">
                                    <span>Quantity (Cannot be 0)</span>
                                    <span style="font-weight: 600;" :class="isQuantityExcessive ? 'text-danger' : 'text-muted'">
                                        Avail: <span class="font-mono" x-text="selectedMaterialStock"></span>
                                    </span>
                                </label>
                                <input
                                    type="number"
                                    id="material_qty_input"
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
                        <label for="manual_cost_quantity" class="form-label">Quantity <span class="required">*</span></label>
                        <input
                            id="manual_cost_quantity"
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
                                :style="selectedMaterialId ? 'background: var(--color-surface-alt); cursor: not-allowed; opacity: 0.85;' : ''"
                                required
                            >
                                <option value="materials">Materials</option>
                                <option value="equipment">Equipment Rental</option>
                                <option value="labor">Labor / Overtime</option>
                                <option value="subcontractor">Subcontractor</option>
                                <option value="other">Other / Miscellaneous</option>
                            </select>
                            <input type="hidden" name="cost_type" value="materials" :disabled="!selectedMaterialId">
                            <p class="form-hint" x-show="selectedMaterialId" style="margin-top: 4px;">Locked to Materials for catalog items.</p>
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
                        <p class="form-hint" x-show="!selectedMaterialId">This will be added to the project's actual spend and reflected in Costing (minimum ₱100.00).</p>
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

    {{-- ─── Modal 2: Assign Personnel to Project (Multi-Select) ───────────────── --}}
    <div class="reassign-modal-overlay" style="display: none;" x-show="assignModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="assignModalOpen = false"></div>
        <div class="reassign-modal-content card" x-show="assignModalOpen" x-transition style="max-width: 560px;">
            <div class="card-header">
                <span>Assign Field Personnel (Select Multiple)</span>
                <button type="button" class="flash-close" @click="assignModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            @php
                $existingRoles = $project->personnel->groupBy('expertise')->map->count()->toArray();
                $availData = $availablePersonnel->map(fn($p) => [
                    'id' => $p->id,
                    'full_name' => $p->full_name,
                    'employee_id' => $p->employee_id,
                    'expertise' => $p->expertise,
                ])->values();
            @endphp
            <form 
                action="{{ route('projects.assign-personnel', $project) }}" 
                method="POST" 
                x-data="{
                    selectedAvail: [],
                    allAvailIds: [{{ $availablePersonnel->pluck('id')->implode(',') }}],
                    availList: {{ Js::from($availData) }},
                    assignedCounts: {{ Js::from($existingRoles) }},
                    limits: {
                        'Technician': { min: 1, max: 2, label: '1-2' },
                        'Project Manager': { min: 1, max: 1, label: '1' },
                        'QA Inspector': { min: 1, max: 1, label: '1' },
                        'Site Engineer': { min: 1, max: 1, label: '1' },
                        'Worker / Installation Personnel': { min: 4, max: 6, label: '4-6' },
                        'Safety Officer': { min: 1, max: 1, label: '1' },
                        'Foreman': { min: 1, max: 1, label: '1' }
                    },
                    toggleAll() {
                        this.selectedAvail = (this.selectedAvail.length === this.allAvailIds.length) ? [] : [...this.allAvailIds];
                    },
                    getExceededRoles() {
                        const newCounts = {};
                        this.selectedAvail.forEach(id => {
                            const p = this.availList.find(item => item.id === id);
                            if (p && p.expertise) {
                                newCounts[p.expertise] = (newCounts[p.expertise] || 0) + 1;
                            }
                        });
                        const warnings = [];
                        for (const [role, rule] of Object.entries(this.limits)) {
                            const current = this.assignedCounts[role] || 0;
                            const adding = newCounts[role] || 0;
                            const total = current + adding;
                            if (total > rule.max) {
                                warnings.push({
                                    role: role,
                                    current: current,
                                    adding: adding,
                                    total: total,
                                    max: rule.max,
                                    recommended: rule.label
                                });
                            }
                        }
                        return warnings;
                    },
                    handleSubmit(e) {
                        const exceeded = this.getExceededRoles();
                        if (exceeded.length > 0) {
                            const details = exceeded.map(item => 
                                `• ${item.role}: ${item.total} total (${item.current} already assigned + ${item.adding} new) exceeds recommended ${item.recommended}`
                            ).join('\n');
                            const confirmMsg = `Notice: The following expert role(s) exceed the project estimation:\n\n${details}\n\nAre you sure you want to proceed with assigning?`;
                            if (!confirm(confirmMsg)) {
                                e.preventDefault();
                                return false;
                            }
                        }
                    }
                }"
                @submit="handleSubmit($event)"
            >
                @csrf
                <div class="card-body">
                    <p class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-4);">
                        Assign available field workers to <strong>{{ $project->name }}</strong> (<span class="font-mono">{{ $project->project_code }}</span>).
                    </p>

                    @if ($availablePersonnel->isEmpty())
                        <div class="alert alert-info">
                            All personnel in the roster are currently assigned to projects. You can add new personnel from the Manpower module or unassign workers from other projects.
                        </div>
                    @else
                        {{-- Estimation Guide Banner --}}
                        <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3); margin-bottom: var(--space-3); font-size: 0.75rem;">
                            <div style="font-weight: 600; color: var(--color-ink); margin-bottom: 2px;">
                                Project Expert Estimation Guide:
                            </div>
                            <div style="display: flex; flex-wrap: wrap; gap: 6px 12px; color: var(--color-muted);">
                                <span>Technician: <strong>1-2</strong></span>
                                <span>Project Manager: <strong>1</strong></span>
                                <span>QA Inspector: <strong>1</strong></span>
                                <span>Site Engineer: <strong>1</strong></span>
                                <span>Worker / Installation: <strong>4-6</strong></span>
                                <span>Safety Officer: <strong>1</strong></span>
                                <span>Foreman: <strong>1</strong></span>
                            </div>
                        </div>

                        {{-- Real-time Exceeded Warning Banner --}}
                        <template x-if="getExceededRoles().length > 0">
                            <div class="alert alert-warning" style="margin-bottom: var(--space-3); padding: var(--space-2) var(--space-3); font-size: 0.8125rem; border-left: 3px solid #f59e0b; background: rgba(245, 158, 11, 0.08);">
                                <div style="display: flex; align-items: flex-start; gap: 6px;">
                                    <svg style="width: 16px; height: 16px; color: #d97706; flex-shrink: 0; margin-top: 2px;" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    <div>
                                        <strong style="color: #92400e;">Estimation Requirement Exceeded:</strong>
                                        <ul style="margin: 4px 0 0 16px; padding: 0; list-style-type: disc;">
                                            <template x-for="item in getExceededRoles()" :key="item.role">
                                                <li style="color: #92400e; margin-bottom: 2px;">
                                                    <span x-text="item.role"></span>: total of <strong x-text="item.total"></strong> (<span x-text="item.current"></span> assigned + <span x-text="item.adding"></span> selected) exceeds recommended max of <strong x-text="item.recommended"></strong>.
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                            <span class="form-label" style="margin-bottom: 0;">Choose Personnel (<span x-text="selectedAvail.length"></span> of {{ $availablePersonnel->count() }} selected):</span>
                            <button type="button" class="btn btn-secondary btn-sm" @click="toggleAll()" style="font-size: 0.75rem; padding: 2px 8px;">
                                <span x-text="selectedAvail.length === allAvailIds.length ? 'Deselect All' : 'Select All Available'"></span>
                            </button>
                        </div>

                        <div class="personnel-select-list" style="max-height: 260px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface);">
                            @foreach ($availablePersonnel as $avail)
                                <label 
                                    class="personnel-select-row"
                                    for="personnel_check_{{ $avail->id }}"
                                    :class="{ 'is-selected': selectedAvail.includes({{ $avail->id }}) }"
                                >
                                    <div class="personnel-select-checkbox-cell">
                                        <input
                                            type="checkbox"
                                            name="personnel_ids[]"
                                            id="personnel_check_{{ $avail->id }}"
                                            value="{{ $avail->id }}"
                                            x-model.number="selectedAvail"
                                            class="personnel-select-checkbox form-check-input"
                                        >
                                    </div>
                                    <div class="personnel-select-info-cell">
                                        <div class="personnel-select-name-wrap">
                                            <strong class="personnel-select-name">{{ $avail->full_name }}</strong>
                                            <span class="font-mono text-muted personnel-select-id">{{ $avail->employee_id }}</span>
                                        </div>
                                        <span class="badge personnel-select-badge">
                                            {{ $avail->expertise }}
                                        </span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <p class="form-hint" style="margin-top: 6px;">Select one or multiple personnel to assign them together in a single step.</p>
                    @endif
                </div>
                <div class="card-footer">
                    <div class="form-actions">
                        @if ($availablePersonnel->isNotEmpty())
                            <button type="submit" class="btn btn-primary" :disabled="selectedAvail.length === 0">
                                Assign <span x-text="selectedAvail.length > 0 ? selectedAvail.length + ' Personnel' : 'Personnel'"></span>
                            </button>
                        @endif
                        <button type="button" class="btn btn-secondary" @click="assignModalOpen = false">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ─── Modal 3: Add Major Task ─────────────────────────────────────────── --}}
    <div 
        class="reassign-modal-overlay" 
        style="display: none;" 
        x-show="createMajorTaskModalOpen" 
        x-cloak
        x-init="newTask.start_date = '{{ $defaultStartDate->format('Y-m-d') }}'; newTask.end_date = '{{ $defaultEndDate->format('Y-m-d') }}';"
    >
        <div class="reassign-modal-backdrop" @click="createMajorTaskModalOpen = false"></div>
        <div class="reassign-modal-content card" x-show="createMajorTaskModalOpen" x-transition style="max-width: 580px;">
            <div class="card-header">
                <span style="font-weight: 700;">+ Add New Major Task (Week {{ $nextWeekNum }})</span>
                <button type="button" class="flash-close" @click="createMajorTaskModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <form action="{{ route('projects.tasks.store', $project) }}" method="POST">
                @csrf
                <div class="card-body">
                    <p class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-4);">
                        Define a major milestone / major task for this project. Start and end dates are automatically pre-filled to cover a 1-week timeline but can be customized.
                    </p>

                    <div class="form-group form-group-premium" style="margin-bottom: var(--space-4);">
                        <label for="task_name" class="form-label">Task Name <span class="required">*</span></label>
                        <input
                            id="task_name"
                            name="task_name"
                            type="text"
                            class="form-control"
                            value="Week {{ $nextWeekNum }} - "
                            placeholder="e.g., Week {{ $nextWeekNum }} - Mechanical & Rail Installation"
                            required
                        >
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-4);">
                        <div class="form-group form-group-premium">
                            <label for="start_date" class="form-label">Start Date <span class="required">*</span></label>
                            <input
                                id="start_date"
                                name="start_date"
                                type="date"
                                class="form-control"
                                x-model="newTask.start_date"
                                value="{{ $defaultStartDate->format('Y-m-d') }}"
                                min="{{ $defaultStartDate->format('Y-m-d') }}"
                                @change="if (newTask.end_date < newTask.start_date) { newTask.end_date = newTask.start_date; }"
                                required
                            >
                        </div>

                        <div class="form-group form-group-premium">
                            <label for="end_date" class="form-label">End Date <span class="required">*</span></label>
                            <input
                                id="end_date"
                                name="end_date"
                                type="date"
                                class="form-control"
                                x-model="newTask.end_date"
                                value="{{ $defaultEndDate->format('Y-m-d') }}"
                                :min="newTask.start_date || '{{ $defaultStartDate->format('Y-m-d') }}'"
                                required
                            >
                        </div>
                    </div>
                    <p class="form-hint" style="margin-top: -8px; margin-bottom: var(--space-4);">
                        Must start on or after {{ $defaultStartDate->format('M j, Y') }} (following previous Major Task / Week). Pre-calculated duration: 7 days.
                    </p>

                    <div class="form-group form-group-premium" style="margin-bottom: var(--space-4);">
                        <label for="task_description" class="form-label">Description / Scope Summary</label>
                        <textarea
                            id="task_description"
                            name="description"
                            rows="2"
                            class="form-control"
                            placeholder="Key activities, milestones, and deliverable objectives for this major task..."
                        ></textarea>
                    </div>

                    {{-- Dynamic Sub Tasks Inputs in Create Modal --}}
                    <div style="border-top: 1px solid var(--color-border); padding-top: var(--space-4);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                            <label class="form-label" style="margin-bottom: 0;">
                                Specific Sub Tasks / Checklist Items (Optional)
                            </label>
                            <button 
                                type="button" 
                                class="btn btn-secondary btn-sm" 
                                @click="addSubtaskField()"
                                style="font-size: 0.75rem; padding: 2px 8px;"
                            >
                                + Add Task Row
                            </button>
                        </div>
                        <p class="form-hint" style="margin-bottom: var(--space-3);">
                            Target dates must fall within the major task week (<span x-text="newTask.start_date"></span> to <span x-text="newTask.end_date"></span>).
                        </p>

                        <div style="display: flex; flex-direction: column; gap: var(--space-2); max-height: 200px; overflow-y: auto;">
                            <template x-for="(item, idx) in initialSubtasks" :key="idx">
                                <div style="display: flex; gap: var(--space-2); align-items: center;">
                                    <span class="font-mono text-muted" style="font-size: 0.75rem; width: 20px;" x-text="(idx + 1) + '.'"></span>
                                    <input
                                        type="text"
                                        name="subtasks[]"
                                        x-model="initialSubtasks[idx].title"
                                        class="form-control form-control--compact"
                                        style="flex: 2;"
                                        placeholder="e.g., Hoistway rail laser alignment"
                                    >
                                    <input
                                        type="date"
                                        name="subtask_dates[]"
                                        x-model="initialSubtasks[idx].due_date"
                                        :min="newTask.start_date"
                                        :max="newTask.end_date"
                                        class="form-control form-control--compact"
                                        style="width: 150px;"
                                        title="Target date within this Major Task's week"
                                    >
                                    <button
                                        type="button"
                                        @click="removeSubtaskField(idx)"
                                        class="btn-icon"
                                        title="Remove row"
                                        style="background: none; border: none; color: var(--color-danger); cursor: pointer;"
                                    >
                                        &times;
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Create Major Task</button>
                        <button type="button" class="btn btn-secondary" @click="createMajorTaskModalOpen = false">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ─── Modal 4: Edit Major Task ─────────────────────────────────────────── --}}
    <div class="reassign-modal-overlay" style="display: none;" x-show="editMajorTaskModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="editMajorTaskModalOpen = false"></div>
        <div class="reassign-modal-content card" x-show="editMajorTaskModalOpen" x-transition style="max-width: 540px;">
            <div class="card-header">
                <span style="font-weight: 700;">Edit Major Task</span>
                <button type="button" class="flash-close" @click="editMajorTaskModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <form :action="editingTask.updateUrl" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="form-group form-group-premium" style="margin-bottom: var(--space-4);">
                        <label for="edit_task_name" class="form-label">Task Name <span class="required">*</span></label>
                        <input
                            id="edit_task_name"
                            name="task_name"
                            type="text"
                            class="form-control"
                            x-model="editingTask.task_name"
                            required
                        >
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-4);">
                        <div class="form-group form-group-premium">
                            <label for="edit_start_date" class="form-label">Start Date <span class="required">*</span></label>
                            <input
                                id="edit_start_date"
                                name="start_date"
                                type="date"
                                class="form-control"
                                x-model="editingTask.start_date"
                                required
                            >
                        </div>

                        <div class="form-group form-group-premium">
                            <label for="edit_end_date" class="form-label">End Date <span class="required">*</span></label>
                            <input
                                id="edit_end_date"
                                name="end_date"
                                type="date"
                                class="form-control"
                                x-model="editingTask.end_date"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-group form-group-premium">
                        <label for="edit_task_description" class="form-label">Description / Scope Summary</label>
                        <textarea
                            id="edit_task_description"
                            name="description"
                            rows="3"
                            class="form-control"
                            x-model="editingTask.description"
                        ></textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Update Major Task</button>
                        <button type="button" class="btn btn-secondary" @click="editMajorTaskModalOpen = false">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ─── Modal 5: Edit Sub Task ─────────────────────────────────────── --}}
    <div class="reassign-modal-overlay" style="display: none;" x-show="editSubtaskModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="editSubtaskModalOpen = false"></div>
        <div class="reassign-modal-content card" x-show="editSubtaskModalOpen" x-transition style="max-width: 480px;">
            <div class="card-header">
                <span style="font-weight: 700;">Edit Sub Task</span>
                <button type="button" class="flash-close" @click="editSubtaskModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <form :action="editingSubtask.updateUrl" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="form-group form-group-premium" style="margin-bottom: var(--space-4);">
                        <label for="edit_subtask_title" class="form-label">Task Title <span class="required">*</span></label>
                        <input
                            id="edit_subtask_title"
                            name="title"
                            type="text"
                            class="form-control"
                            x-model="editingSubtask.title"
                            required
                        >
                    </div>

                    <div class="form-group form-group-premium">
                        <label for="edit_subtask_due_date" class="form-label">Due Date (Optional)</label>
                        <input
                            id="edit_subtask_due_date"
                            name="due_date"
                            type="date"
                            class="form-control"
                            x-model="editingSubtask.due_date"
                            :min="editingSubtask.min_date"
                            :max="editingSubtask.max_date"
                        >
                        <p class="form-hint" style="margin-top: 4px;" x-show="editingSubtask.min_date && editingSubtask.max_date">
                            Allowed dates for this major task week: <strong x-text="editingSubtask.min_date"></strong> to <strong x-text="editingSubtask.max_date"></strong>.
                        </p>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <button type="button" class="btn btn-secondary" @click="editSubtaskModalOpen = false">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ─── Modal 6: Mark Project as Completed Confirmation ───────────── --}}
    <div class="reassign-modal-overlay" style="display: none;" x-show="markCompletedModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="markCompletedModalOpen = false"></div>
        <div class="reassign-modal-content card" x-show="markCompletedModalOpen" x-transition style="max-width: 500px; border-top: 4px solid var(--color-success);">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <svg style="width: 20px; height: 20px; color: var(--color-success);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <span style="font-weight: 700; font-size: 1rem;">Mark Project as Completed?</span>
                </div>
                <button type="button" class="flash-close" @click="markCompletedModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <form action="{{ route('projects.transition', $project) }}" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="completed">
                <div class="card-body" style="padding: var(--space-5);">
                    <p style="margin-top: 0; margin-bottom: var(--space-3); font-size: 0.9375rem; color: var(--color-ink);">
                        All timeline, checklist, tasks, and milestones for <strong>{{ $project->name }}</strong> have been finished.
                    </p>
                    <div style="background: var(--color-surface-alt); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: var(--space-4); font-size: 0.8125rem;">
                        <ul style="margin: 0; padding-left: var(--space-4); color: var(--color-muted);">
                            <li>Transitions status to <strong>Completed</strong>.</li>
                            <li>Automatically releases all currently assigned manpower back to the available pool.</li>
                            <li>Enables option to archive this project from the project registry.</li>
                        </ul>
                    </div>
                    <div class="form-group form-group-premium" style="margin-bottom: 0;">
                        <label for="completion_notes" class="form-label">Completion Notes / Client Sign-off <span class="text-muted">(optional)</span></label>
                        <textarea
                            id="completion_notes"
                            name="notes"
                            rows="2"
                            class="form-control"
                            maxlength="500"
                            placeholder="e.g. All elevator installation checklists verified and certified complete."
                        ></textarea>
                    </div>
                </div>
                <div class="card-footer" style="display: flex; justify-content: flex-end; gap: var(--space-2);">
                    <button type="button" class="btn btn-secondary" @click="markCompletedModalOpen = false">Keep In Progress</button>
                    <button type="submit" class="btn btn-primary" style="background: var(--color-success); border-color: var(--color-success);">Yes, Mark Completed</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ─── Modal: Archive Project Confirmation Modal ──────────────────────── --}}
    @can('archive', $project)
        <div class="reassign-modal-overlay" style="display: none;" x-show="archiveModalOpen" x-cloak>
            <div class="reassign-modal-backdrop" @click="archiveModalOpen = false"></div>
            <div class="reassign-modal-content card" x-show="archiveModalOpen" x-transition style="max-width: 480px; border-top: 4px solid var(--color-danger);">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: var(--space-2);">
                        <svg style="width: 20px; height: 20px; color: var(--color-danger);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 8v13H3V8"></path>
                            <path d="M1 3h22v5H1z"></path>
                            <path d="M10 12h4"></path>
                        </svg>
                        <span style="font-weight: 700; font-size: 1rem;">You are archiving this project:</span>
                    </div>
                    <button type="button" class="flash-close" @click="archiveModalOpen = false" aria-label="Close modal">&times;</button>
                </div>
                <form action="{{ route('projects.archive', $project) }}" method="POST">
                    @csrf
                    <div class="card-body" style="padding: var(--space-5);">
                        <p style="margin: 0; font-size: 0.9375rem; color: var(--color-ink); line-height: 1.5;">
                            If you archive <strong>{{ $project->name }}</strong>, you will not be able to see it from your active project list and you will not be able to edit it unless you reactivate it.
                        </p>
                    </div>
                    <div class="card-footer" style="display: flex; justify-content: flex-end; gap: var(--space-2);">
                        <button type="button" class="btn btn-secondary" @click="archiveModalOpen = false">Cancel</button>
                        <button type="submit" class="btn btn-danger">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    {{-- ─── Modal 7: Upload Project Files & Documents (Categorized & Multi-Upload) ──── --}}
    <div class="reassign-modal-overlay" style="display: none;" x-show="uploadFileModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="closeUploadProjectFileModal()"></div>
        <div class="reassign-modal-content card" x-show="uploadFileModalOpen" x-transition style="max-width: 540px; width: 100%;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <svg style="width: 18px; height: 18px; color: var(--color-primary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <span style="font-weight: 700; font-size: 1rem;">Upload Project Files</span>
                </div>
                <button type="button" class="flash-close" @click="closeUploadProjectFileModal()" aria-label="Close modal">&times;</button>
            </div>
            <form id="project-upload-files-form" action="{{ route('projects.files.store', $project) }}" method="POST" enctype="multipart/form-data" @submit.prevent="submitModalUploadForm($event.target)">
                @csrf
                <div class="card-body">
                    {{-- Default File Type Category Selector --}}
                    <div class="form-group form-group-premium" style="margin-bottom: var(--space-3);">
                        <label for="project_file_default_category" class="form-label">File Type Category <span class="required">*</span></label>
                        <select 
                            id="project_file_default_category" 
                            name="file_type" 
                            class="form-control" 
                            x-model="uploadModalDefaultCategory"
                            @change="uploadModalFiles.forEach(f => f.category = uploadModalDefaultCategory); syncModalUploadInputs();"
                            required
                        >
                            <option value="project_document">Project document</option>
                            <option value="project_transaction">Project transaction</option>
                            <option value="invoice">Project invoice</option>
                            <option value="project_notes">Project notes</option>
                            <option value="project_drawing">Project drawing / blueprint</option>
                        </select>
                        <p class="form-hint" style="margin-top: 3px;">Select category for your upload. You can also customize categories per file below.</p>
                    </div>

                    {{-- Hidden synchronized inputs for form submission --}}
                    <div id="modal_project_files_inputs" style="display: none;"></div>
                    <div id="modal_single_file_slot" style="display: none;"></div>

                    {{-- Hidden multiple file picker input --}}
                    <input
                        type="file"
                        x-ref="modalProjectFilesInput"
                        @change="handleModalFilesSelected($event)"
                        multiple
                        accept=".pdf,.doc,.docx,image/*"
                        style="display: none;"
                    >

                    {{-- When Files are Attached: List with remove ("x") and "+ Attach Another File" --}}
                    <template x-if="uploadModalFiles.length > 0">
                        <div style="margin-bottom: var(--space-3);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                                <span style="font-size: 0.8125rem; font-weight: 700; color: var(--color-ink);">
                                    Attached Files (<span x-text="uploadModalFiles.length"></span>)
                                </span>
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    @click="$refs.modalProjectFilesInput.click()"
                                    style="padding: 2px 10px; font-size: 0.75rem;"
                                >
                                    + Attach Another File
                                </button>
                            </div>

                            {{-- Scrollable staging list to prevent modal scroll fatigue --}}
                            <div style="display: flex; flex-direction: column; gap: var(--space-2); max-height: 230px; overflow-y: auto; padding-right: 2px;">
                                <template x-for="(fileItem, index) in uploadModalFiles" :key="fileItem.id">
                                    <div 
                                        style="display: flex; flex-direction: column; gap: var(--space-1); padding: var(--space-2) var(--space-3); background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.8125rem;"
                                    >
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-2);">
                                            <div style="display: flex; align-items: center; gap: var(--space-2); min-width: 0; flex: 1;">
                                                <span x-text="getFileIcon(fileItem.name)" style="font-size: 1.1rem; flex-shrink: 0;"></span>
                                                <div style="min-width: 0; flex: 1;">
                                                    <div style="font-weight: 600; color: var(--color-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="fileItem.name"></div>
                                                    <div class="text-muted" style="font-size: 0.6875rem;" x-text="fileItem.formattedSize"></div>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                class="btn btn-secondary btn-sm"
                                                @click="removeModalUploadFile(index)"
                                                style="padding: 2px 7px; color: #ef4444; border-color: rgba(239, 68, 68, 0.3); font-weight: 700; line-height: 1; border-radius: 4px;"
                                                title="Remove this attached file"
                                            >
                                                ✕
                                            </button>
                                        </div>

                                        {{-- Inline Category & Description for this specific file --}}
                                        <div style="display: flex; gap: var(--space-2); align-items: center; margin-top: 2px;">
                                            <select 
                                                x-model="fileItem.category" 
                                                @change="syncModalUploadInputs()"
                                                class="form-control"
                                                style="padding: 2px 6px; font-size: 0.75rem; height: 26px; width: 175px; flex-shrink: 0;"
                                                title="Select category for this file"
                                            >
                                                <option value="project_document">Project document</option>
                                                <option value="project_transaction">Project transaction</option>
                                                <option value="invoice">Project invoice</option>
                                                <option value="project_notes">Project notes</option>
                                                <option value="project_drawing">Project drawing / blueprint</option>
                                            </select>
                                            <input 
                                                type="text" 
                                                x-model="fileItem.description"
                                                @input="syncModalUploadInputs()"
                                                class="form-control"
                                                placeholder="Optional notes..."
                                                maxlength="255"
                                                style="padding: 2px 8px; font-size: 0.75rem; height: 26px; flex: 1;"
                                            >
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Empty Dropzone State --}}
                    <template x-if="uploadModalFiles.length === 0">
                        <div 
                            class="pms-dropzone"
                            :class="{ 'is-dragging': isDraggingModal }"
                            @dragover.prevent="isDraggingModal = true"
                            @dragleave.prevent="isDraggingModal = false"
                            @drop.prevent="isDraggingModal = false; handleModalFilesDropped($event)"
                            @click="$refs.modalProjectFilesInput.click()"
                            style="padding: var(--space-4); text-align: center; border: 2px dashed var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); cursor: pointer; transition: all 0.15s ease;"
                        >
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                                <svg style="width: 28px; height: 28px; color: var(--color-accent); opacity: 0.85;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="17 8 12 3 7 8"></polyline>
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                </svg>
                                <span style="font-size: 0.875rem; font-weight: 600; color: var(--color-ink);">
                                    Click to select or drop file(s)
                                </span>
                                <span class="text-muted" style="font-size: 0.75rem;">
                                    PDF, DOCX, or Images up to 20MB. Multiple files supported.
                                </span>
                                <button type="button" class="btn btn-primary btn-sm" style="margin-top: 4px; pointer-events: none;">
                                    + Attach Files
                                </button>
                            </div>
                        </div>
                    </template>

                    {{-- Error message --}}
                    <template x-if="uploadModalError">
                        <div class="form-error" style="color: #ef4444; font-size: 0.75rem; margin-top: 6px;" x-text="uploadModalError"></div>
                    </template>

                    @if(Auth::user()->hasRole('Staff'))
                        <div style="margin-top: var(--space-3); padding: var(--space-2) var(--space-3); background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: var(--radius-sm); font-size: 0.75rem; color: #b45309;">
                            <strong>Staff Notice:</strong> Uploaded files will be submitted for manager or admin approval before reflecting in the project.
                        </div>
                    @endif
                </div>

                <div class="card-footer" style="display: flex; justify-content: flex-end; gap: var(--space-2);">
                    <button type="button" class="btn btn-secondary" @click="closeUploadProjectFileModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="uploadModalFiles.length === 0 || uploadModalSubmitting">
                        <span x-show="!uploadModalSubmitting" x-text="uploadModalFiles.length > 1 ? 'Upload ' + uploadModalFiles.length + ' Files' : (uploadModalFiles.length === 1 ? 'Upload 1 File' : 'Upload File')"></span>
                        <span x-show="uploadModalSubmitting">Uploading...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ─── Modal 9: File Details, Description Editor & Actions ─────── --}}
    <div class="reassign-modal-overlay" style="display: none;" x-show="viewFileModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="viewFileModalOpen = false"></div>
        <div class="reassign-modal-content card" x-show="viewFileModalOpen" x-transition style="max-width: 620px; width: 100%;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: var(--space-2); min-width: 0;">
                    <span x-text="activeFileData.is_img ? '🖼️' : (activeFileData.is_pdf ? '📕' : (activeFileData.is_word ? '📘' : (activeFileData.type === 'project_drawing' ? '📐' : '📄')))"></span>
                    <span style="font-weight: 700; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="activeFileData.name"></span>
                </div>
                <button type="button" class="flash-close" @click="viewFileModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            
            <form :action="activeFileData.update_url" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body">
                    {{-- File Meta Info Grid --}}
                    <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-3); background: var(--color-surface-alt); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--color-border); margin-bottom: var(--space-4); font-size: 0.8125rem;">
                        <div>
                            <span class="text-muted" style="display: block; font-size: 0.6875rem; text-transform: uppercase; font-weight: 700;">File Size</span>
                            <span class="font-mono font-bold" x-text="activeFileData.size"></span>
                        </div>
                        <div>
                            <span class="text-muted" style="display: block; font-size: 0.6875rem; text-transform: uppercase; font-weight: 700;">Category</span>
                            <span class="badge" style="background: var(--color-surface); font-size: 0.75rem;" x-text="activeFileData.category_label || (activeFileData.type === 'invoice' ? 'Project invoice' : (activeFileData.type === 'project_transaction' ? 'Project transaction' : (activeFileData.type === 'project_notes' ? 'Project notes' : (activeFileData.type === 'project_drawing' ? 'Project drawing / blueprint' : (activeFileData.type === 'task_attachment' ? 'Task Attachment' : 'Project document')))))"></span>
                        </div>
                        <div>
                            <span class="text-muted" style="display: block; font-size: 0.6875rem; text-transform: uppercase; font-weight: 700;">Uploaded By</span>
                            <span x-text="activeFileData.uploader"></span>
                        </div>
                        <div>
                            <span class="text-muted" style="display: block; font-size: 0.6875rem; text-transform: uppercase; font-weight: 700;">Upload Date</span>
                            <span class="font-mono" x-text="activeFileData.date"></span>
                        </div>
                        <template x-if="activeFileData.subtask_title">
                            <div style="grid-column: 1 / -1; padding-top: var(--space-2); border-top: 1px solid var(--color-border);">
                                <span class="text-muted" style="display: block; font-size: 0.6875rem; text-transform: uppercase; font-weight: 700;">Associated Sub Task</span>
                                <span style="font-weight: 600;" x-text="activeFileData.subtask_title"></span>
                            </div>
                        </template>
                    </div>

                    {{-- Description Editor --}}
                    <div class="form-group form-group-premium" style="margin-bottom: 0;">
                        <label for="modal_file_description" class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                            <span>File Description / Notes</span>
                            <span class="text-muted" style="font-size: 0.6875rem;">Editable</span>
                        </label>
                        <textarea
                            id="modal_file_description"
                            name="description"
                            rows="3"
                            class="form-control"
                            maxlength="500"
                            x-model="activeFileData.description"
                            placeholder="Add or update notes about this document..."
                        ></textarea>
                        <p class="form-hint" style="margin-top: 4px;">Click 'Save Description' below to record changes to this file's description.</p>
                    </div>
                </div>

                <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; gap: var(--space-3); flex-wrap: nowrap; padding: var(--space-3) var(--space-4);">
                    <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                        <a 
                            :href="activeFileData.download_url ? (activeFileData.download_url + (activeFileData.download_url.includes('?') ? '&preview=1' : '?preview=1')) : '#'" 
                            class="btn btn-secondary btn-sm" 
                            target="_blank" 
                            rel="noopener noreferrer" 
                            style="display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; height: 32px; padding: 0 10px;"
                        >
                            <svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <span>Preview</span>
                        </a>
                        <a 
                            :href="activeFileData.download_url" 
                            class="btn btn-secondary btn-sm" 
                            target="_blank" 
                            style="display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; height: 32px; padding: 0 10px;"
                        >
                            <svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            <span>Download</span>
                        </a>
                        @can('update', $project)
                            <button
                                type="button"
                                class="btn btn-secondary btn-sm"
                                style="color: var(--color-danger); border-color: rgba(239, 68, 68, 0.35); display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; height: 32px; padding: 0 10px;"
                                @click="deleteActiveFile()"
                            >
                                <svg style="width: 13px; height: 13px;" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                <span>Delete</span>
                            </button>
                        @endcan
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                        <button type="button" class="btn btn-secondary btn-sm" style="white-space: nowrap; height: 32px; padding: 0 12px;" @click="viewFileModalOpen = false">Close</button>
                        @can('update', $project)
                            <button type="submit" class="btn btn-primary btn-sm" style="white-space: nowrap; height: 32px; padding: 0 14px; font-weight: 600;">Save Description</button>
                        @endcan
                    </div>
                </div>
            </form>

            {{-- Hidden delete form for modal --}}
            <form id="activeFileDeleteForm" :action="activeFileData.delete_url" method="POST" style="display: none;">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    {{-- ─── Modal 8: Sub Task Attachments & Upload Modal ────────────────── --}}
    <div class="reassign-modal-overlay" style="display: none;" x-show="subtaskFileModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="subtaskFileModalOpen = false"></div>
        <div class="reassign-modal-content card" x-show="subtaskFileModalOpen" x-transition style="max-width: 520px;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: var(--space-2); min-width: 0;">
                    <svg style="width: 18px; height: 18px; color: var(--color-accent); flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <span style="font-weight: 700; font-size: 0.9375rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="'Task Proofs: ' + activeSubtaskData.title"></span>
                </div>
                <button type="button" class="flash-close" @click="subtaskFileModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <div class="card-body">
                {{-- Completion Remarks & Notes if present --}}
                <template x-if="activeSubtaskData.notes">
                    <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-left: 3px solid var(--color-accent); border-radius: var(--radius-sm); padding: var(--space-3); margin-bottom: var(--space-4);">
                        <span class="text-muted font-bold" style="font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">Completion Remarks / Notes</span>
                        <p style="margin: 0; font-size: 0.8125rem; color: var(--color-ink); white-space: pre-wrap;" x-text="activeSubtaskData.notes"></p>
                    </div>
                </template>

                {{-- Existing Subtask Files List --}}
                <div style="margin-bottom: var(--space-4);">
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-muted); margin-bottom: var(--space-2);">
                        Attached Documents (<span x-text="activeSubtaskData.files.length"></span>)
                    </div>
                    <template x-if="activeSubtaskData.files.length === 0">
                        <div style="background: var(--color-surface); border: 1px dashed var(--color-border); padding: var(--space-3); border-radius: var(--radius-md); text-align: center; color: var(--color-muted); font-size: 0.8125rem;">
                            No files attached to this sub task yet.
                        </div>
                    </template>
                    <div style="display: flex; flex-direction: column; gap: var(--space-2); max-height: 180px; overflow-y: auto;" x-show="activeSubtaskData.files.length > 0">
                        <template x-for="f in activeSubtaskData.files" :key="f.id">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); padding: var(--space-2) var(--space-3); background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); font-size: 0.8125rem;">
                                <div style="display: flex; align-items: center; gap: var(--space-2); min-width: 0; flex: 1;">
                                    <span x-text="f.is_img ? '🖼️' : (f.is_pdf ? '📕' : (f.is_word ? '📘' : '📄'))"></span>
                                    <a :href="f.url" target="_blank" class="table-link font-bold" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px;" x-text="f.name"></a>
                                    <span class="text-muted" style="font-size: 0.75rem;" x-text="'(' + f.size + ')'"></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: var(--space-1); flex-shrink: 0;">
                                    <a :href="f.url" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" title="Download">⬇</a>
                                    @can('update', $project)
                                        <button 
                                            type="button" 
                                            class="btn btn-secondary btn-sm" 
                                            style="color: var(--color-danger); padding: 2px 6px; font-size: 0.75rem;"
                                            title="Delete Attachment"
                                            @click="openDeleteFileModal('/projects/{{ $project->id }}/files/' + f.id, f.name)"
                                        >
                                            ✕
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Upload New Attachment Form --}}
                @if(Auth::user()->hasAnyRole(['Admin', 'Manager', 'Staff']))
                    @if ($isProjectReadOnly)
                        <div style="border-top: 1px solid var(--color-border); padding-top: var(--space-3);">
                            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-muted); margin-bottom: var(--space-2);">
                                Attach File ({{ $project->trashed() ? 'Archived' : 'Completed' }})
                            </div>
                            <p class="text-muted" style="font-size: 0.8125rem; margin-bottom: var(--space-3);">New attachments cannot be uploaded to a {{ $project->trashed() ? 'archived' : 'completed' }} project.</p>
                            <div style="display: flex; justify-content: flex-end; gap: var(--space-2);">
                                <button type="button" class="btn btn-secondary" @click="subtaskFileModalOpen = false">Close</button>
                                <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.6; cursor: not-allowed;" title="{{ $project->trashed() ? 'Cannot upload files to an archived project' : 'Cannot upload files to a completed project unless reactivated' }}">Attach File</button>
                            </div>
                        </div>
                    @else
                        <div style="border-top: 1px solid var(--color-border); padding-top: var(--space-3);">
                            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-muted); margin-bottom: var(--space-2);">
                                + Upload New Attachment
                            </div>
                            <form :action="activeSubtaskData.uploadUrl" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="form-group form-group-premium" style="margin-bottom: var(--space-3);">
                                    <input
                                        type="file"
                                        name="file"
                                        class="form-control"
                                        accept=".pdf,.doc,.docx,image/*"
                                        required
                                    >
                                    <p class="form-hint" style="margin-top: 4px;">PDF, DOCX, or Images up to 20MB.</p>
                                </div>
                                <div class="form-group form-group-premium" style="margin-bottom: var(--space-3);">
                                    <input
                                        type="text"
                                        name="description"
                                        class="form-control"
                                        maxlength="255"
                                        placeholder="Description / note (e.g. Approved Permit Certificate)"
                                    >
                                </div>
                                <div style="display: flex; justify-content: flex-end; gap: var(--space-2);">
                                    <button type="button" class="btn btn-secondary" @click="subtaskFileModalOpen = false">Close</button>
                                    <button type="submit" class="btn btn-primary btn-sm">Attach File</button>
                                </div>
                            </form>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- ─── Modal 11: Task Completion Record & Proof Details Modal ─── --}}
    <div class="reassign-modal-overlay" style="display: none;" x-show="taskProofModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="taskProofModalOpen = false"></div>
        <div class="reassign-modal-content card" x-show="taskProofModalOpen" x-transition style="max-width: 580px; width: 100%; border-top: 4px solid var(--color-success); max-height: 90vh; display: flex; flex-direction: column;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                <div style="display: flex; align-items: center; gap: var(--space-2); min-width: 0;">
                    <svg style="width: 18px; height: 18px; color: var(--color-success); flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span style="font-weight: 700; font-size: 0.9375rem; color: var(--color-ink);">
                        Task Completion Record & Proof
                    </span>
                </div>
                <button type="button" class="flash-close" @click="taskProofModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <div class="card-body" style="padding: var(--space-5); overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: var(--space-4);">
                {{-- Task Information Summary --}}
                <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4);">
                    <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-muted); margin-bottom: 2px;">
                        Milestone / Major Task
                    </div>
                    <div style="font-size: 0.875rem; font-weight: 600; color: var(--color-ink); margin-bottom: var(--space-2);" x-text="taskProofData.task_name || 'Project Milestone'"></div>
                    
                    <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-muted); margin-bottom: 2px;">
                        Specific Task Completed
                    </div>
                    <div style="display: flex; align-items: center; gap: var(--space-2); font-size: 0.9375rem; font-weight: 700; color: var(--color-ink); margin-bottom: var(--space-3);">
                        <svg style="width: 16px; height: 16px; color: var(--color-success); flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span x-text="taskProofData.subtask_title"></span>
                    </div>

                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: var(--space-2); font-size: 0.75rem; padding-top: var(--space-2); border-top: 1px dashed var(--color-border);">
                        <span class="status-pill status-pill--completed" style="font-size: 0.6875rem; padding: 2px 8px;">
                            ✓ Completed
                        </span>
                        <template x-if="taskProofData.due_date">
                            <span class="badge font-mono" style="background: var(--color-surface); border: 1px solid var(--color-border); color: var(--color-muted); font-size: 0.75rem; padding: 2px 8px;">
                                📅 Target: <span x-text="taskProofData.due_date"></span>
                            </span>
                        </template>
                        <template x-if="taskProofData.completed_at">
                            <span class="badge font-mono" style="background: var(--color-surface); border: 1px solid var(--color-border); color: var(--color-ink); font-size: 0.75rem; padding: 2px 8px;">
                                Finished: <span x-text="taskProofData.completed_at"></span>
                            </span>
                        </template>
                    </div>
                </div>

                {{-- Accountability & Approval (Who Completed & Who Approved) --}}
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-3);">
                    {{-- Marked Completed By --}}
                    <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4);">
                        <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-muted); margin-bottom: var(--space-1); display: flex; align-items: center; gap: 4px;">
                            <svg style="width: 12px; height: 12px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            <span>Marked Completed By</span>
                        </div>
                        <div style="font-weight: 700; font-size: 0.875rem; color: var(--color-ink); margin-bottom: 3px;" x-text="taskProofData.completed_by_name || 'Direct Completion'"></div>
                        <span class="badge" style="background: var(--color-surface); border: 1px solid var(--color-border); color: var(--color-muted); font-size: 0.6875rem;" x-text="taskProofData.completed_by_role || 'Staff / User'"></span>
                    </div>

                    {{-- Approved & Verified By --}}
                    <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4);">
                        <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-muted); margin-bottom: var(--space-1); display: flex; align-items: center; gap: 4px;">
                            <svg style="width: 12px; height: 12px; color: var(--color-success);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                            <span>Approved & Verified By</span>
                        </div>
                        <div style="font-weight: 700; font-size: 0.875rem; color: var(--color-ink); margin-bottom: 3px;" x-text="taskProofData.approved_by_name || (taskProofData.is_direct ? 'Direct Sign-off' : 'Manager / Admin')"></div>
                        <span class="badge" style="background: var(--color-surface); border: 1px solid var(--color-border); color: var(--color-muted); font-size: 0.6875rem;" x-text="taskProofData.approved_by_role || (taskProofData.is_direct ? 'Direct Sign-off' : 'Manager / Admin')"></span>
                    </div>
                </div>

                {{-- Remarks & Notes --}}
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-muted); margin-bottom: var(--space-1);">
                        Remarks & Notes
                    </div>
                    <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); max-height: 120px; overflow-y: auto; font-size: 0.875rem; color: var(--color-ink); line-height: 1.5; white-space: pre-wrap;" x-text="taskProofData.notes ? taskProofData.notes : 'No specific remarks or inspection notes recorded.'"></div>
                </div>

                {{-- Attached Proof Files --}}
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                        <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-muted);">
                            Attached Proof Files
                        </div>
                        <span class="badge" style="background: var(--color-surface-alt); border: 1px solid var(--color-border); color: var(--color-muted); font-size: 0.6875rem; font-weight: 600;" x-text="(taskProofData.files && taskProofData.files.length) ? (taskProofData.files.length + ' file(s)') : '0 files'"></span>
                    </div>

                    <div style="border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface-alt); padding: var(--space-2); max-height: 200px; overflow-y: auto; display: flex; flex-direction: column; gap: var(--space-2);">
                        <template x-if="!taskProofData.files || taskProofData.files.length === 0">
                            <div style="text-align: center; padding: var(--space-4); color: var(--color-muted); font-size: 0.8125rem;">
                                No proof files were attached to this task.
                            </div>
                        </template>
                        <template x-for="file in (taskProofData.files || [])" :key="file.id || file.name">
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: var(--space-2) var(--space-3);">
                                <div style="display: flex; align-items: center; gap: var(--space-2); min-width: 0; flex: 1;">
                                    <span style="font-size: 1.125rem; flex-shrink: 0;" x-text="getFileIcon(file.name)"></span>
                                    <div style="min-width: 0; flex: 1;">
                                        <div style="font-size: 0.8125rem; font-weight: 600; color: var(--color-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" :title="file.name" x-text="file.name"></div>
                                        <div style="font-size: 0.6875rem; color: var(--color-muted);" x-text="file.size"></div>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: var(--space-2); flex-shrink: 0;">
                                    <a 
                                        :href="file.url ? (file.url + (file.url.includes('?') ? '&preview=1' : '?preview=1')) : '#'" 
                                        target="_blank" 
                                        rel="noopener noreferrer" 
                                        class="btn btn-secondary btn-sm"
                                        style="padding: 2px 8px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; border-color: var(--color-border); color: var(--color-ink);"
                                        title="Preview file in new tab"
                                    >
                                        <svg style="width: 12px; height: 12px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        <span>Preview</span>
                                    </a>
                                    <a 
                                        :href="file.url" 
                                        download 
                                        class="btn btn-secondary btn-sm"
                                        style="padding: 2px 8px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; border-color: var(--color-border); color: var(--color-ink);"
                                        title="Download file"
                                    >
                                        <svg style="width: 12px; height: 12px; color: var(--color-muted);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                        <span>Download</span>
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            <div class="card-footer" style="display: flex; justify-content: flex-end; padding: var(--space-3) var(--space-5); border-top: 1px solid var(--color-border); flex-shrink: 0;">
                <button type="button" class="btn btn-secondary" @click="taskProofModalOpen = false">Close</button>
            </div>
        </div>
    </div>

    {{-- ─── Modal 10: Task Completion Confirmation & Proof Upload Modal ─── --}}
    <div class="reassign-modal-overlay" style="display: none;" x-show="confirmCompletionModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="cancelTaskCompletion()"></div>
        <div class="reassign-modal-content card" x-show="confirmCompletionModalOpen" x-transition style="max-width: 540px; border-top: 4px solid var(--color-accent);">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: var(--space-2); min-width: 0;">
                    <svg style="width: 18px; height: 18px; color: var(--color-success); flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                    <span style="font-weight: 700; font-size: 0.9375rem;">
                        {{ Auth::user()->hasRole('Staff') ? 'Submit Task Completion Request' : 'Confirm Task Completion' }}
                    </span>
                </div>
                <button type="button" class="flash-close" @click="cancelTaskCompletion()" aria-label="Close modal">&times;</button>
            </div>
            <div class="card-body" style="padding: var(--space-5);">
                {{-- Task Information Summary --}}
                <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-3) var(--space-4); margin-bottom: var(--space-4);">
                    <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-muted); margin-bottom: 2px;">
                        Milestone / Major Task
                    </div>
                    <div style="font-size: 0.875rem; font-weight: 600; color: var(--color-ink); margin-bottom: var(--space-2);" x-text="completingSubtask.taskName"></div>
                    
                    <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-muted); margin-bottom: 2px;">
                        Specific Task to Complete
                    </div>
                    <div style="display: flex; align-items: center; gap: var(--space-2); font-size: 0.9375rem; font-weight: 700; color: var(--color-accent);">
                        <svg style="width: 16px; height: 16px; color: var(--color-success); flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span x-text="completingSubtask.subtaskTitle"></span>
                    </div>
                </div>

                @if(Auth::user()->hasRole('Staff'))
                    <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-left: 3px solid #f59e0b; padding: var(--space-3); border-radius: var(--radius-sm); margin-bottom: var(--space-4); font-size: 0.8125rem; color: var(--color-ink);">
                        <strong style="color: #f59e0b;">Staff Submission Notice:</strong> <span style="color: var(--color-muted);">Your remarks and proof attachment will be submitted to the project manager or administrator for review and approval before this task is marked as finished.</span>
                    </div>
                @endif

                {{-- Remarks & Notes Field (Multi-line) --}}
                <div class="form-group form-group-premium" style="margin-bottom: var(--space-4);">
                    <label for="completion_notes_input" class="form-label">
                        Remarks & Notes <span class="text-danger">*</span>
                    </label>
                    <textarea
                        id="completion_notes_input"
                        x-model="completionNotes"
                        rows="3"
                        class="form-control"
                        placeholder="Enter completion remarks, site observations, technician details, or inspection sign-off..."
                        required
                    ></textarea>
                    <p class="form-hint">Provide detailed completion notes, site observations, or inspection findings.</p>
                </div>

                {{-- Proof File Attachment Section with Border Separation --}}
                <div class="completion-attachment-panel" style="border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-4); background: var(--color-surface-alt); margin-bottom: var(--space-4);">
                    {{-- Header with separation border --}}
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-3); padding-bottom: var(--space-3); border-bottom: 1px solid var(--color-border);">
                        <label class="form-label" style="margin-bottom: 0; font-weight: 700; font-size: 0.875rem; color: var(--color-ink); display: flex; align-items: center; gap: 4px;">
                            <span>Proof of Completion Attachment</span>
                            <span class="text-danger">*</span>
                        </label>
                        <span class="badge" style="background: var(--color-surface); border: 1px solid var(--color-border); color: var(--color-muted); font-size: 0.6875rem; font-weight: 600;">
                            PDF, DOCX, Images (Max 20MB)
                        </span>
                    </div>

                    {{-- Hidden file input for single or multiple selection --}}
                    <input 
                        type="file" 
                        x-ref="proofFileInput" 
                        style="display: none;" 
                        accept=".pdf,.doc,.docx,image/*"
                        multiple
                        @change="handleProofFilesSelected($event)"
                    >

                    {{-- Empty State / Upload Dropzone --}}
                    <template x-if="completionFiles.length === 0">
                        <div 
                            style="border: 2px dashed var(--color-border); border-radius: var(--radius-md); padding: var(--space-4); text-align: center; background: var(--color-surface); cursor: pointer; transition: all 0.15s ease;"
                            @click="$refs.proofFileInput.click()"
                            @dragover.prevent="$el.style.borderColor = 'var(--color-accent)'; $el.style.background = 'var(--color-surface-alt)';"
                            @dragleave.prevent="$el.style.borderColor = 'var(--color-border)'; $el.style.background = 'var(--color-surface)';"
                            @drop.prevent="$el.style.borderColor = 'var(--color-border)'; $el.style.background = 'var(--color-surface)'; handleProofFilesDropped($event)"
                        >
                            <svg style="width: 28px; height: 28px; color: var(--color-accent); margin: 0 auto 6px auto; display: block;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            <div style="font-size: 0.8125rem; font-weight: 600; color: var(--color-ink);">Click or drag &amp; drop to attach proof file(s)</div>
                            <div class="form-hint" style="margin-top: 2px; font-size: 0.75rem;">Photos, signed inspection sheets, or delivery certificates</div>
                        </div>
                    </template>

                    {{-- Populated State: Attached Files List + "Attach Another File" button --}}
                    <template x-if="completionFiles.length > 0">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-muted);">
                                    Attached Proofs (<span x-text="completionFiles.length"></span>)
                                </span>
                                <button 
                                    type="button" 
                                    class="btn btn-secondary btn-sm" 
                                    style="font-size: 0.75rem; padding: 3px 10px; display: inline-flex; align-items: center; gap: 5px; border-color: var(--color-border); color: var(--color-ink); background: var(--color-surface);"
                                    @click="$refs.proofFileInput.click()"
                                >
                                    <svg style="width: 13px; height: 13px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <line x1="12" y1="5" x2="12" y2="19"></line>
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                    </svg>
                                    <span>Attach Another File</span>
                                </button>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: var(--space-2); max-height: 180px; overflow-y: auto; padding-right: 2px;">
                                <template x-for="(item, idx) in completionFiles" :key="idx">
                                    <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); padding: 8px 12px; background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-sm);">
                                        <div style="display: flex; align-items: center; gap: var(--space-2); min-width: 0; flex: 1;">
                                            <span style="font-size: 1.25rem; flex-shrink: 0;" x-text="getFileIcon(item.name)"></span>
                                            <div style="min-width: 0; flex: 1;">
                                                <div style="font-size: 0.8125rem; font-weight: 600; color: var(--color-ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="item.name"></div>
                                                <span style="font-size: 0.6875rem; color: var(--color-muted);" x-text="item.size"></span>
                                            </div>
                                        </div>
                                        <button 
                                            type="button" 
                                            class="btn btn-secondary btn-sm" 
                                            style="color: var(--color-danger); padding: 2px 8px; font-size: 0.75rem; flex-shrink: 0; border-color: var(--color-border); background: var(--color-surface);" 
                                            @click.stop="removeProofFile(idx)"
                                            title="Remove file"
                                        >
                                            ✕ Remove
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <p class="form-hint" style="margin-top: var(--space-2); margin-bottom: 0; font-size: 0.75rem;">Proof document or photographic evidence is required before completing this task.</p>
                </div>

                {{-- Error Message Display --}}
                <template x-if="completionFileError">
                    <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-left: 3px solid var(--color-danger); padding: var(--space-2) var(--space-3); border-radius: var(--radius-sm); margin-top: var(--space-3); font-size: 0.8125rem; color: var(--color-danger);" x-text="completionFileError"></div>
                </template>
            </div>
            <div class="card-footer" style="display: flex; justify-content: flex-end; gap: var(--space-2);">
                <button 
                    type="button" 
                    class="btn btn-secondary" 
                    @click="cancelTaskCompletion()" 
                    :disabled="completionSubmitting"
                >
                    Cancel
                </button>
                <button 
                    type="button" 
                    class="btn btn-primary" 
                    @click="submitTaskCompletion()" 
                    :disabled="completionSubmitting || !completionNotes.trim() || completionFiles.length === 0"
                    style="display: inline-flex; align-items: center; gap: 6px;"
                >
                    <span x-show="completionSubmitting" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    <span x-text="completionSubmitting ? 'Submitting...' : '{{ Auth::user()->hasRole('Staff') ? 'Submit Completion Request' : 'Confirm & Complete Task' }}'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Hidden form for cost deletion --}}
    <form id="project-delete-cost-form" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>

    {{-- Centered In-App Delete Expenditure Confirmation Modal --}}
    <div
        class="reassign-modal-overlay"
        style="display: none;"
        x-show="deleteCostModalOpen"
        x-cloak
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-cost-modal-title"
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

            <h3 id="delete-cost-modal-title" style="margin: 0 0 var(--space-2); font-size: 1.15rem; font-weight: 700; color: var(--color-ink);">
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

    {{-- Hidden form for unassigning personnel --}}
    <form id="project-unassign-personnel-form" method="POST" style="display: none;">
        @csrf
    </form>

    {{-- Centered In-App Delete Major Task Confirmation Modal --}}
    <form id="project-delete-task-form" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>

    <div
        class="reassign-modal-overlay"
        style="display: none;"
        x-show="deleteTaskModalOpen"
        x-cloak
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-major-task-modal-title"
    >
        <div class="reassign-modal-backdrop" @click="deleteTaskModalOpen = false"></div>
        <div
            class="reassign-modal-content card"
            style="max-width: 480px; width: 100%; text-align: center; padding: var(--space-6);"
            x-show="deleteTaskModalOpen"
            x-transition:enter="modal-enter"
            x-transition:leave="modal-leave"
        >
            <div style="width: 54px; height: 54px; border-radius: 50%; background: rgba(239, 68, 68, 0.12); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-4); color: var(--color-danger, #ef4444);">
                <svg style="width: 28px; height: 28px;" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>

            <h3 id="delete-major-task-modal-title" style="margin: 0 0 var(--space-2); font-size: 1.15rem; font-weight: 700; color: var(--color-ink);">
                Delete Major Task
            </h3>
            <p style="color: var(--color-muted); font-size: 0.875rem; line-height: 1.5; margin: 0 0 var(--space-5);">
                Are you sure you want to delete <strong style="color: var(--color-ink);" x-text="deleteTaskTarget.name"></strong> along with all its specific sub tasks? This action cannot be undone.
            </p>

            <div style="display: flex; gap: var(--space-3); justify-content: center;">
                <button
                    type="button"
                    class="btn btn-secondary"
                    style="min-width: 110px;"
                    @click="deleteTaskModalOpen = false"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="btn btn-danger"
                    style="min-width: 110px; background: var(--color-danger, #ef4444); border-color: var(--color-danger, #ef4444); color: #fff;"
                    @click="confirmDeleteTask()"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>

    {{-- Centered In-App Unassign Personnel Confirmation Modal --}}
    <div
        class="reassign-modal-overlay"
        style="display: none;"
        x-show="unassignPersonnelModalOpen"
        x-cloak
        role="dialog"
        aria-modal="true"
        aria-labelledby="unassign-personnel-modal-title"
    >
        <div class="reassign-modal-backdrop" @click="unassignPersonnelModalOpen = false"></div>
        <div
            class="reassign-modal-content card"
            style="max-width: 480px; width: 100%; text-align: center; padding: var(--space-6);"
            x-show="unassignPersonnelModalOpen"
            x-transition:enter="modal-enter"
            x-transition:leave="modal-leave"
        >
            <div style="width: 54px; height: 54px; border-radius: 50%; background: rgba(239, 68, 68, 0.12); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-4); color: var(--color-danger, #ef4444);">
                <svg style="width: 28px; height: 28px;" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                </svg>
            </div>

            <h3 id="unassign-personnel-modal-title" style="margin: 0 0 var(--space-2); font-size: 1.15rem; font-weight: 700; color: var(--color-ink);">
                Unassign Personnel
            </h3>
            <p style="color: var(--color-muted); font-size: 0.875rem; line-height: 1.5; margin: 0 0 var(--space-5);">
                Are you sure you want to unassign <strong style="color: var(--color-ink);" x-text="unassignPersonnelTarget.name"></strong> from this project? They will be returned to the available manpower pool.
            </p>

            <div style="display: flex; gap: var(--space-3); justify-content: center;">
                <button
                    type="button"
                    class="btn btn-secondary"
                    style="min-width: 110px;"
                    @click="unassignPersonnelModalOpen = false"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="btn btn-danger"
                    style="min-width: 110px; background: var(--color-danger, #ef4444); border-color: var(--color-danger, #ef4444); color: #fff;"
                    @click="confirmUnassignPersonnel()"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>

    {{-- Hidden form for file/attachment deletion --}}
    <form id="project-delete-file-form" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>

    {{-- Centered In-App Delete File / Attachment Confirmation Modal --}}
    <div
        class="reassign-modal-overlay"
        style="display: none;"
        x-show="deleteFileModalOpen"
        x-cloak
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-file-modal-title"
    >
        <div class="reassign-modal-backdrop" @click="deleteFileModalOpen = false"></div>
        <div
            class="reassign-modal-content card"
            style="max-width: 480px; width: 100%; text-align: center; padding: var(--space-6);"
            x-show="deleteFileModalOpen"
            x-transition:enter="modal-enter"
            x-transition:leave="modal-leave"
        >
            <div style="width: 54px; height: 54px; border-radius: 50%; background: rgba(239, 68, 68, 0.12); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-4); color: var(--color-danger, #ef4444);">
                <svg style="width: 28px; height: 28px;" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>

            <h3 id="delete-file-modal-title" style="margin: 0 0 var(--space-2); font-size: 1.15rem; font-weight: 700; color: var(--color-ink);">
                Delete File / Attachment
            </h3>
            <p style="color: var(--color-muted); font-size: 0.875rem; line-height: 1.5; margin: 0 0 var(--space-5);">
                Are you sure you want to delete <strong style="color: var(--color-ink);" x-text="deleteFileTarget.name"></strong>? This action cannot be undone.
            </p>

            <div style="display: flex; gap: var(--space-3); justify-content: center;">
                <button
                    type="button"
                    class="btn btn-secondary"
                    style="min-width: 110px;"
                    @click="deleteFileModalOpen = false"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="btn btn-danger"
                    style="min-width: 110px; background: var(--color-danger, #ef4444); border-color: var(--color-danger, #ef4444); color: #fff;"
                    @click="confirmDeleteFile()"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>

    {{-- ─── Modal 13: Warranty & Maintenance Information Modal ───────────────────── --}}
    @include('projects.partials.warranty-modal')

</div>
@endsection

@push('styles')
<style>
.project-file-item {
    transition: all 0.15s ease-in-out !important;
}
.project-file-item:hover {
    border-color: var(--color-primary) !important;
    background: var(--color-surface-alt) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.project-files-scroll-list::-webkit-scrollbar {
    width: 6px;
}
.project-files-scroll-list::-webkit-scrollbar-track {
    background: transparent;
}
.project-files-scroll-list::-webkit-scrollbar-thumb {
    background: var(--color-border, rgba(255, 255, 255, 0.15));
    border-radius: 9999px;
}
.project-files-scroll-list::-webkit-scrollbar-thumb:hover {
    background: var(--color-muted, rgba(255, 255, 255, 0.3));
}

.timeline-stages-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: var(--space-4);
}

.timeline-carousel-container {
    position: relative;
    width: 100%;
}

.timeline-carousel-track {
    display: flex;
    gap: var(--space-4);
    overflow-x: auto;
    padding: var(--space-2) 2px var(--space-4) 2px;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
}

.timeline-carousel-track::-webkit-scrollbar {
    height: 6px;
}

.timeline-carousel-track::-webkit-scrollbar-thumb {
    background: var(--color-border);
    border-radius: var(--radius-pill);
}

.timeline-carousel-track > .timeline-stage-card {
    flex: 0 0 calc(33.333% - var(--space-3));
    min-width: 260px;
    max-width: 360px;
    scroll-snap-align: start;
}

@media (max-width: 900px) {
    .timeline-carousel-track > .timeline-stage-card {
        flex: 0 0 calc(50% - var(--space-3));
    }
}

@media (max-width: 600px) {
    .timeline-carousel-track > .timeline-stage-card {
        flex: 0 0 85%;
    }
}

.timeline-stage-card {
    padding: var(--space-4);
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    display: flex;
    flex-direction: column;
    transition: all 0.2s ease;
}

.timeline-stage-card:hover {
    border-color: var(--color-accent);
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.timeline-stage-card--active {
    border-color: var(--color-accent);
    background: #fbfdff;
    box-shadow: 0 0 0 2px rgb(44 95 124 / 0.25);
}

.timeline-weekly-container {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
    margin-top: var(--space-4);
}

.timeline-week-row {
    display: flex;
    gap: var(--space-4);
    align-items: flex-start;
}

.timeline-week-indicator {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 32px;
    flex-shrink: 0;
}

.timeline-week-dot {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--color-surface-alt);
    border: 2px solid var(--color-border);
    color: var(--color-muted);
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

.timeline-week-dot--passed {
    background: var(--color-success-light);
    border-color: var(--color-success);
    color: var(--color-success);
}

.timeline-week-dot--current {
    background: var(--color-accent-light);
    border-color: var(--color-accent);
    color: var(--color-accent);
    box-shadow: 0 0 0 3px rgb(44 95 124 / 0.2);
}

.timeline-week-line {
    width: 2px;
    height: 100%;
    min-height: 40px;
    background: var(--color-border);
    margin: 4px 0;
}

.timeline-week-line--passed {
    background: var(--color-success);
}

.timeline-week-row--current .timeline-week-content {
    border-color: var(--color-accent);
    box-shadow: var(--shadow-sm);
}

.mb-6 { margin-bottom: 1.5rem; }

/* ─── Personnel Assign Multi-Select Row Layout ──────────────────────── */
.personnel-select-row {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-start !important;
    padding: 0.625rem 0.75rem !important;
    border-bottom: 1px solid var(--color-surface-alt) !important;
    cursor: pointer !important;
    transition: background var(--transition-fast) !important;
    margin: 0 !important;
    width: 100% !important;
    box-sizing: border-box !important;
}

.personnel-select-row:last-child {
    border-bottom: none !important;
}

.personnel-select-row:hover {
    background: var(--color-surface-alt) !important;
}

.personnel-select-row.is-selected {
    background: var(--color-surface-alt) !important;
}

.personnel-select-checkbox-cell {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
    width: 28px !important;
    margin-right: 0.625rem !important;
}

.personnel-select-checkbox {
    margin: 0 !important;
    width: 17px !important;
    height: 17px !important;
    flex-shrink: 0 !important;
    cursor: pointer !important;
    accent-color: var(--color-accent) !important;
}

.personnel-select-info-cell {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: space-between !important;
    flex: 1 1 auto !important;
    min-width: 0 !important;
    gap: 0.5rem !important;
}

.personnel-select-name-wrap {
    display: flex !important;
    align-items: baseline !important;
    gap: 0.375rem !important;
    min-width: 0 !important;
    overflow: hidden !important;
}

.personnel-select-name {
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    color: var(--color-ink) !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}

.personnel-select-id {
    font-size: 0.75rem !important;
    flex-shrink: 0 !important;
}

.personnel-select-badge {
    background: var(--color-surface-alt) !important;
    border: 1px solid var(--color-border) !important;
    font-size: 0.6875rem !important;
    font-weight: 600 !important;
    flex-shrink: 0 !important;
    white-space: nowrap !important;
}

/* ─── Disabled / Locked Subtask Styles ───────────────────────────────── */
.subtask-checkbox:disabled {
    cursor: not-allowed !important;
}

/* Only gray out uncompleted locked tasks (forward lock) */
.subtask-checkbox.is-forward-locked:disabled {
    opacity: 0.45;
    filter: grayscale(100%);
}

/* Completed locked tasks (reverse lock) retain normal check styling without being grayed out */
.subtask-checkbox.is-reverse-locked:disabled {
    opacity: 0.95;
    filter: none;
    accent-color: var(--color-success);
}

.subtask-row-locked {
    background: var(--color-surface-alt) !important;
    border-style: dashed !important;
}

.subtask-row-forward-locked {
    opacity: 0.6;
}

.subtask-row-locked label {
    cursor: not-allowed !important;
}

.subtask-row-forward-locked span[class*="subtask-title-"] {
    color: var(--color-muted) !important;
}

/* ─── Custom Floating Orange Tooltip ────────────────────────────────── */
.dex-tooltip-wrap {
    position: relative;
}

.dex-tooltip-bubble {
    position: absolute;
    bottom: calc(100% + 8px);
    left: 28px;
    z-index: 100;
    pointer-events: none;
    white-space: normal;
    min-width: 260px;
    max-width: 380px;
    padding: 0.5rem 0.75rem;
    background: #C98A2C; /* Brand Amber/Orange */
    color: #FFFFFF;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1.35;
    border-radius: var(--radius-md);
    box-shadow: 0 4px 14px rgba(201, 138, 44, 0.35), 0 2px 6px rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.2);
    animation: dexTooltipFadeIn 0.15s ease-out;
}

.dex-tooltip-bubble::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 18px;
    border-width: 6px;
    border-style: solid;
    border-color: #C98A2C transparent transparent transparent;
}

@keyframes dexTooltipFadeIn {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('projectDetail', projectDetail);
});

function projectDetail() {
    return {
        init() {
            window.__projectDetailComponent = this;
            const params = new URLSearchParams(window.location.search);
            const taskIdParam = params.get('task_id');
            if (taskIdParam) {
                const parsedId = parseInt(taskIdParam, 10);
                if (!isNaN(parsedId)) {
                    this.activeTaskId = parsedId;
                    this.checklistMinimized = false;
                }
            }
            @if(session('staff_submitted') || session('status'))
                this.expendituresMinimized = false;
            @endif
        },
        timelineMode: 'minimized',
        archiveModalOpen: false,
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
            const form = document.getElementById('project-delete-cost-form');
            form.action = this.deleteCostTarget.url;
            form.submit();
        },
        unassignPersonnelModalOpen: false,
        unassignPersonnelTarget: {
            url: '',
            name: ''
        },
        openUnassignPersonnelModal(url, name) {
            this.unassignPersonnelTarget = {
                url: url,
                name: name
            };
            this.unassignPersonnelModalOpen = true;
        },
        confirmUnassignPersonnel() {
            if (!this.unassignPersonnelTarget.url) return;
            const form = document.getElementById('project-unassign-personnel-form');
            form.action = this.unassignPersonnelTarget.url;
            form.submit();
        },
        deleteTaskModalOpen: false,
        deleteTaskTarget: {
            url: '',
            name: ''
        },
        openDeleteTaskModal(url, name) {
            this.deleteTaskTarget = {
                url: url,
                name: name
            };
            this.deleteTaskModalOpen = true;
        },
        confirmDeleteTask() {
            if (!this.deleteTaskTarget.url) return;
            const form = document.getElementById('project-delete-task-form');
            form.action = this.deleteTaskTarget.url;
            form.submit();
        },
        deleteFileModalOpen: false,
        deleteFileTarget: {
            url: '',
            name: ''
        },
        openDeleteFileModal(url, name) {
            this.deleteFileTarget = {
                url: url,
                name: name
            };
            this.deleteFileModalOpen = true;
        },
        confirmDeleteFile() {
            if (!this.deleteFileTarget.url) return;
            const form = document.getElementById('project-delete-file-form');
            form.action = this.deleteFileTarget.url;
            form.submit();
        },
        addCostModalOpen: false,
        openAddCostModal() {
            this.clearMaterialSelection();
            this.costForm.cost_type = 'materials';
            this.addCostModalOpen = true;
        },
        assignModalOpen: false,
        createMajorTaskModalOpen: false,
        editMajorTaskModalOpen: false,
        editSubtaskModalOpen: false,
        markCompletedModalOpen: false,
        warrantyInfoModalOpen: false,
        uploadFileModalOpen: false,
        uploadModalDefaultCategory: 'project_document',
        uploadModalFiles: [],
        uploadModalError: '',
        uploadModalSubmitting: false,
        isDraggingModal: false,
        openUploadProjectFileModal(defaultCat = 'project_document') {
            this.uploadModalDefaultCategory = defaultCat || 'project_document';
            this.uploadModalFiles = [];
            this.uploadModalError = '';
            this.uploadModalSubmitting = false;
            this.uploadFileModalOpen = true;
        },
        closeUploadProjectFileModal() {
            this.uploadFileModalOpen = false;
            this.uploadModalFiles = [];
            this.uploadModalError = '';
            this.uploadModalSubmitting = false;
        },
        handleModalFilesSelected(e) {
            const files = e.target.files;
            if (!files || files.length === 0) return;
            this.addModalFiles(Array.from(files));
            e.target.value = '';
        },
        handleModalFilesDropped(e) {
            const files = e.dataTransfer ? e.dataTransfer.files : null;
            if (!files || files.length === 0) return;
            this.addModalFiles(Array.from(files));
        },
        addModalFiles(newFiles) {
            this.uploadModalError = '';
            const maxSize = 20 * 1024 * 1024; // 20MB
            for (const file of newFiles) {
                if (file.size > maxSize) {
                    this.uploadModalError = `File "${file.name}" exceeds the 20MB limit.`;
                    continue;
                }
                if (!this.uploadModalFiles.some(f => f.name === file.name && f.file.size === file.size)) {
                    this.uploadModalFiles.push({
                        id: Date.now() + '_' + Math.random().toString(36).substr(2, 9),
                        file: file,
                        name: file.name,
                        size: file.size,
                        formattedSize: (file.size >= 1048576) ? (file.size / 1048576).toFixed(2) + ' MB' : (file.size / 1024).toFixed(1) + ' KB',
                        category: this.uploadModalDefaultCategory,
                        description: ''
                    });
                }
            }
            this.syncModalUploadInputs();
        },
        removeModalUploadFile(index) {
            this.uploadModalFiles.splice(index, 1);
            this.syncModalUploadInputs();
        },
        syncModalUploadInputs() {
            const container = document.getElementById('modal_project_files_inputs');
            if (!container) return;
            container.innerHTML = '';
            this.uploadModalFiles.forEach((item, idx) => {
                const fInput = document.createElement('input');
                fInput.type = 'file';
                fInput.name = `files[${idx}]`;
                fInput.style.display = 'none';
                try {
                    const dt = new DataTransfer();
                    dt.items.add(item.file);
                    fInput.files = dt.files;
                } catch (e) {
                    console.error('DataTransfer error', e);
                }
                container.appendChild(fInput);

                const cInput = document.createElement('input');
                cInput.type = 'hidden';
                cInput.name = `categories[${idx}]`;
                cInput.value = item.category || this.uploadModalDefaultCategory;
                container.appendChild(cInput);

                const dInput = document.createElement('input');
                dInput.type = 'hidden';
                dInput.name = `descriptions[${idx}]`;
                dInput.value = item.description || '';
                container.appendChild(dInput);
            });

            const singleSlot = document.getElementById('modal_single_file_slot');
            if (singleSlot) {
                singleSlot.innerHTML = '';
                if (this.uploadModalFiles.length > 0) {
                    const singleInput = document.createElement('input');
                    singleInput.type = 'file';
                    singleInput.name = 'file';
                    singleInput.style.display = 'none';
                    try {
                        const dt = new DataTransfer();
                        dt.items.add(this.uploadModalFiles[0].file);
                        singleInput.files = dt.files;
                    } catch(e) {}
                    singleSlot.appendChild(singleInput);
                }
            }
        },
        submitModalUploadForm(formEl) {
            if (!this.uploadModalFiles || this.uploadModalFiles.length === 0) {
                this.uploadModalError = 'Please select at least one file to upload.';
                return;
            }
            this.uploadModalSubmitting = true;
            this.syncModalUploadInputs();
            formEl.submit();
        },
        subtaskFileModalOpen: false,
        viewFileModalOpen: false,
        confirmCompletionModalOpen: false,
        taskProofModalOpen: false,
        taskProofData: {},
        openTaskProofModal(payload) {
            this.taskProofData = payload || {};
            this.taskProofModalOpen = true;
        },
        completingSubtask: {
            projectId: null,
            taskId: null,
            subtaskId: null,
            taskName: '',
            subtaskTitle: '',
            checkboxEl: null,
        },
        completionNotes: '',
        completionFiles: [],
        completionFileError: '',
        completionSubmitting: false,
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
            if (this.selectedMaterialId) {
                this.costForm.cost_type = 'materials';
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
        },
        activeFileData: {
            id: null,
            name: '',
            size: '',
            type: '',
            description: '',
            uploader: '',
            date: '',
            download_url: '',
            update_url: '',
            delete_url: '',
            is_img: false,
            is_pdf: false,
            is_word: false,
            subtask_title: null
        },
        activeSubtaskData: {
            taskId: null,
            subtaskId: null,
            title: '',
            files: [],
            uploadUrl: ''
        },
        activeTaskId: {{ $project->tasks->first()?->id ?? 'null' }},
        checklistMinimized: true,
        expendituresMinimized: true,
        manpowerMinimized: true,
        auditTrailMinimized: true,
        subtaskLocks: @js($initialSubtaskLocks),
        subtaskCompleted: @js($initialSubtaskCompleted),
        pendingSubtasks: @js($initialSubtaskPending),
        initialSubtasks: [{ title: '', due_date: '{{ $defaultStartDate->format('Y-m-d') }}' }],
        editingTask: {
            id: null,
            task_name: '',
            start_date: '',
            end_date: '',
            description: '',
            updateUrl: ''
        },
        newTask: {
            start_date: '{{ $defaultStartDate->format('Y-m-d') }}',
            end_date: '{{ $defaultEndDate->format('Y-m-d') }}'
        },
        editingSubtask: {
            id: null,
            task_id: null,
            title: '',
            due_date: '',
            min_date: '',
            max_date: '',
            updateUrl: ''
        },
        openEditTaskModal(task) {
            this.editingTask = {
                id: task.id,
                task_name: task.task_name,
                start_date: (task.start_date || '').substring(0, 10),
                end_date: (task.end_date || '').substring(0, 10),
                description: task.description || '',
                updateUrl: '/projects/{{ $project->id }}/tasks/' + task.id
            };
            this.editMajorTaskModalOpen = true;
        },
        openViewFileModal(fileData) {
            this.activeFileData = Object.assign({}, fileData);
            this.viewFileModalOpen = true;
        },
        deleteActiveFile() {
            if (!this.activeFileData.delete_url) return;
            this.openDeleteFileModal(this.activeFileData.delete_url, this.activeFileData.name);
        },
        openSubtaskUploadModal(taskId, subtaskId, title, files = [], notes = '') {
            this.activeSubtaskData = {
                taskId: taskId,
                subtaskId: subtaskId,
                title: title,
                notes: notes || '',
                files: files || [],
                uploadUrl: `/projects/{{ $project->id }}/tasks/${taskId}/subtasks/${subtaskId}/files`
            };
            this.subtaskFileModalOpen = true;
        },
        openEditSubtaskModal(taskId, subtaskId, currentTitle, currentDate = '', minDate = '', maxDate = '') {
            this.editingSubtask = {
                id: subtaskId,
                task_id: taskId,
                title: currentTitle,
                due_date: currentDate || '',
                min_date: minDate || '',
                max_date: maxDate || '',
                updateUrl: '/projects/{{ $project->id }}/tasks/' + taskId + '/subtasks/' + subtaskId
            };
            this.editSubtaskModalOpen = true;
        },
        addSubtaskField() {
            this.initialSubtasks.push({ title: '', due_date: this.newTask.start_date || '' });
        },
        removeSubtaskField(index) {
            if (this.initialSubtasks.length > 1) {
                this.initialSubtasks.splice(index, 1);
            } else {
                this.initialSubtasks[0] = { title: '', due_date: this.newTask.start_date || '' };
            }
        },
        toggleTimeline() {
            this.timelineMode = (this.timelineMode === 'minimized') ? 'expanded' : 'minimized';
        },
        scrollCarousel(direction) {
            const track = document.getElementById('timeline-carousel-track');
            if (track) {
                const scrollAmount = track.clientWidth * 0.75;
                track.scrollBy({ left: direction === 'left' ? -scrollAmount : scrollAmount, behavior: 'smooth' });
            }
        },
        onSubtaskCheckboxClick(projectId, taskId, subtaskId, taskName, subtaskTitle, checkboxEl) {
            if (this.pendingSubtasks && this.pendingSubtasks[subtaskId]) {
                checkboxEl.checked = false;
                if (window.showToast) {
                    window.showToast({
                        type: 'warning',
                        title: 'Awaiting Review',
                        message: 'This task completion request is currently pending review by a manager or administrator.',
                        duration: 5000
                    });
                }
                return;
            }

            if (this.subtaskLocks && this.subtaskLocks[subtaskId]) {
                checkboxEl.checked = !checkboxEl.checked;
                return;
            }

            if (checkboxEl.checked) {
                // Keep visually unchecked while confirmation modal is open
                checkboxEl.checked = false;
                this.completingSubtask = {
                    projectId: projectId,
                    taskId: taskId,
                    subtaskId: subtaskId,
                    taskName: taskName,
                    subtaskTitle: subtaskTitle,
                    checkboxEl: checkboxEl
                };
                this.completionNotes = '';
                this.completionFiles = [];
                this.completionFileError = '';
                this.completionSubmitting = false;
                this.confirmCompletionModalOpen = true;
            } else {
                // Unchecking an already completed task (Admin/Manager only)
                if (!confirm('Are you sure you want to uncheck this task and mark it as incomplete?')) {
                    checkboxEl.checked = true;
                    return;
                }
                this.executeToggleSubtask(projectId, taskId, subtaskId, checkboxEl, false);
            }
        },

        handleProofFilesSelected(event) {
            const files = event.target.files;
            if (!files || files.length === 0) return;
            this.addProofFiles(Array.from(files));
            event.target.value = '';
        },

        handleProofFilesDropped(event) {
            const files = event.dataTransfer.files;
            if (!files || files.length === 0) return;
            this.addProofFiles(Array.from(files));
        },

        addProofFiles(newFiles) {
            this.completionFileError = '';
            const maxSize = 20 * 1024 * 1024; // 20MB
            for (const file of newFiles) {
                if (file.size > maxSize) {
                    this.completionFileError = `File "${file.name}" exceeds the 20MB limit.`;
                    continue;
                }
                const already = this.completionFiles.some(f => f.name === file.name && f.file.size === file.size);
                if (!already) {
                    this.completionFiles.push({
                        file: file,
                        name: file.name,
                        size: (file.size / (1024 * 1024)).toFixed(2) + ' MB'
                    });
                }
            }
        },

        removeProofFile(index) {
            this.completionFiles.splice(index, 1);
        },

        getFileIcon(name) {
            if (!name) return '📄';
            const ext = name.split('.').pop().toLowerCase();
            if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext)) return '🖼️';
            if (['pdf'].includes(ext)) return '📕';
            if (['doc', 'docx'].includes(ext)) return '📘';
            if (['xls', 'xlsx'].includes(ext)) return '📗';
            if (['dwg', 'dxf', 'cad'].includes(ext)) return '📐';
            return '📄';
        },

        cancelTaskCompletion() {
            this.confirmCompletionModalOpen = false;
            const subtaskId = this.completingSubtask ? this.completingSubtask.subtaskId : null;
            if (subtaskId) {
                const checkboxes = document.querySelectorAll(`.subtask-check-${subtaskId}`);
                checkboxes.forEach(cb => { cb.checked = false; });
            }
            this.completingSubtask = {};
            this.completionNotes = '';
            this.completionFiles = [];
            this.completionFileError = '';
            this.completionSubmitting = false;
        },

        submitTaskCompletion() {
            if (!this.completingSubtask || !this.completingSubtask.subtaskId) return;

            const notes = (this.completionNotes || '').trim();
            if (!notes) {
                this.completionFileError = 'Please enter remarks and notes before confirming.';
                return;
            }
            if (!this.completionFiles || this.completionFiles.length === 0) {
                this.completionFileError = 'Please attach at least one proof file before confirming.';
                return;
            }

            this.completionSubmitting = true;
            this.completionFileError = '';

            const formData = new FormData();
            formData.append('_method', 'PATCH');
            formData.append('notes', notes);
            formData.append('proof_file', this.completionFiles[0].file);
            this.completionFiles.forEach(item => {
                formData.append('proof_files[]', item.file);
            });

            const projectId = this.completingSubtask.projectId;
            const taskId = this.completingSubtask.taskId;
            const subtaskId = this.completingSubtask.subtaskId;
            const checkboxEl = this.completingSubtask.checkboxEl;

            const url = `/projects/${projectId}/tasks/${taskId}/subtasks/${subtaskId}/toggle`;

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(async res => {
                let data = {};
                try {
                    data = await res.json();
                } catch (e) {
                    data = { message: res.statusText || 'Unexpected response from server.' };
                }
                return { status: res.status, ok: res.ok, data };
            })
            .then(({ status, ok, data }) => {
                this.completionSubmitting = false;

                if (!ok || !data.success) {
                    const errorMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Unable to complete task.');
                    this.completionFileError = errorMsg;
                    if (checkboxEl) checkboxEl.checked = false;
                    if (window.showToast) {
                        window.showToast({
                            type: 'error',
                            title: 'Submission Failed',
                            message: errorMsg,
                            duration: 5000
                        });
                    }
                    return;
                }

                this.confirmCompletionModalOpen = false;

                if (data.pending_approval) {
                    const checkboxes = document.querySelectorAll(`.subtask-check-${subtaskId}`);
                    checkboxes.forEach(cb => { cb.checked = false; });
                    this.pendingSubtasks[subtaskId] = true;
                    if (data.subtask_locks) {
                        this.subtaskLocks = data.subtask_locks;
                    }
                    window.dispatchEvent(new CustomEvent('refresh-notifications'));
                    const toastDetail = {
                        type: 'success',
                        title: 'Waiting for approval',
                        message: data.message || 'Your action has been submitted successfully and will be reviewed by your manager or admin to take effect.',
                        duration: 6500
                    };
                    if (window.showToast) {
                        window.showToast(toastDetail);
                    } else {
                        window.dispatchEvent(new CustomEvent('show-toast', { detail: toastDetail }));
                    }
                    return;
                }

                // Admin / Manager direct completion
                const checkboxes = document.querySelectorAll(`.subtask-check-${subtaskId}`);
                checkboxes.forEach(cb => { cb.checked = true; });

                const titleEls = document.querySelectorAll(`.subtask-title-${subtaskId}`);
                titleEls.forEach(el => {
                    el.style.textDecoration = 'line-through';
                    el.style.color = 'var(--color-muted)';
                });

                window.__proofModalData = window.__proofModalData || {};
                if (data.proof_modal_data) {
                    window.__proofModalData[subtaskId] = data.proof_modal_data;
                }
                const proofContainers = [
                    document.getElementById(`subtask-proof-container-${subtaskId}`),
                    document.getElementById(`expanded-subtask-proof-container-${subtaskId}`)
                ];
                proofContainers.forEach(container => {
                    if (container) {
                        container.style.display = 'inline-flex';
                        const btnLabel = 'View';
                        container.innerHTML = `
                            <button type="button" onclick="window.__openProofModal(${subtaskId})" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 4px; border-color: var(--color-border); color: var(--color-ink);" title="${btnLabel}">
                                <svg style="width: 13px; height: 13px; color: var(--color-accent);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                                <span>${btnLabel}</span>
                            </button>
                        `;
                    }
                });

                this.applySubtaskResponseUI(taskId, subtaskId, data);

                if (window.showToast) {
                    window.showToast({
                        type: 'success',
                        title: 'Task Completed',
                        message: 'Task marked as done with completion proof recorded.',
                        duration: 5000
                    });
                } else {
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: {
                            type: 'success',
                            title: 'Task Completed',
                            message: 'Task marked as done with completion proof recorded.',
                            duration: 5000
                        }
                    }));
                }
            })
            .catch(err => {
                this.completionSubmitting = false;
                const netErrMsg = 'A network error occurred while submitting. Please try again.';
                this.completionFileError = netErrMsg;
                if (checkboxEl) checkboxEl.checked = false;
                if (window.showToast) {
                    window.showToast({
                        type: 'error',
                        title: 'Submission Error',
                        message: netErrMsg,
                        duration: 5000
                    });
                }
                console.error('Error completing subtask:', err);
            });
        },

        executeToggleSubtask(projectId, taskId, subtaskId, checkboxEl, newCompleted) {
            const url = `/projects/${projectId}/tasks/${taskId}/subtasks/${subtaskId}/toggle`;
            const formData = new FormData();
            formData.append('_method', 'PATCH');

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const checkboxes = document.querySelectorAll(`.subtask-check-${subtaskId}`);
                    checkboxes.forEach(cb => { cb.checked = data.is_completed; });

                    const titleEls = document.querySelectorAll(`.subtask-title-${subtaskId}`);
                    titleEls.forEach(el => {
                        if (data.is_completed) {
                            el.style.textDecoration = 'line-through';
                            el.style.color = 'var(--color-muted)';
                        } else {
                            el.style.textDecoration = 'none';
                            el.style.color = 'var(--color-ink)';
                        }
                    });

                    this.applySubtaskResponseUI(taskId, subtaskId, data);

                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: {
                            type: 'info',
                            title: 'Task Incomplete',
                            message: 'Task marked as incomplete.',
                            duration: 4000
                        }
                    }));
                } else if (data.message) {
                    if (checkboxEl) {
                        checkboxEl.checked = !newCompleted;
                    }
                    window.dispatchEvent(new CustomEvent('show-toast', {
                        detail: {
                            type: 'warning',
                            title: 'Sequential Task Progression',
                            message: data.message,
                            duration: 5000
                        }
                    }));
                }
            })
            .catch(err => {
                console.error('Error toggling subtask:', err);
                if (checkboxEl) {
                    checkboxEl.checked = !newCompleted;
                }
            });
        },

        toggleSubtask(projectId, taskId, subtaskId, checkboxEl) {
            this.executeToggleSubtask(projectId, taskId, subtaskId, checkboxEl, false);
        },

        applySubtaskResponseUI(taskId, subtaskId, data) {
            const color = data.progress === 100 
                ? 'var(--color-success)' 
                : (data.progress > 0 ? 'var(--color-accent)' : 'var(--color-border)');

            // 1. Update Carousel Card Progress Bar, Percentage & Count
            const cardProgressText = document.getElementById(`card-progress-text-${taskId}`);
            const cardProgressBar = document.getElementById(`card-progress-bar-${taskId}`);
            const cardTaskCount = document.getElementById(`card-task-count-${taskId}`);
            if (cardProgressText) cardProgressText.textContent = `${data.progress}%`;
            if (cardProgressBar) {
                cardProgressBar.style.width = `${data.progress}%`;
                cardProgressBar.style.background = color;
            }
            if (cardTaskCount) {
                cardTaskCount.textContent = `${data.completed_count}/${data.total_count} tasks`;
            }

            // 2. Update Status Pills (Card & Detail)
            const statusLabel = data.status === 'completed' ? 'Completed' : (data.status === 'in_progress' ? 'In Progress' : 'Scheduled');
            const statusClass = data.status === 'completed' ? 'completed' : (data.status === 'in_progress' ? 'ongoing' : 'pending');

            const cardPill = document.getElementById(`card-status-pill-${taskId}`);
            if (cardPill) {
                cardPill.textContent = statusLabel;
                cardPill.className = `status-pill status-pill--${statusClass}`;
            }

            const detailPill = document.getElementById(`detail-status-pill-${taskId}`);
            if (detailPill) {
                detailPill.textContent = statusLabel;
                detailPill.className = `status-pill status-pill--${statusClass}`;
            }

            // 3. Update Expanded View Progress Bar, Text, Count, Status Pill & Dot
            const expProgressBar = document.getElementById(`expanded-progress-bar-${taskId}`);
            const expProgressText = document.getElementById(`expanded-progress-text-${taskId}`);
            const expProgressCount = document.getElementById(`expanded-progress-count-${taskId}`);
            const expStatusPill = document.getElementById(`expanded-status-pill-${taskId}`);
            const expDot = document.getElementById(`expanded-week-dot-${taskId}`);

            if (expProgressBar) {
                expProgressBar.style.width = `${data.progress}%`;
                expProgressBar.style.background = color;
            }
            if (expProgressText) expProgressText.textContent = `${data.progress}%`;
            if (expProgressCount) expProgressCount.textContent = `Progress (${data.completed_count}/${data.total_count} tasks)`;
            if (expStatusPill) {
                expStatusPill.textContent = statusLabel;
                expStatusPill.className = `status-pill status-pill--${statusClass}`;
            }
            if (expDot) {
                if (data.status === 'completed') {
                    expDot.className = 'timeline-week-dot timeline-week-dot--passed';
                    expDot.textContent = '✓';
                } else if (data.status === 'in_progress') {
                    expDot.className = 'timeline-week-dot timeline-week-dot--current';
                    expDot.textContent = expDot.getAttribute('data-week-num') || '';
                } else {
                    expDot.className = 'timeline-week-dot';
                    expDot.textContent = expDot.getAttribute('data-week-num') || '';
                }
            }

            // 4. Update Overall Project Progress in Timeline Card Header
            const overallContainer = document.getElementById('project-overall-progress-container');
            const overallProgressText = document.getElementById('project-overall-progress-text');
            const overallCountText = document.getElementById('project-overall-count-text');
            if (overallContainer) overallContainer.style.display = '';
            if (overallProgressText && data.overall_progress !== undefined) {
                overallProgressText.textContent = `${data.overall_progress}%`;
                overallProgressText.style.color = data.overall_progress === 100 ? 'var(--color-success)' : 'var(--color-accent)';
            }
            if (overallCountText && data.completed_subtasks_across_project !== undefined) {
                overallCountText.textContent = `(${data.completed_subtasks_across_project}/${data.total_subtasks_across_project} tasks)`;
            }

            // 5. Refresh project status badge in overview header if updated
            if (data.project_status) {
                const projectPill = document.getElementById('project-overview-status-pill');
                if (projectPill) {
                    projectPill.textContent = data.project_status_label;
                    const pillClass = (data.project_status === 'ongoing' || data.project_status === 'in_progress') ? 'ongoing' : data.project_status;
                    projectPill.className = `status-pill status-pill--${pillClass}`;
                }
            }

            // 6. Dynamically update Audit Trail & Status History table
            const tbody = document.getElementById('audit-trail-tbody');
            if (tbody) {
                const appendAuditRow = (entry) => {
                    const emptyRow = document.getElementById('audit-trail-empty-row');
                    if (emptyRow) {
                        emptyRow.remove();
                    }
                    const tr = document.createElement('tr');
                    const pillClass = (entry.status === 'ongoing' || entry.status === 'in_progress') ? 'ongoing' : entry.status;
                    tr.innerHTML = `
                        <td class="font-mono" style="font-size: 0.8125rem; white-space: nowrap;">
                            ${entry.date_time}
                        </td>
                        <td>
                            <span class="status-pill status-pill--${pillClass}">
                                <span class="status-dot"></span>
                                ${entry.status_label}
                            </span>
                        </td>
                        <td>
                            <strong>${entry.user_name}</strong>
                        </td>
                        <td>
                            ${entry.notes}
                        </td>
                    `;
                    tbody.prepend(tr);
                };

                if (data.transition_audit_entry) {
                    appendAuditRow(data.transition_audit_entry);
                }
                if (data.audit_entry) {
                    appendAuditRow(data.audit_entry);
                }

                const countEl = document.getElementById('audit-trail-count');
                if (countEl) {
                    const currentRows = tbody.querySelectorAll('tr').length;
                    countEl.textContent = `${currentRows} ${currentRows === 1 ? 'event' : 'events'}`;
                }
            }

            // 7. Pop up completion confirmation modal if all subtasks across the project are completed
            if (data.prompt_completion) {
                this.markCompletedModalOpen = true;
            }

            // 8. Update subtaskLocks map so succeeding checkboxes unlock or lock sequentially
            if (data.subtask_locks) {
                this.subtaskLocks = data.subtask_locks;
            }
            if (data.subtask_completed) {
                this.subtaskCompleted = data.subtask_completed;
            } else {
                this.subtaskCompleted[subtaskId] = data.is_completed;
            }
        }
    };
}
window.projectDetail = projectDetail;

window.__openProofModal = function(subtaskId) {
    if (window.__proofModalData && window.__proofModalData[subtaskId] && window.__projectDetailComponent) {
        window.__projectDetailComponent.openTaskProofModal(window.__proofModalData[subtaskId]);
    }
};
</script>
@endpush
