<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

describe('clippings:install-screenshots', function () {
    beforeEach(function () {
        $this->originalComposerJson = File::get(base_path('composer.json'));
        $this->originalPackageJson = File::get(base_path('package.json'));
    });

    afterEach(function () {
        File::put(base_path('composer.json'), $this->originalComposerJson);
        File::put(base_path('package.json'), $this->originalPackageJson);
    });

    it('reports failure when composer require fails', function () {
        // Remove spatie/browsershot from composer.json so the command tries to install it
        $composer = json_decode($this->originalComposerJson, true);
        unset($composer['require']['spatie/browsershot'], $composer['require-dev']['spatie/browsershot']);
        File::put(base_path('composer.json'), json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        Process::fake([
            'composer require spatie/browsershot' => Process::result(
                output: '',
                errorOutput: 'Version conflict',
                exitCode: 1,
            ),
        ]);

        $this->artisan('clippings:install-screenshots')
            ->expectsOutput('Installing spatie/browsershot...')
            ->expectsOutput('Failed to install spatie/browsershot.')
            ->assertFailed();
    });

    it('reports failure when npm install fails', function () {
        // Remove puppeteer from package.json so the command tries to install it
        $package = json_decode($this->originalPackageJson, true);
        unset($package['dependencies']['puppeteer'], $package['devDependencies']['puppeteer']);
        File::put(base_path('package.json'), json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        Process::fake([
            'composer require spatie/browsershot' => Process::result(exitCode: 0),
            'npm install puppeteer' => Process::result(
                output: '',
                errorOutput: 'npm ERR!',
                exitCode: 1,
            ),
        ]);

        $this->artisan('clippings:install-screenshots')
            ->expectsOutputToContain('browsershot')
            ->expectsOutputToContain('Installing puppeteer')
            ->expectsOutput('Failed to install puppeteer.')
            ->assertFailed();
    });

    it('reports failure when pipeline test fails', function () {
        Process::fake([
            'composer require spatie/browsershot' => Process::result(exitCode: 0),
            'npm install puppeteer' => Process::result(exitCode: 0),
            'php artisan clippings:verify-pipeline' => Process::result(
                output: 'Chromium not found at /path/to/chrome',
                errorOutput: '',
                exitCode: 1,
            ),
        ]);

        $this->artisan('clippings:install-screenshots')
            ->expectsOutput('Verifying screenshot pipeline...')
            ->expectsOutput('Pipeline test failed:')
            ->expectsOutputToContain('Chromium not found at /path/to/chrome')
            ->assertFailed();
    });

    it('succeeds when all steps complete', function () {
        Process::fake([
            'composer require spatie/browsershot' => Process::result(exitCode: 0),
            'npm install puppeteer' => Process::result(exitCode: 0),
            'php artisan clippings:verify-pipeline' => Process::result(exitCode: 0),
        ]);

        $this->artisan('clippings:install-screenshots')
            ->expectsOutput('Screenshot pipeline verified successfully.')
            ->expectsOutputToContain('Screenshot system installed')
            ->assertSuccessful();
    });

    it('skips already installed packages', function () {
        Process::fake([
            'php artisan clippings:verify-pipeline' => Process::result(exitCode: 0),
        ]);

        $this->artisan('clippings:install-screenshots')
            ->expectsOutput('spatie/browsershot is already installed.')
            ->expectsOutput('puppeteer is already installed.')
            ->assertSuccessful();

        Process::assertDidntRun('composer require spatie/browsershot');
        Process::assertDidntRun('npm install puppeteer');
    });
});
