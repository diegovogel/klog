<?php

namespace App\Console\Commands;

use App\Services\ScreenshotService;
use Illuminate\Console\Command;

class VerifyScreenshotPipeline extends Command
{
    protected $signature = 'clippings:verify-pipeline';

    protected $description = 'Render a tiny test page through Browsershot to verify the screenshot pipeline (used by clippings:install-screenshots).';

    protected $hidden = true;

    public function handle(ScreenshotService $service): int
    {
        try {
            if (! $service->testPipeline()) {
                $this->error('Browsershot saved no output. Chromium may have launched but produced an empty file.');

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            if ($previous = $e->getPrevious()) {
                $this->line($previous->getMessage());
            }

            return self::FAILURE;
        }

        $this->info('Pipeline OK.');

        return self::SUCCESS;
    }
}
