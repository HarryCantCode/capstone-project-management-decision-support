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
@endphp

@section('content')
<div x-data="{
    timelineMode: 'minimized',
    addCostModalOpen: false,
    assignModalOpen: false,
    createMajorTaskModalOpen: false,
    editMajorTaskModalOpen: false,
    editSubtaskModalOpen: false,
    activeTaskId: {{ $project->tasks->first()?->id ?? 'null' }},
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
            }
        })
        .catch(err => {
            console.error('Error toggling subtask:', err);
            if (checkboxEl) {
                checkboxEl.checked = !checkboxEl.checked;
            }
        });
    }
}">

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
            @can('update', $project)
                <a href="{{ route('projects.edit', $project) }}" class="btn btn-secondary">Edit Project</a>
            @endcan
            @can('delete', $project)
                <div x-data="{ confirmingDeletion: false }">
                    <button type="button" class="btn btn-danger" x-show="!confirmingDeletion" @click="confirmingDeletion = true">Delete Project</button>
                    <form method="POST" action="{{ route('projects.destroy', $project) }}" x-show="confirmingDeletion" x-cloak class="inline-form">
                        @csrf
                        @method('DELETE')
                        <span class="delete-confirmation">Delete this project?</span>
                        <button type="submit" class="btn btn-danger btn-sm">Yes, Delete</button>
                        <button type="button" class="btn btn-secondary btn-sm" @click="confirmingDeletion = false">Cancel</button>
                    </form>
                </div>
            @endcan
        </div>
    </div>

    {{-- Top Overview Grid --}}
    <div class="project-detail-grid mb-6">
        
        {{-- Overview Card --}}
        <section class="card card-glass" aria-labelledby="project-overview-heading">
            <div class="card-header">
                <h2 id="project-overview-heading">Overview</h2>
                <x-status-pill :status="$project->status" />
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

    </div>

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
                    <button type="button" class="btn btn-primary btn-sm" @click="addCostModalOpen = true" style="white-space: nowrap;">
                        + Log New Expense / Cost
                    </button>
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
                        <button type="button" class="btn btn-primary btn-sm" @click="addCostModalOpen = true">
                            + Log First Cost Entry
                        </button>
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
                                <td class="cell-numeric text-success">
                                    ₱{{ number_format($cost->amount, 2) }}
                                </td>
                                <td class="font-mono">
                                    {{ $cost->incurred_date ? $cost->incurred_date->format('M j, Y') : $cost->created_at->format('M j, Y') }}
                                </td>
                                <td>{{ $cost->creator ? $cost->creator->name : 'System' }}</td>
                                <td class="text-right">
                                    @can('update', $project)
                                        <form method="POST" action="{{ route('projects.costs.destroy', ['project' => $project, 'cost' => $cost]) }}" class="inline-form" style="justify-content: flex-end;" onsubmit="return confirm('Remove this cost entry?');">
                                            @csrf
                                            @method('DELETE')
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

            @if ($project->costs->count() > 3)
                <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8125rem; background: var(--color-surface-alt);">
                    <span class="text-muted">Showing 3 most recent expenditures (out of {{ $project->costs->count() }} total logged).</span>
                    <a href="{{ route('costing.show', $project) }}" class="table-link font-bold">View Complete History in Full Breakdown ↗</a>
                </div>
            @endif
        @endif
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
                @can('update', $project)
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        @click="createMajorTaskModalOpen = true"
                        id="btn-add-major-task"
                    >
                        + Add Major Task
                    </button>
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
                            <button type="button" class="btn btn-primary" @click="createMajorTaskModalOpen = true" id="btn-add-first-major-task">
                                + Add First Major Task
                            </button>
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
                                    @click="activeTaskId = {{ $task->id }}"
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
                        <div x-show="activeTaskId === {{ $task->id }}" x-cloak style="margin-top: var(--space-6); background: var(--color-surface-alt); padding: var(--space-5); border-radius: var(--radius-lg); border: 1px solid var(--color-border);" id="task-detail-{{ $task->id }}">
                            
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

                                @can('update', $project)
                                    <div style="display: flex; gap: var(--space-2); align-items: center;">
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
                                    </div>
                                @endcan
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
                                            <li 
                                                style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); background: var(--color-surface); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); border: 1px solid var(--color-border); transition: background 0.15s ease;"
                                                id="subtask-row-{{ $subtask->id }}"
                                            >
                                                <label style="display: flex; align-items: center; gap: var(--space-3); flex: 1; margin-bottom: 0; cursor: pointer;">
                                                    <input 
                                                        type="checkbox" 
                                                        class="subtask-checkbox subtask-check-{{ $subtask->id }}"
                                                        id="subtask-check-{{ $subtask->id }}"
                                                        {{ $subtask->is_completed ? 'checked' : '' }}
                                                        @change="toggleSubtask({{ $project->id }}, {{ $task->id }}, {{ $subtask->id }}, $el)"
                                                        style="width: 18px; height: 18px; accent-color: var(--color-success); cursor: pointer;"
                                                    >
                                                    <span 
                                                        class="subtask-title-{{ $subtask->id }}"
                                                        style="font-size: 0.875rem; {{ $subtask->is_completed ? 'text-decoration: line-through; color: var(--color-muted);' : 'color: var(--color-ink); font-weight: 500;' }}"
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

                                                    @can('update', $project)
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
                                                    @endcan
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                {{-- Quick Add Sub Task Form --}}
                                @can('update', $project)
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
                                                        <li style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-2); font-size: 0.8125rem;">
                                                            <label style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: 0; cursor: pointer; flex: 1;">
                                                                <input 
                                                                    type="checkbox" 
                                                                    class="subtask-checkbox subtask-check-{{ $subtask->id }}"
                                                                    {{ $subtask->is_completed ? 'checked' : '' }}
                                                                    @change="toggleSubtask({{ $project->id }}, {{ $task->id }}, {{ $subtask->id }}, $el)"
                                                                    style="width: 15px; height: 15px; accent-color: var(--color-success); cursor: pointer;"
                                                                >
                                                                <span 
                                                                    class="subtask-title-{{ $subtask->id }}"
                                                                    style="{{ $subtask->is_completed ? 'text-decoration: line-through; color: var(--color-muted);' : 'color: var(--color-ink);' }}"
                                                                >
                                                                    {{ $subtask->title }}
                                                                </span>
                                                            </label>
                                                            @if ($subtask->due_date)
                                                                <span class="badge font-mono" style="background: var(--color-surface-alt); color: var(--color-muted); font-size: 0.6875rem;">
                                                                    📅 {{ $subtask->due_date->format('M j') }}
                                                                </span>
                                                            @endif
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
                    <button type="button" class="btn btn-primary btn-sm" @click="assignModalOpen = true" id="btn-assign-personnel">
                        + Assign Personnel
                    </button>
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
                                        <td><span class="font-mono font-bold">{{ $history->personnel->employee_id }}</span></td>
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
                            <button type="button" class="btn btn-primary btn-sm" @click="assignModalOpen = true">
                                + Assign First Worker
                            </button>
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
                                    <td><span class="font-mono font-bold">{{ $person->employee_id }}</span></td>
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
                                            <form method="POST" action="{{ route('projects.unassign-personnel', ['project' => $project, 'personnel' => $person]) }}" class="inline-form" style="justify-content: flex-end;" onsubmit="return confirm('Are you sure you want to unassign {{ $person->full_name }} from this project?');">
                                                @csrf
                                                <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--color-danger); border-color: #fad2cf;">
                                                    Unassign
                                                </button>
                                            </form>
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

    {{-- ─── Status Transition & Status History ────────────────────────────────── --}}
    <div class="project-detail-grid">
        
        {{-- Status Transition --}}
        @can('transition', $project)
            <section class="card card-glass" aria-labelledby="status-update-heading">
                <div class="card-header">
                    <h2 id="status-update-heading">Update Project Status</h2>
                    <x-status-pill :status="$project->status" />
                </div>
                <form method="POST" action="{{ route('projects.transition', $project) }}" class="card-body">
                    @csrf
                    @method('PATCH')
                    <div class="form-group form-group-premium">
                        <label for="status" class="form-label">New Status</label>
                        <select id="status" name="status" class="form-control @error('status') is-invalid @enderror" required>
                            <option value="">-- Select Status --</option>
                            @if ($project->status !== 'ongoing')
                                <option value="ongoing">Ongoing</option>
                            @endif
                            @if ($project->status !== 'delayed')
                                <option value="delayed">Delayed</option>
                            @endif
                            @if ($project->status !== 'completed')
                                <option value="completed">Completed</option>
                            @endif
                            @if ($project->status !== 'pending')
                                <option value="pending">Pending</option>
                            @endif
                        </select>
                        @error('status')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group form-group-premium">
                        <label for="notes" class="form-label">Status Update Reason / Notes <span class="text-muted">(optional)</span></label>
                        <textarea id="notes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3" maxlength="500" placeholder="e.g. Work started on site, delayed due to weather/permits, or project completed.">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </form>
            </section>
        @endcan

        {{-- Status & Activity History Trailing --}}
        <section class="card card-glass" aria-labelledby="status-history-heading" style="{{ !auth()->user()->can('transition', $project) ? 'grid-column: 1 / -1;' : '' }}">
            <div class="card-header">
                <h2 id="status-history-heading">Audit Trail & Status History</h2>
                <span class="text-muted" style="font-size: 0.8125rem;">{{ $project->statusHistory->count() }} {{ Str::plural('event', $project->statusHistory->count()) }}</span>
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
                    <tbody>
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
                            <tr>
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
        <div class="reassign-modal-content card" x-show="addCostModalOpen" x-transition>
            <div class="card-header">
                <span>Add Cost Entry to Project</span>
                <button type="button" class="flash-close" @click="addCostModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <form action="{{ route('projects.costs.store', $project) }}" method="POST">
                @csrf
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
                        <p class="form-hint">This will be added to the project's actual spend and reflected in Costing.</p>
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
        <div class="reassign-modal-content card" x-show="assignModalOpen" x-transition style="max-width: 540px;">
            <div class="card-header">
                <span>Assign Field Personnel (Select Multiple)</span>
                <button type="button" class="flash-close" @click="assignModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <form action="{{ route('projects.assign-personnel', $project) }}" method="POST" x-data="{ selectedAvail: [], allAvailIds: [{{ $availablePersonnel->pluck('id')->implode(',') }}], toggleAll() { this.selectedAvail = (this.selectedAvail.length === this.allAvailIds.length) ? [] : [...this.allAvailIds]; } }">
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
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-2);">
                            <span class="form-label" style="margin-bottom: 0;">Choose Personnel (<span x-text="selectedAvail.length"></span> of {{ $availablePersonnel->count() }} selected):</span>
                            <button type="button" class="btn btn-secondary btn-sm" @click="toggleAll()" style="font-size: 0.75rem; padding: 2px 8px;">
                                <span x-text="selectedAvail.length === allAvailIds.length ? 'Deselect All' : 'Select All Available'"></span>
                            </button>
                        </div>

                        <div style="max-height: 260px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface);">
                            @foreach ($availablePersonnel as $avail)
                                <label style="display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) var(--space-3); border-bottom: 1px solid var(--color-surface-alt); cursor: pointer; transition: background var(--transition-fast);" :style="selectedAvail.includes({{ $avail->id }}) ? 'background: var(--color-surface-alt);' : ''">
                                    <input
                                        type="checkbox"
                                        name="personnel_ids[]"
                                        value="{{ $avail->id }}"
                                        x-model.number="selectedAvail"
                                    >
                                    <div style="flex: 1; display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong style="font-size: 0.875rem;">{{ $avail->full_name }}</strong>
                                            <span class="font-mono text-muted" style="font-size: 0.75rem; margin-left: var(--space-1);">{{ $avail->employee_id }}</span>
                                        </div>
                                        <span class="badge" style="background: var(--color-surface-alt); font-size: 0.6875rem; font-weight: 600;">
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

</div>
@endsection

@push('styles')
<style>
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
</style>
@endpush
