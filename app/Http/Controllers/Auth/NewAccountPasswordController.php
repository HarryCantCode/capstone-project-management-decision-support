<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PasswordComplexity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class NewAccountPasswordController extends Controller
{
    /**
     * Show the password creation form for newly invited users via signed URL or token.
     */
    public function create(Request $request, string $token): View|RedirectResponse
    {
        // If another session is already authenticated on this browser, log them out
        // so the user can cleanly establish credentials for this account without guest redirection conflicts.
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $email = $request->query('email');

        if (! $email) {
            return redirect()->route('login')->withErrors(['email' => 'Invalid password creation link.']);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'User account not found.']);
        }

        // Verify token against password_resets table
        $tokenValid = Password::broker()->tokenExists($user, $token);
        if (! $tokenValid) {
            return redirect()->route('login')->withErrors(['email' => 'This password creation link is invalid or has expired. Please contact your administrator.']);
        }

        return view('auth.set-new-account-password', [
            'user' => $user,
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Store the newly established password and log the user in.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            return back()->withErrors(['email' => 'We could not find an active account with that email address.']);
        }

        $tokenValid = Password::broker()->tokenExists($user, $request->input('token'));
        if (! $tokenValid) {
            return redirect()->route('login')->withErrors(['email' => 'This password creation link has expired or is invalid.']);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', new PasswordComplexity($user)],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        Password::broker()->deleteToken($user);

        app(\App\Services\NotificationService::class)->notifyPasswordChanged($user, $request->ip());

        // Log the newly password-established user in cleanly
        Auth::login($user);
        $request->session()->regenerate();

        // Forget any previous intended URL from an old/other session to avoid redirect conflicts
        $request->session()->forget('url.intended');

        $targetRoute = $user->defaultLandingRoute();

        return redirect($targetRoute)->with(
            'status',
            'Your password has been created successfully. Welcome to Dex International Co.!',
        );
    }
}
