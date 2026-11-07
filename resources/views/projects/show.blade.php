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
    $foundFirstUncompleted = false;
    $totalOrderedSubtasks = $orderedSubtasks->count();
    $isProjectReadOnly = $project->trashed() || $project->status === 'completed';

    foreach ($orderedSubtasks as $idx => $st) {
        $initialSubtaskCompleted[$st->id] = (bool) $st->is_completed;
        if ($st->is_completed) {
            $hasCompletedAfter = false;
            for ($k = $idx + 1; $k < $totalOrderedSubtasks; $k++) {
                if ($orderedSubtasks[$k]->is_completed) {
                    $hasCompletedAfter = true;
                    break;
                }
            }
            $initialSubtaskLocks[$st->id] = $hasCompletedAfter;
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
                <div><dt>Warranty Period</dt><dd class="font-mono">{{ $project->warranty_period ?? '1 Year' }} @if($project->warranty_end_date)<span class="text-muted" style="font-size: 0.8125rem;">(Until {{ $project->warranty_end_date->format('M j, Y') }})</span>@endif</dd></div>
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
            <section class="card card-glass" aria-labelledby="project-files-heading" id="project-files-card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-2);">
                    <div style="display: flex; align-items: center; gap: var(--space-2);">
                        <svg style="width: 18px; height: 18px; color: var(--color-primary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <h2 id="project-files-heading" style="margin: 0; font-size: 0.9375rem; font-weight: 700;">Project Files & Documents</h2>
                        <span class="badge font-mono" style="background: var(--color-surface-alt); font-size: 0.75rem; font-weight: 600;">{{ $project->files->count() }}</span>
                    </div>
                    @can('update', $project)
                        @if ($isProjectReadOnly)
                            <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.6; cursor: not-allowed; white-space: nowrap; font-size: 0.75rem; padding: 4px 10px;" title="{{ $project->trashed() ? 'Cannot upload files to an archived project' : 'Cannot upload files to a completed project unless reactivated' }}">
                                + Upload File
                            </button>
                        @else
                            <button type="button" class="btn btn-primary btn-sm" @click="uploadFileModalOpen = true" style="white-space: nowrap; font-size: 0.75rem; padding: 4px 10px;">
                                + Upload File
                            </button>
                        @endif
                    @endcan
                </div>
                <div class="card-body" style="padding: var(--space-3) var(--space-4);">
                    @if ($project->files->isEmpty())
                        <div style="text-align: center; padding: var(--space-5) var(--space-3); color: var(--color-muted);">
                            <svg style="width: 32px; height: 32px; margin: 0 auto var(--space-2); opacity: 0.4;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <p style="margin: 0; font-size: 0.8125rem;">No files uploaded yet.</p>
                            <p class="form-hint" style="margin-top: 4px; font-size: 0.75rem;">Supported: PDF, DOCX, and Images (up to 20MB).</p>
                        </div>
                    @else
                        <div style="display: flex; flex-direction: column; gap: var(--space-2); max-height: 280px; overflow-y: auto; padding-right: 2px;">
                            @foreach ($project->files as $file)
                                @php
                                    $fileData = [
                                        'id' => $file->id,
                                        'name' => $file->file_name,
                                        'size' => $file->formatted_size,
                                        'type' => $file->file_type,
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
                                    style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); padding: var(--space-2) var(--space-3); background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-md); font-size: 0.8125rem; cursor: pointer; transition: all 0.15s ease;"
                                    @click="openViewFileModal({{ json_encode($fileData) }})"
                                    title="Click to view details, update description, download, or delete"
                                >
                                    <div style="display: flex; align-items: center; gap: var(--space-2); min-width: 0; flex: 1;">
                                        @if ($file->is_image)
                                            <span style="font-size: 1.125rem;">🖼️</span>
                                        @elseif ($file->is_pdf)
                                            <span style="font-size: 1.125rem;">📕</span>
                                        @elseif ($file->is_word)
                                            <span style="font-size: 1.125rem;">📘</span>
                                        @else
                                            <span style="font-size: 1.125rem;">📄</span>
                                        @endif
                                        <div style="min-width: 0; flex: 1;">
                                            <div style="display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap;">
                                                <span class="table-link font-bold" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px;" title="{{ $file->file_name }}">
                                                    {{ $file->file_name }}
                                                </span>
                                                @if ($file->file_type === 'invoice')
                                                    <span class="badge" style="background: rgba(34, 197, 94, 0.12); color: var(--color-success); font-size: 0.6875rem; font-weight: 700;">Invoice</span>
                                                @elseif ($file->file_type === 'task_attachment' && $file->subtask)
                                                    <span class="badge" style="background: var(--color-surface-alt); font-size: 0.6875rem;" title="Subtask: {{ $file->subtask->title }}">
                                                        Task #{{ $file->subtask->id }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-muted" style="font-size: 0.6875rem; display: flex; gap: var(--space-2); align-items: center;">
                                                <span>{{ $file->formatted_size }}</span>
                                                <span>·</span>
                                                <span>{{ $file->created_at->format('M j, Y') }}</span>
                                                @if ($file->uploader)
                                                    <span>·</span>
                                                    <span>by {{ $file->uploader->name }}</span>
                                                @endif
                                                @if ($file->description)
                                                    <span>·</span>
                                                    <span style="font-style: italic; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px;">"{{ $file->description }}"</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: var(--space-1); flex-shrink: 0;" @click.stop>
                                        <a href="{{ route('projects.files.download', [$project, $file]) }}" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" title="Direct Download">
                                            ⬇
                                        </a>
                                        <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem;" @click="openViewFileModal({{ json_encode($fileData) }})" title="Details & Edit">
                                            ⚙️
                                        </button>
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
                                            
                                            <form 
                                                method="POST" 
                                                action="{{ route('projects.tasks.destroy', [$project, $task]) }}" 
                                                class="inline-form"
                                                onsubmit="return confirm('Delete this major task and all its sub tasks? This action cannot be undone.');"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--color-danger); border-color: #fad2cf;" id="btn-delete-major-task-{{ $task->id }}">
                                                    🗑 Delete
                                                </button>
                                            </form>
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
                                            @endphp
                                            <li 
                                                style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); background: var(--color-surface); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--color-border); transition: background 0.15s ease;"
                                                :class="{ 
                                                    'subtask-row-locked': !!subtaskLocks[{{ $subtask->id }}],
                                                    'subtask-row-forward-locked': !!subtaskLocks[{{ $subtask->id }}] && !subtaskCompleted[{{ $subtask->id }}]
                                                }"
                                                id="subtask-row-{{ $subtask->id }}"
                                                x-data="{ showLockedTooltip: false }"
                                                class="dex-tooltip-wrap"
                                                @mouseleave="showLockedTooltip = false"
                                            >
                                                {{-- Floating Brand-Orange Tooltip on Hover when untickable --}}
                                                <div 
                                                    x-show="showLockedTooltip && !!subtaskLocks[{{ $subtask->id }}]"
                                                    x-cloak
                                                    class="dex-tooltip-bubble"
                                                >
                                                    <span x-text="subtaskCompleted[{{ $subtask->id }}] ? 'You cannot uncheck your previous task unless you uncheck the task that comes after it.' : 'You cannot proceed to mark these tasks as in-progress, as you still have previous on-going task.'"></span>
                                                </div>

                                                <label 
                                                    style="display: flex; align-items: center; gap: var(--space-3); flex: 1; margin-bottom: 0; cursor: {{ ($isProjectReadOnly || $isSubtaskLocked) ? 'not-allowed' : 'pointer' }};"
                                                    :style="subtaskLocks[{{ $subtask->id }}] ? 'cursor: not-allowed !important;' : ''"
                                                    @mouseenter="if (subtaskLocks[{{ $subtask->id }}]) showLockedTooltip = true"
                                                    @click="if (subtaskLocks[{{ $subtask->id }}]) showLockedTooltip = true"
                                                >
                                                    <input 
                                                        type="checkbox" 
                                                        class="subtask-checkbox subtask-check-{{ $subtask->id }}"
                                                        :class="{
                                                            'is-forward-locked': !!subtaskLocks[{{ $subtask->id }}] && !subtaskCompleted[{{ $subtask->id }}],
                                                            'is-reverse-locked': !!subtaskLocks[{{ $subtask->id }}] && !!subtaskCompleted[{{ $subtask->id }}]
                                                        }"
                                                        id="subtask-check-{{ $subtask->id }}"
                                                        {{ $subtask->is_completed ? 'checked' : '' }}
                                                        {{ ($isProjectReadOnly || $isSubtaskLocked) ? 'disabled' : '' }}
                                                        :disabled="{{ $isProjectReadOnly ? 'true' : '!!subtaskLocks[' . $subtask->id . ']' }}"
                                                        @change="toggleSubtask({{ $project->id }}, {{ $task->id }}, {{ $subtask->id }}, $el)"
                                                        style="width: 18px; height: 18px; accent-color: var(--color-success); cursor: {{ ($isProjectReadOnly || $isSubtaskLocked) ? 'not-allowed' : 'pointer' }};"
                                                        :style="subtaskLocks[{{ $subtask->id }}] ? 'cursor: not-allowed !important;' : ''"
                                                    >
                                                    <span 
                                                        class="subtask-title-{{ $subtask->id }}"
                                                        style="font-size: 0.875rem; {{ $subtask->is_completed ? 'text-decoration: line-through; color: var(--color-muted);' : 'color: var(--color-ink); font-weight: 500;' }}"
                                                        :style="(subtaskLocks[{{ $subtask->id }}] && !subtaskCompleted[{{ $subtask->id }}]) ? 'color: var(--color-muted) !important;' : ''"
                                                    >
                                                        {{ $subtask->title }}
                                                    </span>
                                                </label>

                                                <div style="display: flex; align-items: center; gap: var(--space-2); flex-shrink: 0;">
                                                    @if ($subtask->due_date)
                                                        <span class="badge font-mono" style="background: var(--color-surface-alt); color: var(--color-ink); font-size: 0.75rem; padding: 2px 8px; border: 1px solid var(--color-border);" title="Target Date">
                                                            📅 {{ $subtask->due_date->format('M j, Y') }}
                                                        </span>
                                                    @endif

                                                    {{-- Subtask Attachment Button & Count --}}
                                                    <button 
                                                        type="button" 
                                                        class="btn-icon" 
                                                        title="{{ $isProjectReadOnly ? 'View files for this sub task (' . $subtask->files->count() . ' attached)' : 'Upload or view files for this sub task (' . $subtask->files->count() . ' attached)' }}"
                                                        @click="openSubtaskUploadModal({{ $task->id }}, {{ $subtask->id }}, '{{ addslashes($subtask->title) }}', {{ json_encode($subtask->files->map(fn($f) => ['id' => $f->id, 'name' => $f->file_name, 'size' => $f->formatted_size, 'url' => route('projects.files.download', [$project, $f]), 'is_img' => $f->is_image, 'is_pdf' => $f->is_pdf, 'is_word' => $f->is_word])) }})"
                                                        style="background: none; border: none; cursor: pointer; color: {{ $subtask->files->isNotEmpty() ? 'var(--color-primary)' : 'var(--color-muted)' }}; padding: 4px; border-radius: var(--radius-sm); position: relative;"
                                                        id="btn-files-subtask-{{ $subtask->id }}"
                                                    >
                                                        <svg style="width: 15px; height: 15px;" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M15.621 4.379a3 3 0 00-4.242 0l-7 7a3 3 0 004.241 4.243h.001l.497-.5a.75.75 0 011.064 1.057l-.498.501-.002.002a4.5 4.5 0 01-6.364-6.364l7-7a4.5 4.5 0 016.368 6.36l-3.455 3.553A2.625 2.625 0 119.5 9.525l3.45-3.451a.75.75 0 111.061 1.06l-3.45 3.451a1.125 1.125 0 001.587 1.595l3.454-3.553a3 3 0 000-4.248z" clip-rule="evenodd" />
                                                        </svg>
                                                        @if ($subtask->files->isNotEmpty())
                                                            <span style="font-size: 0.625rem; font-weight: 700; color: var(--color-primary); margin-left: 1px;">{{ $subtask->files->count() }}</span>
                                                        @endif
                                                    </button>

                                                    @can('update', $project)
                                                        @if (!$isProjectReadOnly)
                                                            <button 
                                                                type="button" 
                                                                class="btn-icon" 
                                                                title="Edit Sub Task"
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
                                                        @endphp
                                                        <li 
                                                            style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); font-size: 0.8125rem;"
                                                            :class="{ 
                                                                'subtask-row-locked': !!subtaskLocks[{{ $subtask->id }}],
                                                                'subtask-row-forward-locked': !!subtaskLocks[{{ $subtask->id }}] && !subtaskCompleted[{{ $subtask->id }}]
                                                            }"
                                                            id="expanded-subtask-row-{{ $subtask->id }}"
                                                            x-data="{ showExpandedLockedTooltip: false }"
                                                            class="dex-tooltip-wrap"
                                                            @mouseleave="showExpandedLockedTooltip = false"
                                                        >
                                                            {{-- Floating Brand-Orange Tooltip on Hover when untickable --}}
                                                            <div 
                                                                x-show="showExpandedLockedTooltip && !!subtaskLocks[{{ $subtask->id }}]"
                                                                x-cloak
                                                                class="dex-tooltip-bubble"
                                                            >
                                                                <span x-text="subtaskCompleted[{{ $subtask->id }}] ? 'You cannot uncheck your previous task unless you uncheck the task that comes after it.' : 'You cannot proceed to mark these tasks as in-progress, as you still have previous on-going task.'"></span>
                                                            </div>

                                                            <label 
                                                                style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: 0; cursor: {{ ($isProjectReadOnly || $isSubtaskLocked) ? 'not-allowed' : 'pointer' }}; flex: 1;"
                                                                :style="subtaskLocks[{{ $subtask->id }}] ? 'cursor: not-allowed !important;' : ''"
                                                                @mouseenter="if (subtaskLocks[{{ $subtask->id }}]) showExpandedLockedTooltip = true"
                                                                @click="if (subtaskLocks[{{ $subtask->id }}]) showExpandedLockedTooltip = true"
                                                            >
                                                                <input 
                                                                    type="checkbox" 
                                                                    class="subtask-checkbox subtask-check-{{ $subtask->id }}"
                                                                    :class="{
                                                                        'is-forward-locked': !!subtaskLocks[{{ $subtask->id }}] && !subtaskCompleted[{{ $subtask->id }}],
                                                                        'is-reverse-locked': !!subtaskLocks[{{ $subtask->id }}] && !!subtaskCompleted[{{ $subtask->id }}]
                                                                    }"
                                                                    id="expanded-subtask-check-{{ $subtask->id }}"
                                                                    {{ $subtask->is_completed ? 'checked' : '' }}
                                                                    {{ ($isProjectReadOnly || $isSubtaskLocked) ? 'disabled' : '' }}
                                                                    :disabled="{{ $isProjectReadOnly ? 'true' : '!!subtaskLocks[' . $subtask->id . ']' }}"
                                                                    @change="toggleSubtask({{ $project->id }}, {{ $task->id }}, {{ $subtask->id }}, $el)"
                                                                    style="width: 15px; height: 15px; accent-color: var(--color-success); cursor: {{ ($isProjectReadOnly || $isSubtaskLocked) ? 'not-allowed' : 'pointer' }};"
                                                                    :style="subtaskLocks[{{ $subtask->id }}] ? 'cursor: not-allowed !important;' : ''"
                                                                >
                                                                <span 
                                                                    class="subtask-title-{{ $subtask->id }}"
                                                                    style="{{ $subtask->is_completed ? 'text-decoration: line-through; color: var(--color-muted);' : 'color: var(--color-ink);' }}"
                                                                    :style="(subtaskLocks[{{ $subtask->id }}] && !subtaskCompleted[{{ $subtask->id }}]) ? 'color: var(--color-muted) !important;' : ''"
                                                                >
                                                                    {{ $subtask->title }}
                                                                </span>
                                                            </label>
                                                            <div style="display: flex; align-items: center; gap: var(--space-2);">
                                                                @if ($subtask->due_date)
                                                                    <span class="badge font-mono" style="background: var(--color-surface-alt); color: var(--color-muted); font-size: 0.6875rem;">
                                                                        📅 {{ $subtask->due_date->format('M j') }}
                                                                    </span>
                                                                @endif
                                                                <button 
                                                                    type="button" 
                                                                    class="btn-icon" 
                                                                    title="Attachments ({{ $subtask->files->count() }})"
                                                                    @click="openSubtaskUploadModal({{ $task->id }}, {{ $subtask->id }}, '{{ addslashes($subtask->title) }}', {{ json_encode($subtask->files->map(fn($f) => ['id' => $f->id, 'name' => $f->file_name, 'size' => $f->formatted_size, 'url' => route('projects.files.download', [$project, $f]), 'is_img' => $f->is_image, 'is_pdf' => $f->is_pdf, 'is_word' => $f->is_word])) }})"
                                                                    style="background: none; border: none; cursor: pointer; color: {{ $subtask->files->isNotEmpty() ? 'var(--color-primary)' : 'var(--color-muted)' }}; padding: 2px; border-radius: var(--radius-sm);"
                                                                >
                                                                    📎 @if($subtask->files->isNotEmpty())<span style="font-size: 0.6875rem; font-weight: bold;">{{ $subtask->files->count() }}</span>@endif
                                                                </button>
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
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);">
            <div>
                <h2 id="project-expenditures-heading" style="display: inline-block; margin-right: var(--space-2); margin-bottom: 0;">Expenditures</h2>
                <span class="text-muted" style="font-size: 0.8125rem;">
                    Total Spend: <strong class="font-mono text-dark">₱{{ number_format($project->actual_spend, 2) }}</strong> ({{ $project->costs->count() }} {{ Str::plural('entry', $project->costs->count()) }})
                </span>
                <span class="badge" style="background: var(--color-surface-alt); border: 1px solid var(--color-border); margin-left: var(--space-2); font-size: 0.75rem; font-weight: 600;">
                    Recent 3 Entries
                </span>
            </div>
            <div style="display: flex; flex-direction: row; gap: var(--space-2); align-items: center; flex-wrap: nowrap;">
                <a href="{{ route('costing.show', $project) }}" class="btn btn-secondary btn-sm" style="white-space: nowrap;">
                    Full Breakdown ↗
                </a>
                @can('update', $project)
                    @if ($isProjectReadOnly)
                        <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.6; cursor: not-allowed; white-space: nowrap;" title="{{ $project->trashed() ? 'Cannot log expenses on an archived project' : 'Cannot log expenses on a completed project unless reactivated' }}">
                            + Log New Expense / Cost
                        </button>
                    @else
                        <button type="button" class="btn btn-primary btn-sm" @click="addCostModalOpen = true" style="white-space: nowrap;">
                            + Log New Expense / Cost
                        </button>
                    @endif
                @endcan
            </div>
        </div>

        @if ($project->costs->isEmpty())
            <div class="card-body">
                <div class="empty-state" style="padding: var(--space-6) var(--space-4);">
                    <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <h3 class="empty-state__title">No itemized costs logged yet</h3>
                    <p class="empty-state__description">
                        Manually type down what each cost is for (materials, labor, equipment rental, permits) to keep an accurate spend ledger.
                    </p>
                    @can('update', $project)
                        @if ($isProjectReadOnly)
                            <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.6; cursor: not-allowed;" title="{{ $project->trashed() ? 'Cannot log expenses on an archived project' : 'Cannot log expenses on a completed project unless reactivated' }}">
                                + Log First Cost Entry
                            </button>
                        @else
                            <button type="button" class="btn btn-primary btn-sm" @click="addCostModalOpen = true">
                                + Log First Cost Entry
                            </button>
                        @endif
                    @endcan
                </div>
            </div>
        @else
            {{-- Indicator banner for recent 3 entries --}}
            <div style="background: var(--color-surface-alt); padding: var(--space-2) var(--space-6); border-bottom: 1px solid var(--color-border); font-size: 0.8125rem;">
                <span class="text-muted">
                    📌 <strong>Quick View:</strong> Displaying the <strong>3 most recent expenditures</strong> for this project.
                </span>
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
                        @foreach ($project->costs->take(3) as $cost)
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
                                    {{ $cost->incurred_date ? $cost->incurred_date->format('M j, Y') : $cost->created_at->format('M j, Y') }}
                                </td>
                                <td>{{ $cost->creator ? $cost->creator->name : 'System' }}</td>
                                <td class="text-right">
                                    @can('update', $project)
                                        @if (!$isProjectReadOnly)
                                            <form method="POST" action="{{ route('projects.costs.destroy', ['project' => $project, 'cost' => $cost]) }}" class="inline-form" style="justify-content: flex-end;" onsubmit="return confirm('Remove this cost entry?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--color-danger); border-color: #fad2cf;">
                                                    Delete
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($project->costs->count() > 3)
                <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8125rem; background: var(--color-surface-alt);">
                    <span class="text-muted">Showing 3 most recent expenditures (out of {{ $project->costs->count() }} total logged).</span>
                    <a href="{{ route('costing.show', $project) }}" class="table-link font-bold">View Complete History in Full Breakdown ↗</a>
                </div>
            @endif
        @endif
    </section>

    {{-- ─── Assigned Manpower / Project Personnel (Active & Archived Records) ─── --}}
    <section class="card card-glass mb-6" aria-labelledby="assigned-manpower-heading">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);">
            <div>
                <h2 id="assigned-manpower-heading" style="display: inline-block; margin-right: var(--space-2); margin-bottom: 0;">
                    @if ($project->status === 'completed')
                        Manpower & Construction Team (Archived)
                    @else
                        Assigned Manpower
                    @endif
                </h2>
                @if ($project->status === 'completed')
                    <span class="badge" style="background: rgba(34, 197, 94, 0.15); color: var(--color-success); font-weight: 600; margin-left: var(--space-2);">
                        {{ $project->personnelHistory->unique('personnel_id')->count() }} Total {{ Str::plural('Contributor', $project->personnelHistory->unique('personnel_id')->count()) }}
                    </span>
                @else
                    <span class="text-muted" style="font-size: 0.8125rem;">({{ $project->personnel->count() }} {{ Str::plural('personnel', $project->personnel->count()) }})</span>
                @endif
            </div>
            @if ($project->status !== 'completed')
                @can('update', $project)
                    @if ($project->trashed())
                        <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.6; cursor: not-allowed;" title="Cannot assign personnel to an archived project" id="btn-assign-personnel">
                            + Assign Personnel
                        </button>
                    @else
                        <button type="button" class="btn btn-primary btn-sm" @click="assignModalOpen = true" id="btn-assign-personnel">
                            + Assign Personnel
                        </button>
                    @endif
                @endcan
            @endif
        </div>

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
                                                <form method="POST" action="{{ route('projects.unassign-personnel', ['project' => $project, 'personnel' => $person]) }}" class="inline-form" style="justify-content: flex-end;" onsubmit="return confirm('Are you sure you want to unassign {{ $person->full_name }} from this project?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--color-danger); border-color: #fad2cf;">
                                                        Unassign
                                                    </button>
                                                </form>
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
    </section>

    {{-- ─── Status & Activity Audit Trail History ──────────────────────────── --}}
    <div class="project-detail-grid">
        <section class="card card-glass" aria-labelledby="status-history-heading" style="grid-column: 1 / -1;">
            <div class="card-header">
                <h2 id="status-history-heading">Audit Trail &amp; Status History</h2>
                <span id="audit-trail-count" class="text-muted" style="font-size: 0.8125rem;">{{ $project->statusHistory->count() }} {{ Str::plural('event', $project->statusHistory->count()) }}</span>
            </div>
            <div class="table-wrapper" style="max-height: 290px; overflow-y: auto; position: relative;">
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
                        <label class="form-label" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <span style="font-weight: 600;">Choose from Materials Catalog (Optional)</span>
                            <span class="text-muted" style="font-size: 0.75rem; font-weight: normal;">Search by name, code or model</span>
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
                                    :max="selectedMaterialStock"
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
                            placeholder="1"
                            required
                        >
                        <p class="form-hint">Default is 1, minimum is 1 (cannot be 0).</p>
                    </div>
                    <input type="hidden" name="quantity" :value="materialQuantity" x-show="selectedMaterialId">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-4);">
                        <div class="form-group form-group-premium" style="margin-bottom: 0;">
                            <label for="cost_type" class="form-label">Category <span class="required">*</span></label>
                            <select id="cost_type" name="cost_type" class="form-control" x-model="costForm.cost_type" required>
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
                        <label for="cost_amount" class="form-label">Amount (PHP ₱) <span class="required">*</span></label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-weight: 600; color: var(--color-muted); font-family: var(--font-mono);">₱</span>
                            <input
                                id="cost_amount"
                                name="amount"
                                type="number"
                                step="0.01"
                                min="100"
                                class="form-control font-mono"
                                style="padding-left: 32px;"
                                x-model="costForm.amount"
                                :readonly="Boolean(selectedMaterialId)"
                                :style="selectedMaterialId ? 'background: var(--color-surface-alt); cursor: not-allowed;' : ''"
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
                                required
                            >
                        </div>
                    </div>
                    <p class="form-hint" style="margin-top: -8px; margin-bottom: var(--space-4);">
                        Pre-calculated duration: 7 days (1 week). You can modify start or end date freely.
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

    {{-- ─── Modal 7: Upload Project File (Invoice or General Document) ──── --}}
    <div class="reassign-modal-overlay" style="display: none;" x-show="uploadFileModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="uploadFileModalOpen = false"></div>
        <div class="reassign-modal-content card" x-show="uploadFileModalOpen" x-transition style="max-width: 480px;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: var(--space-2);">
                    <svg style="width: 18px; height: 18px; color: var(--color-primary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <span style="font-weight: 700; font-size: 1rem;">Upload Project Document</span>
                </div>
                <button type="button" class="flash-close" @click="uploadFileModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <form action="{{ route('projects.files.store', $project) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <div class="form-group form-group-premium" style="margin-bottom: var(--space-4);">
                        <label for="project_file_input" class="form-label">Select File <span class="required">*</span></label>
                        <input
                            type="file"
                            id="project_file_input"
                            name="file"
                            class="form-control"
                            accept=".pdf,.doc,.docx,image/*"
                            required
                        >
                        <p class="form-hint" style="margin-top: 4px;">Allowed formats: PDF, DOCX, and Images (up to 20MB).</p>
                    </div>

                    <div class="form-group form-group-premium" style="margin-bottom: var(--space-4);">
                        <label for="project_file_type" class="form-label">File Type <span class="required">*</span></label>
                        <select id="project_file_type" name="file_type" class="form-control" required>
                            <option value="general">General Project File / Document</option>
                            <option value="invoice">Official Invoice / Payment Receipt</option>
                        </select>
                    </div>

                    <div class="form-group form-group-premium" style="margin-bottom: 0;">
                        <label for="project_file_description" class="form-label">Description / Note <span class="text-muted">(optional)</span></label>
                        <input
                            type="text"
                            id="project_file_description"
                            name="description"
                            class="form-control"
                            maxlength="255"
                            placeholder="e.g. Official signed billing invoice #1024"
                        >
                    </div>
                </div>
                <div class="card-footer" style="display: flex; justify-content: flex-end; gap: var(--space-2);">
                    <button type="button" class="btn btn-secondary" @click="uploadFileModalOpen = false">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload File</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ─── Modal 9: File Details, Description Editor & Actions ─────── --}}
    <div class="reassign-modal-overlay" style="display: none;" x-show="viewFileModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="viewFileModalOpen = false"></div>
        <div class="reassign-modal-content card" x-show="viewFileModalOpen" x-transition style="max-width: 520px;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: var(--space-2); min-width: 0;">
                    <span x-text="activeFileData.is_img ? '🖼️' : (activeFileData.is_pdf ? '📕' : (activeFileData.is_word ? '📘' : '📄'))"></span>
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
                            <span class="badge" style="text-transform: capitalize; background: var(--color-surface); font-size: 0.75rem;" x-text="activeFileData.type === 'invoice' ? 'Invoice / Billing' : (activeFileData.type === 'task_attachment' ? 'Task Attachment' : 'General Document')"></span>
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

                <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-2);">
                    <div style="display: flex; gap: var(--space-2);">
                        <a :href="activeFileData.download_url" class="btn btn-secondary btn-sm" target="_blank" style="display: inline-flex; align-items: center; gap: 4px;">
                            ⬇ Download File
                        </a>
                        @can('update', $project)
                            <button
                                type="button"
                                class="btn btn-secondary btn-sm"
                                style="color: var(--color-danger); border-color: #fecaca;"
                                @click="deleteActiveFile()"
                            >
                                🗑 Delete File
                            </button>
                        @endcan
                    </div>
                    <div style="display: flex; gap: var(--space-2);">
                        <button type="button" class="btn btn-secondary btn-sm" @click="viewFileModalOpen = false">Close</button>
                        @can('update', $project)
                            <button type="submit" class="btn btn-primary btn-sm">Save Description</button>
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
                    <svg style="width: 18px; height: 18px; color: var(--color-primary); flex-shrink: 0;" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M15.621 4.379a3 3 0 00-4.242 0l-7 7a3 3 0 004.241 4.243h.001l.497-.5a.75.75 0 011.064 1.057l-.498.501-.002.002a4.5 4.5 0 01-6.364-6.364l7-7a4.5 4.5 0 016.368 6.36l-3.455 3.553A2.625 2.625 0 119.5 9.525l3.45-3.451a.75.75 0 111.061 1.06l-3.45 3.451a1.125 1.125 0 001.587 1.595l3.454-3.553a3 3 0 000-4.248z" clip-rule="evenodd" />
                    </svg>
                    <span style="font-weight: 700; font-size: 0.9375rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="'Subtask Files: ' + activeSubtaskData.title"></span>
                </div>
                <button type="button" class="flash-close" @click="subtaskFileModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <div class="card-body">
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
                                        <form :action="'/projects/{{ $project->id }}/files/' + f.id" method="POST" onsubmit="return confirm('Delete this attachment?');" class="inline-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--color-danger); padding: 2px 6px; font-size: 0.75rem;">✕</button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Upload New Attachment Form --}}
                @can('update', $project)
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
                @endcan
            </div>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
.project-file-item:hover {
    border-color: var(--color-primary) !important;
    background: var(--color-surface-alt) !important;
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
            const params = new URLSearchParams(window.location.search);
            const taskIdParam = params.get('task_id');
            if (taskIdParam) {
                const parsedId = parseInt(taskIdParam, 10);
                if (!isNaN(parsedId)) {
                    this.activeTaskId = parsedId;
                    this.checklistMinimized = false;
                }
            }
        },
        timelineMode: 'minimized',
        archiveModalOpen: false,
        addCostModalOpen: false,
        assignModalOpen: false,
        createMajorTaskModalOpen: false,
        editMajorTaskModalOpen: false,
        editSubtaskModalOpen: false,
        markCompletedModalOpen: false,
        uploadFileModalOpen: false,
        subtaskFileModalOpen: false,
        viewFileModalOpen: false,
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
            const amt = parseFloat(this.costForm.amount);
            if (isNaN(amt) || amt < 100) {
                window.dispatchEvent(new CustomEvent('show-toast', {
                    detail: { type: 'error', title: 'Minimum Amount Required', message: 'Expenditure amount cannot be less than ₱100.00.', duration: 5000 }
                }));
                return false;
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
                this.costForm.amount = total;

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
        subtaskLocks: @js($initialSubtaskLocks),
        subtaskCompleted: @js($initialSubtaskCompleted),
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
            if (confirm(`Are you sure you want to delete "${this.activeFileData.name}"? This action cannot be undone.`)) {
                const form = document.getElementById('activeFileDeleteForm');
                if (form) {
                    form.submit();
                }
            }
        },
        openSubtaskUploadModal(taskId, subtaskId, title, files = []) {
            this.activeSubtaskData = {
                taskId: taskId,
                subtaskId: subtaskId,
                title: title,
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
        toggleSubtask(projectId, taskId, subtaskId, checkboxEl) {
            const url = `/projects/${projectId}/tasks/${taskId}/subtasks/${subtaskId}/toggle`;
            fetch(url, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({})
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Keep all checkboxes for this subtask in sync (card checklist & expanded view)
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
                } else if (data.message) {
                    if (checkboxEl) {
                        checkboxEl.checked = !checkboxEl.checked;
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
                    checkboxEl.checked = !checkboxEl.checked;
                }
            });
        }
    };
}
window.projectDetail = projectDetail;
</script>
@endpush
