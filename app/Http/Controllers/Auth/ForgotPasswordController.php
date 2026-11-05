<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ForgotPasswordController extends Controller
{
    public function __construct(
        protected PasswordResetService $passwordResetService,
    ) {
    }

    /**
     * Handle request for a randomized new password via email.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string'],
        ]);

        $result = $this->passwordResetService->requestNewPassword($request->string('email')->toString());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
            ], $result['success'] ? 200 : 422);
        }

        if (! $result['success']) {
            return back()->withErrors(['email' => $result['message']]);
        }

        return back()->with('status', $result['message']);
    }
}
