@extends('layouts.app')

@section('title', 'Add User')
@section('meta-description', 'Add a new user to the system.')

@section('content')
    <div class="page-header">
        <div>
            <a href="{{ route('account.settings') }}" class="back-link">← Back to User Management</a>
            <h1>Add User</h1>
            <p class="page-header__description">Create a new system user account and assign their role.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('account.settings') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </div>

    <form method="POST" action="{{ route('account.users.store') }}" class="card card-glass project-form" id="create-user-form">
        @csrf
        <div class="card-header">User Information</div>
        <div class="card-body">
            {{-- Auto-generated Identifiers: Employee ID and Account Number --}}
            <div class="personnel-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                <div class="form-group-premium">
                    <label for="employee_number_preview" class="form-label">
                        Employee ID 
                        <span class="status-pill status-pill--completed" style="font-size: 0.6875rem; vertical-align: middle;">Auto-generated</span>
                    </label>
                    <input
                        type="text"
                        id="employee_number_preview"
                        class="form-control font-mono"
                        value="{{ $nextEmployeeNumber }}"
                        disabled
                        style="background: var(--color-surface-alt, #f8fafc); color: var(--color-accent, #3b82f6); font-weight: 600;"
                    >
                </div>

                <div class="form-group-premium">
                    <label for="account_number_preview" class="form-label">
                        Account Number (7-Digit) 
                        <span class="status-pill status-pill--completed" style="font-size: 0.6875rem; vertical-align: middle;">Auto-generated</span>
                    </label>
                    <input
                        type="text"
                        id="account_number_preview"
                        class="form-control font-mono"
                        value="{{ $nextAccountNumber }}"
                        disabled
                        style="background: var(--color-surface-alt, #f8fafc); color: var(--color-accent, #3b82f6); font-weight: 600;"
                    >
                </div>
            </div>

            {{-- Name Grid: First Name, Middle Name, Last Name --}}
            <div class="personnel-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div class="form-group-premium">
                    <label for="first_name" class="form-label">First Name <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}" required autofocus>
                    @error('first_name')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="middle_name" class="form-label">Middle Name <span class="text-muted" style="font-weight: 400;">(optional)</span></label>
                    <input type="text" id="middle_name" name="middle_name" class="form-control @error('middle_name') is-invalid @enderror" value="{{ old('middle_name') }}">
                    @error('middle_name')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="last_name" class="form-label">Last Name <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}" required oninput="updatePasswordPreview()">
                    @error('last_name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Birthdate and Email --}}
            <div class="personnel-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                <div class="form-group-premium">
                    <label for="birthdate" class="form-label">Birthdate <span class="required" aria-hidden="true">*</span></label>
                    <input type="date" id="birthdate" name="birthdate" class="form-control @error('birthdate') is-invalid @enderror" value="{{ old('birthdate') }}" max="{{ now()->format('Y-m-d') }}" required onchange="updatePasswordPreview()">
                    @error('birthdate')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="email" class="form-label">Email Address <span class="required" aria-hidden="true">*</span></label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required placeholder="user@dex-pms.local">
                    @error('email')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Job Title & System Role --}}
            <div class="personnel-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                <div class="form-group-premium">
                    <label for="job_title_choice" class="form-label">Job Title <span class="required" aria-hidden="true">*</span></label>
                    <select id="job_title_choice" name="job_title_choice" class="form-control @error('job_title_choice') is-invalid @enderror" required onchange="handleJobTitleChange(this)">
                        <option value="" disabled {{ old('job_title_choice') ? '' : 'selected' }}>Select a job title...</option>
                        @foreach($jobTitles as $title)
                            <option value="{{ $title }}" {{ old('job_title_choice') === $title ? 'selected' : '' }}>{{ $title }}</option>
                        @endforeach
                        <option value="Others" {{ old('job_title_choice') === 'Others' ? 'selected' : '' }}>Others (specify below)</option>
                    </select>
                    @error('job_title_choice')<div class="form-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group-premium">
                    <label for="role" class="form-label">System Role <span class="required" aria-hidden="true">*</span></label>
                    <select id="role" name="role" class="form-control @error('role') is-invalid @enderror" required>
                        <option value="" disabled {{ old('role') ? '' : 'selected' }}>Select a role...</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" {{ old('role') === $role->name ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            {{-- Custom Job Title input (shown if Others is selected) --}}
            <div class="form-group-premium" id="custom_job_title_group" style="{{ old('job_title_choice') === 'Others' ? '' : 'display: none;' }}">
                <label for="job_title_other" class="form-label">Custom Job Title <span class="required" aria-hidden="true">*</span></label>
                <input type="text" id="job_title_other" name="job_title_other" class="form-control @error('job_title_other') is-invalid @enderror" value="{{ old('job_title_other') }}" placeholder="Enter custom job title...">
                @error('job_title_other')<div class="form-error">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Auto-generated Password Card --}}
        <div class="card-header" style="border-top: 1px solid var(--color-border);">Security & Credentials</div>
        <div class="card-body">
            <div class="password-preview-box" style="background: rgba(59, 130, 246, 0.05); border: 1px solid var(--color-border); border-radius: var(--radius-md, 8px); padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                    <label for="generated_password_display" class="form-label" style="margin-bottom: 0; font-weight: 600;">
                        Auto-Generated Password 
                        <span class="status-pill status-pill--completed" style="font-size: 0.6875rem; margin-left: 0.5rem;">Auto-formatted</span>
                    </label>
                </div>
                <div style="margin-top: 0.5rem;">
                    <input
                        type="text"
                        id="generated_password_display"
                        class="form-control font-mono"
                        value="@smithDEX2000"
                        readonly
                        style="font-weight: 700; letter-spacing: 0.05em; color: var(--color-accent, #2563eb); background: var(--color-surface, #ffffff); font-size: 1.05rem;"
                    >
                </div>
                <p class="form-hint" style="margin-top: 0.5rem; margin-bottom: 0;">
                    Password pattern: <code>@ + lastname + DEX + birthyear</code> (e.g. <code>@smithDEX2000</code>). This password is automatically generated and assigned upon account creation.
                </p>
            </div>
        </div>

        <div class="card-footer">
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create User Account</button>
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
                customInput.focus();
            } else {
                customGroup.style.display = 'none';
                customInput.removeAttribute('required');
            }
        }

        function updatePasswordPreview() {
            const lastNameInput = document.getElementById('last_name');
            const birthdateInput = document.getElementById('birthdate');
            const displayInput = document.getElementById('generated_password_display');

            const lastName = (lastNameInput ? lastNameInput.value : '').trim().replace(/\s+/g, '').toLowerCase();
            const birthdateVal = birthdateInput ? birthdateInput.value : '';
            
            let year = '2000';
            if (birthdateVal) {
                const parts = birthdateVal.split('-');
                if (parts.length >= 1 && parts[0].length === 4) {
                    year = parts[0];
                } else {
                    const d = new Date(birthdateVal);
                    if (!isNaN(d.getFullYear())) {
                        year = d.getFullYear();
                    }
                }
            }
            
            const ln = lastName || 'lastname';
            displayInput.value = '@' + ln + 'DEX' + year;
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updatePasswordPreview();
            const jobTitleSelect = document.getElementById('job_title_choice');
            if (jobTitleSelect) {
                handleJobTitleChange(jobTitleSelect);
            }
        });
    </script>
@endsection
