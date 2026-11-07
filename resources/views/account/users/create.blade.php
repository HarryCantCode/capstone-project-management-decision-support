@extends('layouts.app')

@section('title', 'Add User')
@section('meta-description', 'Add a new user to the system.')

@section('content')
    <div
        x-data="{
            confirmModalOpen: false,
            acknowledgedCorrect: false,
            firstName: '{{ old('first_name', '') }}',
            middleName: '{{ old('middle_name', '') }}',
            lastName: '{{ old('last_name', '') }}',
            birthdate: '{{ old('birthdate', '') }}',
            email: '{{ old('email', '') }}',
            jobTitleChoice: '{{ old('job_title_choice', '') }}',
            jobTitleOther: '{{ old('job_title_other', '') }}',
            role: '{{ old('role', '') }}',
            
            get resolvedFullName() {
                return [this.firstName, this.middleName, this.lastName].filter(Boolean).join(' ');
            },
            get resolvedJobTitle() {
                return this.jobTitleChoice === 'Others' ? (this.jobTitleOther || 'Other') : (this.jobTitleChoice || 'N/A');
            },
            triggerConfirm() {
                const form = document.getElementById('create-user-form');
                if (!form.reportValidity()) {
                    return;
                }
                this.acknowledgedCorrect = false;
                this.confirmModalOpen = true;
            },
            submitCreateUser() {
                if (!this.acknowledgedCorrect) {
                    return;
                }
                document.getElementById('create-user-form').submit();
            }
        }"
    >
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
                            style="background: var(--color-surface-alt, #f8fafc); color: var(--color-accent, #2C5F7C); font-weight: 600;"
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
                            style="background: var(--color-surface-alt, #f8fafc); color: var(--color-accent, #2C5F7C); font-weight: 600;"
                        >
                    </div>
                </div>

                {{-- Name Grid: First Name, Middle Name, Last Name --}}
                <div class="personnel-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group-premium">
                        <label for="first_name" class="form-label">First Name <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="first_name" name="first_name" x-model="firstName" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}" required autofocus placeholder="ex. Juan">
                        @error('first_name')<div class="form-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group-premium">
                        <label for="middle_name" class="form-label">Middle Name <span class="text-muted" style="font-weight: 400;">(optional)</span></label>
                        <input type="text" id="middle_name" name="middle_name" x-model="middleName" class="form-control @error('middle_name') is-invalid @enderror" value="{{ old('middle_name') }}" placeholder="ex. David">
                        @error('middle_name')<div class="form-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group-premium">
                        <label for="last_name" class="form-label">Last Name <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="last_name" name="last_name" x-model="lastName" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}" required placeholder="ex. Dela Cruz">
                        @error('last_name')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                {{-- Birthdate and Email --}}
                <div class="personnel-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                    <div class="form-group-premium">
                        <label for="birthdate" class="form-label">Birthdate <span class="required" aria-hidden="true">*</span></label>
                        <input type="date" id="birthdate" name="birthdate" x-model="birthdate" class="form-control @error('birthdate') is-invalid @enderror" value="{{ old('birthdate') }}" max="{{ now()->subYears(18)->format('Y-m-d') }}" required>
                        @error('birthdate')<div class="form-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group-premium">
                        <label for="email" class="form-label">Email Address <span class="required" aria-hidden="true">*</span></label>
                        <input type="email" id="email" name="email" x-model="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required placeholder="user@dex-pms.local">
                        @error('email')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                {{-- Job Title & System Role --}}
                <div class="personnel-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                    <div class="form-group-premium">
                        <label for="job_title_choice" class="form-label">Job Title <span class="required" aria-hidden="true">*</span></label>
                        <select id="job_title_choice" name="job_title_choice" x-model="jobTitleChoice" class="form-control @error('job_title_choice') is-invalid @enderror" required onchange="handleJobTitleChange(this)">
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
                        <select id="role" name="role" x-model="role" class="form-control @error('role') is-invalid @enderror" required>
                            <option value="" disabled {{ old('role') ? '' : 'selected' }}>Select a role...</option>
                            @foreach($roles as $r)
                                <option value="{{ $r->name }}" {{ old('role') === $r->name ? 'selected' : '' }}>{{ $r->name }}</option>
                            @endforeach
                        </select>
                        @error('role')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                {{-- Custom Job Title input (shown if Others is selected) --}}
                <div class="form-group-premium" id="custom_job_title_group" style="{{ old('job_title_choice') === 'Others' ? '' : 'display: none;' }}">
                    <label for="job_title_other" class="form-label">Custom Job Title <span class="required" aria-hidden="true">*</span></label>
                    <input type="text" id="job_title_other" name="job_title_other" x-model="jobTitleOther" class="form-control @error('job_title_other') is-invalid @enderror" value="{{ old('job_title_other') }}" placeholder="Enter custom job title...">
                    @error('job_title_other')<div class="form-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="card-footer">
                <div class="form-actions">
                    <button type="button" class="btn btn-primary" @click="triggerConfirm()">Create User Account</button>
                    <a href="{{ route('account.settings') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>

        {{-- ─── Modal: Confirmation Before Sending Password Link ─── --}}
        <div
            class="reassign-modal-overlay"
            x-show="confirmModalOpen"
            x-cloak
            @keydown.escape.window="confirmModalOpen = false"
            style="display: none;"
        >
            <div class="reassign-modal-backdrop" @click="confirmModalOpen = false"></div>
            <div class="reassign-modal-content card" style="max-width: 520px;">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 600; font-size: 0.95rem;">Confirm New User Details</span>
                    <button type="button" class="flash-close" @click="confirmModalOpen = false" aria-label="Close modal">&times;</button>
                </div>
                <div class="card-body">
                    <p class="text-muted" style="font-size: 0.8125rem; line-height: 1.5; margin-bottom: 1rem;">
                        Please review the details below. Once confirmed, an email invitation with a password creation link will be dispatched to the user.
                    </p>

                    <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.25rem; font-size: 0.8125rem;">
                        <div style="display: grid; grid-template-columns: 140px 1fr; row-gap: 0.5rem; column-gap: 0.75rem;">
                            <span style="color: var(--color-text-muted); font-weight: 500;">Full Name:</span>
                            <strong style="color: var(--color-ink);" x-text="resolvedFullName || 'N/A'"></strong>

                            <span style="color: var(--color-text-muted); font-weight: 500;">Email Address:</span>
                            <span class="font-mono" style="color: var(--color-ink);" x-text="email || 'N/A'"></span>

                            <span style="color: var(--color-text-muted); font-weight: 500;">Employee ID:</span>
                            <span class="font-mono text-muted">{{ $nextEmployeeNumber }}</span>

                            <span style="color: var(--color-text-muted); font-weight: 500;">Account Number:</span>
                            <span class="font-mono text-muted">{{ $nextAccountNumber }}</span>

                            <span style="color: var(--color-text-muted); font-weight: 500;">Job Title:</span>
                            <span style="color: var(--color-ink);" x-text="resolvedJobTitle"></span>

                            <span style="color: var(--color-text-muted); font-weight: 500;">System Role:</span>
                            <span style="color: var(--color-ink); font-weight: 600;" x-text="role || 'N/A'"></span>

                            <span style="color: var(--color-text-muted); font-weight: 500;">Birthdate:</span>
                            <span style="color: var(--color-ink);" x-text="birthdate || 'N/A'"></span>
                        </div>
                    </div>

                    {{-- Mandatory Confirmation Checkbox --}}
                    <div class="form-check" style="display: flex; align-items: flex-start; gap: 0.5rem; margin-top: 0.75rem;">
                        <input
                            type="checkbox"
                            id="confirm_correct_checkbox"
                            class="form-check-input"
                            x-model="acknowledgedCorrect"
                            style="margin-top: 0.2rem; cursor: pointer;"
                        >
                        <label for="confirm_correct_checkbox" class="form-check-label" style="font-size: 0.8125rem; color: var(--color-ink); cursor: pointer; user-select: none; line-height: 1.45;">
                            I confirm that all of the fields entered above are accurate and ready for account provisioning.
                        </label>
                    </div>
                </div>
                <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <button type="button" class="btn btn-secondary btn-sm" @click="confirmModalOpen = false">Cancel</button>
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        :disabled="!acknowledgedCorrect"
                        @click="submitCreateUser()"
                        style="display: inline-flex; align-items: center; gap: 0.4rem;"
                    >
                        <span>Confirm and send password link</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

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

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const jobTitleSelect = document.getElementById('job_title_choice');
            if (jobTitleSelect) {
                handleJobTitleChange(jobTitleSelect);
            }
        });
    </script>
@endsection
