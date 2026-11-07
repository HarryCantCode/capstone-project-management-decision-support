@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
    <div x-data="{ forgotModalOpen: false }">
        <div class="auth-header-group">
            <h1 class="auth-heading">Welcome back</h1>
            <p class="auth-subheading">Sign in with your email or 7-digit account number</p>
        </div>

        {{-- Session status (e.g. "You have been logged out") --}}
        @if (session('status'))
            <div class="alert alert-info mb-4" role="alert">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" id="login-form" novalidate>
            @csrf

            {{-- Email or Account Number --}}
            <div class="form-group">
                <label for="email" class="form-label">Email address or Account Number</label>
                <div class="input-with-icon">
                    <span class="input-icon" aria-hidden="true">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </span>
                    <input
                        id="email"
                        type="text"
                        name="email"
                        class="form-control form-control--with-icon @error('email') is-invalid @enderror"
                        value="{{ old('email') }}"
                        required
                        autocomplete="username"
                        autofocus
                        placeholder="name@dex-pms.local or 1000001"
                        aria-describedby="email-error"
                    >
                </div>
                @error('email')
                    <div id="email-error" class="invalid-feedback d-block" role="alert">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            {{-- Password --}}
            <div class="form-group" x-data="{ showPassword: false }">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label mb-0">Password</label>
                </div>
                <div class="input-with-icon">
                    <span class="input-icon" aria-hidden="true">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </span>
                    <input
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        type="password"
                        name="password"
                        class="form-control form-control--with-icon form-control--with-trailing @error('password') is-invalid @enderror"
                        required
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        aria-describedby="password-error"
                    >
                    <button
                        type="button"
                        class="password-toggle-btn"
                        @click="showPassword = !showPassword"
                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        :title="showPassword ? 'Hide password' : 'Show password'"
                        tabindex="-1"
                    >
                        <svg x-show="!showPassword" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg x-show="showPassword" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                    </button>
                </div>
                @error('password')
                    <div id="password-error" class="invalid-feedback d-block" role="alert">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            {{-- Remember me --}}
            <div class="form-check mb-4">
                <input
                    id="remember"
                    type="checkbox"
                    name="remember"
                    class="form-check-input"
                    {{ old('remember') ? 'checked' : '' }}
                >
                <label for="remember" class="form-check-label">Keep me signed in</label>
            </div>

            {{-- Submit button --}}
            <button
                id="login-submit"
                type="submit"
                class="btn btn-primary auth-submit-btn w-100"
            >
                <span>Sign in</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </button>

            {{-- Forgot password link: placed below sign in button, centered, and compact --}}
            <div class="text-center mt-3">
                <button
                    type="button"
                    class="auth-forgot-link"
                    @click="forgotModalOpen = true"
                >
                    Forgot password?
                </button>
            </div>
        </form>

        {{-- Centered Pop-up Modal (following PMS Design System standards: .reassign-modal-overlay) --}}
        <div
            class="reassign-modal-overlay"
            x-show="forgotModalOpen"
            x-cloak
            @keydown.escape.window="forgotModalOpen = false"
            style="display: none;"
        >
            {{-- Modal Backdrop --}}
            <div
                class="reassign-modal-backdrop"
                @click="forgotModalOpen = false"
                x-show="forgotModalOpen"
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
                x-show="forgotModalOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 transform scale-100"
                x-transition:leave-end="opacity-0 transform scale-95"
                style="max-width: 460px;"
            >
                <form id="forgot-password-form" method="POST" action="{{ route('password.forgot.store') }}">
                    @csrf
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 600; font-size: 0.95rem;">Reset Password</span>
                        <button
                            type="button"
                            class="flash-close"
                            @click="forgotModalOpen = false"
                            aria-label="Close modal"
                        >
                            &times;
                        </button>
                    </div>

                    <div class="card-body">
                        <p class="text-muted small mb-3" style="font-size: 0.8125rem; line-height: 1.5;">
                            Enter your email address or 7-digit account number. We will send a secure password creation link directly to your registered email address.
                        </p>

                        <div id="forgot-password-feedback" class="d-none alert mb-3" role="alert"></div>

                        <div class="form-group mb-0">
                            <label for="reset_email" class="form-label" style="font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">
                                Email Address or Account Number <span class="required" aria-hidden="true">*</span>
                            </label>
                            <input
                                type="text"
                                name="email"
                                id="reset_email"
                                class="form-control"
                                placeholder="name@dex-pms.local or 1000001"
                                required
                            >
                        </div>
                    </div>

                    <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.5rem;">
                        <button
                            type="button"
                            class="btn btn-secondary btn-sm"
                            @click="forgotModalOpen = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            id="request-pwd-btn"
                            class="btn btn-primary btn-sm"
                        >
                            <span id="pwd-btn-spinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" style="width: 13px; height: 13px; border-width: 2px;"></span>
                            <span>Send Reset Link</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('forgot-password-form');
            const feedback = document.getElementById('forgot-password-feedback');
            const submitBtn = document.getElementById('request-pwd-btn');
            const spinner = document.getElementById('pwd-btn-spinner');

            if (form) {
                form.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    feedback.className = 'd-none alert mb-3';
                    feedback.textContent = '';
                    submitBtn.disabled = true;
                    if (spinner) spinner.classList.remove('d-none');

                    const formData = new FormData(form);

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: formData
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            feedback.className = 'alert alert-success mb-3';
                            feedback.textContent = data.message;
                            form.reset();
                        } else {
                            feedback.className = 'alert alert-danger mb-3';
                            feedback.textContent = data.message || 'We could not complete your request. Please try again.';
                        }
                    } catch (err) {
                        feedback.className = 'alert alert-danger mb-3';
                        feedback.textContent = 'A network error occurred. Please try again later.';
                    } finally {
                        submitBtn.disabled = false;
                        if (spinner) spinner.classList.add('d-none');
                    }
                });
            }
        });
    </script>
@endsection
