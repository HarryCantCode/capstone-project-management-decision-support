<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\StaffPendingAction;
use App\Services\LiveUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * LiveUpdateController — lightweight polling endpoint for real-time
 * notifications, pending requests, sidebar badges, and workspace changes.
 */
class LiveUpdateController extends Controller
{
    public function __construct(
        private readonly LiveUpdateService $liveUpdateService,
    ) {
    }

    public function poll(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $versions = $this->liveUpdateService->getVersions($user->id);

        $unreadNotifications = Notification::where('user_id', $user->id)
            ->active()
            ->unread()
            ->count();

        $latestNotification = Notification::where('user_id', $user->id)
            ->active()
            ->orderByDesc('id')
            ->first(['id', 'type', 'title', 'message', 'data', 'read_at', 'created_at']);

        $pendingActionsCount = 0;
        if (Schema::hasTable('staff_pending_actions')) {
            if ($user->hasAnyRole(['Admin', 'Manager'])) {
                $pendingActionsCount = StaffPendingAction::where('status', 'pending')->count();
            } elseif ($user->hasRole('Staff')) {
                $pendingActionsCount = StaffPendingAction::where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->count();
            }
        }

        return response()->json([
            'versions' => $versions,
            'unread_notifications' => $unreadNotifications,
            'latest_notification' => $latestNotification ? [
                'id' => $latestNotification->id,
                'type' => $latestNotification->type,
                'title' => $latestNotification->title,
                'message' => $latestNotification->message,
                'url' => $latestNotification->url,
                'is_read' => $latestNotification->isRead(),
            ] : null,
            'pending_actions_count' => $pendingActionsCount,
        ]);
    }
}
