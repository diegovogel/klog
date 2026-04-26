<?php

use App\Enums\TwoFactorMethod;
use App\Models\User;
use App\Services\TwoFactorConfigService;

it('uses the admin-configured remember duration for the cookie and DB token', function () {
    app(TwoFactorConfigService::class)->saveRememberDays(3);

    $user = User::factory()
        ->withTwoFactor(TwoFactorMethod::EMAIL)
        ->create(['password' => 'password']);

    // Inject a known email code
    $code = '123456';
    \Cache::put("two_factor_code:{$user->id}", \Hash::make($code), now()->addMinutes(10));

    $response = $this->actingAs($user)
        ->withSession(['two_factor_required_for' => $user->id])
        ->post(route('two-factor.verify'), [
            'code' => $code,
            'remember' => '1',
        ]);

    $response->assertRedirect();

    // DB-side: the remember device row expires in 3 days.
    $device = $user->fresh()->rememberedDevices()->first();
    expect($device)->not->toBeNull();
    $expected = now()->addDays(3);
    expect($device->expires_at->isBetween($expected->copy()->subMinute(), $expected->copy()->addMinute()))
        ->toBeTrue();

    // Cookie-side: the cookie's max-age (in minutes) should match.
    $cookies = collect($response->headers->getCookies())
        ->keyBy(fn ($c) => $c->getName());
    $cookie = $cookies->get('two_factor_remember');
    expect($cookie)->not->toBeNull();
    $expiresAt = $cookie->getExpiresTime();
    expect($expiresAt)->toBeGreaterThan(now()->addDays(3)->subMinute()->timestamp);
    expect($expiresAt)->toBeLessThan(now()->addDays(3)->addMinute()->timestamp);
});

it('renders the configured remember duration on the challenge page', function () {
    app(TwoFactorConfigService::class)->saveRememberDays(5);

    $user = User::factory()
        ->withTwoFactor(TwoFactorMethod::EMAIL)
        ->create(['password' => 'password']);

    $this->actingAs($user)
        ->withSession(['two_factor_required_for' => $user->id])
        ->get(route('two-factor.challenge'))
        ->assertSuccessful()
        ->assertSee('Remember this device for 5 days');
});
