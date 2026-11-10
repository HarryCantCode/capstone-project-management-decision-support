<?php

namespace App\Http\Controllers;

use App\Models\StaffPendingAction;
use App\Services\StaffPendingActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\View\View;

/**
 * StaffApprovalListController — allows staff members to view, search, filter,
 * inspect details, and ping/remind reviewers about their submitted requests.
 */
class StaffApprovalListController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'two-factor',
        ];
    }

    public function __construct(
        private readonly StaffPendingActionService $staffPendingActionService,
    ) {
    }

    /**
     * Display the Staff Approval List module.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewOwnList', StaffPendingAction::class);

        $data = $this->staffPendingActionService->getStaffApprovalListData(
            $request->user(),
            $request->only(['search', 'status', 'recency', 'action_type']),
        );

        return view('approval-list.index', $data);
    }

    /**
     * Retrieve details for a specific pending action submitted by the user.
     */
    public function show(StaffPendingAction $pendingAction): JsonResponse
    {
        $this->authorize('view', $pendingAction);

        $pendingAction->load(['user', 'project', 'reviewer']);

        return response()->json([
            'success' => true,
            'action' => $pendingAction,
            'formatted_action' => $this->staffPendingActionService->formatActionForModal($pendingAction),
        ]);
    }

    /**
     * Ping / send a reminder for a pending action with a 15-minute cooldown.
     */
    public function ping(Request $request, StaffPendingAction $pendingAction): JsonResponse
    {
        $this->authorize('ping', $pendingAction);

        $result = $this->staffPendingActionService->ping($pendingAction, $request->user());

        return response()->json($result, $result['status_code'] ?? 200);
    }
}
