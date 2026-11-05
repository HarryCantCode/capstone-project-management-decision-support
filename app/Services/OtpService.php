<?php

namespace App\Services;

use App\Mail\DeviceVerificationOtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use App\Models\UserDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OtpService
{
    public const OTP_EXPIRY_MINUTES = 3;
    public const MAX_ATTEMPTS = 5;
    public const MAX_CONSECUTIVE_SENDS = 2;
    public const THROTTLE_LOCKOUT_SECONDS = 180; // 3 minutes

    public function __construct(
        protected DeviceDetectionService $deviceDetectionService,
    ) {
    }

    /**
     * Check rate limiting for sending OTP:
     * - Only 2 attempts consecutively.
     * - After the 2nd attempt, throttled for 3 minutes (180 seconds).
     *
     * @return array{allowed: bool, remaining_seconds: int, attempts: int}
     */
    public function checkSendThrottle(int $userId, string $action): array
    {
        $countKey = "otp_send_count:{$userId}:{$action}";
        $lockKey = "otp_send_lock:{$userId}:{$action}";

        // If locked out with cooldown timer
        if (Cache::has($lockKey)) {
            $lockExpiry = Cache::get($lockKey);
            $remaining = max(0, $lockExpiry - time());
            if ($remaining > 0) {
                return [
                    'allowed' => false,
                    'remaining_seconds' => $remaining,
                    'attempts' => self::MAX_CONSECUTIVE_SENDS,
                ];
            }
        }

        $count = (int) Cache::get($countKey, 0);

        return [
            'allowed' => true,
            'remaining_seconds' => 0,
            'attempts' => $count,
        ];
    }

    /**
     * Increment send attempt count and apply 3-minute lock if reaching threshold.
     */
    public function recordSendAttempt(int $userId, string $action): void
    {
        $countKey = "otp_send_count:{$userId}:{$action}";
        $lockKey = "otp_send_lock:{$userId}:{$action}";

        $count = (int) Cache::get($countKey, 0) + 1;

        if ($count >= self::MAX_CONSECUTIVE_SENDS) {
            // Reached 2 consecutive sends: lock for 3 minutes and reset count
            $lockUntil = time() + self::THROTTLE_LOCKOUT_SECONDS;
            Cache::put($lockKey, $lockUntil, self::THROTTLE_LOCKOUT_SECONDS);
            Cache::forget($countKey);
        } else {
            // Keep count valid for 10 minutes sliding window
            Cache::put($countKey, $count, 600);
        }
    }

    /**
     * Determine if the request comes from a trusted/verified device/browser/location for this user.
     */
    public function isDeviceKnownAndVerified(User $user, Request $request): bool
    {
        // Excluded accounts from OTP requirement
        if (strtolower($user->email) === 'harry.aga@dex-pms.local') {
            return true;
        }

        $deviceInfo = $this->deviceDetectionService->detect($request);

        $device = UserDevice::where('user_id', $user->id)
            ->where('fingerprint', $deviceInfo['fingerprint'])
            ->first();

        if ($device && $device->isVerified()) {
            $device->update([
                'last_active_at' => now(),
                'ip_address' => $deviceInfo['ip_address'],
                'location' => $deviceInfo['location'],
            ]);

            return true;
        }

        return false;
    }

    /**
     * Generate and dispatch a 6-digit OTP for device verification.
     */
    public function sendDeviceVerificationOtp(User $user, Request $request): EmailOtp
    {
        $deviceInfo = $this->deviceDetectionService->detect($request);

        // Invalidate prior pending OTPs for this user & purpose
        EmailOtp::where('user_id', $user->id)
            ->where('purpose', 'device_verification')
            ->delete();

        // 6-digit cryptographic random number
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = EmailOtp::create([
            'user_id' => $user->id,
            'code' => $code,
            'purpose' => 'device_verification',
            'fingerprint' => $deviceInfo['fingerprint'],
            'expires_at' => Carbon::now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            'attempts' => 0,
        ]);

        // Send email via configured Mailable
        try {
            Mail::to($user->email)->send(new DeviceVerificationOtpMail($user, $code, $deviceInfo));
        } catch (\Throwable $e) {
            Log::error("Failed to send Device Verification OTP email to {$user->email}: " . $e->getMessage());
        }

        return $otp;
    }

    /**
     * Generate and dispatch a 6-digit OTP for action verification (e.g. email change, password update).
     */
    public function sendActionOtp(User $user, string $purpose, Request $request, ?string $recipientEmail = null): EmailOtp
    {
        $deviceInfo = $this->deviceDetectionService->detect($request);

        EmailOtp::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->delete();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = EmailOtp::create([
            'user_id' => $user->id,
            'code' => $code,
            'purpose' => $purpose,
            'fingerprint' => $deviceInfo['fingerprint'],
            'expires_at' => Carbon::now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            'attempts' => 0,
        ]);

        $email = $recipientEmail ?: $user->email;

        try {
            Mail::to($email)->send(new DeviceVerificationOtpMail($user, $code, $deviceInfo));
        } catch (\Throwable $e) {
            Log::error("Failed to send {$purpose} OTP email to {$email}: " . $e->getMessage());
        }

        return $otp;
    }

    /**
     * Verify OTP for a specific purpose without marking devices.
     *
     * @return array{success: bool, message?: string}
     */
    public function verifyActionOtp(User $user, string $purpose, string $code): array
    {
        $otp = EmailOtp::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->latest()
            ->first();

        if (! $otp) {
            return [
                'success' => false,
                'message' => 'No verification code was requested or it has already been used.',
            ];
        }

        if ($otp->isExpired()) {
            return [
                'success' => false,
                'message' => 'The verification code has expired. Please request a new one.',
            ];
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            $otp->delete();

            return [
                'success' => false,
                'message' => 'Too many failed verification attempts. Please request a new code.',
            ];
        }

        if ($otp->code !== trim($code)) {
            $otp->increment('attempts');

            $remaining = self::MAX_ATTEMPTS - $otp->attempts;

            return [
                'success' => false,
                'message' => "Invalid code. You have {$remaining} attempt(s) remaining.",
            ];
        }

        $otp->delete();

        // Clear send throttle locks on successful verification
        Cache::forget("otp_send_count:{$user->id}:{$purpose}");
        Cache::forget("otp_send_lock:{$user->id}:{$purpose}");

        return ['success' => true];
    }

    /**
     * Verify the entered OTP and mark the device as trusted.
     *
     * @return array{success: bool, message?: string}
     */
    public function verifyDeviceOtp(User $user, string $code, Request $request): array
    {
        $deviceInfo = $this->deviceDetectionService->detect($request);

        $otp = EmailOtp::where('user_id', $user->id)
            ->where('purpose', 'device_verification')
            ->latest()
            ->first();

        if (! $otp) {
            return [
                'success' => false,
                'message' => 'No verification code was requested or it has already been used.',
            ];
        }

        if ($otp->isExpired()) {
            return [
                'success' => false,
                'message' => 'The verification code has expired. Please request a new one.',
            ];
        }

        if ($otp->attempts >= self::MAX_ATTEMPTS) {
            $otp->delete();

            return [
                'success' => false,
                'message' => 'Too many failed verification attempts. Please request a new code.',
            ];
        }

        if ($otp->code !== trim($code)) {
            $otp->increment('attempts');

            $remaining = self::MAX_ATTEMPTS - $otp->attempts;

            return [
                'success' => false,
                'message' => "Invalid code. You have {$remaining} attempt(s) remaining.",
            ];
        }

        // OTP verified successfully: mark or create user device record as verified
        UserDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'fingerprint' => $deviceInfo['fingerprint'],
            ],
            [
                'ip_address' => $deviceInfo['ip_address'],
                'browser' => $deviceInfo['browser'],
                'platform' => $deviceInfo['platform'],
                'device_type' => $deviceInfo['device_type'],
                'location' => $deviceInfo['location'],
                'verified_at' => now(),
                'last_active_at' => now(),
            ],
        );

        // Delete used OTP
        $otp->delete();

        // Clear send throttle locks on successful verification
        Cache::forget("otp_send_count:{$user->id}:{$purpose}");
        Cache::forget("otp_send_lock:{$user->id}:{$purpose}");

        return ['success' => true];
    }
}
