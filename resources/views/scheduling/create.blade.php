@extends('layouts.app')

@section('title', 'Add New Personnel')
@section('meta-description', 'Register a new field personnel into the manpower system.')

@section('content')
    <div class="page-header">
        <div>
            <a href="{{ route('scheduling.index') }}" class="back-link">← Back to Manpower</a>
            <h1>Add New Personnel</h1>
            <p class="page-header__description">Register a new field worker into the system.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('scheduling.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </div>

    <form method="POST" action="{{ route('scheduling.store') }}" class="card project-form" novalidate>
        @csrf

        {{-- Auto-generated Employee ID --}}
        <div class="card-header">Employee Information</div>
        <div class="card-body">
            <div class="form-group-premium">
                <label for="employee_id_preview" class="form-label">Employee ID <span class="status-pill status-pill--completed" style="font-size: 0.6875rem; vertical-align: middle;">Auto-generated</span></label>
                <input
                    type="text"
                    id="employee_id_preview"
                    class="form-control font-mono"
                    value="{{ $nextEmployeeId }}"
                    disabled
                    style="background: var(--color-surface-alt); color: var(--color-accent); font-weight: 600;"
                >
                <p class="form-hint">This ID is automatically assigned by the system and cannot be modified.</p>
            </div>

            <div class="personnel-form-grid">
                <div class="form-group-premium">
                    <label for="first_name" class="form-label">First Name <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}" required placeholder="ex. Juan">
                    @error('first_name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="middle_name" class="form-label">Middle Name <span class="text-muted" style="font-weight: 400;">(optional)</span></label>
                    <input type="text" id="middle_name" name="middle_name" class="form-control @error('middle_name') is-invalid @enderror" value="{{ old('middle_name') }}" placeholder="ex. David">
                    @error('middle_name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="last_name" class="form-label">Last Name <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}" required placeholder="ex. Dela Cruz">
                    @error('last_name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="personnel-form-grid personnel-form-grid--two">
                <div class="form-group-premium">
                    <label for="date_of_birth" class="form-label">Date of Birth <span class="required" aria-hidden="true">*</span></label>
                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror" value="{{ old('date_of_birth') }}" max="{{ now()->subYears(18)->format('Y-m-d') }}" required>
                    @error('date_of_birth')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="expertise" class="form-label">Expertise / Role <span class="required" aria-hidden="true">*</span></label>
                    <select id="expertise" name="expertise" class="form-control @error('expertise') is-invalid @enderror" required>
                        <option value="" disabled {{ old('expertise') ? '' : 'selected' }}>Select expertise...</option>
                        @foreach ($expertiseOptions as $option)
                            <option value="{{ $option }}" @selected(old('expertise') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    @error('expertise')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="personnel-form-grid personnel-form-grid--two">
                <div class="form-group-premium">
                    <label for="address" class="form-label">Address <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="address" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address') }}" required placeholder="ex. Unit 101, High Street">
                    @error('address')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="region" class="form-label">Region <span class="required" aria-hidden="true">*</span></label>
                    <select id="region" name="region" class="form-control @error('region') is-invalid @enderror" data-initial-value="{{ old('region') }}" required>
                        <option value="" disabled {{ old('region') ? '' : 'selected' }}>Select Region...</option>
                        @foreach(\App\Support\PhilippineRegions::all() as $regName)
                            <option value="{{ $regName }}" @selected(old('region') === $regName)>
                                {{ $regName }}
                            </option>
                        @endforeach
                    </select>
                    @error('region')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="personnel-form-grid personnel-form-grid--two">
                <div class="form-group-premium">
                    <label for="province" class="form-label">Province <span class="required" aria-hidden="true">*</span></label>
                    <select id="province" name="province" class="form-control @error('province') is-invalid @enderror" data-initial-value="{{ old('province') }}" required>
                        <option value="" disabled {{ old('province') ? '' : 'selected' }}>Select Province...</option>
                        @if(old('province'))
                            <option value="{{ old('province') }}" selected>{{ old('province') }}</option>
                        @endif
                    </select>
                    @error('province')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="city" class="form-label">City <span class="required" aria-hidden="true">*</span></label>
                    <select id="city" name="city" class="form-control @error('city') is-invalid @enderror" data-initial-value="{{ old('city') }}" required>
                        <option value="" disabled {{ old('city') ? '' : 'selected' }}>Select City / Municipality...</option>
                        @if(old('city'))
                            <option value="{{ old('city') }}" selected>{{ old('city') }}</option>
                        @endif
                    </select>
                    @error('city')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="barangay" class="form-label">Barangay</label>
                    <select id="barangay" name="barangay" class="form-control @error('barangay') is-invalid @enderror" data-initial-value="{{ old('barangay') }}">
                        <option value="">Select Barangay...</option>
                        @if(old('barangay'))
                            <option value="{{ old('barangay') }}" selected>{{ old('barangay') }}</option>
                        @endif
                    </select>
                    @error('barangay')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="postal_code" class="form-label">Postal Code</label>
                    <input
                        type="text"
                        id="postal_code"
                        name="postal_code"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        class="form-control font-mono @error('postal_code') is-invalid @enderror"
                        value="{{ old('postal_code') }}"
                        maxlength="10"
                        placeholder="ex. 1226"
                    >
                    @error('postal_code')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        {{-- Project Assignment (Optional) --}}
        <div class="card-header" style="border-top: 1px solid var(--color-border);">Project Assignment</div>
        <div class="card-body" x-data="{ assignProject: {{ old('assign_project', '0') == '1' ? 'true' : 'false' }} }">
            <div class="form-group-premium">
                <label class="form-check" style="cursor: pointer;">
                    <input
                        type="checkbox"
                        id="assign_project"
                        name="assign_project"
                        value="1"
                        class="form-check-input"
                        x-model="assignProject"
                        {{ old('assign_project') ? 'checked' : '' }}
                    >
                    <span class="form-check-label">Assign this personnel to a project now</span>
                </label>
                <p class="form-hint">You can assign them later using the Reassign action from the personnel list.</p>
            </div>

            <div class="form-group-premium" x-show="assignProject" x-transition style="margin-top: var(--space-4);">
                <label for="project_id" class="form-label">Select Project <span class="required" aria-hidden="true">*</span></label>
                <select id="project_id" name="project_id" class="form-control @error('project_id') is-invalid @enderror">
                    <option value="" disabled selected>Choose a project...</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>
                            {{ $project->project_code }} — {{ $project->name }}
                        </option>
                    @endforeach
                </select>
                @error('project_id')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="card-footer">
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="btn-submit-personnel">Add Personnel</button>
                <a href="{{ route('scheduling.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </form>
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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof initPhilippineAddress === 'function') {
            initPhilippineAddress({
                regionSelect: '#region',
                provinceSelect: '#province',
                citySelect: '#city',
                barangaySelect: '#barangay',
                postalCodeInput: '#postal_code',
                addressInput: '#address',
                initialRegion: @js(old('region', '')),
                initialProvince: @js(old('province', '')),
                initialCity: @js(old('city', '')),
                initialBarangay: @js(old('barangay', ''))
            });
        }
    });
</script>
@endpush
