<?php

namespace App\Http\Controllers;

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

        return response()->file($disk->path($media->path), [
            'Content-Type' => $media->mime_type,
        ]);
    }
}
