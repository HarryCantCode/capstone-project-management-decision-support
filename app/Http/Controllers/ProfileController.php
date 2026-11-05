<?php

namespace App\Http\Controllers;

use App\Rules\PasswordComplexity;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * ProfileController
 *
 * Enables authenticated users (Admin, Manager, Staff) to update
 * their own email address and password from the Account Settings page.
 */
class ProfileController extends Controller implements HasMiddleware
{
    public function __construct(
        protected OtpService $otpService,
    ) {
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            'auth',
            'two-factor',
        ];
    }

    /**
     * Send OTP for profile credential changes.
     * Enforces limit of 2 consecutive sends, then 3-minute cooldown.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', 'in:change_email,change_password'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $purpose = $request->string('action')->toString();

        // Check 2-attempt throttle and 3-minute cooldown
        $throttle = $this->otpService->checkSendThrottle($user->id, $purpose);
        if (! $throttle['allowed']) {
            return response()->json([
                'success' => false,
                'message' => "Too many OTP requests. Please wait {$throttle['remaining_seconds']} seconds before requesting a new code.",
                'remaining_seconds' => $throttle['remaining_seconds'],
            ], 429);
        }

        $this->otpService->recordSendAttempt($user->id, $purpose);
        $this->otpService->sendActionOtp($user, $purpose, $request);

        // Check if now locked after this send
        $postThrottle = $this->otpService->checkSendThrottle($user->id, $purpose);

        return response()->json([
            'success' => true,
            'message' => 'A 6-digit OTP code has been sent to your registered email address.',
            'remaining_seconds' => $postThrottle['remaining_seconds'],
            'attempts' => $postThrottle['attempts'],
        ]);
    }

    /**
     * Update the authenticated user's email address.
     */
    public function updateEmail(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'current_password_for_email' => ['required', 'current_password'],
            'otp' => ['required', 'string', 'digits:6'],
        ]);

        // Verify OTP for change_email action
        $verification = $this->otpService->verifyActionOtp($user, 'change_email', $validated['otp']);
        if (! $verification['success']) {
            throw ValidationException::withMessages([
                'otp' => $verification['message'],
            ]);
        }

        $user->email = $validated['email'];
        $user->save();

        app(\App\Services\NotificationService::class)->notifyCredentialReset($user, 'email');

        return redirect()->route('account.settings')->with('status', 'Your email address has been updated successfully.');
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', new PasswordComplexity($user)],
            'otp' => ['required', 'string', 'digits:6'],
        ]);

        // Verify OTP for change_password action
        $verification = $this->otpService->verifyActionOtp($user, 'change_password', $validated['otp']);
        if (! $verification['success']) {
            throw ValidationException::withMessages([
                'otp' => $verification['message'],
            ]);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        app(\App\Services\NotificationService::class)->notifyCredentialReset($user, 'password');

        return redirect()->route('account.settings')->with('status', 'Your password has been changed successfully.');
    }
}
