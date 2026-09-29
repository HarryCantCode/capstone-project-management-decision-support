<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * AccountUserController
 *
 * Manages the CRUD operations for user (employee) accounts.
 * Restricted to users with the Admin role.
 */
class AccountUserController extends Controller implements HasMiddleware
{
    /**
     * Predefined job titles available in the system.
     */
    public const JOB_TITLES = [
        'Manager',
        'Engineer',
        'CEO',
        'Inventory personnel',
        'Designers personnel',
    ];

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            'auth',
            'two-factor',
            'role:Admin',
        ];
    }

    /**
     * Show the form for creating a new user.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $roles = Role::all();
        $nextEmployeeNumber = User::generateEmployeeNumber();
        $nextAccountNumber = User::generateAccountNumber();
        $jobTitles = self::JOB_TITLES;

        return view('account.users.create', compact('roles', 'nextEmployeeNumber', 'nextAccountNumber', 'jobTitles'));
    }

    /**
     * Store a newly created user in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date', 'before:today'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'job_title_choice' => ['required', 'string'],
            'job_title_other' => ['nullable', 'required_if:job_title_choice,Others', 'string', 'max:255'],
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        // Determine actual job title
        $jobTitle = $validated['job_title_choice'] === 'Others'
            ? trim($validated['job_title_other'] ?? '')
            : $validated['job_title_choice'];

        // Auto-generate employee number and 7-digit account number
        $employeeNumber = User::generateEmployeeNumber();
        $accountNumber = User::generateAccountNumber();

        // Auto-generate password with format: @ + lastname + DEX + birthyear
        // Example: @smithDEX2000
        $birthYear = \Carbon\Carbon::parse($validated['birthdate'])->format('Y');
        $cleanLastName = strtolower(preg_replace('/\s+/', '', $validated['last_name']));
        $plainPassword = '@' . $cleanLastName . 'DEX' . $birthYear;

        $fullNameParts = array_filter([
            $validated['first_name'],
            $validated['middle_name'] ?? null,
            $validated['last_name'],
        ]);
        $fullName = implode(' ', $fullNameParts);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'birthdate' => $validated['birthdate'],
            'name' => $fullName,
            'email' => $validated['email'],
            'employee_number' => $employeeNumber,
            'account_number' => $accountNumber,
            'job_title' => $jobTitle,
            'password' => Hash::make($plainPassword),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('account.settings')->with(
            'status',
            "User account for {$fullName} ({$employeeNumber}, Acc: {$accountNumber}) created successfully. Default password is: {$plainPassword}",
        );
    }

    /**
     * Show the form for editing the specified user.
     *
     * @param \App\Models\User $user
     * @return \Illuminate\View\View
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        $jobTitles = self::JOB_TITLES;

        return view('account.users.edit', compact('user', 'roles', 'jobTitles'));
    }

    /**
     * Update the specified user in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\User $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'job_title_choice' => ['required', 'string'],
            'job_title_other' => ['nullable', 'required_if:job_title_choice,Others', 'string', 'max:255'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $jobTitle = $validated['job_title_choice'] === 'Others'
            ? trim($validated['job_title_other'] ?? '')
            : $validated['job_title_choice'];

        $fullNameParts = array_filter([
            $validated['first_name'],
            $validated['middle_name'] ?? null,
            $validated['last_name'],
        ]);
        $fullName = implode(' ', $fullNameParts);

        $user->first_name = $validated['first_name'];
        $user->middle_name = $validated['middle_name'] ?? null;
        $user->last_name = $validated['last_name'];
        if (!empty($validated['birthdate'])) {
            $user->birthdate = $validated['birthdate'];
        }
        $user->name = $fullName;
        $user->email = $validated['email'];
        $user->job_title = $jobTitle;
        $user->updated_by = auth()->id();

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        return redirect()->route('account.settings')->with('status', 'User account updated successfully.');
    }

    /**
     * Archive the specified user account (soft delete).
     * Prevents the currently authenticated user from archiving themselves.
     *
     * @param \App\Models\User $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(User $user)
    {
        // Prevent archiving oneself
        if (auth()->id() === $user->id) {
            return back()->with('error', 'You cannot archive your own account.');
        }

        $name = $user->name;
        $empNo = $user->employee_number ?? $user->account_number;
        $user->delete();

        return redirect()->route('account.settings')->with('status', "User account {$name} ({$empNo}) has been archived and moved to Archived Accounts.");
    }

    /**
     * Restore and activate an archived user account.
     *
     * @param string|int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore($id)
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->save();
        $user->restore();

        return redirect()->route('account.settings')->with('status', "User account {$user->name} ({$user->employee_number}) has been activated successfully.");
    }

    /**
     * Unlock and activate a temporarily locked user account.
     *
     * @param \App\Models\User $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function unlock(User $user)
    {
        $user->unlock();

        return redirect()->route('account.settings')->with(
            'status',
            "User account {$user->name} ({$user->employee_number}) has been activated and unlocked successfully.",
        );
    }
}
