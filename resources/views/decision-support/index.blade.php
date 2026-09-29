@extends('layouts.app')

@section('title', 'Decision Support Engine')
@section('meta-description', 'AI-driven project recommendations and readiness analysis.')

@section('content')
    <div class="page-header">
        <div>
            <h1>Decision Support</h1>
            <p class="page-header__description">Get AI-driven recommendations for manpower, materials, and project timelines.</p>
        </div>
    </div>

    <div class="card card-glass mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('decision-support.index') }}" class="project-form" style="display: flex; gap: 1rem; align-items: flex-end;">
                <div class="form-group-premium" style="flex: 1;">
                    <label for="project_id" class="form-label">Select Project for Analysis</label>
                    <select id="project_id" name="project_id" class="form-control" required>
                        <option value="" disabled {{ !$selectedProject ? 'selected' : '' }}>Choose a project...</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ ($selectedProject?->id == $project->id) ? 'selected' : '' }}>
                                {{ $project->project_number }} - {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-actions" style="margin-bottom: 0;">
                    <button type="submit" class="btn btn-primary" {{ $projects->isEmpty() ? 'disabled' : '' }}>
                        Generate Analysis
                    </button>
                    @if($selectedProject)
                        <a href="{{ route('decision-support.index') }}" class="btn btn-secondary">Clear</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if($selectedProject && $result)
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
            
            <!-- Operational Readiness -->
            <div class="stat-card" style="grid-column: 1 / -1;">
                <div class="stat-card__icon" style="background: rgba(var(--color-accent-rgb), 0.1); color: var(--color-accent);">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="stat-card__content">
                    <h3 class="stat-card__label">Operational Readiness</h3>
                    <div class="stat-card__value text-muted" style="font-size: 1.25rem;">
                        <span class="badge" style="background: rgba(255,255,255,0.1);">Not yet available</span>
                    </div>
                </div>
            </div>

            <!-- Manpower Suggestion -->
            <div class="stat-card">
                <div class="stat-card__icon" style="background: rgba(255, 255, 255, 0.05); color: #fff;">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                    </svg>
                </div>
                <div class="stat-card__content">
                    <h3 class="stat-card__label">Manpower Suggestion</h3>
                    <p class="text-muted mt-2" style="font-size: 0.9rem;">Analysis model is currently in training phase. Scaffold mode active.</p>
                </div>
            </div>

            <!-- Material Suggestion -->
            <div class="stat-card">
                <div class="stat-card__icon" style="background: rgba(255, 255, 255, 0.05); color: #fff;">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="stat-card__content">
                    <h3 class="stat-card__label">Material Estimation</h3>
                    <p class="text-muted mt-2" style="font-size: 0.9rem;">Analysis model is currently in training phase. Scaffold mode active.</p>
                </div>
            </div>

            <!-- Timeline Suggestion -->
            <div class="stat-card">
                <div class="stat-card__icon" style="background: rgba(255, 255, 255, 0.05); color: #fff;">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="stat-card__content">
                    <h3 class="stat-card__label">Timeline Estimation</h3>
                    <p class="text-muted mt-2" style="font-size: 0.9rem;">Analysis model is currently in training phase. Scaffold mode active.</p>
                </div>
            </div>

        </div>
    @elseif(!$projects->isEmpty())
        <div class="text-center py-5">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-muted mb-3" style="width: 48px; height: 48px; margin: 0 auto; display: block; opacity: 0.5;">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <h3 class="text-muted">Select a project to begin</h3>
            <p class="text-muted">Choose a project from the dropdown above to generate AI-driven operational insights.</p>
        </div>
    @else
        <div class="text-center py-5">
            <h3 class="text-muted">No projects found</h3>
            <p class="text-muted">You must create a project first before running decision support analysis.</p>
        </div>
    @endif
@endsection
