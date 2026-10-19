@extends('layouts.app')

@section('title', 'Manage Personnel Roster')
@section('meta-description', 'View and manage active and inactive field personnel roster, update profiles, and restore archived personnel.')

@section('content')
<div x-data="{
    editModalOpen: false,
    editForm: {
        id: null,
        employee_id: '',
        first_name: '',
        middle_name: '',
        last_name: '',
        date_of_birth: '',
        expertise: 'Worker',
        address: '',
        project_id: '',
        archive_profile: false,
        actionUrl: '',
    },
    personnelMap: {
        @foreach ($personnel as $p)
        {{ $p->id }}: {
            id: {{ $p->id }},
            employee_id: @js($p->employee_id),
            first_name: @js($p->first_name),
            middle_name: @js($p->middle_name ?? ''),
            last_name: @js($p->last_name),
            date_of_birth: @js($p->date_of_birth ? $p->date_of_birth->format('Y-m-d') : ''),
            expertise: @js($p->expertise),
            address: @js($p->address),
            project_id: @js($p->project_id ?? ''),
            actionUrl: '{{ route('scheduling.update', $p->id) }}',
        },
        @endforeach
    },
    openEditModal(id) {
        const item = this.personnelMap[id];
        if (!item) return;
        this.editForm = {
            id: item.id,
            employee_id: item.employee_id,
            first_name: item.first_name,
            middle_name: item.middle_name,
            last_name: item.last_name,
            date_of_birth: item.date_of_birth,
            expertise: item.expertise,
            address: item.address,
            project_id: item.project_id || '',
            archive_profile: false,
            actionUrl: item.actionUrl,
        };
        this.editModalOpen = true;
    },
    archiveDirectly(id, name) {
        if (confirm('Archive personnel profile for ' + name + '? They will be unassigned from projects and moved to the inactive roster.')) {
            const form = document.getElementById('standalone-archive-form');
            form.action = '/scheduling/' + id;
            form.submit();
        }
    }
}">
    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <a href="{{ route('scheduling.index') }}" class="back-link">← Back to Manpower</a>
            <h1>Personnel Roster</h1>
            <p class="page-header__description">
                Centralized registry of active and inactive field personnel. Configure profiles, archive personnel, or reactivate previously archived personnel.
            </p>
        </div>
        <div class="page-actions" style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="{{ route('scheduling.create') }}" class="btn btn-primary" id="btn-add-personnel">
                + Add New Personnel
            </a>
        </div>
    </div>

    {{-- KPI Metric Cards --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: var(--space-4); margin-bottom: var(--space-6);">
        <div class="card" style="padding: var(--space-4);">
            <span class="text-muted" style="font-size: 0.8125rem; font-weight: 500;">Total Roster</span>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-ink); margin-top: 0.25rem;">
                {{ $stats['total'] }}
            </div>
            <span class="text-muted" style="font-size: 0.75rem;">All registered field personnel</span>
        </div>

        <div class="card" style="padding: var(--space-4); border-left: 3px solid var(--color-success, #10b981);">
            <span class="text-muted" style="font-size: 0.8125rem; font-weight: 500;">Active Personnel</span>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-success, #10b981); margin-top: 0.25rem;">
                {{ $stats['active'] }}
            </div>
            <span class="text-muted" style="font-size: 0.75rem;">Eligible for project staffing</span>
        </div>

        <div class="card" style="padding: var(--space-4); border-left: 3px solid var(--color-accent, #3b82f6);">
            <span class="text-muted" style="font-size: 0.8125rem; font-weight: 500;">Currently Assigned</span>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-accent, #3b82f6); margin-top: 0.25rem;">
                {{ $stats['assigned'] }}
            </div>
            <span class="text-muted" style="font-size: 0.75rem;">Deployed on active projects</span>
        </div>

        <div class="card" style="padding: var(--space-4); border-left: 3px solid #8b5cf6;">
            <span class="text-muted" style="font-size: 0.8125rem; font-weight: 500;">Available Pool</span>
            <div style="font-size: 1.5rem; font-weight: 700; color: #8b5cf6; margin-top: 0.25rem;">
                {{ $stats['available'] }}
            </div>
            <span class="text-muted" style="font-size: 0.75rem;">Ready to be assigned</span>
        </div>

        <div class="card" style="padding: var(--space-4); border-left: 3px solid var(--color-danger, #ef4444);">
            <span class="text-muted" style="font-size: 0.8125rem; font-weight: 500;">Inactive / Archived</span>
            <div style="font-size: 1.5rem; font-weight: 700; color: var(--color-danger, #ef4444); margin-top: 0.25rem;">
                {{ $stats['archived'] }}
            </div>
            <span class="text-muted" style="font-size: 0.75rem;">Archived profiles</span>
        </div>
    </div>

    {{-- Tabs and Search Filter Bar --}}
    <div class="card mb-6" id="roster-filters">
        <div class="card-body" style="padding: var(--space-4) var(--space-6);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: var(--space-4);">
                {{-- Status Filter Tabs --}}
                <div style="display: inline-flex; gap: 0.375rem; background: var(--color-surface-alt, #f1f5f9); padding: 4px; border-radius: var(--radius-md);">
                    <a
                        href="{{ route('scheduling.roster', array_merge($filters, ['tab' => 'all'])) }}"
                        class="btn btn-sm {{ ($filters['tab'] ?? 'all') === 'all' ? 'btn-primary' : 'btn-secondary' }}"
                        style="padding: 0.35rem 0.85rem; border: none;"
                        id="tab-all-personnel"
                    >
                        All ({{ $stats['total'] }})
                    </a>
                    <a
                        href="{{ route('scheduling.roster', array_merge($filters, ['tab' => 'active'])) }}"
                        class="btn btn-sm {{ ($filters['tab'] ?? '') === 'active' ? 'btn-primary' : 'btn-secondary' }}"
                        style="padding: 0.35rem 0.85rem; border: none;"
                        id="tab-active-personnel"
                    >
                        Active Personnel ({{ $stats['active'] }})
                    </a>
                    <a
                        href="{{ route('scheduling.roster', array_merge($filters, ['tab' => 'archived'])) }}"
                        class="btn btn-sm {{ in_array($filters['tab'] ?? '', ['archived', 'inactive']) ? 'btn-primary' : 'btn-secondary' }}"
                        style="padding: 0.35rem 0.85rem; border: none;"
                        id="tab-archived-personnel"
                    >
                        Inactive / Archived ({{ $stats['archived'] }})
                    </a>
                </div>
            </div>

            {{-- Search & Criteria Form --}}
            <form method="GET" action="{{ route('scheduling.roster') }}" class="search-filter-bar">
                <input type="hidden" name="tab" value="{{ $filters['tab'] ?? 'all' }}">

                <div class="search-input-wrapper">
                    <svg class="search-input-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                    <input
                        type="text"
                        id="roster-search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        placeholder="Search by name, employee ID, role, or project..."
                        class="form-control search-input"
                    >
                </div>

                <div class="filter-group">
                    <label for="filter-roster-expertise" class="visually-hidden">Filter by role</label>
                    <select id="filter-roster-expertise" name="expertise" class="form-control form-control--compact filter-select">
                        <option value="">All Expertise</option>
                        @foreach ($expertiseOptions as $opt)
                            <option value="{{ $opt }}" @selected(($filters['expertise'] ?? '') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filter-roster-project" class="visually-hidden">Filter by project</label>
                    <select id="filter-roster-project" name="project_id" class="form-control form-control--compact filter-select">
                        <option value="">All Projects</option>
                        @foreach ($activeProjects as $proj)
                            <option value="{{ $proj->id }}" @selected(($filters['project_id'] ?? '') == $proj->id)>{{ $proj->project_code }} — {{ $proj->name }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="btn btn-secondary btn-sm" id="btn-roster-apply-filter">
                    Filter
                </button>

                @if (!empty($filters['search']) || !empty($filters['expertise']) || !empty($filters['project_id']))
                    <a href="{{ route('scheduling.roster', ['tab' => $filters['tab'] ?? 'all']) }}" class="btn btn-secondary btn-sm" style="color: var(--color-danger);">
                        ✕ Clear
                    </a>
                @endif
            </form>
        </div>
    </div>

    {{-- Personnel Roster Table --}}
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-weight: 600;">Personnel List</span>
                @if (($filters['tab'] ?? 'all') === 'active')
                    <span class="status-pill status-pill--completed" style="font-size: 0.6875rem;">Active Only</span>
                @elseif (in_array($filters['tab'] ?? '', ['archived', 'inactive']))
                    <span class="status-pill status-pill--cancelled" style="font-size: 0.6875rem;">Archived / Inactive Only</span>
                @else
                    <span class="badge" style="font-size: 0.6875rem;">Active & Inactive</span>
                @endif
            </div>
            <span class="text-muted" style="font-size: 0.8125rem;">
                {{ $personnel->total() }} {{ Str::plural('record', $personnel->total()) }}
            </span>
        </div>

        @if ($personnel->isEmpty())
            <div class="card-body">
                <div class="empty-state">
                    <svg class="empty-state__icon" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                        <rect width="48" height="48" rx="12" fill="var(--color-surface-alt)"/>
                        <path d="M24 16a5 5 0 1 1 0 10 5 5 0 0 1 0-10zM15 34c0-4.418 4.03-8 9-8s9 3.582 9 8" stroke="var(--color-muted)" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <h2 class="empty-state__title">No personnel records found</h2>
                    <p class="empty-state__description">
                        No workers match your current tab and filters. Try switching tabs or resetting criteria.
                    </p>
                </div>
            </div>
        @else
            <div class="table-wrapper">
                <table class="data-table" id="roster-table">
                    <caption class="visually-hidden">Field personnel roster listing active and inactive records</caption>
                    <thead>
                        <tr>
                            <th scope="col">Employee ID</th>
                            <th scope="col">Full Name</th>
                            <th scope="col">Role / Expertise</th>
                            <th scope="col">Assigned Project</th>
                            <th scope="col">Status</th>
                            <th scope="col">Date Registered / Archived</th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($personnel as $person)
                            <tr id="roster-row-{{ $person->id }}" style="{{ $person->trashed() ? 'opacity: 0.85;' : '' }}">
                                <td>
                                    <span class="font-mono font-bold">{{ $person->employee_id }}</span>
                                </td>
                                <td>
                                    <strong>{{ $person->full_name }}</strong>
                                    @if ($person->trashed())
                                        <span class="status-pill status-pill--cancelled" style="font-size: 0.6875rem; margin-left: 0.35rem;">
                                            Archived
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $person->expertise }}</td>
                                <td>
                                    @if ($person->project && !$person->trashed())
                                        <span class="font-mono text-muted" style="font-size: 0.75rem;">{{ $person->project->project_code }}</span><br>
                                        <strong style="color: var(--color-accent);">{{ $person->project->name }}</strong>
                                    @elseif ($person->trashed())
                                        <span class="text-muted" style="font-style: italic;">Inactive (Unassigned)</span>
                                    @else
                                        <span class="text-muted">— (Available)</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($person->trashed())
                                        <span class="status-pill status-pill--cancelled">Archived</span>
                                    @elseif ($person->project_id)
                                        <span class="status-pill status-pill--completed">Active · Assigned</span>
                                    @else
                                        <span class="status-pill status-pill--pending">Active · Available</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($person->trashed())
                                        <span class="text-muted" style="font-size: 0.8125rem;">
                                            Archived {{ $person->deleted_at ? $person->deleted_at->format('M j, Y') : 'N/A' }}
                                        </span>
                                    @else
                                        <span class="font-mono" style="font-size: 0.8125rem;">
                                            {{ $person->created_at ? $person->created_at->format('M j, Y') : '—' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-right" style="white-space: nowrap;">
                                    <div style="display: inline-flex; gap: 0.375rem; align-items: center; justify-content: flex-end;">
                                        {{-- Configure / Edit button --}}
                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            @click="openEditModal({{ $person->id }})"
                                            title="Configure and update personnel information"
                                        >
                                            Configure
                                        </button>

                                        @if (!$person->trashed())
                                            {{-- Archive Action --}}
                                            <form
                                                method="POST"
                                                action="{{ route('scheduling.destroy', $person) }}"
                                                onsubmit="return confirm('Archive personnel profile for {{ addslashes($person->full_name) }}? They will be unassigned and placed in the inactive roster.');"
                                                style="display: inline;"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="btn btn-sm"
                                                    style="background: transparent; color: var(--color-danger, #ef4444); border: 1px solid rgba(239, 68, 68, 0.35); font-size: 0.75rem; padding: 0.25rem 0.5rem;"
                                                    title="Archive this personnel profile"
                                                >
                                                    Archive
                                                </button>
                                            </form>
                                        @else
                                            {{-- Restore / Activate Action --}}
                                            <form
                                                method="POST"
                                                action="{{ route('scheduling.restore', $person->id) }}"
                                                onsubmit="return confirm('Reactivate and restore {{ addslashes($person->full_name) }} to the active personnel roster?');"
                                                style="display: inline;"
                                            >
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-primary"
                                                    style="background: var(--color-success, #10b981); border-color: var(--color-success, #10b981); color: #fff; font-size: 0.75rem; padding: 0.25rem 0.5rem;"
                                                    title="Reactivate this personnel record"
                                                >
                                                    Activate
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-footer pagination-wrapper">
                {{ $personnel->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

    {{-- Embedded Shared Configure Modal --}}
    @include('scheduling.partials.configure-modal')
</div>
@endsection
