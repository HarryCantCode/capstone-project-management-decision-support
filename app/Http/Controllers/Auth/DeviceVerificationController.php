<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DeviceVerificationController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
    ) {
    }

    /**
     * Show the OTP input form for device verification.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $userId = $request->session()->get('device_verification_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (! $user) {
            return redirect()->route('login');
        }

        $throttle = $this->otpService->checkSendThrottle($user->id, 'device_verification');

        return view('auth.verify-device-otp', [
            'user' => $user,
            'emailMasked' => $this->maskEmail($user->email),
            'cooldownSeconds' => $throttle['remaining_seconds'],
            'sendAttempts' => $throttle['attempts'],
        ]);
    }

    /**
     * Verify the submitted OTP code.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'digits:6'],
        ]);

        $userId = $request->session()->get('device_verification_user_id');
        if (! $userId) {
            return redirect()->route('login')->withErrors(['email' => 'Session expired. Please sign in again.']);
        }

        $user = User::findOrFail($userId);

        $result = $this->otpService->verifyDeviceOtp($user, $request->string('otp')->toString(), $request);

        if (! $result['success']) {
            return back()->withErrors([
                'otp' => $result['message'],
            ]);
        }

        // Device is verified! Clean up temporary session key and complete login
        $remember = $request->session()->get('device_verification_remember', false);
        $request->session()->forget(['device_verification_user_id', 'device_verification_remember']);

        Auth::login($user, $remember);
        $request->session()->regenerate();

        // If user is Admin and has TOTP enabled, proceed to TOTP challenge
        if ($user->hasRole('Admin') && $user->two_factor_enabled) {
            return redirect()->route('auth.two-factor');
        }

        // Mandatory password change check (e.g. from password reset or first login)
        if ($user->must_change_password) {
            return redirect()->route('password.first-login');
        }

        // Staff redirected to resources index
        if ($user->hasRole('Staff')) {
            $request->session()->forget('url.intended');

            return redirect()->route('resources.index');
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Resend verification OTP code.
     */
    public function resend(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('device_verification_user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($userId);

        // Check throttle: max 2 attempts, then 3-minute cooldown
        $throttle = $this->otpService->checkSendThrottle($user->id, 'device_verification');
        if (! $throttle['allowed']) {
            $minutes = ceil($throttle['remaining_seconds'] / 60);

            return back()->withErrors([
                'otp' => "Too many OTP requests. Please wait {$throttle['remaining_seconds']} seconds before requesting a new code.",
            ]);
        }

        $this->otpService->recordSendAttempt($user->id, 'device_verification');
        $this->otpService->sendDeviceVerificationOtp($user, $request);

        return back()->with('status', 'A fresh 6-digit verification code has been sent to your email.');
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $name = $parts[0];
        $domain = $parts[1];

        $len = strlen($name);
        if ($len <= 2) {
            $maskedName = $name . '***';
        } else {
            $maskedName = substr($name, 0, 2) . str_repeat('*', max(3, $len - 2)) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }
}
