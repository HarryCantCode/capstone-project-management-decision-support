@extends('layouts.app')

@section('title', 'Resource Allocation')
@section('meta-description', 'View stock and allocate resources to projects.')

@section('content')
    <div class="page-header">
        <div>
            <h1>Resource Allocation</h1>
            <p class="page-header__description">Track available materials, tools, and equipment.</p>
        </div>
    </div>

    <div class="card card-glass">
        <div class="card-header">
            <span>Available Resources</span>
            <form method="GET" action="{{ route('resources.index') }}" class="project-filter">
                <label for="type" class="visually-hidden">Filter resources by type</label>
                <select id="type" name="type" class="form-control form-control--compact" onchange="this.form.submit()">
                    <option value="">All resource types</option>
                    <option value="material" @selected($type === 'material')>Materials</option>
                    <option value="tool" @selected($type === 'tool')>Tools</option>
                    <option value="equipment" @selected($type === 'equipment')>Equipment</option>
                </select>
            </form>
        </div>

        @if ($resources->isEmpty())
            <div class="card-body">
                <div class="empty-state">
                    <h2 class="empty-state__title">No resources found</h2>
                    <p class="empty-state__description">No inventory records match the selected type.</p>
                </div>
            </div>
        @else
            <div class="table-wrapper">
                <table class="data-table">
                    <caption class="visually-hidden">Resource inventory</caption>
                    <thead>
                        <tr>
                            <th scope="col">Resource</th>
                            <th scope="col">Type</th>
                            <th scope="col">Condition</th>
                            <th scope="col" class="text-right">Available</th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($resources as $resource)
                            <tr>
                                <td>
                                    <a href="{{ route('resources.show', $resource) }}" class="table-link">
                                        <span class="font-mono">{{ $resource->resource_code }}</span>
                                        <span class="project-name">{{ $resource->name }}</span>
                                    </a>
                                </td>
                                <td>{{ ucfirst($resource->type) }}</td>
                                <td><span class="badge-condition badge-condition--{{ $resource->condition }}">{{ ucfirst(str_replace('_', ' ', $resource->condition)) }}</span></td>
                                <td class="cell-numeric">{{ number_format($resource->quantity_available) }}</td>
                                <td class="text-right"><a href="{{ route('resources.show', $resource) }}" class="btn btn-secondary btn-sm">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer pagination-wrapper">{{ $resources->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
@endsection
