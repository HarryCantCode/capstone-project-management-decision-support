@extends('layouts.app')

@section('title', 'Equipment Records')
@section('meta-description', 'Manage and track condition of tools and equipment.')

@section('content')
    <div class="page-header">
        <div>
            <h1>Equipment Records</h1>
            <p class="page-header__description">Track the condition of all company tools and equipment.</p>
        </div>
    </div>

    <div class="card card-glass mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('equipment.index') }}" class="project-form" style="display: flex; gap: 1rem; align-items: flex-end;">
                <div class="form-group-premium" style="flex: 1;">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Search by name or code..." value="{{ request('search') }}">
                </div>
                <div class="form-group-premium" style="flex: 1;">
                    <label for="condition" class="form-label">Filter by Condition</label>
                    <select id="condition" name="condition" class="form-control">
                        <option value="">All Conditions</option>
                        @foreach($conditions as $key => $label)
                            <option value="{{ $key }}" {{ request('condition') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-actions" style="margin-bottom: 0;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    @if(request()->hasAny(['search', 'condition']))
                        <a href="{{ route('equipment.index') }}" class="btn btn-secondary">Clear</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card card-glass" aria-labelledby="equipment-heading">
        <div class="card-header">
            <h2 id="equipment-heading">Equipment & Tools Inventory</h2>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Code</th>
                        <th scope="col">Name</th>
                        <th scope="col">Type</th>
                        <th scope="col">Quantity</th>
                        <th scope="col">Condition</th>
                        <th scope="col" class="text-right">Update Condition</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($equipments as $equipment)
                        <tr>
                            <td><span class="font-mono text-muted">{{ $equipment->resource_code }}</span></td>
                            <td><strong>{{ $equipment->name }}</strong></td>
                            <td><span class="badge" style="text-transform: capitalize;">{{ $equipment->type }}</span></td>
                            <td>{{ number_format($equipment->quantity_available) }}</td>
                            <td>
                                @php
                                    $conditionClass = match($equipment->condition) {
                                        'good' => 'badge-condition--good',
                                        'needs_maintenance' => 'badge-condition--needs_repair',
                                        'out_of_service' => 'badge-condition--critical',
                                        default => ''
                                    };
                                @endphp
                                <span class="badge-condition {{ $conditionClass }}">
                                    {{ $conditions[$equipment->condition] ?? $equipment->condition }}
                                </span>
                            </td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('equipment.update', $equipment) }}" style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                    @csrf
                                    @method('PUT')
                                    <select name="condition" class="form-control" style="width: auto; padding: 0.25rem 0.5rem; height: auto;" required onchange="this.form.submit()">
                                        <option value="" disabled selected>Update...</option>
                                        @foreach($conditions as $key => $label)
                                            @if($key !== $equipment->condition)
                                                <option value="{{ $key }}">{{ $label }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No equipment records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($equipments->hasPages())
            <div class="card-footer">
                {{ $equipments->links() }}
            </div>
        @endif
    </div>
@endsection
