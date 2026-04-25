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

    public function handle(ScreenshotFeatureService $feature): void
    {
        // Snapshot the prior installed state so we know whether a failed
        // install actually added anything we should remove.
        $wasInstalled = $feature->isInstalled();

        $feature->markStatus('running', 'Installing screenshot packages…', 'install');

        try {
            $exit = Artisan::call('clippings:install-screenshots');
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            $this->rollBack($feature, $wasInstalled, 'Install threw: '.$e->getMessage());

            return;
        }

        if ($exit !== 0) {
            $this->rollBack($feature, $wasInstalled, 'Install failed:'.PHP_EOL.$output);

            return;
        }

        $feature->setEnabled(true);
        $feature->markStatus('success', 'Screenshot packages installed.', 'install');
    }

    private function rollBack(ScreenshotFeatureService $feature, bool $wasInstalled, string $reason): void
    {
        // Only auto-uninstall when this run was the one that started installing.
        // If packages were already present, don't strip them on a partial-retry failure.
        if (! $wasInstalled) {
            try {
                Artisan::call('clippings:uninstall-screenshots');
            } catch (\Throwable) {
                // best-effort rollback
            }
        }

        $feature->markStatus('failed', $reason, 'install');
    }
}
