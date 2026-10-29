@extends('layouts.app')

@section('title', 'Resource Details')
@section('meta-description', 'View resource stock, condition, and allocations.')

@section('content')
    <div class="page-header">
        <div>
            <a href="{{ route('resources.index') }}" class="back-link">← All resources</a>
            <h1>{{ $resource->name }}</h1>
            <p class="page-header__description"><span class="font-mono">{{ $resource->resource_code }}</span> · {{ ucfirst($resource->type) }}</p>
        </div>
        @can('allocate', $resource)
            @if ($resource->quantity_available > 0)
                <a href="{{ route('resources.allocate', $resource) }}" class="btn btn-primary">Allocate Resource</a>
            @endif
        @endcan
    </div>

    <section class="card card-glass" aria-labelledby="resource-overview-heading">
        <div class="card-header"><h2 id="resource-overview-heading">Inventory Overview</h2></div>
        <dl class="card-body detail-list">
            <div><dt>Available quantity</dt><dd class="font-mono">{{ number_format($resource->quantity_available) }}</dd></div>
            <div><dt>Condition</dt><dd><span class="badge-condition badge-condition--{{ $resource->condition }}">{{ ucfirst(str_replace('_', ' ', $resource->condition)) }}</span></dd></div>
            <div><dt>Resource type</dt><dd>{{ ucfirst($resource->type) }}</dd></div>
            <div><dt>Last updated</dt><dd>{{ $resource->updated_at->format('M j, Y · g:i A') }}</dd></div>
        </dl>
    </section>

    <section class="card card-glass mt-6" aria-labelledby="allocation-history-heading">
        <div class="card-header"><h2 id="allocation-history-heading">Allocation History</h2></div>
        @if ($resource->allocations->isEmpty())
            <div class="card-body"><p class="text-muted">This resource has not been allocated to a project yet.</p></div>
        @else
            <div class="table-wrapper">
                <table class="data-table">
                    <caption class="visually-hidden">Resource allocation history</caption>
                    <thead><tr><th scope="col">Project</th><th scope="col" class="text-right">Quantity</th><th scope="col">Allocated by</th><th scope="col">Notes</th></tr></thead>
                    <tbody>
                        @foreach ($resource->allocations as $allocation)
                            <tr>
                                <td><span class="font-mono">{{ $allocation->project->project_code }}</span><br>{{ $allocation->project->name }}</td>
                                <td class="cell-numeric">{{ number_format($allocation->quantity) }}</td>
                                <td>{{ $allocation->creator?->name ?? 'System' }}</td>
                                <td>{{ $allocation->notes ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
