<?php

use App\Models\User;

it('logs out an already-authenticated user once they are deactivated', function () {
    $user = User::factory()->create(['password' => 'pass']);
    $this->actingAs($user)->withSession(['two_factor_confirmed' => true]);

    // Sanity: the user can hit a protected route.
    $this->get('/')->assertSuccessful();

    // Admin deactivates the user out-of-band.
    $user->deactivate();

    // The next request should bounce them to /login as guests.
    $this->get('/')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('rejects accepting an invite when the underlying user has been deactivated', function () {
    $user = User::factory()->deactivated()->create();
    $invite = \App\Models\UserInvite::factory()->for($user)->create();

    $this->get(route('invites.show', ['token' => $invite->token]))->assertNotFound();

    $this->post(route('invites.accept', ['token' => $invite->token]), [
        'name' => 'X',
        'password' => 'super-secret-12',
        'password_confirmation' => 'super-secret-12',
    ])->assertNotFound();
});

it('clears database sessions for the deactivated user when using the database session driver', function () {
    config()->set('session.driver', 'database');

    $user = User::factory()->create();
    $other = User::factory()->create();

    \DB::table('sessions')->insert([
        ['id' => 'sess-keep', 'user_id' => $other->id, 'ip_address' => '127.0.0.1', 'user_agent' => 't', 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'sess-kill-1', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 't', 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'sess-kill-2', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 't', 'payload' => '', 'last_activity' => now()->timestamp],
    ]);

    $user->deactivate();

    expect(\DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    expect(\DB::table('sessions')->where('id', 'sess-keep')->count())->toBe(1);
});
