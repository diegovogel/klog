<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class InstallClippingScreenshots extends Command
{
    protected $signature = 'clippings:install-screenshots';

    protected $description = 'Install Browsershot and Puppeteer for web clipping screenshots';

    public function handle(): int
    {
        // 1. Install Composer package
        if ($this->composerHasPackage('spatie/browsershot')) {
            $this->info('spatie/browsershot is already installed.');
        } else {
            $this->info('Installing spatie/browsershot...');
            $result = Process::path(base_path())->run('composer require spatie/browsershot');

            if ($result->failed()) {
                $this->error('Failed to install spatie/browsershot.');
                $this->line($result->errorOutput());

                return self::FAILURE;
            }

            $this->info('spatie/browsershot installed.');
        }

        // 2. Install npm package
        if ($this->npmHasPackage('puppeteer')) {
            $this->info('puppeteer is already installed.');
        } else {
            $this->info('Installing puppeteer (this downloads Chromium and may take a minute)...');
            $result = Process::path(base_path())->timeout(300)->run('npm install puppeteer');

            if ($result->failed()) {
                $this->error('Failed to install puppeteer.');
                $this->line($result->errorOutput());

                return self::FAILURE;
            }

            $this->info('puppeteer installed.');
        }

        // 3. Test the pipeline in a subprocess.
        //    We can't test in-process because composer require ran in a separate
        //    shell and this process's autoloader is stale. A subprocess gets a
        //    fresh autoloader that knows about the newly installed package.
        $this->info('Verifying screenshot pipeline...');

        // Use exit() so a `false` return from testPipeline becomes a
        // non-zero subprocess exit code that we can detect here. Otherwise
        // tinker would happily print `false` and exit 0, masking the failure.
        $result = Process::path(base_path())->run(
            'php artisan tinker --execute="exit(app(App\\Services\\ScreenshotService::class)->testPipeline() ? 0 : 1);"'
        );

        if ($result->failed()) {
            $this->error('Pipeline test failed: '.($result->errorOutput() ?: $result->output()));

            return self::FAILURE;
        }

        $this->info('Screenshot pipeline verified successfully.');

        $this->newLine();
        $this->info('Screenshot system installed. The scheduler will capture screenshots daily at 02:00.');
        $this->info('Commit composer.json, composer.lock, package.json, and package-lock.json to persist the installation.');

        return self::SUCCESS;
    }

    private function composerHasPackage(string $package): bool
    {
        $composerJson = json_decode(file_get_contents(base_path('composer.json')), true);

        return isset($composerJson['require'][$package])
            || isset($composerJson['require-dev'][$package]);
    }

    private function npmHasPackage(string $package): bool
    {
        $packageJson = json_decode(file_get_contents(base_path('package.json')), true);

        return isset($packageJson['dependencies'][$package])
            || isset($packageJson['devDependencies'][$package]);
    }
}
