<?php

use App\Models\User;

it('preserves the current trusted device when logging out other devices', function () {
    $user = User::factory()->create(['password' => 'password']);

    $currentToken = 'current-token-value';
    $user->rememberedDevices()->create([
        'token_hash' => hash('sha256', $currentToken),
        'expires_at' => now()->addDays(10),
    ]);
    $user->rememberedDevices()->create([
        'token_hash' => hash('sha256', 'other-device-1'),
        'expires_at' => now()->addDays(10),
    ]);
    $user->rememberedDevices()->create([
        'token_hash' => hash('sha256', 'other-device-2'),
        'expires_at' => now()->addDays(10),
    ]);

    $this->actingAs($user)->withSession(['two_factor_confirmed' => true])
        ->withCookie('two_factor_remember', $currentToken)
        ->post(route('settings.log-out-other-devices'), ['password' => 'password'])
        ->assertRedirect(route('settings'));

    $remaining = $user->fresh()->rememberedDevices;
    expect($remaining)->toHaveCount(1)
        ->and($remaining->first()->token_hash)->toBe(hash('sha256', $currentToken));
});

it('still clears all devices when no current trusted-device cookie is present', function () {
    $user = User::factory()->create(['password' => 'password']);

    $user->rememberedDevices()->create([
        'token_hash' => hash('sha256', 'one'),
        'expires_at' => now()->addDays(10),
    ]);
    $user->rememberedDevices()->create([
        'token_hash' => hash('sha256', 'two'),
        'expires_at' => now()->addDays(10),
    ]);

    $this->actingAs($user)->withSession(['two_factor_confirmed' => true])
        ->post(route('settings.log-out-other-devices'), ['password' => 'password'])
        ->assertRedirect(route('settings'));

    expect($user->fresh()->rememberedDevices)->toHaveCount(0);
});
