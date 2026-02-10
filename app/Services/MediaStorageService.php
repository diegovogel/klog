<?php

namespace App\Services;

use App\Enums\MimeType;
use App\Models\Media;
use App\Models\Memory;
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

        return $memory->media()->create([
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
    }
}
