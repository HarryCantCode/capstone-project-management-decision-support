<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewStaffPendingActionRequest;
use App\Models\StaffPendingAction;
use App\Services\StaffPendingActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * StaffPendingActionController — thin orchestrator for the Staff Request Approvals module.
 *
 * Business logic resides in StaffPendingActionService.
 * Authorization is enforced via StaffPendingActionPolicy and role middleware.
 */
class StaffPendingActionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'two-factor',
            'role:Admin|Manager',
        ];
    }

    public function __construct(
        private readonly StaffPendingActionService $staffPendingActionService,
    ) {
    }

    /**
     * Display the Staff Request Approvals module (Request Approvals List & View from Staff List).
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StaffPendingAction::class);

        $data = $this->staffPendingActionService->getModuleData(
            $request->only(['search', 'status', 'recency', 'action_type', 'view']),
        );

        return view('pending-actions.index', $data);
    }

    /**
     * Retrieve details for a specific pending staff action.
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
     * Approve and execute a pending staff action.
     */
    public function approve(ReviewStaffPendingActionRequest $request, StaffPendingAction $pendingAction): JsonResponse|RedirectResponse
    {
        $this->authorize('review', $pendingAction);

        $result = $this->staffPendingActionService->approve(
            $pendingAction,
            (int) Auth::id(),
            $request->validated('notes'),
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result, $result['status_code']);
        }

        return back()->with($result['success'] ? 'status' : 'error', $result['message']);
    }

    /**
     * Reject a pending staff action.
     */
    public function reject(ReviewStaffPendingActionRequest $request, StaffPendingAction $pendingAction): JsonResponse|RedirectResponse
    {
        $this->authorize('review', $pendingAction);

        $result = $this->staffPendingActionService->reject(
            $pendingAction,
            (int) Auth::id(),
            $request->validated('notes'),
        );

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result, $result['status_code']);
        }

        return back()->with($result['success'] ? 'warning' : 'error', $result['message']);
    }
}
