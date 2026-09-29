@extends('layouts.auth')

@section('title', 'Two-Factor Verification')

@section('content')
    <h1 class="auth-heading">Two-factor verification</h1>
    <p class="auth-subheading">Open your authenticator app and enter the 6-digit code.</p>

    <form method="POST" action="{{ route('auth.two-factor.store') }}" id="two-factor-form" novalidate>
        @csrf

        <div class="form-group">
            <label for="one_time_password" class="form-label">One-time password</label>
            <input
                id="one_time_password"
                type="text"
                name="one_time_password"
                class="form-control form-control-otp @error('one_time_password') is-invalid @enderror"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="6"
                pattern="\d{6}"
                required
                autofocus
                aria-describedby="otp-error"
                placeholder="000000"
            >
            @error('one_time_password')
                <div id="otp-error" class="invalid-feedback" role="alert">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <button id="two-factor-submit" type="submit" class="btn btn-primary w-100">
            Verify
        </button>
    </form>

    <p class="auth-footer-link mt-3 text-center">
        <a href="{{ route('logout') }}"
           onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
        >Not you? Sign out</a>
    </p>

    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="d-none">
        @csrf
    </form>
@endsection
