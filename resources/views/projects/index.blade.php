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
        <div class="card-header">
            <span>All Projects</span>
            <form method="GET" action="{{ route('projects.index') }}" class="project-filter">
                <label for="status" class="visually-hidden">Filter projects by status</label>
                <select id="status" name="status" class="form-control form-control--compact" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                    <option value="ongoing" @selected($status === 'ongoing')>Ongoing</option>
                    <option value="delayed" @selected($status === 'delayed')>Delayed</option>
                    <option value="completed" @selected($status === 'completed')>Completed</option>
                </select>
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
                        {{ $status ? 'No projects match this status filter.' : 'Start tracking work by creating the first project.' }}
                    </p>
                    @if (! $status)
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
