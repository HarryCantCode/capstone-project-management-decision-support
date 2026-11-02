@extends('layouts.app')

@section('title', 'Projects')
@section('meta-description', 'View and manage Dex International Co. projects.')

@section('content')
    <div class="page-header">
        <div>
            <h1>Projects</h1>
            <p class="page-header__description">Track active work, clients, and project progress.</p>
        </div>
        @can('create', App\Models\Project::class)
            <a href="{{ route('projects.create') }}" class="btn btn-primary">Create Project</a>
        @endcan
    </div>

    <div class="card card-glass">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3);">
            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <span style="font-weight: 700; font-size: 1rem;">{{ $tab === 'archived' ? 'Archived Projects' : 'All Projects' }}</span>
                <div class="btn-group" role="group" aria-label="Project category filter" style="display: inline-flex; border: 1px solid var(--color-border); border-radius: var(--radius-sm); overflow: hidden;">
                    <a
                        href="{{ route('projects.index', array_filter(['tab' => 'active', 'search' => $search, 'status' => $status])) }}"
                        class="btn btn-sm {{ $tab !== 'archived' ? 'btn-primary' : 'btn-secondary' }}"
                        style="padding: 0.35rem 0.85rem; border: none; font-size: 0.8125rem;"
                        id="tab-active-projects"
                    >
                        Active Work ({{ $activeCount }})
                    </a>
                    <a
                        href="{{ route('projects.index', array_filter(['tab' => 'archived', 'search' => $search, 'status' => $status])) }}"
                        class="btn btn-sm {{ $tab === 'archived' ? 'btn-primary' : 'btn-secondary' }}"
                        style="padding: 0.35rem 0.85rem; border: none; font-size: 0.8125rem;"
                        id="tab-archived-projects"
                    >
                        Archived Projects ({{ $archivedCount }})
                    </a>
                </div>
            </div>

            <form method="GET" action="{{ route('projects.index') }}" class="project-header-actions" role="search">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="project-search-wrapper">
                    <label for="project-search" class="visually-hidden">Search projects</label>
                    <svg class="project-search-icon" width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 16px; height: 16px; flex-shrink: 0;">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                    <input
                        type="text"
                        id="project-search"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Search name, code, client..."
                        class="form-control form-control--compact project-search-input"
                        title="Search by project name, project code, client name, or creator"
                    >
                    @if (!empty($search))
                        <a href="{{ route('projects.index', array_filter(['tab' => $tab, 'status' => $status])) }}" class="project-search-clear" title="Clear search" aria-label="Clear search">
                            <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="width: 12px; height: 12px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4l8 8M12 4l-8 8" />
                            </svg>
                        </a>
                    @endif
                </div>

                <div class="project-status-wrapper" style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: nowrap;">
                    <label for="status" class="visually-hidden">Filter projects by status</label>
                    <select id="status" name="status" class="form-control form-control--compact project-status-select" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        <option value="pending" @selected($status === 'pending')>Pending</option>
                        <option value="ongoing" @selected($status === 'ongoing')>Ongoing</option>
                        <option value="delayed" @selected($status === 'delayed')>Delayed</option>
                        <option value="completed" @selected($status === 'completed')>Completed</option>
                    </select>

                    <button type="submit" class="btn btn-primary btn-sm" id="btn-projects-filter" style="display: inline-flex; align-items: center; gap: 0.35rem; white-space: nowrap;">
                        <svg style="width: 14px; height: 14px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                        </svg>
                        Filter
                    </button>
                </div>
            </form>
        </div>

        @if ($projects->isEmpty())
            <div class="card-body">
                <div class="empty-state">
                    <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9z"/>
                    </svg>
                    <h2 class="empty-state__title">No projects found</h2>
                    <p class="empty-state__description">
                        @if (!empty($search) && !empty($status))
                            No projects match "{{ $search }}" with status "{{ ucfirst($status) }}".
                        @elseif (!empty($search))
                            No projects match "{{ $search }}".
                        @elseif (!empty($status))
                            No projects match this status filter.
                        @elseif ($tab === 'archived')
                            No archived projects found in the repository.
                        @else
                            Start tracking work by creating the first project.
                        @endif
                    </p>
                    @if (!empty($search) || !empty($status))
                        <a href="{{ route('projects.index', ['tab' => $tab]) }}" class="btn btn-secondary btn-sm">Clear Search &amp; Filters</a>
                    @elseif ($tab === 'archived')
                        <a href="{{ route('projects.index') }}" class="btn btn-secondary btn-sm">← Back to Active Projects</a>
                    @else
                        @can('create', App\Models\Project::class)
                            <a href="{{ route('projects.create') }}" class="btn btn-primary">Create First Project</a>
                        @endcan
                    @endif
                </div>
            </div>
        @else
            <div class="table-wrapper">
                <table class="data-table">
                    <caption class="visually-hidden">Project register</caption>
                    <thead>
                        <tr>
                            <th scope="col">Project</th>
                            <th scope="col">Client</th>
                            <th scope="col">Target Date</th>
                            <th scope="col" class="text-right">Contract Price</th>
                            <th scope="col">Status</th>
                            <th scope="col">Created by</th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody x-data="{
                        init() {
                            if (window.Echo) {
                                window.Echo.channel('projects')
                                    .listen('ProjectCreated', (e) => {
                                        this.$el.insertAdjacentHTML('afterbegin', e.html);
                                    })
                                    .listen('ProjectDeleted', (e) => {
                                        const row = document.getElementById('project-row-' + e.id);
                                        if (row) row.remove();
                                    });
                            }
                        }
                    }">
                        @foreach ($projects as $project)
                            @include('projects.partials.row', ['project' => $project])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer pagination-wrapper">
                {{ $projects->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection
