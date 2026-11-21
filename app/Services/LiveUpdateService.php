<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * LiveUpdateService — lightweight Redis/cache-backed version tracker
 * for real-time UI synchronization across LAN clients without requiring
 * a separate WebSocket daemon.
 */
class LiveUpdateService
{
    private const KEY_PREFIX = 'live_updates:v:';

    /**
     * Bump the monotonic version token for a global domain.
     */
    public function bump(string $domain): void
    {
        Cache::forever(self::KEY_PREFIX . $domain, $this->nextToken());

        if (in_array($domain, ['projects', 'pending_actions', 'resources'], true)) {
            Cache::forget('dashboard.stats.global');
        }
    }

    /**
     * Bump the notification version token for one or more user IDs.
     *
     * @param int|array<int, int|string> $userIds
     */
    public function bumpNotifications(int|array $userIds): void
    {
        $ids = is_array($userIds) ? $userIds : [$userIds];
        $token = $this->nextToken();

        foreach (array_unique($ids) as $userId) {
            if ($userId) {
                Cache::forever(self::KEY_PREFIX . 'notifications:' . $userId, $token);
            }
        }
    }

    /**
     * Retrieve current version tokens for the given user.
     *
     * @return array<string, string>
     */
    public function getVersions(int $userId): array
    {
        return [
            'notifications' => (string) Cache::get(self::KEY_PREFIX . 'notifications:' . $userId, '0'),
            'pending_actions' => (string) Cache::get(self::KEY_PREFIX . 'pending_actions', '0'),
            'projects' => (string) Cache::get(self::KEY_PREFIX . 'projects', '0'),
            'resources' => (string) Cache::get(self::KEY_PREFIX . 'resources', '0'),
        ];
    }

    private function nextToken(): string
    {
        return (string) hrtime(true);
    }
}
