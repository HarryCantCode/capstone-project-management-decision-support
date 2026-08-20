@extends('layouts.app')

@section('title', 'Dashboard')
@section('meta-description', 'Overview of Dex International Co. project status and activity.')

@section('content')
    <div class="page-header">
        <div>
            <h1>Dashboard</h1>
            <p class="page-header__description">
                Good {{ now()->format('g:i A') }} — here's what's happening today.
            </p>
        </div>
    </div>

    {{-- Status summary cards --}}
    <div class="dashboard-stats">

        <div class="stat-card">
            <div class="stat-card__value numeric">{{ number_format($stats['projects_total']) }}</div>
            <div class="stat-card__label">Total Projects</div>
        </div>

        <div class="stat-card">
            <div class="stat-card__value numeric">{{ number_format($stats['projects_ongoing']) }}</div>
            <div class="stat-card__label">
                <span class="status-pill status-pill--ongoing" aria-label="Ongoing projects">Ongoing</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card__value numeric">{{ number_format($stats['projects_pending']) }}</div>
            <div class="stat-card__label">
                <span class="status-pill status-pill--pending" aria-label="Pending projects">Pending</span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-card__value numeric">{{ number_format($stats['projects_completed']) }}</div>
            <div class="stat-card__label">
                <span class="status-pill status-pill--completed" aria-label="Completed projects">Completed</span>
            </div>
        </div>

    </div>

    {{-- Recent projects section (Phase 2 will populate this) --}}
    <div class="card mt-8">
        <div class="card-header">
            Recent Projects
            {{-- Phase 2: <a href="{{ route('projects.index') }}" class="btn btn-secondary btn-sm">View all</a> --}}
        </div>
        <div class="card-body">
            <div class="empty-state">
                <svg class="empty-state__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9z"/>
                </svg>
                <h2 class="empty-state__title">No projects yet</h2>
                <p class="empty-state__description">
                    Projects will appear here once they've been created. Project management is coming in Phase 2.
                </p>
                {{-- Phase 2: <a href="{{ route('projects.create') }}" class="btn btn-primary">Create first project</a> --}}
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
.dashboard-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.mt-8 { margin-top: 2rem; }
</style>
@endpush
