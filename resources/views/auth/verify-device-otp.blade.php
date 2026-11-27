@extends('layouts.auth')

@section('title', 'Verify Device')

@push('styles')
<style>
    .otp-input-group {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin: 0 auto;
    }
    .otp-digit-box {
        width: 48px;
        height: 56px;
        padding: 0;
        text-align: center;
        font-family: var(--font-mono, 'Roboto Mono', monospace);
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1;
        color: var(--color-ink);
        background-color: var(--color-surface);
        border: 1.5px solid var(--color-border);
        border-radius: 10px;
        outline: none;
        caret-color: var(--color-accent);
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease, transform 0.1s ease;
        -moz-appearance: textfield;
    }
    .otp-digit-box:focus {
        border-color: var(--color-accent);
        box-shadow: 0 0 0 3px rgba(44, 95, 124, 0.18);
        transform: translateY(-1px);
    }
    .otp-digit-box.is-filled {
        border-color: var(--color-accent);
        background-color: rgba(44, 95, 124, 0.04);
    }
    .otp-digit-box.is-invalid {
        border-color: var(--color-danger, #dc2626);
        box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
    }
    [data-theme="dark"] .otp-digit-box {
        background-color: #0E1522;
        border-color: #2A3548;
        color: #F1F5F9;
        caret-color: #38BDF8;
    }
    [data-theme="dark"] .otp-digit-box:focus {
        border-color: #38BDF8;
        background-color: #111B2E;
        box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.22);
    }
    [data-theme="dark"] .otp-digit-box.is-filled {
        border-color: rgba(56, 189, 248, 0.65);
        background-color: rgba(56, 189, 248, 0.08);
    }
    [data-theme="dark"] .otp-digit-box.is-invalid {
        border-color: #F87171;
        box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.2);
    }
    @media (max-width: 420px) {
        .otp-input-group { gap: 6px; }
        .otp-digit-box { width: 42px; height: 50px; font-size: 1.25rem; }
    }
</style>
@endpush

@section('content')
    <div
        x-data="{
            cooldown: {{ $cooldownSeconds ?? 0 }},
            digits: ['', '', '', '', '', ''],
            init() {
                if (this.cooldown > 0) {
                    const timer = setInterval(() => {
                        this.cooldown--;
                        if (this.cooldown <= 0) clearInterval(timer);
                    }, 1000);
                }
                this.$nextTick(() => {
                    this.focusBox(0);
                });
            },
            formatCooldown() {
                const m = Math.floor(this.cooldown / 60);
                const s = this.cooldown % 60;
                return `${m}:${s < 10 ? '0' : ''}${s}`;
            },
            focusBox(index) {
                const el = document.getElementById('otp-digit-' + index);
                if (el) {
                    el.focus();
                    el.select();
                }
            },
            handleKeydown(index, event) {
                const allowedControlKeys = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End', 'Enter'];
                if (event.ctrlKey || event.metaKey) {
                    return;
                }
                if (event.key === 'Backspace') {
                    event.preventDefault();
                    if (this.digits[index] !== '') {
                        this.digits[index] = '';
                    } else if (index > 0) {
                        this.digits[index - 1] = '';
                        this.focusBox(index - 1);
                    }
                    return;
                }
                if (event.key === 'ArrowLeft') {
                    event.preventDefault();
                    if (index > 0) this.focusBox(index - 1);
                    return;
                }
                if (event.key === 'ArrowRight') {
                    event.preventDefault();
                    if (index < 5) this.focusBox(index + 1);
                    return;
                }
                if (allowedControlKeys.includes(event.key)) {
                    return;
                }
                if (!/^[0-9]$/.test(event.key)) {
                    event.preventDefault();
                }
            },
            handleInput(index, event) {
                const raw = (event.target.value || '').replace(/\D/g, '');
                if (!raw) {
                    this.digits[index] = '';
                    event.target.value = '';
                    return;
                }
                if (raw.length > 1) {
                    const chars = raw.slice(0, 6 - index).split('');
                    chars.forEach((ch, offset) => {
                        this.digits[index + offset] = ch;
                    });
                    const nextIdx = Math.min(index + chars.length, 5);
                    this.focusBox(nextIdx);
                    return;
                }
                this.digits[index] = raw;
                event.target.value = raw;
                if (index < 5) {
                    this.focusBox(index + 1);
                }
            },
            handlePaste(index, event) {
                event.preventDefault();
                const text = (event.clipboardData || window.clipboardData).getData('text') || '';
                const cleaned = text.replace(/\D/g, '').slice(0, 6);
                if (!cleaned) return;
                const startIndex = cleaned.length === 6 ? 0 : index;
                cleaned.split('').forEach((ch, offset) => {
                    if (startIndex + offset < 6) {
                        this.digits[startIndex + offset] = ch;
                    }
                });
                const nextIdx = Math.min(startIndex + cleaned.length, 5);
                this.focusBox(nextIdx);
            }
        }"
    >
        <div class="auth-header-group">
            <h1 class="auth-heading">Device Verification</h1>
            <p class="auth-subheading">
                We sent a 6-digit one-time code to <strong style="color: var(--color-ink);">{{ $emailMasked }}</strong> to verify this device.
            </p>
        </div>

        @if (session('status'))
            <div class="alert alert-info mb-4" role="alert">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('auth.device-verification.store') }}" id="otp-form" novalidate>
            @csrf

            {{-- OTP Input (6 separate numeric boxes) --}}
            <div class="form-group mb-4">
                <label for="otp-digit-0" class="form-label text-center d-block mb-3">Enter 6-Digit Code</label>
                <input type="hidden" id="otp" name="otp" :value="digits.join('')">
                <div class="otp-input-group" role="group" aria-label="6-digit one-time verification code">
                    @for ($i = 0; $i < 6; $i++)
                        <input
                            id="otp-digit-{{ $i }}"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="1"
                            autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                            class="otp-digit-box @error('otp') is-invalid @enderror"
                            :class="{ 'is-filled': digits[{{ $i }}] !== '' }"
                            x-model="digits[{{ $i }}]"
                            @keydown="handleKeydown({{ $i }}, $event)"
                            @input="handleInput({{ $i }}, $event)"
                            @paste="handlePaste({{ $i }}, $event)"
                            @focus="$event.target.select()"
                            aria-label="Digit {{ $i + 1 }} of 6"
                            aria-describedby="otp-error"
                            required
                        >
                    @endfor
                </div>
                <div class="text-muted text-center mt-3 small">
                    Code expires in <strong>5 minutes</strong>
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

        <div class="d-flex justify-content-between align-items-center mt-3 pt-3" style="border-top: 1px solid var(--color-border);">
            <form method="POST" action="{{ route('auth.device-verification.resend') }}" style="margin: 0;">
                @csrf
                <button
                    type="submit"
                    class="auth-action-link"
                    :disabled="cooldown > 0"
                >
                    <span x-show="cooldown <= 0">Didn't receive code? Resend</span>
                    <span x-show="cooldown > 0" style="display: none;" class="text-warning">
                        Resend available in <span class="font-mono font-bold" x-text="formatCooldown()"></span>
                    </span>
                </button>
            </form>

            <a href="{{ route('login') }}" class="auth-action-link">
                Back to Sign in
            </a>
        </div>
    </div>
@endsection

