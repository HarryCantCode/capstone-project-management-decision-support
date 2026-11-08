<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * NotificationController — API endpoints for the notification bell.
 *
 * All endpoints return JSON for the Alpine.js dropdown component.
 * Authorization: every action is scoped to the authenticated user's
 * own notifications — no cross-user access is possible.
 */
class NotificationController extends Controller
{
    /**
     * List the current user's notifications.
     * Supports filtering: 'all' (default), 'unread', or 'read'.
     *
     * Returns the 50 most recent notifications, plus an unread count.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $filter = $request->input('filter', 'all');

        $query = Notification::where('user_id', $user->id);

        if ($filter === 'unread') {
            $query->active()->unread();
        } elseif ($filter === 'read') {
            $query->active()->whereNotNull('read_at');
        } elseif ($filter === 'archived') {
            $query->archived();
        } else {
            $query->active();
        }

        $notifications = $query->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Notification $n) => $this->formatNotification($n));

        $unreadCount = Notification::where('user_id', $user->id)
            ->active()
            ->unread()
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Get the unread count only (lightweight poll endpoint).
     */
    public function unreadCount(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $count = Notification::where('user_id', $user->id)
            ->active()
            ->unread()
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Notification $notification): JsonResponse
    {
        $this->authorizeOwnership($notification);

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    /**
     * Mark all notifications as read for the current user.
     */
    public function markAllRead(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        Notification::where('user_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
        ]);
    }

    /**
     * Archive a single notification.
     */
    public function archive(Notification $notification): JsonResponse
    {
        $this->authorizeOwnership($notification);

        $notification->archive();

        return response()->json([
            'success' => true,
            'message' => 'Notification archived.',
        ]);
    }

    /**
     * Restore / unarchive a notification.
     */
    public function unarchive(Notification $notification): JsonResponse
    {
        $this->authorizeOwnership($notification);

        $notification->update(['archived_at' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Notification restored from archive.',
        ]);
    }

    /**
     * Ensure the authenticated user owns this notification.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function authorizeOwnership(Notification $notification): void
    {
        abort_if($notification->user_id !== Auth::id(), 403, 'Unauthorized.');
    }

    /**
     * Format a notification model into a JSON-friendly array.
     *
     * @return array<string, mixed>
     */
    private function formatNotification(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'icon' => $notification->icon,
            'title' => $notification->title,
            'message' => $notification->message,
            'url' => $notification->url,
            'data' => $notification->data,
            'is_read' => $notification->isRead(),
            'time_ago' => $notification->created_at->diffForHumans(),
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }
}
