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
        $feature->markStatus('running', 'Installing screenshot packages…', 'install');

        try {
            $exit = Artisan::call('clippings:install-screenshots');
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            $this->rollBack($feature, 'Install threw: '.$e->getMessage());

            return;
        }

        if ($exit !== 0) {
            $this->rollBack($feature, 'Install failed:'.PHP_EOL.$output);

            return;
        }

        $feature->setEnabled(true);
        $feature->markStatus('success', 'Screenshot packages installed.', 'install');
    }

    private function rollBack(ScreenshotFeatureService $feature, string $reason): void
    {
        try {
            Artisan::call('clippings:uninstall-screenshots');
        } catch (\Throwable) {
            // best-effort rollback
        }

        $feature->markStatus('failed', $reason, 'install');
    }
}
