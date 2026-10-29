@extends('layouts.app')

@section('title', 'Edit User')
@section('meta-description', 'Edit user details.')

@section('content')
    <div class="page-header">
        <div>
            <a href="{{ route('account.settings') }}" class="back-link">← Back to User Management</a>
            <h1>Edit User</h1>
            <p class="page-header__description">Update details for {{ $user->name }}.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('account.settings') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </div>

    @php
        $isPredefinedJob = in_array($user->job_title, $jobTitles);
        $selectedJobChoice = old('job_title_choice', $isPredefinedJob ? $user->job_title : (!empty($user->job_title) ? 'Others' : ''));
        $customJobValue = old('job_title_other', !$isPredefinedJob ? $user->job_title : '');
    @endphp

    <form method="POST" action="{{ route('account.users.update', $user) }}" class="card card-glass project-form">
        @csrf
        @method('PUT')
        <div class="card-header">User Details</div>
        <div class="card-body">
            {{-- Employee Number (Read-only) --}}
            <div class="form-group-premium">
                <label for="employee_number" class="form-label">Employee Number</label>
                <input
                    type="text"
                    id="employee_number"
                    class="form-control font-mono"
                    value="{{ $user->employee_number ?? 'N/A' }}"
                    disabled
                    style="background: var(--color-surface-alt, #f8fafc); font-weight: 600;"
                >
            </div>

            {{-- Name Grid --}}
            <div class="personnel-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div class="form-group-premium">
                    <label for="first_name" class="form-label">First Name <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $user->first_name ?: $user->name) }}" required>
                    @error('first_name')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="middle_name" class="form-label">Middle Name <span class="text-muted" style="font-weight: 400;">(optional)</span></label>
                    <input type="text" id="middle_name" name="middle_name" class="form-control @error('middle_name') is-invalid @enderror" value="{{ old('middle_name', $user->middle_name) }}">
                    @error('middle_name')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="last_name" class="form-label">Last Name <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $user->last_name) }}" required>
                    @error('last_name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Birthdate & Email --}}
            <div class="personnel-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                <div class="form-group-premium">
                    <label for="birthdate" class="form-label">Birthdate</label>
                    <input type="date" id="birthdate" name="birthdate" class="form-control @error('birthdate') is-invalid @enderror" value="{{ old('birthdate', $user->birthdate ? $user->birthdate->format('Y-m-d') : '') }}" max="{{ now()->format('Y-m-d') }}">
                    @error('birthdate')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="email" class="form-label">Email Address <span class="required" aria-hidden="true">*</span></label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                    @error('email')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Job Title & System Role --}}
            <div class="personnel-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                <div class="form-group-premium">
                    <label for="job_title_choice" class="form-label">Job Title <span class="required" aria-hidden="true">*</span></label>
                    <select id="job_title_choice" name="job_title_choice" class="form-control @error('job_title_choice') is-invalid @enderror" required onchange="handleJobTitleChange(this)">
                        <option value="" disabled>Select a job title...</option>
                        @foreach($jobTitles as $title)
                            <option value="{{ $title }}" {{ $selectedJobChoice === $title ? 'selected' : '' }}>{{ $title }}</option>
                        @endforeach
                        <option value="Others" {{ $selectedJobChoice === 'Others' ? 'selected' : '' }}>Others (specify below)</option>
                    </select>
                    @error('job_title_choice')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="role" class="form-label">System Role <span class="required" aria-hidden="true">*</span></label>
                    <select id="role" name="role" class="form-control @error('role') is-invalid @enderror" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" {{ old('role', $user->getRoleNames()->first()) === $role->name ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Custom Job Title input --}}
            <div class="form-group-premium" id="custom_job_title_group" style="{{ $selectedJobChoice === 'Others' ? '' : 'display: none;' }}">
                <label for="job_title_other" class="form-label">Custom Job Title <span class="required" aria-hidden="true">*</span></label>
                <input type="text" id="job_title_other" name="job_title_other" class="form-control @error('job_title_other') is-invalid @enderror" value="{{ $customJobValue }}" placeholder="Enter custom job title...">
                @error('job_title_other')<div class="form-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="card-header" style="border-top: 1px solid var(--color-border);">Security</div>
        <div class="card-body">
            <p class="text-muted" style="margin-bottom: 1rem;">Leave password fields blank if you do not wish to change the password.</p>
            <div class="form-group-premium">
                <label for="password" class="form-label">New Password</label>
                <input type="password" id="password" name="password" class="form-control" minlength="8">
                @error('password')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group-premium">
                <label for="password_confirmation" class="form-label">Confirm New Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" minlength="8">
            </div>
        </div>

        <div class="card-footer">
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('account.settings') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </form>

    <script>
        function handleJobTitleChange(select) {
            const customGroup = document.getElementById('custom_job_title_group');
            const customInput = document.getElementById('job_title_other');
            if (select.value === 'Others') {
                customGroup.style.display = 'block';
                customInput.setAttribute('required', 'required');
            } else {
                customGroup.style.display = 'none';
                customInput.removeAttribute('required');
            }
        }
    </script>
@endsection
