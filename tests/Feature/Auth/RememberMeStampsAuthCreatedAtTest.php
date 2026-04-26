<?php

use App\Models\User;

it('stamps auth.created_at on fresh credential login', function () {
    $user = User::factory()->create(['password' => 'secret123']);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertRedirect('/')
        ->assertSessionHas('auth.created_at');
});

it('lets a fresh recaller-restored session through after a prior invalidation epoch', function () {
    $user = User::factory()->create();

    // Pre-existing invalidation epoch (e.g. from a past log-out-others).
    $user->update(['session_invalidated_at' => now()->subHour()]);

    // Simulate a recaller-restored session: auth.created_at was stamped by
    // the StampAuthCreatedAt listener at recaller-time, which is after the
    // invalidation epoch.
    $this->actingAs($user)->withSession([
        'two_factor_confirmed' => true,
        'auth.created_at' => now()->getTimestamp(),
    ]);

    $this->get('/')->assertSuccessful();
});
