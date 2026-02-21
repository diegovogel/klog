<?php

namespace App\Console\Commands;

use App\Models\WebClipping;
use App\Services\WebClippingContentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchClippingContent extends Command
{
    protected $signature = 'clippings:fetch-content {--limit=10 : Maximum number of clippings to process (0 for unlimited)}';

    protected $description = 'Fetch and extract text content for web clippings that don\'t have it yet';

    private const MAX_ATTEMPTS = 14;

    public function handle(WebClippingContentService $contentService): int
    {
        $query = WebClipping::whereNull('content')
            ->where('fetch_attempts', '<', self::MAX_ATTEMPTS);

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $clippings = $query->get();

        if ($clippings->isEmpty()) {
            $this->info('All web clippings already have content.');

            return self::SUCCESS;
        }

        $total = $clippings->count();
        $this->info("Found {$total} web clipping(s) to fetch.");

        $fetched = 0;
        $failed = 0;

        foreach ($clippings as $clipping) {
            $clipping->increment('fetch_attempts');

            try {
                $result = $contentService->extractText($clipping->url);

                if ($result['content'] !== null) {
                    $clipping->update([
                        'title' => $result['title'],
                        'content' => $result['content'],
                    ]);

                    $fetched++;
                    $this->line("  Fetched: {$clipping->url}");
                } else {
                    $failed++;
                    $this->warn("  No content: {$clipping->url}");
                }
            } catch (\Throwable $e) {
                $failed++;

                Log::error("Content fetch failed for WebClipping #{$clipping->id}", [
                    'url' => $clipping->url,
                    'error' => $e->getMessage(),
                ]);

                $this->warn("  Failed: {$clipping->url} — {$e->getMessage()}");
            }
        }

        $this->info("Fetched {$fetched}/{$total} clippings. {$failed} failed.");

        $remaining = WebClipping::whereNull('content')
            ->where('fetch_attempts', '<', self::MAX_ATTEMPTS)
            ->count();

        if ($remaining > 0) {
            $this->info("{$remaining} clipping(s) still need content.");
        }

        return self::SUCCESS;
    }
}
