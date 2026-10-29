@extends('layouts.app')

@section('title', 'Configure Personnel — ' . $personnel->full_name)
@section('meta-description', 'Update field personnel information, manage project assignment, or archive profile.')

@section('content')
    <div class="page-header">
        <div>
            <a href="{{ route('scheduling.roster') }}" class="back-link">← Back to Personnel Roster</a>
            <h1>Configure Personnel</h1>
            <p class="page-header__description">
                Update personal details, expertise, project assignment, or archive worker profile.
            </p>
        </div>
        <div class="page-actions" style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="{{ route('scheduling.roster') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </div>

    @if ($personnel->trashed())
        <div class="alert alert-warning" style="margin-bottom: var(--space-4); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong>Archived Personnel Record:</strong> This profile was archived on {{ $personnel->deleted_at->format('M j, Y') }}.
            </div>
            <form method="POST" action="{{ route('scheduling.restore', $personnel->id) }}" onsubmit="return confirm('Restore this personnel to active status?');">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary" style="background: var(--color-success, #10b981); border-color: var(--color-success, #10b981);">
                    Activate & Restore
                </button>
            </form>
        </div>
    @endif

    <form method="POST" action="{{ route('scheduling.update', $personnel) }}" class="card project-form" novalidate>
        @csrf
        @method('PUT')

        {{-- Employee Details --}}
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Employee Information</span>
            <span class="badge badge-accent font-mono">{{ $personnel->employee_id }}</span>
        </div>
        <div class="card-body">
            <div class="personnel-form-grid">
                <div class="form-group-premium">
                    <label for="first_name" class="form-label">First Name <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $personnel->first_name) }}" required>
                    @error('first_name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="middle_name" class="form-label">Middle Name <span class="text-muted" style="font-weight: 400;">(optional)</span></label>
                    <input type="text" id="middle_name" name="middle_name" class="form-control @error('middle_name') is-invalid @enderror" value="{{ old('middle_name', $personnel->middle_name) }}">
                    @error('middle_name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="last_name" class="form-label">Last Name <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $personnel->last_name) }}" required>
                    @error('last_name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="personnel-form-grid personnel-form-grid--two">
                <div class="form-group-premium">
                    <label for="date_of_birth" class="form-label">Date of Birth <span class="required" aria-hidden="true">*</span></label>
                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror" value="{{ old('date_of_birth', $personnel->date_of_birth ? $personnel->date_of_birth->format('Y-m-d') : '') }}" max="{{ now()->subYears(16)->format('Y-m-d') }}" required>
                    @error('date_of_birth')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="expertise" class="form-label">Expertise / Role <span class="required" aria-hidden="true">*</span></label>
                    <select id="expertise" name="expertise" class="form-control @error('expertise') is-invalid @enderror" required>
                        @foreach ($expertiseOptions as $option)
                            <option value="{{ $option }}" @selected(old('expertise', $personnel->expertise) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    @error('expertise')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-group-premium">
                <label for="address" class="form-label">Address <span class="required" aria-hidden="true">*</span></label>
                <textarea id="address" name="address" rows="3" maxlength="1000" class="form-control @error('address') is-invalid @enderror" required>{{ old('address', $personnel->address) }}</textarea>
                @error('address')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Project Assignment --}}
        <div class="card-header" style="border-top: 1px solid var(--color-border);">Project Assignment</div>
        <div class="card-body">
            <div class="form-group-premium">
                <label for="project_id" class="form-label">Assigned Project</label>
                <select id="project_id" name="project_id" class="form-control @error('project_id') is-invalid @enderror">
                    <option value="">No Assignment (Available)</option>
                    @foreach ($activeProjects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id', $personnel->project_id) == $project->id)>
                            {{ $project->project_code }} — {{ $project->name }}
                        </option>
                    @endforeach
                </select>
                <p class="form-hint">Changing the project will automatically record the assignment audit trail.</p>
                @error('project_id')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>
        </div>

        @if (!$personnel->trashed())
            {{-- Archive Profile Option --}}
            <div class="card-header" style="border-top: 1px solid var(--color-border); color: var(--color-danger, #ef4444);">
                Archive Personnel Profile
            </div>
            <div class="card-body">
                <div style="background: var(--color-surface-alt, rgba(239, 68, 68, 0.04)); border: 1px dashed var(--color-border); border-left: 4px solid var(--color-danger, #ef4444); border-radius: var(--radius-md); padding: var(--space-4);">
                    <p style="margin: 0 0 var(--space-3) 0; font-size: 0.875rem; color: var(--color-muted);">
                        Archiving this personnel profile will soft-delete their record, unassign them from any active project, and place them in the inactive roster. They can be reactivated at any time from the roster.
                    </p>
                    <label class="form-check" style="cursor: pointer;">
                        <input type="checkbox" name="archive_profile" value="1" class="form-check-input" id="archive_profile" {{ old('archive_profile') ? 'checked' : '' }}>
                        <span class="form-check-label" style="font-weight: 500;">Place this personnel profile to archive on save</span>
                    </label>
                </div>
            </div>
        @endif

        <div class="card-footer">
            <div class="form-actions" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <div>
                    @if (!$personnel->trashed())
                        <button
                            type="button"
                            class="btn btn-sm"
                            style="background: transparent; color: var(--color-danger, #ef4444); border: 1px solid rgba(239, 68, 68, 0.35);"
                            onclick="if(confirm('Are you sure you want to archive this personnel profile?')) { document.getElementById('direct-archive-form').submit(); }"
                        >
                            Archive Immediately
                        </button>
                    @endif
                </div>
                <div style="display: flex; gap: var(--space-3);">
                    <a href="{{ route('scheduling.roster') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="btn-save-personnel">Save Changes</button>
                </div>
            </div>
        </div>
    </form>

    @if (!$personnel->trashed())
        <form id="direct-archive-form" method="POST" action="{{ route('scheduling.destroy', $personnel) }}" style="display: none;">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection

@push('styles')
<style>
.personnel-form-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-4);
    margin-bottom: var(--space-5);
}
.personnel-form-grid--two {
    grid-template-columns: repeat(2, 1fr);
}
.required {
    color: var(--color-danger);
}
@media (max-width: 768px) {
    .personnel-form-grid,
    .personnel-form-grid--two {
        grid-template-columns: 1fr;
    }
}
</style>
@endpush
