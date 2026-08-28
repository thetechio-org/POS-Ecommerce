<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Self-service registration is deliberately switched off for the admin panel —
 * staff accounts are created by an Admin through /user/create. These tests hold
 * that decision in place, so re-enabling it has to be a deliberate act.
 */
test('the admin panel has no public registration route', function () {
    expect(Route::has('register'))->toBeFalse();
});

test('posting to the registration endpoint does not create a user', function () {
    $this->post('/register', [
        'name'                  => 'Intruder',
        'email'                 => 'intruder@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::where('email', 'intruder@example.com')->exists())->toBeFalse();
});
