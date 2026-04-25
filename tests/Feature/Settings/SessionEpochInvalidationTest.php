<?php

use App\Models\User;

it('logs out a session whose timestamp predates session_invalidated_at, even after reactivation', function () {
    $user = User::factory()->create(['password' => 'pass']);

    // Simulate an active session that was created before any invalidation.
    $this->actingAs($user)->withSession([
        'two_factor_confirmed' => true,
        'auth.created_at' => now()->subMinutes(10)->getTimestamp(),
    ]);

    // Sanity: pre-deactivation, the session works.
    $this->get('/')->assertSuccessful();

    // Admin deactivates and immediately reactivates (the narrow window).
    $user->deactivate();
    expect($user->fresh()->session_invalidated_at)->not->toBeNull();
    $user->reactivate();

    // Even though the user is active again, the older session should be killed.
    $this->get('/')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('keeps a session whose timestamp is fresher than session_invalidated_at', function () {
    $user = User::factory()->create(['password' => 'pass']);

    $user->update(['session_invalidated_at' => now()->subHour()]);

    $this->actingAs($user)->withSession([
        'two_factor_confirmed' => true,
        'auth.created_at' => now()->getTimestamp(),
    ]);

    $this->get('/')->assertSuccessful();
});
