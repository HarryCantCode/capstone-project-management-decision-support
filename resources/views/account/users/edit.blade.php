@extends('layouts.app')

@section('title', 'Edit User')
@section('meta-description', 'Edit user details.')

@section('content')
    <div
        x-data="{
            confirmModalOpen: false,
            sendTempModalOpen: false,
            generateTempModalOpen: false,
            adminPassword: '',
            adminPasswordError: '',
            generatedPassword: '{{ session('generated_temporary_password') ?? '' }}',
            copied: false,
            copyPassword() {
                if (!this.generatedPassword) return;
                navigator.clipboard.writeText(this.generatedPassword);
                this.copied = true;
                setTimeout(() => this.copied = false, 2500);
            },
            triggerSave() {
                const editForm = document.getElementById('edit-user-form');
                if (!editForm.reportValidity()) {
                    return;
                }
                this.adminPassword = '';
                this.adminPasswordError = '';
                this.confirmModalOpen = true;
                this.$nextTick(() => {
                    const pwdInput = document.getElementById('admin-save-password');
                    if (pwdInput) pwdInput.focus();
                });
            },
            submitSaveWithPassword() {
                if (!this.adminPassword) {
                    this.adminPasswordError = 'Please enter your password to continue.';
                    return;
                }
                const hiddenInput = document.getElementById('confirmed_admin_password');
                if (hiddenInput) {
                    hiddenInput.value = this.adminPassword;
                }
                document.getElementById('edit-user-form').submit();
            }
        }"
    >
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

        {{-- Main User Details Form --}}
        <form id="edit-user-form" method="POST" action="{{ route('account.users.update', $user) }}" class="card card-glass project-form">
            @csrf
            @method('PUT')

            {{-- Hidden input populated when admin verifies their password in modal --}}
            <input type="hidden" name="admin_password" id="confirmed_admin_password" value="">

            <div class="card-header">User Details</div>
            <div class="card-body">
                {{-- Employee Number & Account Number (Read-only) --}}
                <div class="personnel-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group-premium">
                        <label for="employee_number" class="form-label">Employee Number</label>
                        <input
                            type="text"
                            id="employee_number"
                            class="form-control font-mono"
                            value="{{ $user->employee_number ?? 'N/A' }}"
                            disabled
                            style="background: var(--color-surface-alt); font-weight: 600;"
                        >
                    </div>

                    <div class="form-group-premium">
                        <label for="account_number" class="form-label">7-Digit Account Number</label>
                        <input
                            type="text"
                            id="account_number"
                            class="form-control font-mono"
                            value="{{ $user->account_number ?? 'N/A' }}"
                            disabled
                            style="background: var(--color-surface-alt); font-weight: 600;"
                        >
                    </div>
                </div>

                {{-- Name Grid --}}
                <div class="personnel-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group-premium">
                        <label for="first_name" class="form-label">First Name <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $user->first_name ?: $user->name) }}" required placeholder="ex. Juan">
                        @error('first_name')<div class="form-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group-premium">
                        <label for="middle_name" class="form-label">Middle Name <span class="text-muted" style="font-weight: 400;">(optional)</span></label>
                        <input type="text" id="middle_name" name="middle_name" class="form-control @error('middle_name') is-invalid @enderror" value="{{ old('middle_name', $user->middle_name) }}" placeholder="ex. David">
                        @error('middle_name')<div class="form-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group-premium">
                        <label for="last_name" class="form-label">Last Name <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $user->last_name) }}" required placeholder="ex. Dela Cruz">
                        @error('last_name')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>

                {{-- Birthdate & Email --}}
                <div class="personnel-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                    <div class="form-group-premium">
                        <label for="birthdate" class="form-label">Birthdate</label>
                        <input type="date" id="birthdate" name="birthdate" class="form-control @error('birthdate') is-invalid @enderror" value="{{ old('birthdate', $user->birthdate ? $user->birthdate->format('Y-m-d') : '') }}" max="{{ now()->subYears(18)->format('Y-m-d') }}">
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

            <div class="card-footer">
                <div class="form-actions" style="display: flex; gap: 0.75rem;">
                    <button type="button" class="btn btn-primary" @click="triggerSave()">Save Changes</button>
                    <a href="{{ route('account.settings') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>

        {{-- Security / Password Management Section (Dedicated Action Buttons) --}}
        <section class="card card-glass mt-8" style="margin-top: 2rem;">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2 style="font-size: 1rem; margin: 0; font-weight: 600;">Security & Credentials</h2>
                    <p class="text-muted" style="font-size: 0.8125rem; margin-top: 0.2rem; margin-bottom: 0;">
                        Generate or send a randomized temporary password for <strong>{{ $user->name }}</strong>.
                    </p>
                </div>
                @if($user->must_change_password)
                    <span class="status-pill status-pill--pending" title="User is required to rotate their password upon next sign-in">Pending Password Change</span>
                @else
                    <span class="status-pill status-pill--completed">Active Password</span>
                @endif
            </div>

            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                    {{-- Action Card 1: Send Temporary Password to User Email --}}
                    <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                <strong style="font-size: 0.9375rem;">Send Temporary Password</strong>
                            </div>
                            <p class="text-muted" style="font-size: 0.8125rem; line-height: 1.45; margin-bottom: 1rem;">
                                Automatically generates an 8-character randomized password and sends it directly to <strong>{{ $user->email }}</strong>. The user will be required to change it on their next login.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm"
                            style="width: fit-content;"
                            @click="sendTempModalOpen = true"
                        >
                            <span>Send Temporary Password</span>
                        </button>
                    </div>

                    {{-- Action Card 2: Generate & View Temporary Password --}}
                    <div style="background: var(--color-surface-alt); border: 1px solid var(--color-border); border-radius: var(--radius-md); padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                <strong style="font-size: 0.9375rem;">Generate Temporary Password</strong>
                            </div>
                            <p class="text-muted" style="font-size: 0.8125rem; line-height: 1.45; margin-bottom: 1rem;">
                                Generates a randomized temporary password that you can view and copy immediately on-screen to provide directly to the employee.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm"
                            style="width: fit-content;"
                            @click="generateTempModalOpen = true"
                        >
                            <span>Generate & View Password</span>
                        </button>
                    </div>
                </div>

                {{-- Display generated password banner if set from session --}}
                <template x-if="generatedPassword">
                    <div style="margin-top: 1.25rem; padding: 1rem 1.25rem; background: var(--color-surface-alt); border: 1px solid var(--color-accent); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                        <div>
                            <span class="text-muted" style="font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; display: block; margin-bottom: 0.2rem;">Generated Temporary Password</span>
                            <span class="font-mono" style="font-size: 1.15rem; font-weight: 700; letter-spacing: 1px; color: var(--color-ink);" x-text="generatedPassword"></span>
                        </div>
                        <button
                            type="button"
                            class="btn btn-primary btn-sm"
                            @click="copyPassword()"
                        >
                            <span x-text="copied ? '✓ Copied!' : 'Copy Password'"></span>
                        </button>
                    </div>
                </template>
            </div>
        </section>

        {{-- ─── Modal 1: Confirm Details Update (Requires Admin Password) ─── --}}
        <div
            class="reassign-modal-overlay"
            x-show="confirmModalOpen"
            x-cloak
            @keydown.escape.window="confirmModalOpen = false"
            style="display: none;"
        >
            <div class="reassign-modal-backdrop" @click="confirmModalOpen = false"></div>
            <div class="reassign-modal-content card" style="max-width: 440px;">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 600; font-size: 0.95rem;">Confirm Account Changes</span>
                    <button type="button" class="flash-close" @click="confirmModalOpen = false" aria-label="Close modal">&times;</button>
                </div>
                <div class="card-body">
                    <p class="text-muted" style="font-size: 0.8125rem; line-height: 1.5; margin-bottom: 1rem;">
                        For security purposes, please enter your administrator password to confirm the updates for <strong>{{ $user->name }}</strong>.
                    </p>

                    <div class="form-group-premium mb-0">
                        <label for="admin-save-password" class="form-label" style="font-size: 0.8125rem; font-weight: 600;">
                            Your Administrator Password <span class="required" aria-hidden="true">*</span>
                        </label>
                        <input
                            type="password"
                            id="admin-save-password"
                            class="form-control"
                            x-model="adminPassword"
                            @keydown.enter.prevent="submitSaveWithPassword()"
                            placeholder="Enter your account password"
                            required
                        >
                        <template x-if="adminPasswordError">
                            <div class="form-error" style="margin-top: 0.35rem;" x-text="adminPasswordError"></div>
                        </template>
                    </div>
                </div>
                <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <button type="button" class="btn btn-secondary btn-sm" @click="confirmModalOpen = false">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" @click="submitSaveWithPassword()">Confirm & Save</button>
                </div>
            </div>
        </div>

        {{-- ─── Modal 2: Send Temporary Password via Email (Requires Admin Password) ─── --}}
        <div
            class="reassign-modal-overlay"
            x-show="sendTempModalOpen"
            x-cloak
            @keydown.escape.window="sendTempModalOpen = false"
            style="display: none;"
        >
            <div class="reassign-modal-backdrop" @click="sendTempModalOpen = false"></div>
            <div class="reassign-modal-content card" style="max-width: 440px;">
                <form method="POST" action="{{ route('account.users.send-temporary-password', $user) }}">
                    @csrf
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 600; font-size: 0.95rem;">Send Temporary Password</span>
                        <button type="button" class="flash-close" @click="sendTempModalOpen = false" aria-label="Close modal">&times;</button>
                    </div>
                    <div class="card-body">
                        <p class="text-muted" style="font-size: 0.8125rem; line-height: 1.5; margin-bottom: 1rem;">
                            A new 8-character randomized temporary password will be dispatched to <strong>{{ $user->email }}</strong>. Please enter your administrator password to proceed:
                        </p>

                        <div class="form-group-premium mb-0">
                            <label for="admin_pwd_send" class="form-label" style="font-size: 0.8125rem; font-weight: 600;">
                                Your Administrator Password <span class="required" aria-hidden="true">*</span>
                            </label>
                            <input
                                type="password"
                                id="admin_pwd_send"
                                name="admin_password"
                                class="form-control"
                                placeholder="Enter your account password"
                                required
                                autofocus
                            >
                        </div>
                    </div>
                    <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                        <button type="button" class="btn btn-secondary btn-sm" @click="sendTempModalOpen = false">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Send to User</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ─── Modal 3: Generate Temporary Password on Screen (Requires Admin Password) ─── --}}
        <div
            class="reassign-modal-overlay"
            x-show="generateTempModalOpen"
            x-cloak
            @keydown.escape.window="generateTempModalOpen = false"
            style="display: none;"
        >
            <div class="reassign-modal-backdrop" @click="generateTempModalOpen = false"></div>
            <div class="reassign-modal-content card" style="max-width: 440px;">
                <form method="POST" action="{{ route('account.users.generate-temporary-password', $user) }}">
                    @csrf
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 600; font-size: 0.95rem;">Generate Temporary Password</span>
                        <button type="button" class="flash-close" @click="generateTempModalOpen = false" aria-label="Close modal">&times;</button>
                    </div>
                    <div class="card-body">
                        <p class="text-muted" style="font-size: 0.8125rem; line-height: 1.5; margin-bottom: 1rem;">
                            Enter your administrator password to generate an 8-character temporary password that you can view and copy immediately:
                        </p>

                        <div class="form-group-premium mb-0">
                            <label for="admin_pwd_gen" class="form-label" style="font-size: 0.8125rem; font-weight: 600;">
                                Your Administrator Password <span class="required" aria-hidden="true">*</span>
                            </label>
                            <input
                                type="password"
                                id="admin_pwd_gen"
                                name="admin_password"
                                class="form-control"
                                placeholder="Enter your account password"
                                required
                                autofocus
                            >
                        </div>
                    </div>
                    <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                        <button type="button" class="btn btn-secondary btn-sm" @click="generateTempModalOpen = false">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Generate Password</button>
                    </div>
                </form>
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
            } else {
                customGroup.style.display = 'none';
                customInput.removeAttribute('required');
            }
        }
    </script>
@endsection
