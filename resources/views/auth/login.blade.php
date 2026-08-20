@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
    <h1 class="auth-heading">Sign in to your account</h1>
    <p class="auth-subheading">Dex International Co. — Project Management</p>

    {{-- Session status (e.g. "You have been logged out") --}}
    @if (session('status'))
        <div class="alert alert-info mb-4" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" id="login-form" novalidate>
        @csrf

        {{-- Email --}}
        <div class="form-group">
            <label for="email" class="form-label">Email address</label>
            <input
                id="email"
                type="email"
                name="email"
                class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email') }}"
                required
                autocomplete="email"
                autofocus
                aria-describedby="email-error"
            >
            @error('email')
                <div id="email-error" class="invalid-feedback" role="alert">
                    {{ $message }}
                </div>
            @enderror
        </div>

        {{-- Password --}}
        <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <input
                id="password"
                type="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                required
                autocomplete="current-password"
                aria-describedby="password-error"
            >
            @error('password')
                <div id="password-error" class="invalid-feedback" role="alert">
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

        {{-- Submit --}}
        <button
            id="login-submit"
            type="submit"
            class="btn btn-primary w-100"
        >
            Sign in
        </button>
    </form>
@endsection
