<?php

use App\Enums\TwoFactorMethod;
use App\Models\User;
use Illuminate\Support\Facades\Process;

describe('2fa:uninstall-authenticator', function () {
    it('migrates confirmed authenticator users to email method', function () {
        $user = User::factory()->withTwoFactor(TwoFactorMethod::AUTHENTICATOR)->create([
            'two_factor_secret' => 'TESTSECRET',
        ]);

        Process::fake([
            'composer remove pragmarx/google2fa chillerlan/php-qrcode' => Process::result(exitCode: 0),
        ]);

        $this->artisan('2fa:uninstall-authenticator')
            ->expectsOutputToContain('Migrating 1 user')
            ->expectsOutputToContain($user->email)
            ->assertSuccessful();

        $user->refresh();

        expect($user->two_factor_method)->toBe(TwoFactorMethod::EMAIL)
            ->and($user->two_factor_secret)->toBeNull()
            ->and($user->two_factor_confirmed_at)->not->toBeNull();
    });

    it('clears unconfirmed authenticator setups', function () {
        $user = User::factory()->create([
            'two_factor_method' => TwoFactorMethod::AUTHENTICATOR,
            'two_factor_confirmed_at' => null,
            'two_factor_secret' => 'TESTSECRET',
        ]);

        Process::fake([
            'composer remove pragmarx/google2fa chillerlan/php-qrcode' => Process::result(exitCode: 0),
        ]);

        $this->artisan('2fa:uninstall-authenticator')
            ->expectsOutputToContain('Cleared 1 unconfirmed')
            ->assertSuccessful();

        $user->refresh();

        expect($user->two_factor_method)->toBeNull()
            ->and($user->two_factor_secret)->toBeNull();
    });

    it('reports failure when package removal fails', function () {
        Process::fake([
            'composer remove pragmarx/google2fa chillerlan/php-qrcode' => Process::result(
                output: '',
                errorOutput: 'Permission denied',
                exitCode: 1,
            ),
        ]);

        $this->artisan('2fa:uninstall-authenticator')
            ->expectsOutput('Failed to remove packages.')
            ->assertFailed();
    });

    it('succeeds with no authenticator users', function () {
        Process::fake([
            'composer remove pragmarx/google2fa chillerlan/php-qrcode' => Process::result(exitCode: 0),
        ]);

        $this->artisan('2fa:uninstall-authenticator')
            ->expectsOutput('Authenticator packages removed.')
            ->assertSuccessful();
    });
});
