<?php

namespace App\Jobs;

use App\Services\ScreenshotFeatureService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class UninstallScreenshotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function handle(ScreenshotFeatureService $feature): void
    {
        $feature->markStatus('running', 'Removing screenshot packages…', 'uninstall');

        try {
            $exit = Artisan::call('clippings:uninstall-screenshots');
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            $feature->markStatus('failed', 'Uninstall threw: '.$e->getMessage(), 'uninstall');

            return;
        }

        if ($exit !== 0) {
            $feature->markStatus('failed', 'Uninstall failed:'.PHP_EOL.$output, 'uninstall');

            return;
        }

        $feature->markStatus('success', 'Screenshot packages removed.', 'uninstall');
    }
}
