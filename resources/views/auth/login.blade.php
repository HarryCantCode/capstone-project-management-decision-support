@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
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
    </form>
@endsection
