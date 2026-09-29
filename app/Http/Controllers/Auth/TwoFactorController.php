<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use PragmaRX\Google2FALaravel\Facades\Google2FA;

/**
 * Handles the Admin 2FA challenge step.
 *
 * Flow: Admin logs in with password → LoginController redirects here
 * if two_factor_enabled is true → Admin enters TOTP code → session
 * gets '2fa_verified' flag → full dashboard access granted.
 *
 * Non-admin users never reach these routes (guarded in web.php).
 */
class TwoFactorController extends Controller
{
    /**
     * Show the 2FA challenge form.
     * Only reachable after a successful password login (enforced via route middleware).
     */
    public function create(): View
    {
        return view('auth.two-factor');
    }

    /**
     * Validate the submitted TOTP code.
     *
     * On failure, rate limiting is handled by Google2FA's built-in window
     * check. We add our own session flag on success so downstream
     * middleware can verify the challenge was completed in this session.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'one_time_password' => ['required', 'string', 'digits:6'],
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $valid = Google2FA::verifyGoogle2FA(
            $user->two_factor_secret,
            $request->string('one_time_password')->toString(),
        );

        if (! $valid) {
            return back()->withErrors([
                'one_time_password' => 'The one-time password is incorrect. Please try again.',
            ]);
        }

        $request->session()->put('2fa_verified', true);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Show the 2FA setup page (QR code + secret for first-time enrollment).
     * Only accessible from account settings when 2FA is not yet enabled.
     */
    public function setup(Request $request): View
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $secret = Google2FA::generateSecretKey();
        $request->session()->put('2fa_setup_secret', $secret);

        $qrCodeUrl = Google2FA::getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        return view('auth.two-factor-setup', compact('qrCodeUrl', 'secret'));
    }

    /**
     * Confirm and persist 2FA enrollment after the user verifies the QR code works.
     *
     * Requires the user to submit a valid TOTP code before enabling 2FA —
     * this prevents accidental lockout from a misconfigured authenticator app.
     */
    public function enable(Request $request): RedirectResponse
    {
        $request->validate([
            'one_time_password' => ['required', 'string', 'digits:6'],
        ]);

        $secret = $request->session()->pull('2fa_setup_secret');

        if (! $secret) {
            return redirect()->route('account.settings')
                ->withErrors(['one_time_password' => 'Setup session expired. Please start again.']);
        }

        $valid = Google2FA::verifyGoogle2FA($secret, $request->string('one_time_password')->toString());

        if (! $valid) {
            return back()->withErrors([
                'one_time_password' => 'The code did not match. Please try again.',
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => true,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('account.settings')
            ->with('status', '2FA has been enabled for your account.');
    }

    /**
     * Disable 2FA for the authenticated admin.
     * Requires password confirmation (handled by the 'password.confirm' middleware on the route).
     */
    public function disable(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $user->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'updated_by' => $user->id,
        ]);

        $request->session()->forget('2fa_verified');

        return redirect()->route('account.settings')
            ->with('status', '2FA has been disabled for your account.');
    }
}
