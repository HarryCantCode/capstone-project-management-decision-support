<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures Admin users with 2FA enabled have completed the TOTP challenge
 * in their current session before accessing protected routes.
 *
 * Why this middleware instead of relying only on the login flow:
 * A user's session could theoretically be resumed after the 2FA step
 * if session regeneration is misconfigured. This middleware provides
 * defense-in-depth by checking the session flag on every request through
 * the guarded routes, not just at login time.
 *
 * Non-admin users pass through unconditionally — 2FA is only required
 * for the Admin role (per security.md).
 */
class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (
            $user
            && $user->hasRole('Admin')
            && $user->two_factor_enabled
            && ! $request->session()->get('2fa_verified')
        ) {
            return redirect()->route('auth.two-factor');
        }

        return $next($request);
    }
}
