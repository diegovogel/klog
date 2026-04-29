<?php

namespace App\Jobs;

use App\Services\ScreenshotFeatureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class InstallScreenshotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public bool $autoEnable = false) {}

    public function handle(ScreenshotFeatureService $feature): void
    {
        // If this job was auto-dispatched by an enable toggle and the admin
        // disabled the feature again before the worker picked us up, the
        // toggle is what they want — skip the install.
        if ($this->autoEnable && ! $feature->isEnabled()) {
            $feature->clearStatus();

            return;
        }

        // Snapshot per-package presence so rollback only undoes packages
        // this run actually added. A partial prior state (PHP installed but
        // node_modules missing, or vice versa) must NOT be deleted on
        // failure.
        $hadComposerPkg = $this->composerHasPackage('spatie/browsershot');
        $hadNpmPkg = $this->npmHasPackage('puppeteer');

        $feature->markStatus('running', 'Installing screenshot packages…', 'install');

        try {
            $exit = Artisan::call('clippings:install-screenshots');
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            $this->rollBack($feature, $hadComposerPkg, $hadNpmPkg, 'Install threw: '.$e->getMessage());

            return;
        }

        if ($exit !== 0) {
            $this->rollBack($feature, $hadComposerPkg, $hadNpmPkg, 'Install failed:'.PHP_EOL.$output);

            return;
        }

        // Don't force-enable: if the admin toggled the flag while this job
        // was in flight, that decision wins. ScreenshotFeatureService::isEnabled()
        // defaults to true when no value is stored, so a true first-time
        // install still ends up enabled.
        $feature->markStatus('success', 'Screenshot packages installed.', 'install');
    }

    private function rollBack(ScreenshotFeatureService $feature, bool $hadComposerPkg, bool $hadNpmPkg, string $reason): void
    {
        // Only run the unconditional uninstall command when neither package
        // existed before this attempt — i.e. it was a true fresh install
        // and removing both is equivalent to removing only what we added.
        // Any partial prior state is left intact so a failed attempt can't
        // delete a package the admin had already installed by hand.
        if (! $hadComposerPkg && ! $hadNpmPkg) {
            try {
                Artisan::call('clippings:uninstall-screenshots');
            } catch (\Throwable) {
                // best-effort rollback
            }
        }

        $feature->markStatus('failed', $reason, 'install');
    }

    private function composerHasPackage(string $package): bool
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        return isset($composer['require'][$package])
            || isset($composer['require-dev'][$package]);
    }

    private function npmHasPackage(string $package): bool
    {
        $packageJson = json_decode(file_get_contents(base_path('package.json')), true);

        return isset($packageJson['dependencies'][$package])
            || isset($packageJson['devDependencies'][$package]);
    }
}
