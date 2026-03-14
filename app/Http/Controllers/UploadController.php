<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelUploadRequest;
use App\Http\Requests\InitUploadRequest;
use App\Http\Requests\StoreChunkRequest;
use App\Models\UploadSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    private const DISK = 'local';

    public function init(InitUploadRequest $request): JsonResponse
    {
        $session = UploadSession::create([
            'user_id' => $request->user()->id,
            'original_filename' => $request->validated('original_filename'),
            'mime_type' => $request->validated('mime_type'),
            'total_size' => $request->validated('total_size'),
            'total_chunks' => $request->validated('total_chunks'),
        ]);

        Storage::disk(self::DISK)->makeDirectory($session->chunksDirectory());

        return response()->json(['upload_id' => $session->id], 201);
    }

    public function chunk(StoreChunkRequest $request, UploadSession $uploadSession): JsonResponse
    {
        if ($uploadSession->isComplete()) {
            return response()->json(['error' => 'Upload already complete.'], 409);
        }

        $chunkIndex = $request->validated('chunk_index');

        Storage::disk(self::DISK)->putFileAs(
            $uploadSession->chunksDirectory(),
            $request->file('chunk'),
            $chunkIndex.'.part',
        );

        $receivedIndices = $uploadSession->received_chunk_indices;

        if (! in_array($chunkIndex, $receivedIndices, true)) {
            $receivedIndices[] = $chunkIndex;
            $uploadSession->update([
                'received_chunks' => count($receivedIndices),
                'received_chunk_indices' => $receivedIndices,
            ]);
        }

        $uploadSession->refresh();

        if ($uploadSession->isComplete()) {
            $this->assembleFile($uploadSession);
        }

        return response()->json([
            'received' => $uploadSession->received_chunks,
            'total' => $uploadSession->total_chunks,
            'complete' => $uploadSession->isComplete(),
            'upload_id' => $uploadSession->id,
        ]);
    }

    public function cancel(CancelUploadRequest $request, UploadSession $uploadSession): JsonResponse
    {
        Storage::disk(self::DISK)->deleteDirectory($uploadSession->chunksDirectory());

        if ($uploadSession->path) {
            Storage::disk(self::DISK)->delete($uploadSession->path);
        }

        $uploadSession->delete();

        return response()->json([], 204);
    }

    private function assembleFile(UploadSession $session): void
    {
        $extension = $this->guessExtension($session->mime_type);
        $uuid = Str::uuid()->toString();
        $filename = $uuid.'.'.$extension;

        $now = now();
        $directory = sprintf('uploads/%s/%s', $now->format('Y'), $now->format('m'));
        $finalPath = $directory.'/'.$filename;

        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory($directory);

        $outputPath = $disk->path($finalPath);
        $outputStream = fopen($outputPath, 'wb');

        for ($i = 0; $i < $session->total_chunks; $i++) {
            $chunkPath = $disk->path($session->chunksDirectory().$i.'.part');
            $chunkStream = fopen($chunkPath, 'rb');
            stream_copy_to_stream($chunkStream, $outputStream);
            fclose($chunkStream);
        }

        fclose($outputStream);

        $actualSize = filesize($outputPath);
        if ($actualSize !== $session->total_size) {
            unlink($outputPath);
            throw new \RuntimeException("Assembled file size mismatch: expected {$session->total_size}, got {$actualSize}");
        }

        $disk->deleteDirectory($session->chunksDirectory());

        $session->update([
            'path' => $finalPath,
            'completed_at' => now(),
        ]);
    }

    private function guessExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'image/avif' => 'avif',
            'video/quicktime' => 'mov',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/mp4' => 'm4a',
            'audio/webm' => 'webm',
            default => 'bin',
        };
    }
}
