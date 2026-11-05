<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeviceDetectionService
{
    /**
     * Parse client information from HTTP request.
     *
     * @return array{
     *     fingerprint: string,
     *     ip_address: string,
     *     browser: string,
     *     platform: string,
     *     device_type: string,
     *     location: string
     * }
     */
    public function detect(Request $request): array
    {
        $ip = $request->ip() ?? '127.0.0.1';
        $userAgent = $request->userAgent() ?? 'Unknown User-Agent';

        $browser = $this->parseBrowser($userAgent);
        $platform = $this->parsePlatform($userAgent);
        $deviceType = $this->parseDeviceType($userAgent);
        $location = $this->resolveLocation($ip);

        // Fingerprint combines browser, platform, device type, and client IP subnet/location
        // to detect new browser/device or location changes
        $fingerprint = hash('sha256', "{$browser}|{$platform}|{$deviceType}|{$ip}");

        return [
            'fingerprint' => $fingerprint,
            'ip_address' => $ip,
            'browser' => $browser,
            'platform' => $platform,
            'device_type' => $deviceType,
            'location' => $location,
        ];
    }

    /**
     * Resolve geographical location using an IP Geolocation API with timeout safeguards.
     */
    public function resolveLocation(string $ip): string
    {
        // Check for local or private IP addresses
        if ($this->isPrivateOrReservedIp($ip)) {
            return 'Local / Private Network';
        }

        try {
            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}?fields=status,message,country,regionName,city");

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'success') {
                    $parts = array_filter([
                        $data['city'] ?? null,
                        $data['regionName'] ?? null,
                        $data['country'] ?? null,
                    ]);

                    return ! empty($parts) ? implode(', ', $parts) : 'Unknown Location';
                }
            }
        } catch (\Throwable $e) {
            Log::warning("GeoIP resolution failed for IP {$ip}: " . $e->getMessage());
        }

        return 'Unknown Location';
    }

    private function isPrivateOrReservedIp(string $ip): bool
    {
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    private function parseBrowser(string $userAgent): string
    {
        if (preg_match('/Edg(?:e|A|iOS)?\/([0-9.]+)/i', $userAgent)) {
            return 'Microsoft Edge';
        }
        if (preg_match('/Chrome\/([0-9.]+)/i', $userAgent) && ! preg_match('/Edg/i', $userAgent)) {
            return 'Google Chrome';
        }
        if (preg_match('/Firefox\/([0-9.]+)/i', $userAgent)) {
            return 'Mozilla Firefox';
        }
        if (preg_match('/Safari\/([0-9.]+)/i', $userAgent) && ! preg_match('/Chrome/i', $userAgent)) {
            return 'Apple Safari';
        }
        if (preg_match('/OPR\/([0-9.]+)/i', $userAgent) || preg_match('/Opera/i', $userAgent)) {
            return 'Opera';
        }

        return 'Web Browser';
    }

    private function parsePlatform(string $userAgent): string
    {
        if (preg_match('/windows nt 10/i', $userAgent)) {
            return 'Windows 10/11';
        }
        if (preg_match('/windows/i', $userAgent)) {
            return 'Windows';
        }
        if (preg_match('/macintosh|mac os x/i', $userAgent)) {
            return 'macOS';
        }
        if (preg_match('/linux/i', $userAgent)) {
            return 'Linux';
        }
        if (preg_match('/iphone/i', $userAgent)) {
            return 'iOS (iPhone)';
        }
        if (preg_match('/ipad/i', $userAgent)) {
            return 'iPadOS (iPad)';
        }
        if (preg_match('/android/i', $userAgent)) {
            return 'Android';
        }

        return 'Unknown OS';
    }

    private function parseDeviceType(string $userAgent): string
    {
        if (preg_match('/mobile|android.*mobile|iphone|ipod/i', $userAgent)) {
            return 'Mobile Phone';
        }
        if (preg_match('/tablet|ipad|android(?!.*mobile)/i', $userAgent)) {
            return 'Tablet';
        }

        return 'Desktop / Laptop';
    }
}
