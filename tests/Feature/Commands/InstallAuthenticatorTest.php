<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

describe('2fa:install-authenticator', function () {
    beforeEach(function () {
        $this->originalComposerJson = File::get(base_path('composer.json'));
    });

    afterEach(function () {
        File::put(base_path('composer.json'), $this->originalComposerJson);
    });

    it('reports failure when composer require fails', function () {
        $composer = json_decode($this->originalComposerJson, true);
        unset(
            $composer['require']['pragmarx/google2fa'],
            $composer['require-dev']['pragmarx/google2fa'],
            $composer['require']['chillerlan/php-qrcode'],
            $composer['require-dev']['chillerlan/php-qrcode'],
        );
        File::put(base_path('composer.json'), json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        Process::fake([
            'composer require pragmarx/google2fa chillerlan/php-qrcode' => Process::result(
                output: '',
                errorOutput: 'Version conflict',
                exitCode: 1,
            ),
        ]);

        $this->artisan('2fa:install-authenticator')
            ->expectsOutput('Installing authenticator packages...')
            ->expectsOutput('Failed to install packages.')
            ->assertFailed();
    });

    it('reports failure when pipeline verification fails', function () {
        Process::fake([
            'composer require pragmarx/google2fa chillerlan/php-qrcode' => Process::result(exitCode: 0),
            'php artisan tinker*' => Process::result(
                output: 'FAIL',
                errorOutput: '',
                exitCode: 0,
            ),
        ]);

        $this->artisan('2fa:install-authenticator')
            ->expectsOutput('Verifying authenticator pipeline...')
            ->assertFailed();
    });

    it('succeeds when all steps complete', function () {
        Process::fake([
            'composer require pragmarx/google2fa chillerlan/php-qrcode' => Process::result(exitCode: 0),
            'php artisan tinker*' => Process::result(output: 'OK', exitCode: 0),
        ]);

        $this->artisan('2fa:install-authenticator')
            ->expectsOutput('Authenticator pipeline verified successfully.')
            ->expectsOutputToContain('Authenticator app two-factor authentication is now available')
            ->assertSuccessful();
    });

    it('skips already installed packages', function () {
        // Ensure both packages are in composer.json
        $composer = json_decode($this->originalComposerJson, true);
        $composer['require']['pragmarx/google2fa'] = '^5.0';
        $composer['require']['chillerlan/php-qrcode'] = '^5.0';
        File::put(base_path('composer.json'), json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        Process::fake();

        $this->artisan('2fa:install-authenticator')
            ->expectsOutput('Authenticator packages are already installed.')
            ->assertSuccessful();

        Process::assertDidntRun('composer require pragmarx/google2fa chillerlan/php-qrcode');
    });
});
