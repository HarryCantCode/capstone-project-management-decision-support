@extends('layouts.auth')

@section('title', 'Verify Device')

@section('content')
    <div
        x-data="{
            cooldown: {{ $cooldownSeconds ?? 0 }},
            init() {
                if (this.cooldown > 0) {
                    const timer = setInterval(() => {
                        this.cooldown--;
                        if (this.cooldown <= 0) clearInterval(timer);
                    }, 1000);
                }
            },
            formatCooldown() {
                const m = Math.floor(this.cooldown / 60);
                const s = this.cooldown % 60;
                return `${m}:${s < 10 ? '0' : ''}${s}`;
            }
        }"
    >
        <div class="auth-header-group">
            <h1 class="auth-heading">Device Verification</h1>
            <p class="auth-subheading">
                We sent a 6-digit one-time code to <strong class="text-white">{{ $emailMasked }}</strong> to verify this device.
            </p>
        </div>

        @if (session('status'))
            <div class="alert alert-info mb-4" role="alert">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('auth.device-verification.store') }}" id="otp-form" novalidate>
            @csrf

            {{-- OTP Input --}}
            <div class="form-group mb-4">
                <label for="otp" class="form-label text-center d-block">Enter 6-Digit Code</label>
                <div class="input-with-icon">
                    <span class="input-icon" aria-hidden="true">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    </span>
                    <input
                        id="otp"
                        type="text"
                        name="otp"
                        class="form-control form-control--with-icon text-center font-mono @error('otp') is-invalid @enderror"
                        style="font-size: 22px; letter-spacing: 6px; font-weight: 700;"
                        maxlength="6"
                        pattern="[0-9]{6}"
                        inputmode="numeric"
                        required
                        autofocus
                        placeholder="000000"
                        aria-describedby="otp-error"
                    >
                </div>
                <div class="text-muted text-center mt-2 small">
                    Code expires in <strong>3 minutes</strong>
                </div>
                @error('otp')
                    <div id="otp-error" class="invalid-feedback d-block text-center mt-2" role="alert">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            {{-- Submit button --}}
            <button
                type="submit"
                class="btn btn-primary auth-submit-btn w-100 mb-3"
            >
                <span>Verify & Continue</span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            </button>
        </form>

        <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-secondary border-opacity-25">
            <form method="POST" action="{{ route('auth.device-verification.resend') }}">
                @csrf
                <button
                    type="submit"
                    class="btn btn-link text-decoration-none p-0 text-muted small"
                    :disabled="cooldown > 0"
                >
                    <span x-show="cooldown <= 0">Didn't receive code? Resend</span>
                    <span x-show="cooldown > 0" style="display: none;" class="text-warning">
                        Resend available in <span class="font-mono font-bold" x-text="formatCooldown()"></span>
                    </span>
                </button>
            </form>

            <a href="{{ route('login') }}" class="btn btn-link text-decoration-none p-0 text-muted small">
                Back to Sign in
            </a>
        </div>
    </div>
@endsection

