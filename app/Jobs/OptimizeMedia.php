<?php

namespace App\Jobs;

use App\Enums\ProcessingStatus;
use App\Models\Media;
use App\Services\MediaOptimizationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class OptimizeMedia implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public int $tries = 2;

    public function __construct(public int $mediaId) {}

    public function handle(MediaOptimizationService $service): void
    {
        $media = Media::find($this->mediaId);

        if (! $media) {
            return;
        }

        if ($media->processing_status === ProcessingStatus::Complete) {
            return;
        }

        $media->update(['processing_status' => ProcessingStatus::Processing]);

        try {
            if ($service->needsImageConversion($media)) {
                $service->convertImage($media);
            } elseif ($service->needsVideoOptimization($media)) {
                $service->optimizeVideo($media);
            } else {
                $media->update(['processing_status' => ProcessingStatus::Complete]);
            }
        } catch (\Throwable $e) {
            Log::error("Media optimization failed for Media #{$media->id}", [
                'error' => $e->getMessage(),
                'path' => $media->path,
                'mime_type' => $media->mime_type,
            ]);

            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $media = Media::find($this->mediaId);

        $media?->update(['processing_status' => ProcessingStatus::Failed]);
    }
}
