<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
    ) {
    }

    /**
     * Show the login form.
     *
     * Guests only — authenticated users are redirected to the dashboard.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * Validates credentials and verifies device/browser/location via OTP if unknown.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $result = $request->validateCredentialsAndCheckDevice($this->otpService);

        if ($result['needs_device_verification']) {
            $request->session()->put('device_verification_user_id', $result['user']->id);
            $request->session()->put('device_verification_remember', $request->boolean('remember'));

            return redirect()->route('auth.device-verification');
        }

        $request->session()->regenerate();

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // If the authenticated user is an Admin with 2FA enabled,
        // redirect to the 2FA challenge before granting full access.
        if ($user->hasRole('Admin') && $user->two_factor_enabled) {
            return redirect()->route('auth.two-factor');
        }

        // Mandatory first-login or reset password change check
        if ($user->must_change_password) {
            return redirect()->route('password.first-login');
        }

        // Staff cannot access the dashboard
        if ($user->hasRole('Staff')) {
            return redirect()->route('resources.index');
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
