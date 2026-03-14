<?php

namespace App\Services;

use App\Enums\MediaType;
use App\Enums\MimeType;
use App\Enums\ProcessingStatus;
use App\Jobs\OptimizeMedia;
use App\Models\Media;
use App\Models\Memory;
use App\Models\UploadSession;
use App\Models\WebClipping;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaStorageService
{
    private const DISK = 'local';

    /**
     * Store uploaded files and attach them as Media to the given Memory.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, Media>
     */
    public function storeForMemory(Memory $memory, array $files): array
    {
        $mediaRecords = [];

        foreach ($files as $order => $file) {
            $mediaRecords[] = $this->storeFile($memory, $file, $order);
        }

        return $mediaRecords;
    }

    /**
     * Resolve the MIME type for an uploaded file.
     *
     * PHP's finfo detects audio-only files in video containers (WebM, MP4)
     * as video/* because it sees the container format, not the tracks.
     * When the client reports an audio/* MIME and finfo says video/* for the
     * same container, we trust the client since validation already confirmed
     * the MIME type is in our allowed list.
     */
    private function resolveMimeType(UploadedFile $file): string
    {
        $detected = $file->getMimeType();
        $clientMime = $file->getClientMimeType();

        $audioOverrides = [
            'video/webm' => 'audio/webm',
            'video/mp4' => 'audio/mp4',
        ];

        if (isset($audioOverrides[$detected]) && $clientMime === $audioOverrides[$detected]) {
            return $clientMime;
        }

        return $detected;
    }

    /**
     * Store a screenshot file and attach it as Media to the given WebClipping.
     */
    public function storeScreenshotForClipping(WebClipping $clipping, string $tempFilePath): Media
    {
        $uuid = Str::uuid()->toString();
        $filename = $uuid.'.png';

        $now = now();
        $directory = sprintf('uploads/%s/%s', $now->format('Y'), $now->format('m'));
        $path = $directory.'/'.$filename;

        Storage::disk(self::DISK)->put($path, file_get_contents($tempFilePath));

        return $clipping->screenshot()->create([
            'filename' => $uuid,
            'original_filename' => 'screenshot.png',
            'mime_type' => 'image/png',
            'size' => filesize($tempFilePath),
            'disk' => self::DISK,
            'path' => $path,
            'type' => MediaType::IMAGE->value,
            'metadata' => null,
            'order' => 0,
        ]);
    }

    /**
     * Attach completed upload sessions as Media to the given Memory.
     *
     * @param  array<int, string>  $uploadIds
     * @return array<int, Media>
     */
    public function attachUploadSessions(Memory $memory, array $uploadIds): array
    {
        $mediaRecords = [];

        foreach ($uploadIds as $order => $uploadId) {
            $session = UploadSession::where('id', $uploadId)
                ->where('user_id', auth()->id())
                ->whereNotNull('completed_at')
                ->firstOrFail();

            $filename = pathinfo($session->path, PATHINFO_FILENAME);

            $media = $memory->media()->create([
                'filename' => $filename,
                'original_filename' => $session->original_filename,
                'mime_type' => $session->mime_type,
                'size' => $session->total_size,
                'disk' => $session->disk,
                'path' => $session->path,
                'type' => MimeType::mediaTypeFromMime($session->mime_type),
                'metadata' => null,
                'order' => $order,
            ]);

            $this->dispatchOptimizationIfNeeded($media);
            $mediaRecords[] = $media;
        }

        return $mediaRecords;
    }

    private function storeFile(Memory $memory, UploadedFile $file, int $order): Media
    {
        $uuid = Str::uuid()->toString();
        $extension = $file->guessExtension() ?? $file->getClientOriginalExtension();
        $filename = $uuid.'.'.$extension;

        $now = now();
        $directory = sprintf('uploads/%s/%s', $now->format('Y'), $now->format('m'));
        $path = $directory.'/'.$filename;

        Storage::disk(self::DISK)->putFileAs($directory, $file, $filename);

        $mimeType = $this->resolveMimeType($file);

        $media = $memory->media()->create([
            'filename' => $uuid,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'disk' => self::DISK,
            'path' => $path,
            'type' => MimeType::mediaTypeFromMime($mimeType),
            'metadata' => null,
            'order' => $order,
        ]);

        $this->dispatchOptimizationIfNeeded($media);

        return $media;
    }

    private function dispatchOptimizationIfNeeded(Media $media): void
    {
        $service = app(MediaOptimizationService::class);

        if ($service->needsOptimization($media)) {
            $media->update(['processing_status' => ProcessingStatus::Pending]);
            OptimizeMedia::dispatch($media->id);
        }
    }
}
