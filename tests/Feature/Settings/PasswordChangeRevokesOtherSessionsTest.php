<?php

use App\Models\User;

it('bumps session_invalidated_at, cycles remember_token, and clears remembered devices on password change', function () {
    $user = User::factory()->create([
        'password' => 'old-pass-123',
        'remember_token' => 'old-recaller',
    ]);
    $user->rememberedDevices()->create([
        'token_hash' => hash('sha256', 'device-token'),
        'expires_at' => now()->addDays(10),
    ]);

    $this->actingAs($user)->withSession([
        'two_factor_confirmed' => true,
        'auth.created_at' => now()->subMinutes(5)->getTimestamp(),
    ]);

    $this->patch(route('settings.password.update'), [
        'current_password' => 'old-pass-123',
        'password' => 'new-secret-1234',
        'password_confirmation' => 'new-secret-1234',
    ])->assertRedirect(route('settings'));

    $fresh = $user->fresh();
    expect($fresh->session_invalidated_at)->not->toBeNull();
    expect($fresh->getRememberToken())->not->toBe('old-recaller');
    expect($fresh->rememberedDevices)->toHaveCount(0);
});

it('keeps the actor session alive after password change', function () {
    $user = User::factory()->create(['password' => 'old-pass-123']);

    $this->actingAs($user)->withSession([
        'two_factor_confirmed' => true,
        'auth.created_at' => now()->subMinutes(5)->getTimestamp(),
    ]);

    $this->patch(route('settings.password.update'), [
        'current_password' => 'old-pass-123',
        'password' => 'new-secret-1234',
        'password_confirmation' => 'new-secret-1234',
    ])->assertRedirect(route('settings'));

    // The actor's session should be re-stamped, not kicked out.
    $this->get('/')->assertSuccessful();
});

it('kicks other sessions out after password change', function () {
    $user = User::factory()->create(['password' => 'old-pass-123']);

    // First session does the password change.
    $this->actingAs($user)->withSession([
        'two_factor_confirmed' => true,
        'auth.created_at' => now()->subMinutes(5)->getTimestamp(),
    ]);
    $this->patch(route('settings.password.update'), [
        'current_password' => 'old-pass-123',
        'password' => 'new-secret-1234',
        'password_confirmation' => 'new-secret-1234',
    ])->assertRedirect(route('settings'));

    // Simulate a different session that pre-dated the password change.
    $this->flushSession();
    $this->actingAs($user)->withSession([
        'two_factor_confirmed' => true,
        'auth.created_at' => now()->subMinutes(10)->getTimestamp(),
    ]);

    $this->get('/')->assertRedirect(route('login'));
    $this->assertGuest();
});
