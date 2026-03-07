<?php

use App\Enums\TwoFactorMethod;
use App\Models\User;
use App\Services\AuthenticatorService;
use App\Services\TwoFactorService;

beforeEach(function () {
    $this->user = User::factory()->create(['password' => 'password']);
    $this->actingAs($this->user)->withSession(['two_factor_confirmed' => true]);
});

describe('settings page', function () {
    it('shows the two-factor settings page', function () {
        $this->get(route('two-factor.settings'))
            ->assertSuccessful()
            ->assertSee('Two-Factor Authentication');
    });

    it('shows status as disabled when 2fa is off', function () {
        $this->get(route('two-factor.settings'))
            ->assertSuccessful()
            ->assertSee('Disabled');
    });

    it('shows status as enabled when 2fa is on', function () {
        $this->user->update([
            'two_factor_method' => TwoFactorMethod::EMAIL,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->get(route('two-factor.settings'))
            ->assertSuccessful()
            ->assertSee('Enabled');
    });
});

describe('enable', function () {
    it('enables email two-factor with valid password', function () {
        $this->post(route('two-factor.enable'), [
            'method' => 'email',
            'password' => 'password',
        ])->assertRedirect(route('two-factor.settings'))
            ->assertSessionHas('success')
            ->assertSessionHas('recovery_codes');

        $this->user->refresh();

        expect($this->user->two_factor_method)->toBe(TwoFactorMethod::EMAIL)
            ->and($this->user->two_factor_confirmed_at)->not->toBeNull();
    });

    it('rejects enable with wrong password', function () {
        $this->post(route('two-factor.enable'), [
            'method' => 'email',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');

        $this->user->refresh();

        expect($this->user->two_factor_method)->toBeNull();
    });

    it('rejects enable with invalid method', function () {
        $this->post(route('two-factor.enable'), [
            'method' => 'invalid',
            'password' => 'password',
        ])->assertSessionHasErrors('method');
    });

    it('redirects to authenticator setup when choosing authenticator', function () {
        $mock = $this->mock(AuthenticatorService::class);
        $mock->shouldReceive('isAvailable')->andReturn(true);

        $this->post(route('two-factor.enable'), [
            'method' => 'authenticator',
            'password' => 'password',
        ])->assertRedirect(route('two-factor.authenticator.setup'));
    });

    it('rejects authenticator when not available', function () {
        $mock = $this->mock(AuthenticatorService::class);
        $mock->shouldReceive('isAvailable')->andReturn(false);

        $this->post(route('two-factor.enable'), [
            'method' => 'authenticator',
            'password' => 'password',
        ])->assertSessionHasErrors('method');
    });
});

describe('disable', function () {
    it('disables two-factor with valid password', function () {
        $this->user->update([
            'two_factor_method' => TwoFactorMethod::EMAIL,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => TwoFactorService::hashRecoveryCodes(
                TwoFactorService::generateRecoveryCodes()
            ),
        ]);

        $this->post(route('two-factor.disable'), [
            'password' => 'password',
        ])->assertRedirect(route('two-factor.settings'))
            ->assertSessionHas('success');

        $this->user->refresh();

        expect($this->user->two_factor_method)->toBeNull()
            ->and($this->user->two_factor_confirmed_at)->toBeNull();
    });

    it('rejects disable with wrong password', function () {
        $this->user->update([
            'two_factor_method' => TwoFactorMethod::EMAIL,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post(route('two-factor.disable'), [
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');

        $this->user->refresh();

        expect($this->user->two_factor_method)->toBe(TwoFactorMethod::EMAIL);
    });
});

describe('regenerate recovery codes', function () {
    it('regenerates recovery codes with valid password', function () {
        $this->user->update([
            'two_factor_method' => TwoFactorMethod::EMAIL,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => TwoFactorService::hashRecoveryCodes(
                TwoFactorService::generateRecoveryCodes()
            ),
        ]);

        $this->post(route('two-factor.recovery-codes'), [
            'password' => 'password',
        ])->assertRedirect(route('two-factor.settings'))
            ->assertSessionHas('recovery_codes')
            ->assertSessionHas('success');
    });

    it('rejects regeneration with wrong password', function () {
        $this->user->update([
            'two_factor_method' => TwoFactorMethod::EMAIL,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post(route('two-factor.recovery-codes'), [
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');
    });
});

describe('authenticator setup', function () {
    it('returns 404 when authenticator is not available', function () {
        $mock = $this->mock(AuthenticatorService::class);
        $mock->shouldReceive('isAvailable')->andReturn(false);

        $this->get(route('two-factor.authenticator.setup'))
            ->assertNotFound();
    });
});

describe('authenticator confirmation', function () {
    it('rejects confirmation without a session secret', function () {
        $this->post(route('two-factor.authenticator.confirm'), [
            'code' => '123456',
        ])->assertSessionHasErrors('code');
    });

    it('rejects confirmation with wrong code', function () {
        $mock = $this->mock(AuthenticatorService::class);
        $mock->shouldReceive('verify')->andReturn(false);

        $this->withSession(['two_factor_setup_secret' => 'TESTSECRET'])
            ->post(route('two-factor.authenticator.confirm'), [
                'code' => '000000',
            ])->assertSessionHasErrors('code');
    });

    it('confirms authenticator with valid code', function () {
        $mock = $this->mock(AuthenticatorService::class);
        $mock->shouldReceive('verify')
            ->with('TESTSECRET', '123456')
            ->andReturn(true);

        $this->withSession(['two_factor_setup_secret' => 'TESTSECRET'])
            ->post(route('two-factor.authenticator.confirm'), [
                'code' => '123456',
            ])->assertRedirect(route('two-factor.settings'))
            ->assertSessionHas('success')
            ->assertSessionHas('recovery_codes');

        $this->user->refresh();

        expect($this->user->two_factor_method)->toBe(TwoFactorMethod::AUTHENTICATOR)
            ->and($this->user->two_factor_confirmed_at)->not->toBeNull()
            ->and($this->user->two_factor_secret)->toBe('TESTSECRET');
    });
});
