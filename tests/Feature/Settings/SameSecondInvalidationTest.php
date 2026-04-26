<?php

use App\Models\User;

it('treats a session created in the same second as invalidation as stale', function () {
    $user = User::factory()->create();
    $sameSecond = now()->getTimestamp();
    $user->update(['session_invalidated_at' => \Carbon\Carbon::createFromTimestamp($sameSecond)]);

    $this->actingAs($user)->withSession([
        'two_factor_confirmed' => true,
        'auth.created_at' => $sameSecond,
    ]);

    $this->get('/')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('still allows sessions whose auth.created_at is strictly newer than invalidation', function () {
    $user = User::factory()->create();
    $invalidationAt = now()->getTimestamp();
    $user->update(['session_invalidated_at' => \Carbon\Carbon::createFromTimestamp($invalidationAt)]);

    $this->actingAs($user)->withSession([
        'two_factor_confirmed' => true,
        'auth.created_at' => $invalidationAt + 1,
    ]);

    $this->get('/')->assertSuccessful();
});
