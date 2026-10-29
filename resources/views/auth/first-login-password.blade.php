@extends('layouts.auth')

@section('title', 'First-Time Login &mdash; Set Your Password')

@section('content')
    <div class="auth-heading-container" style="margin-bottom: 1.5rem;">
        <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; color: var(--color-accent, #2563eb); margin-bottom: 0.75rem;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            First-Time Login Security
        </div>
        <h1 class="auth-heading" style="margin-bottom: 0.35rem; font-size: 1.5rem; font-weight: 700;">Set your new password</h1>
        <p class="auth-subheading" style="margin-bottom: 0; color: var(--color-text-muted, #64748b); font-size: 0.875rem; line-height: 1.45;">
            Welcome, <strong>{{ $user->first_name ?? $user->name }}</strong>! You are logging in with a temporary password. Please create your personal, permanent password to continue.
        </p>
    </div>

    @if (session('warning'))
        <div class="alert alert-warning" role="alert" style="margin-bottom: 1.25rem; font-size: 0.8125rem; padding: 0.75rem 1rem; border-radius: var(--radius-md, 8px); background: #fef3c7; color: #92400e; border: 1px solid #fde68a;">
            {{ session('warning') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.first-login.update') }}" id="first-login-password-form" novalidate>
        @csrf

        {{-- New Password --}}
        <div class="form-group" style="margin-bottom: 1.1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                <label for="password" class="form-label" style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0;">New Password</label>
                <button type="button" onclick="togglePasswordVisibility('password', this)" style="background: none; border: none; font-size: 0.75rem; color: var(--color-accent, #2563eb); cursor: pointer; padding: 0; font-weight: 500;" aria-label="Toggle password visibility">
                    Show
                </button>
            </div>
            <input
                id="password"
                type="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                required
                autofocus
                autocomplete="new-password"
                placeholder="Enter your new password"
                oninput="checkPasswordRules(this.value)"
                style="padding: 0.65rem 0.85rem; font-size: 0.9375rem; border-radius: var(--radius-md, 8px);"
            >
            @error('password')
                <div class="invalid-feedback" role="alert" style="display: block; font-size: 0.8125rem; margin-top: 0.35rem; color: #dc2626;">
                    {{ $message }}
                </div>
            @enderror
        </div>

        {{-- Confirm Password --}}
        <div class="form-group" style="margin-bottom: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                <label for="password_confirmation" class="form-label" style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0;">Confirm New Password</label>
                <button type="button" onclick="togglePasswordVisibility('password_confirmation', this)" style="background: none; border: none; font-size: 0.75rem; color: var(--color-accent, #2563eb); cursor: pointer; padding: 0; font-weight: 500;" aria-label="Toggle confirm password visibility">
                    Show
                </button>
            </div>
            <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                class="form-control"
                required
                autocomplete="new-password"
                placeholder="Re-enter your new password"
                style="padding: 0.65rem 0.85rem; font-size: 0.9375rem; border-radius: var(--radius-md, 8px);"
            >
        </div>

        {{-- Password Requirements Checklist Card --}}
        <div class="password-requirements-card" style="background: rgba(15, 23, 42, 0.03); border: 1px solid var(--color-border, #e2e8f0); border-radius: var(--radius-md, 8px); padding: 0.875rem 1rem; margin-bottom: 1.5rem;">
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
            </ul>
        </div>

        <button id="update-password-submit" type="submit" class="btn btn-primary w-100" style="padding: 0.75rem 1rem; font-weight: 600; font-size: 0.9375rem; border-radius: var(--radius-md, 8px); box-shadow: 0 2px 4px rgba(37, 99, 235, 0.15);">
            Update Password &amp; Continue
        </button>
    </form>

    <p class="auth-footer-link mt-3 text-center" style="margin-top: 1.25rem; font-size: 0.8125rem;">
        <a href="{{ route('logout') }}"
           onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
           style="color: var(--color-text-muted, #64748b); text-decoration: underline;"
        >Not you? Sign out</a>
    </p>

    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
        @csrf
    </form>

    <script>
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
            const lenOk = password.length >= 8;
            const capOk = /[A-Z]/.test(password);
            const numOk = /[0-9]/.test(password);
            const symOk = /[\W_]/.test(password);

            updateRuleUI('req-len', lenOk);
            updateRuleUI('req-cap', capOk);
            updateRuleUI('req-num', numOk);
            updateRuleUI('req-sym', symOk);
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
    </script>
@endsection
