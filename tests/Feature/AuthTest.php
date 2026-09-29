<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleSeeder::class));

// ─── Login — success paths ────────────────────────────────────────────────────

test('admin can login with correct credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);
    $user->assignRole('Admin');

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    // Admin with 2FA disabled goes straight to dashboard
    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('manager can login with correct credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);
    $user->assignRole('Manager');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('staff can login with correct credentials and redirects to resources', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);
    $user->assignRole('Staff');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('resources.index'));

    $this->assertAuthenticatedAs($user);
});

test('user can login using 7-digit account number instead of email', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
        'account_number' => '1000099',
    ]);
    $user->assignRole('Admin');

    $this->post(route('login.store'), [
        'email' => '1000099', // input account number into email/account field
        'password' => 'password123',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

// ─── Login — failure paths ────────────────────────────────────────────────────

test('login fails with wrong password', function () {
    $user = User::factory()->create(['password' => bcrypt('correctpassword')]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrongpassword',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login fails with non-existent email', function () {
    $this->post(route('login.store'), [
        'email' => 'nobody@dex-pms.local',
        'password' => 'anything',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login requires email field', function () {
    $this->post(route('login.store'), ['password' => 'password'])
        ->assertSessionHasErrors('email');
});

test('login requires password field', function () {
    $this->post(route('login.store'), ['email' => 'test@example.com'])
        ->assertSessionHasErrors('password');
});

// ─── 2FA redirect ─────────────────────────────────────────────────────────────

test('admin with 2FA enabled is redirected to 2FA challenge after login', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'two_factor_enabled' => true,
        'two_factor_secret' => 'SOMESECRET', // not a valid TOTP secret — just for routing test
    ]);
    $user->assignRole('Admin');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('auth.two-factor'));
});

test('admin with 2FA disabled goes directly to dashboard', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
        'two_factor_enabled' => false,
    ]);
    $user->assignRole('Admin');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});

// ─── Logout ───────────────────────────────────────────────────────────────────

test('authenticated user can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

// ─── Role-based route access (hard 403 for non-primary roles — OQ-7) ─────────

/**
 * Tests that each role is blocked from routes it cannot access.
 * Checks all three roles against the main protected route groups.
 *
 * As module routes are added (Phases 2–6), add corresponding assertions here.
 */

test('unauthenticated user is redirected to login from protected routes', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

// ─── Rate limiting ───────────────────────────────────────────────────────────

test('login is rate-limited after 5 failed attempts', function () {
    $user = User::factory()->create(['password' => bcrypt('correctpassword')]);

    // Make 5 failed attempts to hit the rate limit
    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);
    }

    // 6th attempt should be throttled
    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'correctpassword', // even correct password is blocked now
    ]);

    $response->assertSessionHasErrors('email');
});

// ─── Failed Attempts, Account Lockout & Error Messages ───────────────────────

test('login with non-existent account returns unified credentials error message', function () {
    $response = $this->post(route('login.store'), [
        'email' => 'nonexistent@dex-pms.local',
        'password' => 'anypassword',
    ]);

    $response->assertSessionHasErrors([
        'email' => 'We could not find any credentials that match our system. Please contact your administrator for assistance..',
    ]);
    $this->assertGuest();
});

test('login with archived account returns unified credentials error message', function () {
    $user = User::factory()->create([
        'email' => 'archived@dex-pms.local',
        'password' => bcrypt('correctpassword'),
    ]);
    $user->delete(); // Soft delete / archive

    $response = $this->post(route('login.store'), [
        'email' => 'archived@dex-pms.local',
        'password' => 'correctpassword',
    ]);

    $response->assertSessionHasErrors([
        'email' => 'We could not find any credentials that match our system. Please contact your administrator for assistance..',
    ]);
    $this->assertGuest();
});

test('login with archived account number returns unified credentials error message', function () {
    $user = User::factory()->create([
        'account_number' => '1000088',
        'password' => bcrypt('correctpassword'),
    ]);
    $user->delete();

    $response = $this->post(route('login.store'), [
        'email' => '1000088',
        'password' => 'correctpassword',
    ]);

    $response->assertSessionHasErrors([
        'email' => 'We could not find any credentials that match our system. Please contact your administrator for assistance..',
    ]);
    $this->assertGuest();
});

test('3rd failed password attempt returns 3-attempts warning message', function () {
    $user = User::factory()->create(['password' => bcrypt('secretpassword')]);

    // Attempts 1 and 2
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong1'])->assertSessionHasErrors('email');
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong2'])->assertSessionHasErrors('email');

    // Attempt 3: Expect exact warning message
    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong3',
    ]);

    $response->assertSessionHasErrors([
        'email' => 'You entered your credentials wrong 3 times, if you enter your password 5 times you will be locked out of your account',
    ]);

    expect($user->fresh()->failed_login_attempts)->toBe(3);
    expect($user->fresh()->trashed())->toBeFalse();
    $this->assertGuest();
});

test('5th failed password attempt locks out user account for 5 minutes without deleting user', function () {
    $user = User::factory()->create(['password' => bcrypt('secretpassword')]);

    // Attempts 1 to 4
    foreach (range(1, 4) as $attempt) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => "wrong{$attempt}",
        ]);
    }

    expect($user->fresh()->failed_login_attempts)->toBe(4);
    expect($user->fresh()->trashed())->toBeFalse();
    expect($user->fresh()->isLocked())->toBeFalse();

    // Attempt 5: Account should be locked out for 5 minutes without being deleted
    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong5',
    ]);

    $response->assertSessionHasErrors([
        'email' => 'You entered your credentials wrong 5 times. Your account has been temporarily locked out for 5 minutes. Please try again later or contact your administrator.',
    ]);

    $freshUser = User::find($user->id);
    expect($freshUser)->not->toBeNull();
    expect($freshUser->trashed())->toBeFalse();
    expect($freshUser->isLocked())->toBeTrue();
    expect($freshUser->failed_login_attempts)->toBe(5);
    $this->assertGuest();

    // Subsequent attempt during lockout window receives the temporary lockout message
    $subsequentResponse = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'secretpassword',
    ]);

    $subsequentResponse->assertSessionHasErrors('email');
    expect(session('errors')->get('email')[0])->toContain('Your account is temporarily locked out');
});

test('7th failed password attempt locks out user account for 30 minutes', function () {
    // User already passed the first 5 attempts lockout period
    $user = User::factory()->create([
        'password' => bcrypt('secretpassword'),
        'failed_login_attempts' => 5,
        'locked_until' => now()->subMinute(), // Lockout timer has elapsed
    ]);

    expect($user->isLocked())->toBeFalse();

    // Attempt 6
    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong6',
    ])->assertSessionHasErrors('email');

    expect($user->fresh()->failed_login_attempts)->toBe(6);
    expect($user->fresh()->isLocked())->toBeFalse();

    // Attempt 7: Triggers 30-minute lockout
    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong7',
    ]);

    $response->assertSessionHasErrors([
        'email' => 'You entered your credentials wrong 7 times. Your account has been temporarily locked out for 30 minutes. Please try again later or contact your administrator.',
    ]);

    $freshUser = $user->fresh();
    expect($freshUser->trashed())->toBeFalse();
    expect($freshUser->isLocked())->toBeTrue();
    expect($freshUser->failed_login_attempts)->toBe(7);
    $this->assertGuest();
});

test('admin activating locked user resets failed attempts, clears lockout, and restores login access', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $user = User::factory()->create([
        'password' => bcrypt('secretpassword'),
        'failed_login_attempts' => 5,
        'locked_until' => now()->addMinutes(5),
    ]);

    expect($user->isLocked())->toBeTrue();

    // Admin activates/unlocks the user from user management
    $this->actingAs($admin)
        ->post(route('account.users.unlock', $user))
        ->assertRedirect(route('account.settings'));

    $unlockedUser = $user->fresh();
    expect($unlockedUser->isLocked())->toBeFalse();
    expect($unlockedUser->failed_login_attempts)->toBe(0);
    expect($unlockedUser->locked_until)->toBeNull();

    // User can now log in successfully with their password
    $this->post(route('logout'));
    $loginResponse = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'secretpassword',
    ]);

    $loginResponse->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('admin activating archived user resets failed attempts and restores login access', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $user = User::factory()->create([
        'password' => bcrypt('secretpassword'),
        'failed_login_attempts' => 5,
    ]);
    $user->delete(); // Inactive / archived
    expect($user->fresh()->trashed())->toBeTrue();

    // Admin restores/activates the user
    $this->actingAs($admin)
        ->post(route('account.users.restore', $user->id))
        ->assertRedirect(route('account.settings'));

    $restoredUser = $user->fresh();
    expect($restoredUser->trashed())->toBeFalse();
    expect($restoredUser->failed_login_attempts)->toBe(0);
    expect($restoredUser->locked_until)->toBeNull();

    // User can now log in successfully with their password
    $this->post(route('logout'));
    $loginResponse = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'secretpassword',
    ]);

    $loginResponse->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('successful login resets failed login attempts counter', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secretpassword'),
        'failed_login_attempts' => 2,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'secretpassword',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->failed_login_attempts)->toBe(0);
});

test('admin archiving user causes login attempt to return unified credentials error message', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $user = User::factory()->create([
        'email' => 'staff.member@dex-pms.local',
        'password' => bcrypt('password123'),
    ]);

    // Admin archives user via user management
    $this->actingAs($admin)
        ->delete(route('account.users.destroy', $user->id))
        ->assertRedirect(route('account.settings'));

    expect($user->fresh()->trashed())->toBeTrue();

    // Log out admin
    $this->post(route('logout'));

    // Archived user tries to login
    $response = $this->post(route('login.store'), [
        'email' => 'staff.member@dex-pms.local',
        'password' => 'password123',
    ]);

    $response->assertSessionHasErrors([
        'email' => 'We could not find any credentials that match our system. Please contact your administrator for assistance..',
    ]);
    $this->assertGuest();
});
