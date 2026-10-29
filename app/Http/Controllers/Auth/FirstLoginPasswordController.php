<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\PasswordComplexity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Handles the mandatory first-time login password change.
 *
 * Newly provisioned accounts are given an auto-generated temporary password
 * and have `must_change_password = true`. Upon first login, they are routed
 * here to establish their private, secure password before access is granted.
 */
class FirstLoginPasswordController extends Controller
{
    /**
     * Show the first-login password change form.
     */
    public function create(): View|RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->must_change_password) {
            return redirect()->intended(
                $user->hasRole('Staff') ? route('resources.index') : route('dashboard'),
            );
        }

        return view('auth.first-login-password', compact('user'));
    }

    /**
     * Process the first-login password update.
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', new PasswordComplexity()],
        ]);

        // Security requirement: New password must not match the temporary auto-generated password
        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Your new password cannot be the same as your temporary password.',
            ]);
        }

        $user->password = Hash::make($validated['password']);
        $user->must_change_password = false;
        $user->password_changed_at = now();
        $user->save();

        $targetRoute = $user->hasRole('Staff') ? route('resources.index') : route('dashboard');

        return redirect()->intended($targetRoute)->with(
            'status',
            'Your password has been updated successfully. Welcome to Dex PMS!',
        );
    }
}
