<?php

use App\Models\User;

it('does not kick a fresh login that lands in the same second as a prior invalidation', function () {
    // Carbon freezes time across the test so we can guarantee same-second.
    \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::now());
    \Carbon\Carbon::setTestNow(\Carbon\Carbon::now());

    $user = User::factory()->create(['password' => 'secret123', 'session_invalidated_at' => now()]);

    // Sanity: `session_invalidated_at == now()` matches the same second as a fresh login.
    expect($user->session_invalidated_at->getTimestamp())->toBe(now()->getTimestamp());

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ])->assertRedirect('/');

    // After the redirect, hitting a protected route must NOT kick the user out.
    // (It might still hit the 2FA gate if 2FA is on; this user has no 2FA.)
    $this->withSession(['two_factor_confirmed' => true])->get('/')->assertSuccessful();

    \Carbon\CarbonImmutable::setTestNow();
    \Carbon\Carbon::setTestNow();
});
