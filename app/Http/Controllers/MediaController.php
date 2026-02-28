<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function show(Request $request, string $filename): Response
    {
        $media = Media::where('filename', $filename)->firstOrFail();

        $disk = Storage::disk($media->disk);

        abort_unless($disk->exists($media->path), 404);

        if ($request->hasHeader('Range')) {
            return $this->rangeResponse($request, $disk, $media);
        }

        return $disk->response($media->path, $media->original_filename, [
            'Content-Type' => $media->mime_type,
            'Accept-Ranges' => 'bytes',
        ]);
    }

    private function rangeResponse(Request $request, Filesystem $disk, Media $media): StreamedResponse
    {
        $size = $disk->size($media->path);

        preg_match('/bytes=(\d*)-(\d*)/', $request->header('Range', ''), $matches);

        $start = $matches[1] === '' ? null : (int) $matches[1];
        $end = $matches[2] === '' ? null : (int) $matches[2];

        if ($start === null && $end !== null) {
            $start = max(0, $size - $end);
            $end = $size - 1;
        } else {
            $start = $start ?? 0;
            $end = $end !== null ? min($end, $size - 1) : $size - 1;
        }

        if ($start > $end || $start >= $size) {
            return new StreamedResponse(fn () => null, 416, [
                'Content-Range' => "bytes */$size",
            ]);
        }

        $length = $end - $start + 1;

        return new StreamedResponse(function () use ($disk, $media, $start, $length) {
            $stream = $disk->readStream($media->path);
            if ($start > 0) {
                fseek($stream, $start);
            }

            $remaining = $length;

            while ($remaining > 0 && ! feof($stream)) {
                $chunk = fread($stream, min(8192, $remaining));
                echo $chunk;
                $remaining -= strlen($chunk);
                flush();
            }

            fclose($stream);
        }, 206, [
            'Content-Type' => $media->mime_type,
            'Content-Length' => $length,
            'Content-Range' => "bytes $start-$end/$size",
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
