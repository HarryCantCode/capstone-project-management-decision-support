<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures that users who must change their temporary password (e.g. on first login)
 * are redirected to the password rotation screen before they can access any protected
 * parts of the system.
 */
class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if ($user && $user->must_change_password) {
            // Allow first-login password change routes and logout to proceed
            if (
                $request->routeIs('password.first-login') ||
                $request->routeIs('password.first-login.update') ||
                $request->routeIs('logout')
            ) {
                return $next($request);
            }

            return redirect()->route('password.first-login')->with(
                'warning',
                'Please update your temporary password before accessing the system.',
            );
        }

        // If the user does not need to change password, prevent them from accessing first-login page
        if ($user && ! $user->must_change_password) {
            if ($request->routeIs('password.first-login') || $request->routeIs('password.first-login.update')) {
                return redirect()->intended(
                    $user->hasRole('Staff') ? route('resources.index') : route('dashboard'),
                );
            }
        }

        return $next($request);
    }
}
