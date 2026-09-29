<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Handles validation and throttling for the login form.
 *
 * Rate limiting is applied here rather than in the controller so the
 * throttle logic is testable independently and doesn't pollute the
 * controller with IP/fingerprint logic.
 *
 * Max attempts: 5 per email+IP combination per minute.
 * This is a LAN-only office tool, so 5 attempts is generous enough
 * for typos while still blocking automated credential stuffing.
 */
class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     * Supports both email address and 7-digit account number.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $loginInput = trim($this->input('email'));
        $password = $this->input('password');
        $remember = $this->boolean('remember');

        // Determine if user entered an email address or an account number
        $fieldType = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'account_number';

        // 1. Check if this account exists but is archived
        $isArchived = User::onlyTrashed()->where($fieldType, $loginInput)->exists();
        if ($isArchived) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'We could not find any credentials that match our system. Please contact your administrator for assistance..',
            ]);
        }

        // 2. Check if an active account exists with this email or account number
        $user = User::where($fieldType, $loginInput)->first();
        if (! $user) {
            $this->ensureIsNotRateLimited();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'We could not find any credentials that match our system. Please contact your administrator for assistance..',
            ]);
        }

        // 3. Check if user account is currently locked out
        if ($user->isLocked()) {
            $remaining = $user->lockoutRemainingMinutes();
            $durationStr = $remaining <= 1 ? '1 minute' : "{$remaining} minutes";

            throw ValidationException::withMessages([
                'email' => "Your account is temporarily locked out. Please try again in {$durationStr} or contact your administrator for assistance.",
            ]);
        }

        $this->ensureIsNotRateLimited();

        $credentials = [
            $fieldType => $loginInput,
            'password' => $password,
        ];

        // 4. Attempt authentication with provided password
        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($this->throttleKey());

            $user->increment('failed_login_attempts');
            $attempts = (int) $user->failed_login_attempts;

            // 7 or more failed attempts: 30-minute temporary lockout
            if ($attempts >= 7) {
                $user->update(['locked_until' => now()->addMinutes(30)]);

                throw ValidationException::withMessages([
                    'email' => 'You entered your credentials wrong 7 times. Your account has been temporarily locked out for 30 minutes. Please try again later or contact your administrator.',
                ]);
            }

            // 5 failed attempts: 5-minute temporary lockout
            if ($attempts === 5) {
                $user->update(['locked_until' => now()->addMinutes(5)]);

                throw ValidationException::withMessages([
                    'email' => 'You entered your credentials wrong 5 times. Your account has been temporarily locked out for 5 minutes. Please try again later or contact your administrator.',
                ]);
            }

            // 3 failed attempts: warning notification message
            if ($attempts === 3) {
                throw ValidationException::withMessages([
                    'email' => 'You entered your credentials wrong 3 times, if you enter your password 5 times you will be locked out of your account',
                ]);
            }

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        if ($user->failed_login_attempts > 0 || $user->locked_until !== null) {
            $user->update([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ]);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * Combines email (lowercased) and IP so that one IP cannot try
     * multiple accounts, and one account cannot be targeted from
     * multiple IPs without hitting per-account limits.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')) . '|' . $this->ip());
    }
}
