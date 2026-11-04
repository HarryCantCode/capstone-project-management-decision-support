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

        <div class="card-header" style="border-top: 1px solid var(--color-border);">Security</div>
        <div class="card-body">
            <p class="text-muted" style="margin-bottom: 1rem;">Leave password fields blank if you do not wish to change the password.</p>
            <div class="form-group-premium">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                    <label for="password" class="form-label" style="margin-bottom: 0;">New Password</label>
                    <button type="button" onclick="togglePasswordVisibility('password', this)" style="background: none; border: none; font-size: 0.75rem; color: var(--color-accent, #2563eb); cursor: pointer; padding: 0; font-weight: 500;" aria-label="Toggle password visibility">
                        Show
                    </button>
                </div>
                <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" minlength="8" oninput="checkPasswordRules(this.value)">
                @error('password')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group-premium">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                    <label for="password_confirmation" class="form-label" style="margin-bottom: 0;">Confirm New Password</label>
                    <button type="button" onclick="togglePasswordVisibility('password_confirmation', this)" style="background: none; border: none; font-size: 0.75rem; color: var(--color-accent, #2563eb); cursor: pointer; padding: 0; font-weight: 500;" aria-label="Toggle confirm password visibility">
                        Show
                    </button>
                </div>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" minlength="8">
            </div>

            {{-- Password Requirements Checklist Card --}}
            <div class="password-requirements-card" style="background: rgba(15, 23, 42, 0.03); border: 1px solid var(--color-border, #e2e8f0); border-radius: var(--radius-md, 8px); padding: 0.875rem 1rem; margin-top: 1rem; margin-bottom: 0.5rem;">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted, #64748b); margin-bottom: 0.5rem;">
                    Password Format Requirements:
                </div>
                <ul style="list-style: none; padding-left: 0; margin-bottom: 0; font-size: 0.8125rem; display: flex; flex-direction: column; gap: 0.35rem;">
                    <li id="req-len" style="display: flex; align-items: center; gap: 0.5rem; color: var(--color-text-muted, #64748b);">
                        <span class="req-indicator" style="font-size: 0.75rem; width: 14px; text-align: center;">&bull;</span>
                        <span>At least <strong>8 characters</strong> long</span>
                    </li>
                    <li id="req-cap" style="display: flex; align-items: center; gap: 0.5rem; color: var(--color-text-muted, #64748b);">
                        <span class="req-indicator" style="font-size: 0.75rem; width: 14px; text-align: center;">&bull;</span>
                        <span>At least <strong>1 capital letter</strong> (A&ndash;Z)</span>
                    </li>
                    <li id="req-num" style="display: flex; align-items: center; gap: 0.5rem; color: var(--color-text-muted, #64748b);">
                        <span class="req-indicator" style="font-size: 0.75rem; width: 14px; text-align: center;">&bull;</span>
                        <span>At least <strong>1 number</strong> (0&ndash;9)</span>
                    </li>
                    <li id="req-sym" style="display: flex; align-items: center; gap: 0.5rem; color: var(--color-text-muted, #64748b);">
                        <span class="req-indicator" style="font-size: 0.75rem; width: 14px; text-align: center;">&bull;</span>
                        <span>Mix of <strong>symbols</strong> (!@#$%^&amp;*...)</span>
                    </li>
                    <li id="req-personal" style="display: flex; align-items: center; gap: 0.5rem; color: var(--color-text-muted, #64748b);">
                        <span class="req-indicator" style="font-size: 0.75rem; width: 14px; text-align: center;">&bull;</span>
                        <span>Cannot contain user's <strong>name</strong> or <strong>birthdate</strong></span>
                    </li>
                </ul>
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
        @php
            $rawTokens = [
                mb_strtolower(trim($user->first_name ?? '')),
                mb_strtolower(trim($user->middle_name ?? '')),
                mb_strtolower(trim($user->last_name ?? '')),
            ];
            if (!empty($user->name)) {
                $rawTokens = array_merge($rawTokens, preg_split('/[\s,\.\-_]+/', mb_strtolower(trim($user->name))));
            }
            $filteredNameTokens = array_values(array_filter(array_unique($rawTokens), fn($t) => mb_strlen($t) >= 3));

            $dobDigits = [];
            if (!empty($user->birthdate)) {
                $bDate = is_string($user->birthdate) ? \Carbon\Carbon::parse($user->birthdate) : $user->birthdate;
                $dobDigits = array_unique([
                    $bDate->format('Ymd'),
                    $bDate->format('mdY'),
                    $bDate->format('dmY'),
                    $bDate->format('mdy'),
                    $bDate->format('dmy'),
                    $bDate->format('Y-m-d'),
                    $bDate->format('m/d/Y'),
                    $bDate->format('d/m/Y'),
                ]);
            }
        @endphp

        const userPersonalTokens = @json($filteredNameTokens);
        const userDobPatterns = @json(array_values($dobDigits));

        function togglePasswordVisibility(fieldId, btn) {
            const input = document.getElementById(fieldId);
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = 'Hide';
            } else {
                input.type = 'password';
                btn.textContent = 'Show';
            }
        }

        function checkPasswordRules(password) {
            if (!password || password.length === 0) {
                ['req-len', 'req-cap', 'req-num', 'req-sym', 'req-personal'].forEach(id => updateRuleUI(id, false));
                return;
            }

            const lenOk = password.length >= 8;
            const capOk = /[A-Z]/.test(password);
            const numOk = /[0-9]/.test(password);
            const symOk = /[\W_]/.test(password);

            // Dynamically also check against current values typed in first_name, middle_name, last_name, birthdate inputs if changed
            const fNameInput = document.getElementById('first_name');
            const mNameInput = document.getElementById('middle_name');
            const lNameInput = document.getElementById('last_name');
            const bDateInput = document.getElementById('birthdate');

            const currentTokens = [...userPersonalTokens];
            [fNameInput, mNameInput, lNameInput].forEach(inp => {
                if (inp && inp.value && inp.value.trim().length >= 3) {
                    currentTokens.push(inp.value.trim().toLowerCase());
                }
            });

            const currentDobPatterns = [...userDobPatterns];
            if (bDateInput && bDateInput.value) {
                const val = bDateInput.value;
                currentDobPatterns.push(val);
                currentDobPatterns.push(val.replace(/[^0-9]/g, ''));
            }

            const lower = password.toLowerCase();
            const numericOnly = password.replace(/[^0-9]/g, '');

            let containsName = false;
            for (const token of currentTokens) {
                if (token && lower.includes(token)) {
                    containsName = true;
                    break;
                }
            }

            let containsDob = false;
            for (const pattern of currentDobPatterns) {
                if (!pattern) continue;
                const patternNumeric = pattern.replace(/[^0-9]/g, '');
                if (lower.includes(pattern.toLowerCase()) || (patternNumeric.length >= 6 && numericOnly.includes(patternNumeric))) {
                    containsDob = true;
                    break;
                }
            }

            const personalOk = !containsName && !containsDob;

            updateRuleUI('req-len', lenOk);
            updateRuleUI('req-cap', capOk);
            updateRuleUI('req-num', numOk);
            updateRuleUI('req-sym', symOk);
            updateRuleUI('req-personal', personalOk);
        }

        function updateRuleUI(elementId, isMet) {
            const el = document.getElementById(elementId);
            if (!el) return;
            const indicator = el.querySelector('.req-indicator');
            if (isMet) {
                el.style.color = '#10b981'; // emerald green
                if (indicator) indicator.innerHTML = '&#10003;';
            } else {
                el.style.color = 'var(--color-text-muted, #64748b)';
                if (indicator) indicator.innerHTML = '&bull;';
            }
        }

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
