@extends('layouts.app')

@section('title', 'Account Settings')
@section('meta-description', 'Manage your Dex PMS account settings, credentials, and user directory.')

@section('content')
<div x-data="{
    credentialsModalOpen: @json($errors->has('current_password') || $errors->has('password') || $errors->has('email') || $errors->has('current_password_for_email') || $errors->has('otp')),
    activeCredTab: '{{ ($errors->has('email') || $errors->has('current_password_for_email')) ? 'email' : 'password' }}',
    
    // Password Form State
    pwdCurrent: '',
    pwdNew: '',
    pwdConfirm: '',
    pwdOtp: '',
    pwdOtpSent: false,
    pwdSendingOtp: false,
    pwdOtpError: '',
    pwdCooldown: 0,
    pwdCooldownTimer: null,

    // Email Form State
    emailNew: '{{ old('email', Auth::user()->email) }}',
    emailCurrentPwd: '',
    emailOtp: '',
    emailOtpSent: false,
    emailSendingOtp: false,
    emailOtpError: '',
    emailCooldown: 0,
    emailCooldownTimer: null,

    startPwdCooldown(seconds) {
        this.pwdCooldown = seconds;
        if (this.pwdCooldownTimer) clearInterval(this.pwdCooldownTimer);
        this.pwdCooldownTimer = setInterval(() => {
            this.pwdCooldown--;
            if (this.pwdCooldown <= 0) clearInterval(this.pwdCooldownTimer);
        }, 1000);
    },

    startEmailCooldown(seconds) {
        this.emailCooldown = seconds;
        if (this.emailCooldownTimer) clearInterval(this.emailCooldownTimer);
        this.emailCooldownTimer = setInterval(() => {
            this.emailCooldown--;
            if (this.emailCooldown <= 0) clearInterval(this.emailCooldownTimer);
        }, 1000);
    },

    formatTime(sec) {
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return `${m}:${s < 10 ? '0' : ''}${s}`;
    },

    // Validation helper
    canSendPwdOtp() {
        return this.pwdCurrent.trim().length > 0 &&
               this.pwdNew.trim().length >= 8 &&
               this.pwdConfirm.trim().length >= 8 &&
               this.pwdNew === this.pwdConfirm &&
               !this.pwdSendingOtp &&
               this.pwdCooldown <= 0;
    },

    canSendEmailOtp() {
        return this.emailNew.trim().length > 0 &&
               this.emailCurrentPwd.trim().length > 0 &&
               !this.emailSendingOtp &&
               this.emailCooldown <= 0;
    }
}">

    {{-- Page Header --}}
    <div class="page-header">
        <div>
            <h1>Account Settings</h1>
            <p class="page-header__description">Review your profile details, manage authentication credentials, and oversee user access.</p>
        </div>
        <div class="page-actions">
            <button
                type="button"
                class="btn btn-primary"
                @click="credentialsModalOpen = true"
                id="btn-open-credentials-modal"
            >
                <svg style="width: 16px; height: 16px; margin-right: 6px;" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Update Credentials
            </button>
        </div>
    </div>

    {{-- Section 1: My Profile Overview (Prominent & Clean Single Card) --}}
    <section class="card mb-6" aria-labelledby="profile-overview-heading">
        <div class="card-header">
            <div class="card-header__title-wrap">
                <svg class="header-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                </svg>
                <h2 id="profile-overview-heading">My Profile Overview</h2>
            </div>
        </div>
        <div class="card-body">
            <div class="profile-hero">
                <div class="profile-avatar" aria-hidden="true">
                    {{ strtoupper(substr(Auth::user()->first_name ?? Auth::user()->name, 0, 1)) }}{{ strtoupper(substr(Auth::user()->last_name ?? '', 0, 1)) }}
                </div>
                <div class="profile-hero__meta">
                    <div class="profile-hero__name">{{ Auth::user()->name }}</div>
                    <div class="profile-hero__sub">
                        @if(Auth::user()->job_title)
                            <span>{{ Auth::user()->job_title }}</span>
                            <span class="profile-hero__sep" aria-hidden="true">&bull;</span>
                        @endif
                        <span class="status-pill status-pill--pending">{{ Auth::user()->getRoleNames()->first() ?? 'User' }}</span>
                    </div>
                </div>
            </div>

            <div class="profile-details-grid">
                <div class="detail-item">
                    <span class="detail-label">Account Number</span>
                    <div class="detail-value">
                        <span class="font-mono detail-accent">{{ Auth::user()->account_number ?? 'N/A' }}</span>
                    </div>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Employee ID</span>
                    <div class="detail-value">
                        <span class="font-mono">{{ Auth::user()->employee_number ?? 'N/A' }}</span>
                    </div>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Email Address</span>
                    <div class="detail-value text-break">
                        {{ Auth::user()->email }}
                    </div>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Account Status</span>
                    <div class="detail-value">
                        <span class="status-pill status-pill--completed">Active</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Admin Only: User Management Directory (Active Accounts & Archived Accounts) --}}
    @role('Admin')
    <section
        class="card card-directory"
        aria-labelledby="users-heading"
        x-data="{
            accountTab: 'active',
            searchQuery: '',
            roleFilter: 'all',
            matchRow(accountNo, employeeId, name, email, jobTitle, role) {
                const q = this.searchQuery.toLowerCase().trim();
                const matchesRole = this.roleFilter === 'all' || role.toLowerCase() === this.roleFilter.toLowerCase();
                if (!matchesRole) return false;
                if (!q) return true;
                return (
                    (accountNo && accountNo.toLowerCase().includes(q)) ||
                    (employeeId && employeeId.toLowerCase().includes(q)) ||
                    (name && name.toLowerCase().includes(q)) ||
                    (email && email.toLowerCase().includes(q)) ||
                    (jobTitle && jobTitle.toLowerCase().includes(q)) ||
                    (role && role.toLowerCase().includes(q))
                );
            }
        }"
    >
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="card-header__title-wrap">
                    <svg class="header-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                    </svg>
                    <h2 id="users-heading" style="margin: 0; font-size: 1rem; font-weight: 700;">User Management</h2>
                </div>
                
                {{-- Panel Tabs --}}
                <div class="btn-group" role="group" aria-label="User category filter" style="display: inline-flex; border: 1px solid var(--color-border); border-radius: var(--radius-sm); overflow: hidden;">
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="accountTab === 'active' ? 'btn-primary' : 'btn-secondary'"
                        @click="accountTab = 'active'"
                        style="padding: 0.35rem 0.85rem; border: none; font-size: 0.8125rem;"
                    >
                        Active Accounts ({{ $activeUsers->count() }})
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="accountTab === 'archived' ? 'btn-primary' : 'btn-secondary'"
                        @click="accountTab = 'archived'"
                        style="padding: 0.35rem 0.85rem; border: none; font-size: 0.8125rem;"
                    >
                        Archived Accounts ({{ $archivedUsers->count() }})
                    </button>
                </div>
            </div>

            <a href="{{ route('account.users.create') }}" class="btn btn-primary btn-sm">+ Add User</a>
        </div>

        {{-- Filter & Search Toolbar --}}
        <div class="directory-toolbar">
            <div class="directory-search">
                <svg class="search-input-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                </svg>
                <input
                    type="search"
                    x-model="searchQuery"
                    class="form-control directory-search__input"
                    placeholder="Filter by name, email, account no., employee ID, or title..."
                    aria-label="Filter users"
                >
            </div>

            <div class="directory-filters">
                <label for="role-filter-select" class="sr-only">Filter by Role</label>
                <select id="role-filter-select" x-model="roleFilter" class="form-control directory-select">
                    <option value="all">All Roles</option>
                    <option value="Admin">Admin</option>
                    <option value="Project Manager">Project Manager</option>
                    <option value="Staff">Staff</option>
                </select>
            </div>
        </div>

        {{-- Tab 1: Active Accounts Panel --}}
        <div x-show="accountTab === 'active'">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 140px;">Account No.</th>
                            <th scope="col" style="width: 140px;">Employee ID</th>
                            <th scope="col">User</th>
                            <th scope="col">Job Title</th>
                            <th scope="col">System Role</th>
                            <th scope="col" class="text-right" style="width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($activeUsers as $u)
                            @php
                                $uRole = $u->getRoleNames()->first() ?? 'None';
                            @endphp
                            <tr
                                x-show="matchRow('{{ addslashes($u->account_number ?? '') }}', '{{ addslashes($u->employee_number ?? '') }}', '{{ addslashes($u->name) }}', '{{ addslashes($u->email) }}', '{{ addslashes($u->job_title ?? '') }}', '{{ addslashes($uRole) }}')"
                            >
                                <td>
                                    <span class="font-mono table-accent-text">{{ $u->account_number ?? 'N/A' }}</span>
                                </td>
                                <td>
                                    <span class="font-mono text-muted">{{ $u->employee_number ?? 'N/A' }}</span>
                                </td>
                                <td>
                                    <div class="user-row-identity">
                                        <div class="user-avatar-mini" aria-hidden="true">
                                            {{ strtoupper(substr($u->first_name ?? $u->name, 0, 1)) }}
                                        </div>
                                        <div class="user-row-meta">
                                            <div class="user-row-name">
                                                <strong>{{ $u->name }}</strong>
                                                @if (Auth::id() === $u->id)
                                                    <span class="pill-you">You</span>
                                                @endif
                                                @if ($u->isLocked())
                                                    <span class="status-pill status-pill--delayed" title="Account locked due to consecutive failed login attempts">
                                                        Locked ({{ $u->lockoutRemainingMinutes() }}m)
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="user-row-email">{{ $u->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted">{{ $u->job_title ?: '—' }}</span>
                                </td>
                                <td>
                                    @if($uRole === 'Admin')
                                        <span class="status-pill status-pill--completed">Admin</span>
                                    @elseif($uRole === 'Project Manager')
                                        <span class="status-pill status-pill--ongoing">Project Manager</span>
                                    @else
                                        <span class="status-pill status-pill--pending">{{ $uRole }}</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="action-buttons-wrap">
                                        @if ($u->isLocked())
                                            <form method="POST" action="{{ route('account.users.unlock', $u) }}" onsubmit="return confirm('Unlock and activate login access for {{ $u->name }}?');">
                                                @csrf
                                                <button type="submit" class="btn btn-secondary btn-sm action-btn--unlock" title="Unlock account">
                                                    Unlock
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('account.users.edit', $u) }}" class="btn btn-secondary btn-sm">Edit</a>
                                        @if (Auth::id() !== $u->id)
                                            <form method="POST" action="{{ route('account.users.destroy', $u) }}" onsubmit="return confirm('Archive user account {{ $u->name }}? The user will not be able to log in until reactivated.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-secondary btn-sm action-btn--danger">Archive</button>
                                            </form>
                                        @else
                                            <button type="button" class="btn btn-secondary btn-sm" disabled title="Cannot archive your own active account">Archive</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted empty-cell-state">
                                    No active user accounts found in the system.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tab 2: Archived Accounts Panel --}}
        <div x-show="accountTab === 'archived'" x-cloak>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 140px;">Account No.</th>
                            <th scope="col" style="width: 140px;">Employee ID</th>
                            <th scope="col">User</th>
                            <th scope="col">Job Title</th>
                            <th scope="col">System Role</th>
                            <th scope="col">Archived On</th>
                            <th scope="col" class="text-right" style="width: 140px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($archivedUsers as $u)
                            @php
                                $uRole = $u->getRoleNames()->first() ?? 'None';
                            @endphp
                            <tr
                                class="tr--archived"
                                x-show="matchRow('{{ addslashes($u->account_number ?? '') }}', '{{ addslashes($u->employee_number ?? '') }}', '{{ addslashes($u->name) }}', '{{ addslashes($u->email) }}', '{{ addslashes($u->job_title ?? '') }}', '{{ addslashes($uRole) }}')"
                            >
                                <td>
                                    <span class="font-mono text-muted">{{ $u->account_number ?? 'N/A' }}</span>
                                </td>
                                <td>
                                    <span class="font-mono text-muted">{{ $u->employee_number ?? 'N/A' }}</span>
                                </td>
                                <td>
                                    <div class="user-row-identity">
                                        <div class="user-avatar-mini user-avatar-mini--archived" aria-hidden="true">
                                            {{ strtoupper(substr($u->first_name ?? $u->name, 0, 1)) }}
                                        </div>
                                        <div class="user-row-meta">
                                            <div class="user-row-name">
                                                <strong>{{ $u->name }}</strong>
                                                <span class="status-pill status-pill--pending">Archived</span>
                                            </div>
                                            <span class="user-row-email">{{ $u->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-muted">{{ $u->job_title ?: '—' }}</span>
                                </td>
                                <td>
                                    <span class="status-pill status-pill--pending">{{ $uRole }}</span>
                                </td>
                                <td>
                                    <span class="font-mono text-muted cell-sm-text">
                                        {{ $u->deleted_at ? $u->deleted_at->format('M d, Y · H:i') : 'N/A' }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <div class="action-buttons-wrap">
                                        <form method="POST" action="{{ route('account.users.restore', $u->id) }}" onsubmit="return confirm('Restore and reactivate {{ $u->name }}? The user will regain system access immediately.');">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                Reactivate
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted empty-cell-state">
                                    No archived accounts. All user accounts are currently active.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    @endrole

    {{-- Update Credentials Modal Pop-up --}}
    <div
        class="reassign-modal-overlay"
        x-show="credentialsModalOpen"
        x-cloak
        @keydown.escape.window="credentialsModalOpen = false"
        style="display: none;"
    >
        {{-- Modal Backdrop --}}
        <div
            class="reassign-modal-backdrop"
            @click="credentialsModalOpen = false"
            x-show="credentialsModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        ></div>

        {{-- Modal Content Card --}}
        <div
            class="reassign-modal-content card"
            x-show="credentialsModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-95"
            style="max-width: 520px; max-height: 90vh; overflow-y: auto;"
        >
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div class="card-header__title-wrap">
                    <svg class="header-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <h2 style="font-size: 0.95rem; margin: 0; font-weight: 600;">Update Credentials</h2>
                </div>
                <button
                    type="button"
                    class="flash-close"
                    @click="credentialsModalOpen = false"
                    aria-label="Close modal"
                >
                    &times;
                </button>
            </div>

            {{-- Modal Credential Switch (matching exact unified button group style) --}}
            <div style="padding: var(--space-4) var(--space-6) 0;">
                <div class="btn-group" role="group" aria-label="Credentials Tab" style="display: flex; width: 100%; border: 1px solid var(--color-border); border-radius: var(--radius-sm); overflow: hidden;">
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="activeCredTab === 'password' ? 'btn-primary' : 'btn-secondary'"
                        @click="activeCredTab = 'password'"
                        style="flex: 1; padding: 0.35rem 0.85rem; border: none; font-size: 0.8125rem;"
                    >
                        Change Password
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="activeCredTab === 'email' ? 'btn-primary' : 'btn-secondary'"
                        @click="activeCredTab = 'email'"
                        style="flex: 1; padding: 0.35rem 0.85rem; border: none; font-size: 0.8125rem;"
                    >
                        Update Email
                    </button>
                </div>
            </div>

            <div class="card-body">
                {{-- Form 1: Change Password --}}
                <div x-show="activeCredTab === 'password'">
                    <form method="POST" action="{{ route('account.profile.password') }}">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label for="modal_current_password" class="form-label">Current Password <span class="required" aria-hidden="true">*</span></label>
                            <input
                                type="password"
                                id="modal_current_password"
                                name="current_password"
                                x-model="pwdCurrent"
                                class="form-control @error('current_password') is-invalid @enderror"
                                required
                                autocomplete="current-password"
                                placeholder="Enter current password"
                            >
                            @error('current_password')
                                <div class="form-error" role="alert">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <div class="label-with-action">
                                <label for="modal_password" class="form-label">New Password <span class="required" aria-hidden="true">*</span></label>
                                <button
                                    type="button"
                                    class="text-action-link"
                                    onclick="togglePasswordVisibility('modal_password', this)"
                                    aria-label="Toggle new password visibility"
                                >Show</button>
                            </div>
                            <input
                                type="password"
                                id="modal_password"
                                name="password"
                                x-model="pwdNew"
                                class="form-control @error('password') is-invalid @enderror"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Minimum 8 characters"
                                oninput="checkSelfPasswordRules(this.value)"
                            >
                            @error('password')
                                <div class="form-error" role="alert">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <div class="label-with-action">
                                <label for="modal_password_confirmation" class="form-label">Confirm New Password <span class="required" aria-hidden="true">*</span></label>
                                <button
                                    type="button"
                                    class="text-action-link"
                                    onclick="togglePasswordVisibility('modal_password_confirmation', this)"
                                    aria-label="Toggle confirm password visibility"
                                >Show</button>
                            </div>
                            <input
                                type="password"
                                id="modal_password_confirmation"
                                name="password_confirmation"
                                x-model="pwdConfirm"
                                class="form-control"
                                required
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Repeat new password"
                            >
                        </div>

                        {{-- OTP Verification Box --}}
                        <div class="otp-verification-box">
                            <div class="otp-verification-box__header">
                                <label for="modal_pwd_otp" class="form-label mb-0">Email Verification Code <span class="required" aria-hidden="true">*</span></label>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm"
                                    :disabled="!canSendPwdOtp()"
                                    :title="!canSendPwdOtp() ? 'Please fill in current, new, and confirm passwords first.' : ''"
                                    @click="
                                        pwdSendingOtp = true;
                                        pwdOtpError = '';
                                        fetch('{{ route('account.profile.send-otp') }}', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                'Accept': 'application/json'
                                            },
                                            body: JSON.stringify({ action: 'change_password' })
                                        })
                                        .then(res => res.json())
                                        .then(data => {
                                            pwdSendingOtp = false;
                                            if (data.success) {
                                                pwdOtpSent = true;
                                                if (data.remaining_seconds && data.remaining_seconds > 0) {
                                                    startPwdCooldown(data.remaining_seconds);
                                                }
                                            } else {
                                                pwdOtpError = data.message || 'Error sending code';
                                                if (data.remaining_seconds && data.remaining_seconds > 0) {
                                                    startPwdCooldown(data.remaining_seconds);
                                                }
                                            }
                                        })
                                        .catch(() => {
                                            pwdSendingOtp = false;
                                            pwdOtpError = 'Network error sending OTP';
                                        });
                                    "
                                >
                                    <span x-show="!pwdSendingOtp && pwdCooldown <= 0 && !pwdOtpSent">Send OTP</span>
                                    <span x-show="!pwdSendingOtp && pwdCooldown <= 0 && pwdOtpSent">Resend OTP</span>
                                    <span x-show="pwdSendingOtp">Sending...</span>
                                    <span x-show="pwdCooldown > 0" x-text="`Wait ${formatTime(pwdCooldown)}`"></span>
                                </button>
                            </div>
                            <input
                                type="text"
                                id="modal_pwd_otp"
                                name="otp"
                                x-model="pwdOtp"
                                class="form-control font-mono @error('otp') is-invalid @enderror"
                                placeholder="6-digit code"
                                maxlength="6"
                                pattern="[0-9]{6}"
                                inputmode="numeric"
                                required
                            >
                            <div class="otp-hint" x-show="!canSendPwdOtp() && !pwdOtpSent" style="color: var(--color-muted);">
                                Fill in current password, new password, and matching confirmation to enable "Send OTP".
                            </div>
                            <div class="otp-hint" x-show="canSendPwdOtp() || pwdOtpSent">
                                A 6-digit one-time code will be dispatched to {{ Auth::user()->email }}. Valid for 3 minutes.
                            </div>
                            <div x-show="pwdOtpSent" x-cloak class="otp-success-msg">
                                <span class="otp-check-icon">&check;</span> Code sent successfully to {{ Auth::user()->email }}
                            </div>
                            <div x-show="pwdOtpError" x-cloak x-text="pwdOtpError" class="form-error"></div>
                            @error('otp')
                                <div class="form-error" role="alert">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Password Requirements Checklist --}}
                        <div class="password-requirements-card">
                            <div class="password-requirements-card__title">Security Checklist</div>
                            <ul class="password-requirements-list">
                                <li id="self-req-len">
                                    <span class="req-indicator" aria-hidden="true">&bull;</span>
                                    <span>At least <strong>8 characters</strong></span>
                                </li>
                                <li id="self-req-cap">
                                    <span class="req-indicator" aria-hidden="true">&bull;</span>
                                    <span>At least <strong>1 uppercase letter</strong> (A&ndash;Z)</span>
                                </li>
                                <li id="self-req-num">
                                    <span class="req-indicator" aria-hidden="true">&bull;</span>
                                    <span>At least <strong>1 number</strong> (0&ndash;9)</span>
                                </li>
                                <li id="self-req-sym">
                                    <span class="req-indicator" aria-hidden="true">&bull;</span>
                                    <span>Mix of <strong>symbols</strong> (!@#$%^&amp;*)</span>
                                </li>
                                <li id="self-req-personal">
                                    <span class="req-indicator" aria-hidden="true">&bull;</span>
                                    <span>No <strong>name</strong> or <strong>birthdate</strong> pattern</span>
                                </li>
                            </ul>
                        </div>

                        <div class="form-actions-inline" style="display: flex; justify-content: flex-end; gap: 8px;">
                            <button type="button" class="btn btn-secondary" @click="credentialsModalOpen = false">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>

                {{-- Form 2: Update Email --}}
                <div x-show="activeCredTab === 'email'" x-cloak>
                    <form method="POST" action="{{ route('account.profile.email') }}">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label for="modal_new_email" class="form-label">New Email Address <span class="required" aria-hidden="true">*</span></label>
                            <input
                                type="email"
                                id="modal_new_email"
                                name="email"
                                x-model="emailNew"
                                class="form-control @error('email') is-invalid @enderror"
                                required
                                autocomplete="email"
                                placeholder="name@dexinternational.com"
                            >
                            @error('email')
                                <div class="form-error" role="alert">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="modal_current_password_for_email" class="form-label">Current Password <span class="required" aria-hidden="true">*</span></label>
                            <input
                                type="password"
                                id="modal_current_password_for_email"
                                name="current_password_for_email"
                                x-model="emailCurrentPwd"
                                class="form-control @error('current_password_for_email') is-invalid @enderror"
                                required
                                autocomplete="current-password"
                                placeholder="Verify with current password"
                            >
                            @error('current_password_for_email')
                                <div class="form-error" role="alert">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- OTP Verification Box --}}
                        <div class="otp-verification-box">
                            <div class="otp-verification-box__header">
                                <label for="modal_email_otp" class="form-label mb-0">Email Verification Code <span class="required" aria-hidden="true">*</span></label>
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm"
                                    :disabled="!canSendEmailOtp()"
                                    :title="!canSendEmailOtp() ? 'Please fill in new email and current password first.' : ''"
                                    @click="
                                        emailSendingOtp = true;
                                        emailOtpError = '';
                                        fetch('{{ route('account.profile.send-otp') }}', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                'Accept': 'application/json'
                                            },
                                            body: JSON.stringify({ action: 'change_email' })
                                        })
                                        .then(res => res.json())
                                        .then(data => {
                                            emailSendingOtp = false;
                                            if (data.success) {
                                                emailOtpSent = true;
                                                if (data.remaining_seconds && data.remaining_seconds > 0) {
                                                    startEmailCooldown(data.remaining_seconds);
                                                }
                                            } else {
                                                emailOtpError = data.message || 'Error sending code';
                                                if (data.remaining_seconds && data.remaining_seconds > 0) {
                                                    startEmailCooldown(data.remaining_seconds);
                                                }
                                            }
                                        })
                                        .catch(() => {
                                            emailSendingOtp = false;
                                            emailOtpError = 'Network error sending OTP';
                                        });
                                    "
                                >
                                    <span x-show="!emailSendingOtp && emailCooldown <= 0 && !emailOtpSent">Send OTP</span>
                                    <span x-show="!emailSendingOtp && emailCooldown <= 0 && emailOtpSent">Resend OTP</span>
                                    <span x-show="emailSendingOtp">Sending...</span>
                                    <span x-show="emailCooldown > 0" x-text="`Wait ${formatTime(emailCooldown)}`"></span>
                                </button>
                            </div>
                            <input
                                type="text"
                                id="modal_email_otp"
                                name="otp"
                                x-model="emailOtp"
                                class="form-control font-mono @error('otp') is-invalid @enderror"
                                placeholder="6-digit code"
                                maxlength="6"
                                pattern="[0-9]{6}"
                                inputmode="numeric"
                                required
                            >
                            <div class="otp-hint" x-show="!canSendEmailOtp() && !emailOtpSent" style="color: var(--color-muted);">
                                Fill in new email and current password to enable "Send OTP".
                            </div>
                            <div class="otp-hint" x-show="canSendEmailOtp() || emailOtpSent">
                                A 6-digit one-time code will be dispatched to your current email address ({{ Auth::user()->email }}). Valid for 3 minutes.
                            </div>
                            <div x-show="emailOtpSent" x-cloak class="otp-success-msg">
                                <span class="otp-check-icon">&check;</span> Code sent successfully to {{ Auth::user()->email }}
                            </div>
                            <div x-show="emailOtpError" x-cloak x-text="emailOtpError" class="form-error"></div>
                            @error('otp')
                                <div class="form-error" role="alert">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-actions-inline" style="display: flex; justify-content: flex-end; gap: 8px;">
                            <button type="button" class="btn btn-secondary" @click="credentialsModalOpen = false">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Email Address</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Interactive Logic for Password Requirements Validation --}}
<script>
    @php
        $authUser = Auth::user();
        $authRawTokens = [
            mb_strtolower(trim($authUser->first_name ?? '')),
            mb_strtolower(trim($authUser->middle_name ?? '')),
            mb_strtolower(trim($authUser->last_name ?? '')),
        ];
        if (!empty($authUser->name)) {
            $authRawTokens = array_merge($authRawTokens, preg_split('/[\s,\.\-_]+/', mb_strtolower(trim($authUser->name))));
        }
        $authNameTokens = array_values(array_filter(array_unique($authRawTokens), fn($t) => mb_strlen($t) >= 3));

        $authDobDigits = [];
        if (!empty($authUser->birthdate)) {
            $bDate = is_string($authUser->birthdate) ? \Carbon\Carbon::parse($authUser->birthdate) : $authUser->birthdate;
            $authDobDigits = array_unique([
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

    const authPersonalTokens = @json($authNameTokens);
    const authDobPatterns = @json(array_values($authDobDigits));

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

    function checkSelfPasswordRules(password) {
        if (!password || password.length === 0) {
            ['self-req-len', 'self-req-cap', 'self-req-num', 'self-req-sym', 'self-req-personal'].forEach(id => updateSelfRuleUI(id, false));
            return;
        }

        const lenOk = password.length >= 8;
        const capOk = /[A-Z]/.test(password);
        const numOk = /[0-9]/.test(password);
        const symOk = /[\W_]/.test(password);

        const lower = password.toLowerCase();
        const numericOnly = password.replace(/[^0-9]/g, '');

        let containsName = false;
        for (const token of authPersonalTokens) {
            if (token && lower.includes(token)) {
                containsName = true;
                break;
            }
        }

        let containsDob = false;
        for (const pattern of authDobPatterns) {
            if (!pattern) continue;
            const patternNumeric = pattern.replace(/[^0-9]/g, '');
            if (lower.includes(pattern.toLowerCase()) || (patternNumeric.length >= 6 && numericOnly.includes(patternNumeric))) {
                containsDob = true;
                break;
            }
        }

        const personalOk = !containsName && !containsDob;

        updateSelfRuleUI('self-req-len', lenOk);
        updateSelfRuleUI('self-req-cap', capOk);
        updateSelfRuleUI('self-req-num', numOk);
        updateSelfRuleUI('self-req-sym', symOk);
        updateSelfRuleUI('self-req-personal', personalOk);
    }

    function updateSelfRuleUI(elementId, isMet) {
        const el = document.getElementById(elementId);
        if (!el) return;
        const indicator = el.querySelector('.req-indicator');
        if (isMet) {
            el.classList.add('req--met');
            if (indicator) indicator.innerHTML = '&check;';
        } else {
            el.classList.remove('req--met');
            if (indicator) indicator.innerHTML = '&bull;';
        }
    }
</script>
@endsection

@push('styles')
<style>
/* ==========================================================================
   Account Settings Revamp Styles — Precision Minimalist Technical UI
   ========================================================================== */

[x-cloak] { display: none !important; }

.mb-6 { margin-bottom: var(--space-6, 1.5rem); }

/* Card Header Polish */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: var(--space-4, 1rem);
    padding: var(--space-4, 1rem) var(--space-6, 1.5rem);
}

.card-header__title-wrap {
    display: flex;
    align-items: center;
    gap: var(--space-2, 0.5rem);
}

.card-header__title-wrap h2 {
    font-size: 0.9375rem;
    font-weight: 600;
    color: var(--color-ink, #1B1E23);
    letter-spacing: -0.01em;
    margin: 0;
}

.header-icon {
    width: 18px;
    height: 18px;
    color: var(--color-accent, #2C5F7C);
    flex-shrink: 0;
}

/* Profile Hero */
.profile-hero {
    display: flex;
    align-items: center;
    gap: var(--space-4, 1rem);
    padding-bottom: var(--space-5, 1.25rem);
    border-bottom: 1px solid var(--color-border, #DDE1E7);
    margin-bottom: var(--space-5, 1.25rem);
}

.profile-avatar {
    width: 52px;
    height: 52px;
    border-radius: var(--radius-md, 6px);
    background: var(--color-accent, #2C5F7C);
    color: #FFFFFF;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.125rem;
    letter-spacing: 0.05em;
    flex-shrink: 0;
}

.profile-hero__name {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--color-ink, #1B1E23);
    line-height: 1.3;
}

.profile-hero__sub {
    font-size: 0.8125rem;
    color: var(--color-muted, #6B7280);
    margin-top: 3px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.profile-hero__sep {
    color: var(--color-border, #DDE1E7);
}

/* Profile Details Grid */
.profile-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: var(--space-5, 1.25rem) var(--space-6, 1.5rem);
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-label {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--color-muted, #6B7280);
}

.detail-value {
    font-size: 0.9375rem;
    color: var(--color-ink, #1B1E23);
    word-break: break-word;
}

.detail-accent {
    color: var(--color-accent, #2C5F7C);
    font-weight: 700;
}

/* Form Helper Elements */
.label-with-action {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-1, 0.25rem);
}

.label-with-action .form-label {
    margin-bottom: 0;
}

.text-action-link {
    background: transparent;
    border: none;
    font-size: 0.75rem;
    font-weight: 500;
    color: var(--color-accent, #2C5F7C);
    cursor: pointer;
    padding: 0;
}

.text-action-link:hover {
    text-decoration: underline;
}

.text-action-link:focus-visible {
    outline: 2px solid var(--color-accent, #2C5F7C);
    outline-offset: 2px;
}

/* OTP Verification Box */
.otp-verification-box {
    background: var(--color-surface-alt, #F0F2F5);
    border: 1px dashed var(--color-border, #DDE1E7);
    border-radius: var(--radius-md, 6px);
    padding: var(--space-4, 1rem);
    margin: var(--space-4, 1rem) 0;
}

.otp-verification-box__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-2, 0.5rem);
    gap: var(--space-2, 0.5rem);
}

.otp-hint {
    font-size: 0.75rem;
    color: var(--color-muted, #6B7280);
    margin-top: 4px;
    line-height: 1.4;
}

.otp-success-msg {
    color: var(--color-success, #2E7D53);
    font-size: 0.75rem;
    margin-top: 6px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
}

.otp-check-icon {
    font-weight: 700;
}

/* Password Requirements Checklist */
.password-requirements-card {
    background: var(--color-surface-alt, #F0F2F5);
    border: 1px solid var(--color-border, #DDE1E7);
    border-radius: var(--radius-md, 6px);
    padding: var(--space-3, 0.75rem) var(--space-4, 1rem);
    margin: var(--space-4, 1rem) 0;
}

.password-requirements-card__title {
    font-size: 0.6875rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--color-muted, #6B7280);
    margin-bottom: var(--space-2, 0.5rem);
}

.password-requirements-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 0.8125rem;
}

.password-requirements-list li {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--color-muted, #6B7280);
    transition: color var(--transition-fast, 120ms);
}

.password-requirements-list li.req--met {
    color: var(--color-success, #2E7D53);
    font-weight: 500;
}

.password-requirements-list .req-indicator {
    display: inline-block;
    width: 14px;
    text-align: center;
    font-size: 0.8125rem;
    flex-shrink: 0;
}

.user-mgmt-header {
    flex-wrap: wrap;
    gap: var(--space-4, 1rem);
}

.user-mgmt-header__left {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--space-4, 1rem);
}

.user-mgmt-header__right {
    display: flex;
    align-items: center;
}

/* Directory Toolbar (Search & Filter) */
.directory-toolbar {
    display: flex;
    align-items: center;
    gap: var(--space-3, 0.75rem);
    padding: var(--space-3, 0.75rem) var(--space-6, 1.5rem);
    background: var(--color-surface-alt, #F0F2F5);
    border-bottom: 1px solid var(--color-border, #DDE1E7);
    flex-wrap: wrap;
}

.directory-search {
    position: relative;
    flex: 1 1 280px;
}

.search-input-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    color: var(--color-muted, #6B7280);
    pointer-events: none;
}

.directory-search__input {
    padding-left: 32px !important;
    font-size: 0.8125rem !important;
    height: 34px !important;
    border-radius: var(--radius-pill, 9999px) !important;
}

.directory-select {
    width: 150px;
    font-size: 0.8125rem !important;
    height: 34px !important;
    padding: 0 var(--space-3, 0.75rem) !important;
}

/* User Identity Cell in Table */
.user-row-identity {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-avatar-mini {
    width: 30px;
    height: 30px;
    border-radius: var(--radius-sm, 4px);
    background: var(--color-accent-light, #EBF3F8);
    color: var(--color-accent, #2C5F7C);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.75rem;
    flex-shrink: 0;
}

.user-avatar-mini--archived {
    background: var(--color-surface-alt, #F0F2F5);
    color: var(--color-muted, #6B7280);
}

.user-row-meta {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.user-row-name {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.875rem;
    color: var(--color-ink, #1B1E23);
    line-height: 1.3;
}

.user-row-email {
    font-size: 0.75rem;
    color: var(--color-muted, #6B7280);
}

.pill-you {
    display: inline-block;
    padding: 1px 6px;
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    background: var(--color-accent-light, #EBF3F8);
    color: var(--color-accent, #2C5F7C);
    border-radius: var(--radius-pill, 9999px);
}

.table-accent-text {
    font-weight: 600;
    color: var(--color-accent, #2C5F7C);
}

.tr--archived {
    opacity: 0.75;
}

.tr--archived:hover {
    opacity: 1;
}

.cell-sm-text {
    font-size: 0.75rem;
}

.empty-cell-state {
    padding: var(--space-8, 2rem) var(--space-4, 1rem) !important;
}

/* Action Buttons Alignment */
.action-buttons-wrap {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    justify-content: flex-end;
}

.action-btn--unlock {
    color: var(--color-success, #2E7D53) !important;
}

.action-btn--unlock:hover {
    background: var(--color-success-light, #EBF5F0) !important;
    border-color: var(--color-success, #2E7D53) !important;
}

.action-btn--danger {
    color: var(--color-danger, #C0392B) !important;
}

.action-btn--danger:hover {
    background: var(--color-danger-light, #FDECEA) !important;
    border-color: var(--color-danger, #C0392B) !important;
}
</style>
@endpush
