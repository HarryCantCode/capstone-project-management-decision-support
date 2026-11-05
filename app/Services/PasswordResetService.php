<?php

namespace App\Services;

use App\Mail\TemporaryPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PasswordResetService
{
    /**
     * Generate an 8-character password matching the rule:
     * - Exactly 8 characters long
     * - Contains 1-9 numbers
     * - Contains at least 1 uppercase letter
     * - Contains at least 1 symbol
     * Example: @xasqRRup
     */
    public function generateRandomPassword(): string
    {
        $numbers = '123456789';
        $uppers = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowers = 'abcdefghijklmnopqrstuvwxyz';
        $symbols = '@#$%&*!?_';

        // Ensure at least one from each required category
        $required = [
            $numbers[random_int(0, strlen($numbers) - 1)],
            $uppers[random_int(0, strlen($uppers) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        // 5 additional characters from all valid pools (total length = 8)
        $allPool = $lowers . $uppers . $numbers . $symbols;
        for ($i = 0; $i < 5; $i++) {
            $required[] = $allPool[random_int(0, strlen($allPool) - 1)];
        }

        // Shuffle securely
        shuffle($required);

        return implode('', $required);
    }

    /**
     * Request a randomized new password and send it to user's email.
     * Supports both email address and 7-digit account number.
     * Rate limited to 3 times per calendar day for normal user requests.
     *
     * @return array{success: bool, message: string}
     */
    public function requestNewPassword(string $loginInput): array
    {
        $input = trim($loginInput);

        // Case-insensitive lookup supporting email address or 7-digit account number
        $user = User::whereRaw('LOWER(email) = ?', [strtolower($input)])
            ->orWhere('account_number', $input)
            ->first();

        // Security check: verify if the account is soft-deleted/archived
        if (! $user) {
            $archivedExists = User::onlyTrashed()
                ->whereRaw('LOWER(email) = ?', [strtolower($input)])
                ->orWhere('account_number', $input)
                ->exists();

            if ($archivedExists) {
                return [
                    'success' => false,
                    'message' => 'This account has been archived. Please contact your system administrator.',
                ];
            }

            return [
                'success' => false,
                'message' => 'We could not find an active account associated with that email address or account number.',
            ];
        }

        // Daily limit check: maximum 3 requests per day for standard user self-requests
        $dailyKey = 'pwd_reset_daily:' . $user->id . ':' . now()->format('Y-m-d');
        $dailyCount = (int) \Illuminate\Support\Facades\Cache::get($dailyKey, 0);

        if ($dailyCount >= 3) {
            return [
                'success' => false,
                'message' => 'You have reached the maximum limit of 3 password reset requests for today. Please contact your administrator or try again tomorrow.',
            ];
        }

        $temporaryPassword = $this->generateRandomPassword();

        // Update user record: store hashed password and enforce password change upon next login
        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        // Increment daily count expiring at end of day
        $secondsUntilMidnight = now()->diffInSeconds(now()->endOfDay());
        \Illuminate\Support\Facades\Cache::put($dailyKey, $dailyCount + 1, max(60, $secondsUntilMidnight));

        try {
            Mail::to($user->email)->send(new TemporaryPasswordMail($user, $temporaryPassword));
            app(\App\Services\NotificationService::class)->notifyCredentialReset($user, 'password');
        } catch (\Throwable $e) {
            Log::error("Failed to send temporary password email to {$user->email}: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'There was an error dispatching the email. Please contact system support.',
            ];
        }

        return [
            'success' => true,
            'message' => 'A new temporary password has been sent to your registered email address.',
        ];
    }

    /**
     * Admin generates a temporary password for a target user.
     * Optionally sends an email or returns the plaintext password to the admin.
     *
     * @return array{success: bool, temporary_password: string, user: \App\Models\User}
     */
    public function generateTemporaryPasswordForUser(User $user, bool $sendEmail = true): array
    {
        $temporaryPassword = $this->generateRandomPassword();

        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'updated_by' => auth()->id(),
        ])->save();

        if ($sendEmail) {
            try {
                Mail::to($user->email)->send(new TemporaryPasswordMail($user, $temporaryPassword));
            } catch (\Throwable $e) {
                Log::error("Failed to send temporary password email to {$user->email}: " . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'temporary_password' => $temporaryPassword,
            'user' => $user,
        ];
    }
}
