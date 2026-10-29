@extends('layouts.app')

@section('title', 'Reports & Insights')
@section('meta-description', 'View organizational metrics and analytical reports.')

@section('content')
    <div class="page-header">
        <div>
            <h1>Reports & Insights</h1>
            <p class="page-header__description">Real-time organizational performance metrics.</p>
        </div>
        <div class="page-actions">
            <button class="btn btn-secondary" disabled>
                <svg class="nav-icon" style="width: 16px; height: 16px; margin-right: 4px;" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 17a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1zm3.293-7.707a1 1 0 0 1 1.414 0L9 10.586V3a1 1 0 1 1 2 0v7.586l1.293-1.293a1 1 0 1 1 1.414 1.414l-3 3a1 1 0 0 1-1.414 0l-3-3a1 1 0 0 1 0-1.414z" clip-rule="evenodd"/>
                </svg>
                Export CSV
            </button>
        </div>
    </div>

    <div class="project-detail-grid mb-4">
        <div class="stat-card card-glass">
            <div class="stat-card__value">{{ $metrics['active_projects'] }}</div>
            <div class="stat-card__label">Active Projects</div>
        </div>
        <div class="stat-card card-glass">
            <div class="stat-card__value text-success">₱{{ number_format($metrics['total_revenue'] / 1000000, 1) }}M</div>
            <div class="stat-card__label">Projected Revenue</div>
        </div>
        <div class="stat-card card-glass">
            <div class="stat-card__value">{{ $metrics['active_personnel'] }}</div>
            <div class="stat-card__label">Field Personnel</div>
        </div>
    </div>

    <div class="card card-glass mb-6">
        <div class="card-header">
            <span>Monthly Performance Trend</span>
        </div>
        <div class="card-body empty-state">
            <svg class="empty-state__icon text-muted" viewBox="0 0 20 20" fill="currentColor">
                <path d="M2 11a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-5zM8 7a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V7zM14 4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V4z"/>
            </svg>
            <h3 class="empty-state__title">Data Visualization Hub</h3>
            <p class="empty-state__description">Interactive charts and visual insights will be generated here.</p>
        </div>
    </div>
@endsection
