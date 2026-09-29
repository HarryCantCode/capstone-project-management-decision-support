<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
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
     * Validation and throttle checking are delegated to LoginRequest.
     * If the user is Admin and has 2FA enabled, redirect to the 2FA
     * challenge view instead of the dashboard directly.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // If the authenticated user is an Admin with 2FA enabled,
        // redirect to the 2FA challenge before granting full access.
        if ($user->hasRole('Admin') && $user->two_factor_enabled) {
            return redirect()->route('auth.two-factor');
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
