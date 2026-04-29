<?php

namespace App\Jobs;

use App\Services\ScreenshotFeatureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

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
        // Remove only the packages this run actually added — i.e. ones that
        // were absent at start but present now. That preserves any partial
        // prior state the admin had set up by hand, while still cleaning
        // up the half-step we just took before failing verification.
        try {
            if (! $hadComposerPkg && $this->composerHasPackage('spatie/browsershot')) {
                Process::path(base_path())->run('composer remove spatie/browsershot');
            }
            if (! $hadNpmPkg && $this->npmHasPackage('puppeteer')) {
                Process::path(base_path())->run('npm uninstall puppeteer');
            }
        } catch (\Throwable) {
            // best-effort rollback
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
