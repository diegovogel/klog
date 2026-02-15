<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class UninstallClippingScreenshots extends Command
{
    protected $signature = 'clippings:uninstall-screenshots';

    protected $description = 'Remove Browsershot and Puppeteer screenshot packages';

    public function handle(): int
    {
        // 1. Remove Composer package
        $this->info('Removing spatie/browsershot...');
        $result = Process::path(base_path())->run('composer remove spatie/browsershot');

        if ($result->failed()) {
            $this->error('Failed to remove spatie/browsershot.');
            $this->line($result->errorOutput());

            return self::FAILURE;
        }

        // 2. Remove npm package
        $this->info('Removing puppeteer...');
        $result = Process::path(base_path())->run('npm uninstall puppeteer');

        if ($result->failed()) {
            $this->error('Failed to remove puppeteer.');
            $this->line($result->errorOutput());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Screenshot packages removed.');
        $this->info('Existing screenshot images have been preserved as regular media.');
        $this->info('Commit composer.json, composer.lock, package.json, and package-lock.json to persist the removal.');

        return self::SUCCESS;
    }
}
