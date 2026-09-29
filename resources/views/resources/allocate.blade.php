@extends('layouts.app')

@section('title', 'Allocate Resource')
@section('meta-description', 'Allocate available inventory to a project.')

@section('content')
    <div class="page-header">
        <div>
            <h1>Allocate Resource</h1>
            <p class="page-header__description"><span class="font-mono">{{ $resource->resource_code }}</span> · {{ $resource->name }} · {{ number_format($resource->quantity_available) }} available</p>
        </div>
    </div>

    <div class="project-detail-grid">
        <section class="card card-glass" aria-labelledby="resource-info-heading">
            <div class="card-header"><h2 id="resource-info-heading">Resource Info</h2></div>
            <dl class="card-body detail-list" style="grid-template-columns: 1fr;">
                <div><dt>Available quantity</dt><dd class="font-mono" style="font-size: 1.5rem; color: var(--color-success);">{{ number_format($resource->quantity_available) }}</dd></div>
                <div><dt>Condition</dt><dd><span class="badge-condition badge-condition--{{ $resource->condition }}">{{ ucfirst(str_replace('_', ' ', $resource->condition)) }}</span></dd></div>
                <div><dt>Resource type</dt><dd>{{ ucfirst($resource->type) }}</dd></div>
            </dl>
        </section>

        <form method="POST" action="{{ route('resources.allocate.store', $resource) }}" class="card card-glass" novalidate>
            <div class="card-header"><h2>Allocation Details</h2></div>
            @csrf
            <div class="card-body">
                <div class="form-group form-group-premium">
                    <label for="project_id" class="form-label">Project</label>
                    <select id="project_id" name="project_id" class="form-control @error('project_id') is-invalid @enderror" required>
                        <option value="">Select a project</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->project_code }} — {{ $project->name }}</option>
                        @endforeach
                    </select>
                    @error('project_id')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium">
                    <label for="quantity" class="form-label">Quantity to allocate</label>
                    <input id="quantity" type="number" name="quantity" min="1" max="{{ $resource->quantity_available }}" class="form-control @error('quantity') is-invalid @enderror" value="{{ old('quantity') }}" required>
                    @error('quantity')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group form-group-premium">
                    <label for="notes" class="form-label">Allocation notes <span class="text-muted">(optional)</span></label>
                    <textarea id="notes" name="notes" rows="4" maxlength="500" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                    @error('notes')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-actions mt-6">
                    <button type="submit" class="btn btn-primary">Confirm Allocation</button>
                    <a href="{{ route('resources.show', $resource) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>
@endsection
