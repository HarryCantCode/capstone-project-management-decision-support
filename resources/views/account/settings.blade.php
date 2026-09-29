
@extends('layouts.app')

@section('title', 'Account Settings')
@section('meta-description', 'Manage your Dex PMS account settings and credentials.')

@section('content')
    <div class="page-header">
        <div>
            <h1>Account Settings</h1>
            <p class="page-header__description">Manage your profile, credentials, and account preferences.</p>
        </div>
    </div>

    {{-- Top Section: Profile Info & Self-Service Credential Updates --}}
    <div class="personnel-form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        
        {{-- Card 1: My Profile Overview --}}
        <section class="card" aria-labelledby="profile-overview-heading">
            <div class="card-header">
                <h2 id="profile-overview-heading" style="font-size: 1.1rem; margin: 0;">My Profile Overview</h2>
            </div>
            <div class="card-body">
                <dl class="detail-list">
                    <div>
                        <dt>Account Number</dt>
                        <dd><span class="font-mono font-bold" style="color: var(--color-accent, #2563eb); font-weight: 700;">{{ Auth::user()->account_number ?? 'N/A' }}</span></dd>
                    </div>
                    <div>
                        <dt>Employee ID</dt>
                        <dd><span class="font-mono">{{ Auth::user()->employee_number ?? 'N/A' }}</span></dd>
                    </div>
                    <div>
                        <dt>Full Name</dt>
                        <dd><strong>{{ Auth::user()->name }}</strong></dd>
                    </div>
                    <div>
                        <dt>Email Address</dt>
                        <dd>{{ Auth::user()->email }}</dd>
                    </div>
                    <div>
                        <dt>System Role</dt>
                        <dd><span class="badge">{{ Auth::user()->getRoleNames()->first() }}</span></dd>
                    </div>
                </dl>

                @role('Admin')
                    <hr class="settings-divider" style="margin: 1.25rem 0; border: none; border-top: 1px solid var(--color-border);">
                    <h3 class="settings-heading" style="font-size: 0.95rem; margin-bottom: 0.25rem;">Two-Factor Authentication</h3>
                    <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 0.75rem;">
                        Status: <strong>{{ Auth::user()->two_factor_enabled ? 'Enabled' : 'Not enabled' }}</strong>
                    </p>

                    @if (Auth::user()->two_factor_enabled)
                        <form method="POST" action="{{ route('account.two-factor.disable') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Disable 2FA</button>
                        </form>
                    @else
                        <a href="{{ route('account.two-factor.setup') }}" class="btn btn-primary btn-sm">Set Up 2FA</a>
                    @endif
                @endrole
            </div>
        </section>

        {{-- Card 2: Self-Service Email & Password Updates (for Manager, Staff, Admin) --}}
        <section class="card" aria-labelledby="update-credentials-heading" x-data="{ activeTab: 'password' }">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2 id="update-credentials-heading" style="font-size: 1.1rem; margin: 0;">Update Credentials</h2>
                <div style="display: flex; gap: 0.5rem;">
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="activeTab === 'email' ? 'btn-primary' : 'btn-secondary'"
                        @click="activeTab = 'email'"
                        style="padding: 0.25rem 0.75rem; font-size: 0.8125rem;"
                    >
                        Update Email
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="activeTab === 'password' ? 'btn-primary' : 'btn-secondary'"
                        @click="activeTab = 'password'"
                        style="padding: 0.25rem 0.75rem; font-size: 0.8125rem;"
                    >
                        Change Password
                    </button>
                </div>
            </div>

            <div class="card-body">
                {{-- Form 1: Update Email --}}
                <div x-show="activeTab === 'email'" x-cloak>
                    <form method="POST" action="{{ route('account.profile.email') }}">
                        @csrf
                        @method('PUT')

                        <div class="form-group-premium">
                            <label for="new_email" class="form-label">New Email Address <span class="required" aria-hidden="true">*</span></label>
                            <input type="email" id="new_email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', Auth::user()->email) }}" required>
                            @error('email')<div class="form-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group-premium">
                            <label for="current_password_for_email" class="form-label">Current Password <span class="required" aria-hidden="true">*</span></label>
                            <input type="password" id="current_password_for_email" name="current_password_for_email" class="form-control @error('current_password_for_email') is-invalid @enderror" required placeholder="Enter current password to verify">
                            @error('current_password_for_email')<div class="form-error">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm" style="margin-top: 0.5rem;">Save Email Address</button>
                    </form>
                </div>

                {{-- Form 2: Change Password --}}
                <div x-show="activeTab === 'password'">
                    <form method="POST" action="{{ route('account.profile.password') }}">
                        @csrf
                        @method('PUT')

                        <div class="form-group-premium">
                            <label for="current_password" class="form-label">Current Password <span class="required" aria-hidden="true">*</span></label>
                            <input type="password" id="current_password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required placeholder="Enter current password">
                            @error('current_password')<div class="form-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group-premium">
                            <label for="password" class="form-label">New Password <span class="required" aria-hidden="true">*</span></label>
                            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="8" placeholder="Minimum 8 characters">
                            @error('password')<div class="form-error">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group-premium">
                            <label for="password_confirmation" class="form-label">Confirm New Password <span class="required" aria-hidden="true">*</span></label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required minlength="8" placeholder="Repeat new password">
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm" style="margin-top: 0.5rem;">Update Password</button>
                    </form>
                </div>
            </div>
        </section>
    </div>

    {{-- Admin Only: User Management Panels (Active Accounts & Archived Accounts) --}}
    @role('Admin')
    <section class="card mt-8" aria-labelledby="users-heading" x-data="{ accountTab: 'active' }">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <h2 id="users-heading" style="margin: 0;">User Management</h2>
                
                {{-- Panel Tabs --}}
                <div style="display: flex; gap: 0.5rem;">
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="accountTab === 'active' ? 'btn-primary' : 'btn-secondary'"
                        @click="accountTab = 'active'"
                        style="padding: 0.25rem 0.75rem;"
                    >
                        Active Accounts ({{ $activeUsers->count() }})
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="accountTab === 'archived' ? 'btn-primary' : 'btn-secondary'"
                        @click="accountTab = 'archived'"
                        style="padding: 0.25rem 0.75rem;"
                    >
                        Archived Accounts ({{ $archivedUsers->count() }})
                    </button>
                </div>
            </div>

            <a href="{{ route('account.users.create') }}" class="btn btn-primary btn-sm">+ Add User</a>
        </div>

        {{-- Tab 1: Active Accounts Panel --}}
        <div x-show="accountTab === 'active'">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Account No.</th>
                            <th scope="col">Employee ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Job Title</th>
                            <th scope="col">System Role</th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($activeUsers as $u)
                            <tr>
                                <td><span class="font-mono" style="font-weight: 600; color: var(--color-accent, #2563eb);">{{ $u->account_number ?? 'N/A' }}</span></td>
                                <td><span class="font-mono">{{ $u->employee_number ?? 'N/A' }}</span></td>
                                <td>
                                    <strong>{{ $u->name }}</strong>
                                    @if ($u->isLocked())
                                        <span class="status-pill status-pill--cancelled" style="font-size: 0.6875rem; margin-left: 0.25rem;">
                                            Locked ({{ $u->lockoutRemainingMinutes() }}m left)
                                        </span>
                                    @endif
                                </td>
                                <td>{{ $u->email }}</td>
                                <td>{{ $u->job_title ?? 'N/A' }}</td>
                                <td><span class="badge">{{ $u->getRoleNames()->first() ?? 'None' }}</span></td>
                                <td class="text-right" style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                    @if ($u->isLocked())
                                        <form method="POST" action="{{ route('account.users.unlock', $u) }}" onsubmit="return confirm('Activate and unlock user account {{ $u->name }}? This resets failed attempts and immediately restores login access.');">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm" style="background-color: var(--color-success, #10b981); border-color: var(--color-success, #10b981); color: #fff;">
                                                Activate
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('account.users.edit', $u) }}" class="btn btn-secondary btn-sm">Edit</a>
                                    @if (Auth::id() !== $u->id)
                                        <form method="POST" action="{{ route('account.users.destroy', $u) }}" onsubmit="return confirm('Archive this user account? The user will not be able to log in until reactivated.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" style="background-color: var(--color-warning, #f59e0b); border-color: var(--color-warning, #f59e0b); color: #fff;">Archive</button>
                                        </form>
                                    @else
                                        <button class="btn btn-secondary btn-sm" disabled title="Cannot archive your own active account">Archive</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted" style="padding: 2rem;">No active user accounts found.</td>
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
                            <th scope="col">Account No.</th>
                            <th scope="col">Employee ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Job Title</th>
                            <th scope="col">System Role</th>
                            <th scope="col">Archived Date</th>
                            <th scope="col" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($archivedUsers as $u)
                            <tr style="opacity: 0.85;">
                                <td><span class="font-mono">{{ $u->account_number ?? 'N/A' }}</span></td>
                                <td><span class="font-mono">{{ $u->employee_number ?? 'N/A' }}</span></td>
                                <td><strong>{{ $u->name }}</strong> <span class="status-pill status-pill--cancelled" style="font-size: 0.6875rem; margin-left: 0.25rem;">Archived</span></td>
                                <td>{{ $u->email }}</td>
                                <td>{{ $u->job_title ?? 'N/A' }}</td>
                                <td><span class="badge">{{ $u->getRoleNames()->first() ?? 'None' }}</span></td>
                                <td><span class="text-muted" style="font-size: 0.85rem;">{{ $u->deleted_at ? $u->deleted_at->format('M d, Y · h:i A') : 'N/A' }}</span></td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('account.users.restore', $u->id) }}" onsubmit="return confirm('Activate this user account? The user will regain system access immediately.');">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm" style="background-color: var(--color-success, #10b981); border-color: var(--color-success, #10b981); color: #fff;">Activate</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted" style="padding: 2rem;">No archived accounts. All accounts are currently active.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    @endrole
@endsection

@push('styles')
<style>
.mt-8 { margin-top: 2rem; }
[x-cloak] { display: none !important; }
</style>
@endpush
