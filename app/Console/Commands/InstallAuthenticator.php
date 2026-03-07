<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class InstallAuthenticator extends Command
{
    protected $signature = '2fa:install-authenticator';

    protected $description = 'Install packages for authenticator app two-factor authentication';

    public function handle(): int
    {
        if ($this->composerHasPackage('pragmarx/google2fa') && $this->composerHasPackage('chillerlan/php-qrcode')) {
            $this->info('Authenticator packages are already installed.');

            return self::SUCCESS;
        }

        $this->info('Installing authenticator packages...');
        $result = Process::path(base_path())->run('composer require pragmarx/google2fa chillerlan/php-qrcode');

        if ($result->failed()) {
            $this->error('Failed to install packages.');
            $this->line($result->errorOutput());

            return self::FAILURE;
        }

        $this->info('Packages installed.');

        // Test in a subprocess because the current process's autoloader is stale.
        $this->info('Verifying authenticator pipeline...');

        $result = Process::path(base_path())->run(
            'php artisan tinker --execute="echo app(App\\Services\\AuthenticatorService::class)->isAvailable() ? \'OK\' : \'FAIL\';"'
        );

        if ($result->failed() || ! str_contains($result->output(), 'OK')) {
            $this->error('Pipeline verification failed: '.$result->errorOutput());

            return self::FAILURE;
        }

        $this->info('Authenticator pipeline verified successfully.');

        $this->newLine();
        $this->info('Authenticator app two-factor authentication is now available.');
        $this->info('Users can enable it from Settings > Two-Factor Authentication.');
        $this->info('Commit composer.json and composer.lock to persist the installation.');

        return self::SUCCESS;
    }

    private function composerHasPackage(string $package): bool
    {
        $composerJson = json_decode(file_get_contents(base_path('composer.json')), true);

        return isset($composerJson['require'][$package])
            || isset($composerJson['require-dev'][$package]);
    }
}
