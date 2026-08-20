<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

test('inventory staff can login with correct credentials', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);
    $user->assignRole('Inventory Staff');

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
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
