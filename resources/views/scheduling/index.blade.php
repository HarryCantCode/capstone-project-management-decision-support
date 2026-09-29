@extends('layouts.app')

@section('title', 'Manpower Management')
@section('meta-description', 'Manage field personnel, bulk assignments, and project staffing.')

@section('content')
<div x-data="{
    reassignModalOpen: false,
    reassignUrl: '',
    reassignName: '',
    reassignId: '',
    reassignProject: '',
    selectedIds: [],
    visibleIds: [{{ $personnel->pluck('id')->implode(',') }}],
    openReassignModal(id, empId, name, projectId) {
        this.reassignUrl = '/scheduling/' + id + '/reassign';
        this.reassignId = empId;
        this.reassignName = name;
        this.reassignProject = projectId || '';
        this.reassignModalOpen = true;
    },
    toggleSelectAll() {
        if (this.selectedIds.length === this.visibleIds.length && this.visibleIds.length > 0) {
            this.selectedIds = [];
        } else {
            this.selectedIds = [...this.visibleIds];
        }
    },
    isAllSelected() {
        return this.visibleIds.length > 0 && this.selectedIds.length === this.visibleIds.length;
    }
}">
    <div class="page-header">
        <div>
            <h1>Manpower Management</h1>
            <p class="page-header__description">Track field personnel, select multiple workers, and manage project assignments in bulk.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('scheduling.create') }}" class="btn btn-primary" id="btn-add-personnel">+ Add New Personnel</a>
        </div>
    </div>

    {{-- Search and Filter Bar --}}
    <div class="card mb-6" id="personnel-filters">
        <div class="card-body" style="padding: var(--space-4) var(--space-6);">
            <form method="GET" action="{{ route('scheduling.index') }}" class="search-filter-bar">
                <div class="search-input-wrapper">
                    <svg class="search-input-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                    <input
                        type="text"
                        id="personnel-search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search by name, EMP ID, expertise, or project..."
                        class="form-control search-input"
                    >
                </div>

                <div class="filter-group">
                    <label for="filter-expertise" class="visually-hidden">Filter by expertise</label>
                    <select id="filter-expertise" name="expertise" class="form-control form-control--compact filter-select">
                        <option value="">All Expertise</option>
                        <option value="Site Engineer" @selected($expertise === 'Site Engineer')>Site Engineer</option>
                        <option value="Foreman" @selected($expertise === 'Foreman')>Foreman</option>
                        <option value="Safety Officer" @selected($expertise === 'Safety Officer')>Safety Officer</option>
                        <option value="Worker" @selected($expertise === 'Worker')>Worker</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filter-status" class="visually-hidden">Filter by status</label>
                    <select id="filter-status" name="status" class="form-control form-control--compact filter-select">
                        <option value="">All Status</option>
                        <option value="assigned" @selected($status === 'assigned')>Assigned</option>
                        <option value="not_assigned" @selected($status === 'not_assigned')>Not Assigned</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-secondary btn-sm" id="btn-apply-filters">
                    <svg style="width: 14px; height: 14px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                    </svg>
                    Filter
                </button>

                @if ($search || $expertise || $status)
                    <a href="{{ route('scheduling.index') }}" class="btn btn-secondary btn-sm" id="btn-clear-filters" style="color: var(--color-danger);">
                        ✕ Clear
                    </a>
                @endif
            </form>
        </div>
    </div>

    {{-- Interactive Bulk Action Toolbar --}}
    <div
        class="bulk-toolbar"
        x-show="selectedIds.length > 0"
        x-cloak
        x-transition
    >
        {{-- Left: Selection Count Badge & Deselect Action --}}
        <div class="bulk-toolbar__left">
            <span class="bulk-toolbar__badge-wrap">
                <span class="badge badge-accent" x-text="selectedIds.length"></span>
                <strong>Personnel Selected</strong>
            </span>
            <button type="button" class="btn btn-secondary btn-sm bulk-toolbar__deselect-btn" @click="selectedIds = []">
                ✕ Deselect All
            </button>
        </div>

        {{-- Right: Dropdown Selector & Action Buttons (Aligned to Right Corner) --}}
        <div class="bulk-toolbar__right">
            {{-- Bulk Assign Form --}}
            <form action="{{ route('scheduling.bulk-reassign') }}" method="POST" class="bulk-toolbar__form">
                @csrf
                <template x-for="id in selectedIds" :key="id">
                    <input type="hidden" name="personnel_ids[]" :value="id">
                </template>
                <select name="project_id" class="form-control bulk-toolbar__select" required>
                    <option value="">-- Assign Selected to Project --</option>
                    @foreach ($activeProjects as $project)
                        <option value="{{ $project->id }}">{{ $project->project_code }} — {{ $project->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary btn-sm bulk-toolbar__btn">
                    Assign Selected
                </button>
            </form>

            {{-- Bulk Unassign Form --}}
            <form action="{{ route('scheduling.bulk-reassign') }}" method="POST" class="bulk-toolbar__form" onsubmit="return confirm('Unassign all selected personnel from their projects?');">
                @csrf
                <template x-for="id in selectedIds" :key="id">
                    <input type="hidden" name="personnel_ids[]" :value="id">
                </template>
                <button type="submit" class="btn btn-secondary btn-sm bulk-toolbar__btn bulk-toolbar__btn--danger">
                    Unassign Selected
                </button>
            </form>
        </div>
    </div>

    {{-- Personnel Table --}}
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Personnel Roster</span>
            <span class="text-muted" style="font-size: 0.8125rem;">{{ $personnel->total() }} {{ Str::plural('record', $personnel->total()) }}</span>
        </div>

        @if ($personnel->isEmpty())
            <div class="card-body">
                <div class="empty-state">
                    <svg class="empty-state__icon" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                        <rect width="48" height="48" rx="12" fill="var(--color-surface-alt)"/>
                        <path d="M24 16a5 5 0 1 1 0 10 5 5 0 0 1 0-10zM15 34c0-4.418 4.03-8 9-8s9 3.582 9 8" stroke="var(--color-muted)" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <h2 class="empty-state__title">No personnel found</h2>
                    <p class="empty-state__description">
                        @if ($search || $expertise || $status)
                            No records match your current filters. Try adjusting your search criteria.
                        @else
                            Get started by adding your first personnel record.
                        @endif
                    </p>
                    @unless ($search || $expertise || $status)
                        <a href="{{ route('scheduling.create') }}" class="btn btn-primary">+ Add New Personnel</a>
                    @endunless
                </div>
            </div>
        @else
            <div class="table-wrapper">
                <table class="data-table" id="personnel-table">
                    <caption class="visually-hidden">Personnel roster with project assignments</caption>
                    <thead>
                        <tr>
                            <th scope="col" style="width: 44px; text-align: center;">
                                <input
                                    type="checkbox"
                                    @change="toggleSelectAll()"
                                    :checked="isAllSelected()"
                                    title="Select / Deselect all visible on this page"
                                    aria-label="Select all visible personnel"
                                >
                            </th>
                            <th scope="col">Employee ID</th>
                            <th scope="col">Full Name</th>
                            <th scope="col">Role</th>
                            <th scope="col">Assigned Project</th>
                            <th scope="col">Status</th>
                            <th scope="col">Date Assigned</th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($personnel as $person)
                            <tr id="personnel-row-{{ $person->id }}" :class="{ 'selected-row': selectedIds.includes({{ $person->id }}) }">
                                <td style="text-align: center;">
                                    <input
                                        type="checkbox"
                                        :value="{{ $person->id }}"
                                        x-model.number="selectedIds"
                                        aria-label="Select {{ $person->full_name }}"
                                    >
                                </td>
                                <td><span class="font-mono font-bold">{{ $person->employee_id }}</span></td>
                                <td><strong>{{ $person->full_name }}</strong></td>
                                <td>{{ $person->expertise }}</td>
                                <td>
                                    @if ($person->project)
                                        <span class="font-mono text-muted" style="font-size: 0.75rem;">{{ $person->project->project_code }}</span><br>
                                        <strong style="color: var(--color-accent);">{{ $person->project->name }}</strong>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="status-pill status-pill--{{ $person->status === 'Assigned' ? 'completed' : 'pending' }}">
                                        {{ $person->status }}
                                    </span>
                                </td>
                                <td>
                                    @if ($person->date_assigned)
                                        <span class="font-mono">{{ $person->date_assigned->format('M j, Y') }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-sm btn-reassign"
                                        @click="openReassignModal({{ $person->id }}, '{{ $person->employee_id }}', '{{ addslashes($person->full_name) }}', {{ $person->project_id ?? "''" }})"
                                    >
                                        Reassign
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer pagination-wrapper">{{ $personnel->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>

    {{-- Reassign Modal (Single) --}}
    <div class="reassign-modal-overlay" style="display: none;" x-show="reassignModalOpen" x-cloak>
        <div class="reassign-modal-backdrop" @click="reassignModalOpen = false"></div>
        <div class="reassign-modal-content card" x-show="reassignModalOpen" x-transition:enter="modal-enter" x-transition:leave="modal-leave">
            <div class="card-header">
                <span>Reassign Personnel</span>
                <button type="button" class="flash-close" @click="reassignModalOpen = false" aria-label="Close modal">&times;</button>
            </div>
            <form :action="reassignUrl" method="POST">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <p style="margin-bottom: var(--space-4); color: var(--color-muted); font-size: 0.875rem;">
                        Reassigning <strong x-text="reassignName"></strong> (<span class="font-mono" x-text="reassignId"></span>)
                    </p>
                    <div class="form-group form-group-premium">
                        <label for="reassign-project-id" class="form-label">Assign to Project</label>
                        <select id="reassign-project-id" name="project_id" class="form-control" x-model="reassignProject">
                            <option value="">No Assignment (Unassign)</option>
                            @foreach ($activeProjects as $project)
                                <option value="{{ $project->id }}">{{ $project->project_code }} — {{ $project->name }}</option>
                            @endforeach
                        </select>
                        <p class="form-hint">Select a project or leave empty to unassign this personnel.</p>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Confirm Reassignment</button>
                        <button type="button" class="btn btn-secondary" @click="reassignModalOpen = false">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
