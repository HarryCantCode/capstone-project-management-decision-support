@extends('layouts.auth')

@section('title', 'Set Up Two-Factor Authentication')

@section('content')
    <h1 class="auth-heading">Set up two-factor authentication</h1>
    <p class="auth-subheading">Add this secret to your authenticator app, then verify the generated code.</p>

    <div class="alert alert-info mb-4">
        <strong>Secret key:</strong>
        <span class="font-mono">{{ $secret }}</span>
    </div>

    <p class="text-muted mb-4">
        If your authenticator app accepts an <code>otpauth://</code> URI, use the value below.
    </p>
    <p class="font-mono setup-uri">{{ $qrCodeUrl }}</p>

    <form method="POST" action="{{ route('account.two-factor.enable') }}" novalidate>
        @csrf
        <div class="form-group">
            <label for="one_time_password" class="form-label">Verification code</label>
            <input id="one_time_password" type="text" name="one_time_password" class="form-control form-control-otp @error('one_time_password') is-invalid @enderror" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required autofocus>
            @error('one_time_password')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary w-100">Enable 2FA</button>
    </form>

    <p class="auth-footer-link mt-3 text-center"><a href="{{ route('account.settings') }}">Cancel</a></p>
@endsection
