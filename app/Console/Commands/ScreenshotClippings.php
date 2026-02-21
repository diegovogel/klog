<?php

namespace App\Console\Commands;

use App\Models\WebClipping;
use App\Services\MediaStorageService;
use App\Services\ScreenshotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScreenshotClippings extends Command
{
    protected $signature = 'clippings:screenshot {--limit=10 : Maximum number of clippings to process (0 for unlimited)}';

    protected $description = 'Capture screenshots for web clippings that don\'t have one yet';

    private const MAX_ATTEMPTS = 14;

    public function handle(ScreenshotService $screenshotService, MediaStorageService $mediaStorageService): int
    {
        if (! $screenshotService->isAvailable()) {
            $this->error('Browsershot is not installed. Run: php artisan clippings:install-screenshots');

            return self::FAILURE;
        }

        $query = WebClipping::whereDoesntHave('screenshot')
            ->where('screenshot_attempts', '<', self::MAX_ATTEMPTS);

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $clippings = $query->get();

        if ($clippings->isEmpty()) {
            $this->info('All web clippings already have screenshots.');

            return self::SUCCESS;
        }

        $total = $clippings->count();
        $this->info("Found {$total} web clipping(s) to screenshot.");

        $captured = 0;
        $failed = 0;

        foreach ($clippings as $clipping) {
            $clipping->increment('screenshot_attempts');
            $tempPath = null;

            try {
                $tempPath = $screenshotService->capture($clipping->url);
                $mediaStorageService->storeScreenshotForClipping($clipping, $tempPath);
                @unlink($tempPath);

                $captured++;
                $this->line("  Captured: {$clipping->url}");
            } catch (\Throwable $e) {
                if ($tempPath) {
                    @unlink($tempPath);
                }

                $failed++;

                Log::error("Screenshot failed for WebClipping #{$clipping->id}", [
                    'url' => $clipping->url,
                    'error' => $e->getMessage(),
                ]);

                $this->warn("  Failed: {$clipping->url} — {$e->getMessage()}");
            }
        }

        $this->info("Captured {$captured}/{$total} screenshots. {$failed} failed.");

        $remaining = WebClipping::whereDoesntHave('screenshot')
            ->where('screenshot_attempts', '<', self::MAX_ATTEMPTS)
            ->count();
        if ($remaining > 0) {
            $this->info("{$remaining} clipping(s) still need screenshots.");
        }

        return self::SUCCESS;
    }
}
