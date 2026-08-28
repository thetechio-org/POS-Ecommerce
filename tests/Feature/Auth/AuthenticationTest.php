<?php

use App\Models\Role;
use App\Models\User;

/**
 * This application serves the login screen at "/" rather than "/login", and
 * refuses to authenticate a user whose status is not Active.
 */
function activeUser(): User
{
    return User::factory()->create([
        'role_id' => Role::firstOrCreate(['name' => 'Admin'])->id,
        'status'  => 'Active',
    ]);
}

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = activeUser();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = activeUser();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = activeUser();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    // Staff return to the staff sign-in page, not to the shop that now owns "/".
    $response->assertRedirect(route('login'));
});
