<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('user with must_change_password flag is redirected to first-login password change on login', function () {
    $user = User::factory()->create([
        'password' => Hash::make('@tempPass123'),
        'must_change_password' => true,
    ]);
    $user->assignRole('Manager');

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => '@tempPass123',
    ]);

    $response->assertRedirect(route('password.first-login'));
    $this->assertAuthenticatedAs($user);
});

test('user with must_change_password is intercepted by middleware from accessing dashboard and projects', function () {
    $user = User::factory()->create([
        'must_change_password' => true,
    ]);
    $user->assignRole('Manager');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertRedirect(route('password.first-login'))
        ->assertSessionHas('warning');

    $this->actingAs($user)->get(route('projects.index'))
        ->assertRedirect(route('password.first-login'))
        ->assertSessionHas('warning');
});

test('user without must_change_password cannot access first-login page', function () {
    $user = User::factory()->create([
        'must_change_password' => false,
    ]);
    $user->assignRole('Manager');

    $this->actingAs($user)->get(route('password.first-login'))
        ->assertRedirect(route('dashboard'));
});

test('first-login password update rejects passwords less than 8 characters', function () {
    $user = User::factory()->create([
        'password' => Hash::make('@tempPass123'),
        'must_change_password' => true,
    ]);
    $user->assignRole('Manager');

    $response = $this->actingAs($user)->post(route('password.first-login.update'), [
        'password' => 'Sh0rt!',
        'password_confirmation' => 'Sh0rt!',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('first-login password update rejects passwords without capital letters', function () {
    $user = User::factory()->create([
        'password' => Hash::make('@tempPass123'),
        'must_change_password' => true,
    ]);
    $user->assignRole('Manager');

    $response = $this->actingAs($user)->post(route('password.first-login.update'), [
        'password' => 'nocapital123!',
        'password_confirmation' => 'nocapital123!',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('first-login password update rejects passwords without numbers', function () {
    $user = User::factory()->create([
        'password' => Hash::make('@tempPass123'),
        'must_change_password' => true,
    ]);
    $user->assignRole('Manager');

    $response = $this->actingAs($user)->post(route('password.first-login.update'), [
        'password' => 'NoNumbersHere!',
        'password_confirmation' => 'NoNumbersHere!',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('first-login password update rejects passwords without symbols', function () {
    $user = User::factory()->create([
        'password' => Hash::make('@tempPass123'),
        'must_change_password' => true,
    ]);
    $user->assignRole('Manager');

    $response = $this->actingAs($user)->post(route('password.first-login.update'), [
        'password' => 'NoSymbols1234',
        'password_confirmation' => 'NoSymbols1234',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('first-login password update rejects reusing the temporary password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('@smithDEX2000'),
        'must_change_password' => true,
    ]);
    $user->assignRole('Manager');

    $response = $this->actingAs($user)->post(route('password.first-login.update'), [
        'password' => '@smithDEX2000',
        'password_confirmation' => '@smithDEX2000',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('first-login password update succeeds with valid 8+ chars mix of symbols number and capital letter', function () {
    $user = User::factory()->create([
        'password' => Hash::make('@tempPass123'),
        'must_change_password' => true,
    ]);
    $user->assignRole('Manager');

    $response = $this->actingAs($user)->post(route('password.first-login.update'), [
        'password' => 'NewSecur3!P@ss',
        'password_confirmation' => 'NewSecur3!P@ss',
    ]);

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('status');

    $user->refresh();
    expect($user->must_change_password)->toBeFalse();
    expect($user->password_changed_at)->not->toBeNull();
    expect(Hash::check('NewSecur3!P@ss', $user->password))->toBeTrue();

    // Subsequent access to dashboard is now permitted
    $this->actingAs($user)->get(route('dashboard'))->assertStatus(200);
});

test('staff user is redirected to resources index after first-login password update', function () {
    $user = User::factory()->create([
        'password' => Hash::make('@tempPass123'),
        'must_change_password' => true,
    ]);
    $user->assignRole('Staff');

    $response = $this->actingAs($user)->post(route('password.first-login.update'), [
        'password' => 'St@ffSecure999!',
        'password_confirmation' => 'St@ffSecure999!',
    ]);

    $response->assertRedirect(route('resources.index'));
    $response->assertSessionHas('status');

    $user->refresh();
    expect($user->must_change_password)->toBeFalse();
});
