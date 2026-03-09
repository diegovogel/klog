<?php

namespace App\Http\Controllers;

use App\Enums\MimeType;
use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function show(string $filename): BinaryFileResponse
    {
        $media = Media::where('filename', $filename)->firstOrFail();

        $disk = Storage::disk($media->disk);

        abort_unless($disk->exists($media->path), 404);

        $contentType = MimeType::tryFrom($media->mime_type)?->value ?? 'application/octet-stream';
        $disposition = 'inline; filename="'.addcslashes($media->original_filename, '"\\').'"';

        return response()->file($disk->path($media->path), [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition,
        ]);
    }
}
