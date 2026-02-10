<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function show(string $filename): StreamedResponse
    {
        $media = Media::where('filename', $filename)->firstOrFail();

        $disk = Storage::disk($media->disk);

        abort_unless($disk->exists($media->path), 404);

        return $disk->response($media->path, $media->original_filename, [
            'Content-Type' => $media->mime_type,
        ]);
    }
}
